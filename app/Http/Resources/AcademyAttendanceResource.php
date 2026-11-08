<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AcademyAttendanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'class' => new AcademyClassResource($this->resource['class']),
            'month' => $this->resource['month'],
            'dates' => $this->resource['dates'],
            'rows' => $this->resource['rows'],
        ];
    }
}
