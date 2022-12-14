<?php

namespace App\Http\Controllers;

use Dompdf\Options;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Department;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\App;
use mikehaertl\wkhtmlto\Pdf as wkh;
use Illuminate\Foundation\Bus\DispatchesJobs;
use App\Http\Controllers\Reports\ReportController;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class Controller extends BaseController
{
    use AuthorizesRequests;
    use DispatchesJobs;
    use ValidatesRequests;

    public function FilterCompanyList($model, $request, $model_name = null)
    {
        $model = $model::query();

        if (is_null($model_name)) {
            $model->when($request->company_id > 0, function ($q) use ($request) {
                return $q->where('company_id', $request->company_id);
            });

            $model->when(!$request->company_id, function ($q) use ($request) {
                return $q->where('company_id', 0);
            });
        }

        return $model;
    }

    public static function process($action, $job, $model, $id = null)
    {
        try {
            $m = '\\App\\Models\\' . $model;
            $last_id = gettype($job) == 'object' ? $job->id : $id;

            $response = [
                'status' => true,
                'record' => $m::find($last_id),
                'message' => $model . ' has been ' . $action,
            ];

            if ($last_id) {
                return response()->json($response, 200);
            } else {
                return response()->json([
                    'status' => false,
                    'record' => null,
                    'message' => $model . ' cannot ' . $action,
                ], 200);
            }
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    public function process_command($command)
    {
        $url = env("SDK_URL");
        $post = env("LOCAL_PORT");

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => "$url:$post/$command",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
        ));

        $response = curl_exec($curl);

        curl_close($curl);

        return json_decode($response);
    }

    public function response($msg, $record, $status)
    {
        return response()->json(['record' => $record, 'message' => $msg, 'status' => $status], 200);
    }

    public function process_search($model, $input, $fields = [])
    {
        $model->where('id', 'LIKE', "%$input%");

        foreach ($fields as $key => $value) {
            if (is_string($value)) {
                $model->orWhere($value, 'LIKE', "%$input%");
            } else {
                foreach ($value as $relation_value) {
                    $model->orWhereHas($key, function ($query) use ($input, $relation_value) {
                        $query->where($relation_value, 'like', '%' . $input . '%');
                    });
                }
            }
        }
        return $model;
    }

    public function getStatusText($status)
    {
        $report_type = "Summary";

        if ($status == 'P') {
            $report_type = "Present";
        } else if ($status == 'A') {
            $report_type = "Absent";
        } else if ($status == '---') {
            $report_type = "Missing";
        } else if ($status == 'ME') {
            $report_type = "Manual Entry";
        }

        return $report_type;
    }

    public function processPDF($request)
    {
        $company = Company::whereId($request->company_id)->with('contact')->first(["logo", "name", "company_code", "location", "p_o_box_no", "id"]);
        $model = new ReportController;
        $deptName = '';
        $totEmployees = '';
        if ($request->department_id && $request->department_id == -1) {
            $deptName = 'All';
            $totEmployees = Employee::whereCompanyId($request->company_id)->whereDate("created_at", "<", date("Y-m-d"))->count();
        } else {
            $deptName = DB::table('departments')->whereId($request->department_id)->first(["name"])->name ?? '';
            $totEmployees = Employee::where("department_id", $request->department_id)->count();
        }

        $info = (object) [
            'department_name' => $deptName,
            'total_employee' => $totEmployees,
            'total_absent' => $model->report($request)->where('status', 'A')->count(),
            'total_present' => $model->report($request)->where('status', 'P')->count(),
            'total_missing' => $model->report($request)->where('status', '---')->count(),
            'total_early' => $model->report($request)->where('early_going', '!=', '---')->count(),
            'total_late' => $model->report($request)->where('late_coming', '!=', '---')->count(),
            'total_leave' => 0,
            'department' => $request->department_id == -1 ? 'All' :  Department::find($request->department_id)->name,
            "daily_date" => $request->daily_date,
            "report_type" => $this->getStatusText($request->status)
        ];

        $data = $model->report($request)
            ->get();

        return Pdf::loadView('pdf.daily', compact("company", "info", "data"));
    }

    public function daily(Request $request)
    {
        return $this->processPDF($request)->stream();
    }
    public function daily_download_pdf(Request $request)
    {
        return $this->processPDF($request)->download();
    }

    public function daily_download_csv(Request $request)
    {
        $model = new ReportController;

        $data = $model->report($request)->get();

        $fileName = 'report.csv';

        $headers = array(
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=$fileName",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        );

        $callback = function () use ($data) {
            $file = fopen('php://output', 'w');

            $i = 0;

            fputcsv($file, ["#", "Date", "E.ID", "Name", "Dept", "Shift Type", "Shift", "Status", "In", "Out", "Total Hrs", "OT", "Late coming", "Early Going", "D.In", "D.Out"]);
            foreach ($data as $col) {
                fputcsv($file, [
                    ++$i,
                    $col['date'],
                    $col['employee_id'] ?? "---",
                    $col['employee']["display_name"] ?? "---",
                    $col['employee']["department"]["name"] ?? "---",
                    $col["shift_type"]["name"] ?? "---",
                    $col["shift"]["name"] ?? "---",
                    $col["status"] ?? "---",
                    $col["in"] ?? "---",
                    $col["out"] ?? "---",
                    $col["total_hrs"] ?? "---",
                    $col["ot"] ?? "---",
                    $col["late_coming"] ?? "---",
                    $col["early_going"] ?? "---",
                    $col["device_in"]["short_name"] ?? "---",
                    $col["device_out"]["short_name"] ?? "---"
                ], ",");
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function multi_in_out_daily_download_csv(Request $request)
    {
        $model = new ReportController;
        $data = $model->processMultiInOut($request);

        $fileName = 'report.csv';

        $headers = array(
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=$fileName",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        );

        $callback = function () use ($data) {
            $file = fopen('php://output', 'w');
            $i = 0;
            fputcsv($file, [
                "#",
                "Date",
                "E.ID",
                "Name",
                "In1",
                "Out1",
                "In2",
                "Out2",
                "In3",
                "Out3",
                "In4",
                "Out4",
                "In5",
                "Out5",
                "In6",
                "Out6",
                "In7",
                "Out7",
                "Total Hrs",
                "Status",

            ]);
            foreach ($data as $col) {
                fputcsv($file, [
                    ++$i,
                    $col['date'],
                    $col['employee_id'] ?? "---",
                    $col['employee']["display_name"] ?? "---",
                    $col["in1"] ?? "---",
                    $col["out1"] ?? "---",
                    $col["in2"] ?? "---",
                    $col["out2"] ?? "---",
                    $col["in3"] ?? "---",
                    $col["out3"] ?? "---",
                    $col["in4"] ?? "---",
                    $col["out4"] ?? "---",
                    $col["in5"] ?? "---",
                    $col["out5"] ?? "---",
                    $col["in6"] ?? "---",
                    $col["out6"] ?? "---",
                    $col["in7"] ?? "---",
                    $col["out7"] ?? "---",
                    $col["total_hrs"] ?? "---",
                    $col["status"] ?? "---",

                ], ",");
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function mimo(Request $request)
    {
        $company = Company::whereId($request->company_id)->with('contact')->first(["logo", "name", "company_code", "location", "p_o_box_no", "id"]);
        $model = new ReportController;
        $deptName = '';
        $totEmployees = '';
        if ($request->department_id && $request->department_id == -1) {
            $deptName = 'All';
            $totEmployees = Employee::whereCompanyId($request->company_id)->whereDate("created_at", "<", date("Y-m-d"))->count();
        } else {
            $deptName = DB::table('departments')->whereId($request->department_id)->first(["name"])->name ?? '';
            $totEmployees = Employee::where("department_id", $request->department_id)->count();
        }

        $model = $model->report($request);

        $info = (object) [
            'department_name' => $deptName,
            'total_employee' => $totEmployees,
            'total_absent' => $model->clone()->where('status', 'A')->count(),
            'total_present' => $model->clone()->where('status', 'P')->count(),
            'total_missing' => $model->clone()->where('status', '---')->count(),
            'total_early' => $model->clone()->where('early_going', '!=', '---')->count(),
            'total_late' => $model->clone()->where('late_coming', '!=', '---')->count(),
            'total_leave' => 0,
            'department' => $request->department_id == -1 ? 'All' :  Department::find($request->department_id)->name,
            "daily_date" => $request->daily_date,
            "report_type" => $this->getStatusText($request->status)
        ];

        $nextDay =  date('Y-m-d', strtotime($request->daily_date . ' + 1 day'));
        $daily_date =  $request->daily_date;
        $data = $model
            ->with('AttendanceLogs', function ($q) use ($daily_date, $nextDay) {
                $q
                    ->whereDate('LogTime', $daily_date)
                    ->orWhereDate('LogTime', $nextDay)
                    ->orderBy('LogTime', 'asc');
            })
            ->get();


        // return $data;
        // return  count($data);
        // return  gettype($data);
        // ld($data);

        return Pdf::loadView('pdf.mimo', compact("company", "info", "data"))->stream();
    }
}