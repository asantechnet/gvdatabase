<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class AttendanceBrowserGuard
{
    /** Called inside the attendance transaction so failed check-ins do not claim the browser. */
    public function claim(int $employeeId, string $date): void
    {
        $browser = request()->attributes->get('attendance_browser');
        abort_unless($browser, 422, 'business.attendance_browser_required');
        $hash = hash('sha256', $browser);
        // Midnight must not free a browser while another employee's shift is open.
        $otherOpenSession = DB::table('attendance_browser_days as browser')
            ->join('attendances as attendance', function ($join) {
                $join->on('attendance.employee_id', '=', 'browser.employee_id')
                    ->on('attendance.date', '=', 'browser.date');
            })
            ->where('browser.browser_hash', $hash)->where('browser.employee_id', '!=', $employeeId)
            ->where('attendance.status', 'present')->whereNull('attendance.deleted_at')
            ->whereNull('attendance.checked_out_at')->whereNull('attendance.check_out_time')
            ->where(function ($query) {
                $query->whereNotNull('attendance.checked_in_at')->orWhereNotNull('attendance.check_in_time');
            })->exists();
        abort_if($otherOpenSession, 403, 'business.attendance_browser_account_mismatch');

        DB::table('attendance_browser_days')->insertOrIgnore([
            'browser_hash' => $hash,
            'date' => $date,
            'employee_id' => $employeeId,
            'created_at' => now(),
        ]);
        $owner = DB::table('attendance_browser_days')
            ->where('browser_hash', $hash)->where('date', $date)
            ->lockForUpdate()->value('employee_id');

        abort_if((int) $owner !== $employeeId, 403, 'business.attendance_browser_account_mismatch');
    }
}
