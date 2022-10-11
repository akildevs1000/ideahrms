<?php

namespace App\Http\Controllers;

use App\Models\AttendanceLog;
use App\Models\Attendance;
use App\Models\Device;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function SyncAttendance(Attendance $attendance)
    {
        $items = [];
        $model = AttendanceLog::query();
        $model->where("checked", false);
        $model->take(1000);
        if ($model->count() == 0) {
            return "No logs found";
        }
        $logs = $model->get(["id", "UserID", "LogTime", "DeviceID", "company_id"]);

        foreach ($logs as $log) {

            $date = date("Y-m-d", strtotime($log->LogTime));

            $AttendanceLog = new AttendanceLog;

            $orderByAsc = $AttendanceLog->where("UserID", $log->UserID)->whereDate("LogTime", $date);
            $orderByDesc = $AttendanceLog->where("UserID", $log->UserID)->whereDate("LogTime", $date);

            $first_log = $orderByAsc->orderBy("LogTime")->first() ?? false;
            $last_log =  $orderByDesc->orderByDesc('LogTime')->first() ?? false;

            $logs = $AttendanceLog->where("UserID", $log->UserID)->whereDate("LogTime", $date)->count();

            $item = [];
            $item["company_id"] = $log->company_id;
            $item["employee_id"] = $log->UserID;
            $item["date"] = $date;

            if ($first_log) {
                $item["in"] = $first_log->time;
                $item["device_id_in"] = Device::where("device_id", $first_log->DeviceID)->pluck("id")[0] ?? "---";
            }
            if ($logs > 1 && $last_log) {
                $item["out"] = $last_log->time;
                $item["device_id_out"] = Device::where("device_id", $last_log->DeviceID)->pluck("id")[0] ?? "---";
                $item["status"] = "P";
                $diff = abs(($last_log->show_log_time - $first_log->show_log_time));
                $h = floor($diff / 3600);
                $m = floor(($diff % 3600) / 60);
                $item["total_hrs"] = (($h < 10 ? "0" . $h : $h) . ":" . ($m < 10 ? "0" . $m : $m));
            }


            $attendance = Attendance::whereDate("date", $date)->where("employee_id", $log->UserID);

            $attendance->first() ? $attendance->update($item) : Attendance::create($item);

            AttendanceLog::where("id", $log->id)->update(["checked" => true]);

            $items[$date][$log->UserID] = $item;
        }

        return $items;
    }
}
