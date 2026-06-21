<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'student_id' => $this->student_id,
            'teacher_id' => $this->teacher_id,
            'circle_id' => $this->circle_id,
            'date' => $this->date->format('Y-m-d'),
            'status' => $this->status,
            'notes' => $this->notes,
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
