<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AcademyCertificateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'certificate_number' => $this->certificate_number,
            'academy_enrollment_id' => $this->academy_enrollment_id,
            'student' => [
                'id' => $this->snapshot['award']['student_id'] ?? null,
                'name' => $this->snapshot['award']['student_name'] ?? null,
                'father_name' => $this->snapshot['award']['father_name'] ?? null,
            ],
            'program' => $this->snapshot['award']['program'] ?? null,
            'class' => $this->snapshot['award']['class'] ?? null,
            'teachers' => $this->snapshot['award']['teachers'] ?? [],
            'started_on' => $this->snapshot['award']['start_date'] ?? null,
            'ends_on' => $this->snapshot['award']['end_date'] ?? null,
            'completed_on' => $this->snapshot['award']['completion_date'] ?? null,
            'status' => $this->status,
            'issued_at' => $this->issued_at?->toIso8601String(),
            'issued_by' => $this->issuer?->only(['id', 'name']),
            'revoked_at' => $this->revoked_at?->toIso8601String(),
            'revoked_by' => $this->revoker?->only(['id', 'name']),
            'revocation_reason' => $this->revocation_reason,
            'snapshot' => $this->snapshot,
        ];
    }
}
