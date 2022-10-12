<?php

namespace App\Console\Commands;

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AttendanceLogController;
use App\Models\AttendanceLog;
use App\Models\Device;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log as Logger;
use Illuminate\Support\Facades\Mail;
use App\Mail\NotifyIfLogsDoesNotGenerate;


class SyncAttendance extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'task:sync_attendance';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync Attendance';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $date = date("d-m-Y H:i:s");

        $Attendance = new AttendanceController;
        $i = $Attendance->SyncAttendance();

        if (!$i) {
            Logger::channel("custom")->info("Cron: SyncAttendance. No new logs found.");
            echo "[".$date."] Cron: SyncAttendance. No new logs found.\n";
            return;
        }

        Logger::channel("custom")->info("Cron: SyncAttendance. Log processed " . $i);
        echo "[".$date."] Cron: SyncAttendance. Log processed " . $i . ".\n";
        return;
    }
}
