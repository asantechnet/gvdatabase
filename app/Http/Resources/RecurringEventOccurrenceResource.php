<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RecurringEventOccurrenceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'occurrence_date' => $this->occurrence_date->toDateString(), 'status' => $this->status, 'notified_at' => $this->notified_at, 'completed_at' => $this->completed_at, 'skipped_at' => $this->skipped_at, 'result_type' => $this->result_type, 'result_id' => $this->result_id];
    }
}
