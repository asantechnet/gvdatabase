<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AcademyEnrollmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $line = $this->whenLoaded('line') ? $this->line : null;
        $class = $this->whenLoaded('academyClass') ? $this->academyClass : null;

        return [
            'id' => $this->id,
            'student_id' => $this->student_id,
            'student_number' => $this->student?->student_number,
            'student' => $this->whenLoaded('student', fn () => $this->student ? [
                'id' => $this->student->id,
                'student_number' => $this->student->student_number,
                'name' => $this->student->name,
                'contact' => $this->student->contact,
                'email' => $this->student->email,
                'father_phone' => $this->student->father_phone,
                'applicant_id' => $this->student->applicant_id,
            ] : null),
            'applicant' => $this->whenLoaded('applicant', fn () => $this->applicant ? [
                'id' => $this->applicant->id,
                'name' => $this->applicant->name,
                'contact' => $this->applicant->contact,
                'father_phone' => $this->applicant->father_phone,
            ] : null),
            'contract' => $this->whenLoaded('contract', fn () => $this->contract ? [
                'id' => $this->contract->id,
                'contract_number' => $this->contract->contract_number,
                'status' => $this->contract->status,
                'start_date' => $this->contract->start_date?->toDateString(),
                'end_date' => $this->contract->end_date?->toDateString(),
            ] : null),
            'line' => $line ? [
                'id' => $line->id,
                'fee' => (float) $line->fee,
                'discount' => (float) $line->discount,
                'paid' => (float) $line->amount_paid,
                'balance' => round($line->remainingTotal(), 2),
                'payment_status' => $line->payment_status,
                'currency' => $line->relationLoaded('currency') && $line->currency ? [
                    'code' => $line->currency->code,
                    'symbol' => $line->currency->symbol,
                    'decimal_places' => (int) $line->currency->decimal_places,
                ] : null,
            ] : null,
            'class' => $class ? (new AcademyClassResource($class))->resolve($request) : null,
            'started_on' => $this->started_on?->toDateString(),
            'ends_on' => $this->ends_on?->toDateString(),
            'status' => $this->status,
            'completed_on' => $this->completed_on?->toDateString(),
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
