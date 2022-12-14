<?php

namespace App\Http\Controllers\Reports;

use App\Models\Employee;
use App\Models\Attendance;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use App\Http\Controllers\Controller;
use Illuminate\Pagination\Paginator;
use Illuminate\Pagination\LengthAwarePaginator;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        return $this->report($request)
            ->paginate($request->per_page);
    }

    public function multiInOut(Request $request)
    {
        $model =  $this->processMultiInOut($request);
        return $this->paginate($model, $request->per_page);
    }


    public function processMultiInOut($request)
    {
        $model = $this->report($request)
            ->get();
        foreach ($model as $value) {
            $count = count($value->logs ?? []);
            if ($count > 0) {
                if ($count < 8) {
                    $diff = 7 - $count;
                    $count = $count + $diff;
                }
                $i = 1;
                for ($a = 0; $a < $count; $a++) {

                    $holder = $a;
                    $holder_key = ++$holder;

                    $value["in" . $holder_key] = $value->logs[$a]["in"] ?? "---";
                    $value["out" . $holder_key] = $value->logs[$a]["out"] ?? "---";
                }
            }
        }
        return $model;
    }


    public function paginate($items, $perPage = 15, $page = null, $options = [])
    {
        $page = $page ?: (Paginator::resolveCurrentPage() ?: 1);
        $items = $items instanceof Collection ? $items : Collection::make($items);
        return new LengthAwarePaginator($items->forPage($page, $perPage), $items->count(), $perPage, $page, $options);
    }

    public function report($request)
    {
        $model = Attendance::query();
        $model->where('company_id', $request->company_id);

        $model->when($request->filled('employee_id'), function ($q) use ($request) {
            $q->where('employee_id', $request->employee_id);
        });

        $model->when($request->main_shift_type && $request->main_shift_type == 2, function ($q) {
            $q->where('shift_type_id', 2);
        });

        $model->when($request->main_shift_type && $request->main_shift_type != 2, function ($q) {
            $q->whereNot('shift_type_id', 2);
        });

        $model->when($request->department_id && $request->department_id != -1, function ($q) use ($request) {
            $ids = Employee::where("department_id", $request->department_id)->pluck("employee_id");
            $q->whereIn('employee_id', $ids);
        });

        $model->when($request->status == "P", function ($q) {
            $q->where('status', "P");
        });

        $model->when($request->status == "A", function ($q) {
            $q->where('status', "A");
        });

        $model->when($request->status == "---", function ($q) {
            $q->where('status', "---");
        });

        $model->when($request->late_early == "L", function ($q) {
            $q->where('late_coming', "!=", "---");
        });

        $model->when($request->late_early == "E", function ($q) {
            $q->where('early_going', "!=", "---");
        });

        $model->when($request->ot == 1, function ($q) {
            $q->where('ot', "!=", "---");
        });

        $model->when($request->daily_date && $request->report_type == 'Daily', function ($q) use ($request) {
            $q->whereDate('date', $request->daily_date);
            $q->orderBy("id", "desc");
        });

        $model->when($request->from_date && $request->to_date && $request->report_type != 'Daily', function ($q) use ($request) {
            $q->whereBetween("date", [$request->from_date, $request->to_date]);
            $q->orderBy("date", "asc");
        });

        // dd($request->all());

        $model->with([
            "employee:id,system_user_id,display_name,employee_id,department_id,profile_picture",
            "device_in:id,name,short_name,device_id,location",
            "device_out:id,name,short_name,device_id,location",
            "shift",
            "shift_type:id,name"
        ]);

        return $model;
    }
}