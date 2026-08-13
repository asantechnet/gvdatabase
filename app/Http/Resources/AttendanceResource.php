<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;
use App\Models\Setting;
use App\Support\CompanyProfile;

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
            'timestamp' => Carbon::parse($this->logged_at)->format('h:i A'),
            'check_in_time' => $this->check_in_time ? Carbon::parse($this->check_in_time)->format('H:i') : null,
            'check_out_time' => $this->check_out_time ? Carbon::parse($this->check_out_time)->format('H:i') : null,
            'checked_in_at' => $this->checked_in_at?->utc()->toISOString(),
            'checked_out_at' => $this->checked_out_at?->utc()->toISOString(),
            'check_in_state' => $this->checkInState($this->check_in_time),
            'check_out_state' => $this->checkOutState($this->check_out_time),
            'break_minutes' => (int) ($this->break_minutes ?? 0),
            'gross_minutes' => $this->gross_minutes,
            'net_worked_minutes' => $this->net_worked_minutes,
            'required_minutes' => $this->required_minutes,
            'late_arrival_minutes' => (int) $this->late_arrival_minutes,
            'early_departure_minutes' => (int) $this->early_departure_minutes,
            'overtime_minutes' => (int) $this->overtime_minutes,
            'missing_punch_state' => $this->missing_punch_state,
            'is_overnight_shift' => (bool) $this->is_overnight_shift,
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

        return match (substr($time, 0, 5) <=> substr($close, 0, 5)) {
            -1 => 'early_departure',
            0 => 'on_time',
            default => 'overtime',
        };
    }

    private function officeHours(): ?Setting
    {
        static $officeHours;

        return $officeHours ??= Setting::latest('id')->first(['office_open_time', 'office_close_time']);
    }
}
