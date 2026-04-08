<?php

namespace Illimi\Gradebook\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'student_id' => $this->student_id,
            'student' => $this->whenLoaded('student', function () {
                return [
                    'id' => $this->student?->id,
                    'full_name' => $this->student?->full_name,
                    'admission_number' => $this->student?->admission_number,
                    'email' => $this->student?->email,
                ];
            }),
            'academic_class_id' => $this->academic_class_id,
            'academic_class' => $this->whenLoaded('academicClass', function () {
                return [
                    'id' => $this->academicClass?->id,
                    'name' => $this->academicClass?->name,
                    'level' => $this->academicClass?->level,
                ];
            }),
            'academic_year_id' => $this->academic_year_id,
            'academic_year' => $this->whenLoaded('academicYear', function () {
                return [
                    'id' => $this->academicYear?->id,
                    'name' => $this->academicYear?->name,
                    'slug' => $this->academicYear?->slug,
                ];
            }),
            'academic_term_id' => $this->academic_term_id,
            'academic_term' => $this->whenLoaded('academicTerm', function () {
                return [
                    'id' => $this->academicTerm?->id,
                    'name' => $this->academicTerm?->name,
                    'slug' => $this->academicTerm?->slug,
                ];
            }),
            'payload' => $this->payload,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
