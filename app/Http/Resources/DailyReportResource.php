<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DailyReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'attendanceId' => $this->attendance_id,
            'reportDate' => $this->report_date->format('Y-m-d'),
            'summary' => $this->summary,
            'achievements' => $this->achievements,
            'challenges' => $this->challenges,
            'nextDayPlan' => $this->next_day_plan,
            'supportNeeded' => $this->support_needed,
            'submittedAt' => $this->submitted_at->utc()->toISOString(),
        ];
    }
}
