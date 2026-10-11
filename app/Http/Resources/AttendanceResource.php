<?php

namespace App\Http\Resources;

use App\Models\Setting;
use App\Support\CompanyProfile;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'staff_id' => $this->employee_id,
            'name' => $this->staff?->name ?? 'N/A',
            'role' => $this->staff?->position?->title ?? 'N/A',
            'office_id' => $this->office_id,
            'office' => $this->office?->name,
            'date' => $this->date->format('Y-m-d'),
            'status' => $this->status,
            'leave_duration_days' => (float) $this->leave_duration_days,
            'paid_leave_days' => (float) $this->paid_leave_days,
            'unpaid_leave_days' => (float) $this->unpaid_leave_days,
            'timestamp' => Carbon::parse($this->logged_at)->format('h:i A'),
            'check_in_time' => $this->check_in_time ? Carbon::parse($this->check_in_time)->format('H:i') : null,
            'check_out_time' => $this->check_out_time ? Carbon::parse($this->check_out_time)->format('H:i') : null,
            'checked_in_at' => $this->checked_in_at?->utc()->toISOString(),
            'checked_out_at' => $this->checked_out_at?->utc()->toISOString(),
            'check_in_state' => $this->checkInState($this->check_in_time),
            'check_out_state' => $this->checkOutState($this->check_out_time),
            'break_minutes' => $this->gross_minutes !== null && $this->net_worked_minutes !== null
                ? max(0, (int) $this->gross_minutes - (int) $this->net_worked_minutes)
                : (int) ($this->break_minutes ?? 0),
            'gross_minutes' => $this->gross_minutes,
            'net_worked_minutes' => $this->net_worked_minutes,
            'required_minutes' => $this->required_minutes,
            'late_arrival_minutes' => (int) $this->late_arrival_minutes,
            'early_departure_minutes' => (int) $this->early_departure_minutes,
            'overtime_minutes' => (int) $this->overtime_minutes,
            'missing_punch_state' => $this->missing_punch_state,
            'is_overnight_shift' => (bool) $this->is_overnight_shift,
            // This is only a boolean existence flag; daily-report content is
            // retrieved through GET /attendances/{attendance}/daily-report.
            'daily_report_exists' => (bool) ($this->daily_report_exists ?? false),
            'timezone' => app(CompanyProfile::class)->timezone(),
        ];
    }

    private function checkInState(?string $time): ?string
    {
        $open = $this->officeHours()?->office_open_time;

        if (! $time || ! $open) {
            return null;
        }

        return match (substr($time, 0, 5) <=> substr($open, 0, 5)) {
            -1 => 'early',
            0 => 'on_time',
            default => 'late',
        };
    }

    private function checkOutState(?string $time): ?string
    {
        $close = $this->officeHours()?->office_close_time;

        if (! $time || ! $close) {
            return null;
        }

        // Derived metrics know the actual date of an overnight checkout.
        // Comparing HH:mm strings alone can label an early exit as overtime.
        return $this->early_departure_minutes > 0 ? 'early_departure'
            : ($this->overtime_minutes > 0 ? 'overtime' : 'on_time');
    }

    private function officeHours(): ?Setting
    {
        return Setting::latest('id')->first(['office_open_time', 'office_close_time']);
    }
}
