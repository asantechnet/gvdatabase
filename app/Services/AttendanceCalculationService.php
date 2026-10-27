<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Setting;
use App\Support\CompanyProfile;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class AttendanceCalculationService
{
    private const CALCULATION_VERSION = 5;

    public function __construct(private readonly CompanyProfile $companyProfile) {}

    /** Recalculate only when raw inputs or schedule settings changed. */
    public function calculate(Attendance $attendance, bool $force = false): bool
    {
        $attendance->loadMissing('staff');
        $setting = Setting::query()->latest('id')->first();
        $timezone = $this->companyProfile->timezone();
        $scheduleStart = $attendance->staff?->work_start_time ?: $setting?->office_open_time;
        $scheduleEnd = $attendance->staff?->work_end_time ?: $setting?->office_close_time;
        $breakMinutes = (int) ($attendance->break_minutes ?? $setting?->default_break_minutes ?? 0);
        $effectiveStart = $this->punch($attendance, true, $timezone);
        $effectiveEnd = $this->punch($attendance, false, $timezone);
        $hash = hash('sha256', json_encode([
            self::CALCULATION_VERSION,
            $attendance->status, $attendance->date?->format('Y-m-d'), $effectiveStart?->toISOString(),
            $effectiveEnd?->toISOString(),
            $breakMinutes, $scheduleStart, $scheduleEnd, $timezone, (float) $attendance->leave_days,
        ], JSON_THROW_ON_ERROR));

        if (! $force && $attendance->calculation_hash === $hash) {
            return false;
        }

        $derived = $this->derive($attendance, $timezone, $scheduleStart, $scheduleEnd, $breakMinutes);
        $attendance->forceFill([...$derived, 'calculation_hash' => $hash, 'calculated_at' => now('UTC')])->save();

        return true;
    }

    private function derive(Attendance $attendance, string $timezone, ?string $scheduleStart, ?string $scheduleEnd, int $breakMinutes): array
    {
        // An off day is a non-worked day: it has no punches and must not be
        // reported as an incomplete attendance.
        if ($attendance->status !== 'present' || $attendance->off_day_id) {
            return $this->emptySummary('none', 0);
        }

        $start = $this->punch($attendance, true, $timezone);
        $end = $this->punch($attendance, false, $timezone);
        $missing = match (true) {
            ! $start && ! $end => 'both',
            ! $start => 'check_in',
            ! $end => 'check_out',
            default => 'none',
        };
        if ($missing !== 'none') {
            return $this->emptySummary($missing, $breakMinutes);
        }

        if ($end->lessThan($start)) {
            if ($attendance->checked_in_at || $attendance->checked_out_at) {
                return $this->emptySummary('invalid', $breakMinutes);
            }
            // Only undated legacy clock punches require inferred overnight dates.
            $end = $end->addDay();
        }
        $overnight = ! $start->isSameDay($end);
        $gross = max(0, (int) $start->diffInMinutes($end));
        $break = min(max(0, $breakMinutes), $gross);
        $net = max(0, $gross - $break);

        [$required, $late, $early] = $this->scheduleMetrics($attendance, $start, $end, $scheduleStart, $scheduleEnd, max(0, $breakMinutes));
        $leaveFraction = min(1, max(0, (float) $attendance->leave_days));
        if ($required !== null && $leaveFraction > 0) {
            $required = (int) round($required * (1 - $leaveFraction));
            // Without a morning/afternoon designation the partial leave is flexible;
            // punctuality penalties cannot be inferred fairly from the clock alone.
            $late = 0;
            $early = 0;
        }

        return [
            'gross_minutes' => $gross,
            'net_worked_minutes' => $net,
            'required_minutes' => $required,
            'late_arrival_minutes' => $late,
            'early_departure_minutes' => $early,
            // A missing schedule is not evidence of overtime. Once office
            // hours are available, overtime is worked time minus office time.
            'overtime_minutes' => $required === null ? 0 : max(0, $net - $required),
            'missing_punch_state' => 'none',
            'is_overnight_shift' => $overnight,
        ];
    }

    private function punch(Attendance $attendance, bool $checkIn, string $timezone): ?CarbonInterface
    {
        $instant = $checkIn ? $attendance->checked_in_at : $attendance->checked_out_at;
        if ($instant) {
            return $instant->copy()->timezone($timezone);
        }
        $clock = $checkIn ? $attendance->check_in_time : $attendance->check_out_time;
        if (! $clock) {
            return null;
        }

        return Carbon::parse($attendance->date->format('Y-m-d').' '.$clock, $timezone);
    }

    private function scheduleMetrics(Attendance $attendance, CarbonInterface $actualStart, CarbonInterface $actualEnd, ?string $startClock, ?string $endClock, int $break): array
    {
        if (! $startClock || ! $endClock) {
            return [null, 0, 0];
        }
        $date = $attendance->date->format('Y-m-d');
        $scheduledStart = Carbon::parse($date.' '.$startClock, $actualStart->timezoneName);
        $scheduledEnd = Carbon::parse($date.' '.$endClock, $actualStart->timezoneName);
        if ($scheduledEnd->lessThanOrEqualTo($scheduledStart)) {
            $scheduledEnd->addDay();
        }
        $required = max(0, (int) $scheduledStart->diffInMinutes($scheduledEnd) - $break);
        $late = $actualStart->greaterThan($scheduledStart) ? (int) $scheduledStart->diffInMinutes($actualStart) : 0;
        $early = $actualEnd->lessThan($scheduledEnd) ? (int) $actualEnd->diffInMinutes($scheduledEnd) : 0;

        return [$required, $late, $early];
    }

    private function emptySummary(string $missing, int $breakMinutes): array
    {
        return [
            'gross_minutes' => null, 'net_worked_minutes' => null, 'required_minutes' => null,
            'late_arrival_minutes' => 0, 'early_departure_minutes' => 0, 'overtime_minutes' => 0,
            'missing_punch_state' => $missing, 'is_overnight_shift' => false,
        ];
    }
}
