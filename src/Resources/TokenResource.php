<?php

namespace Illimi\Gradebook\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TokenResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
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
            'code' => $this->code,
            'generated_by' => $this->generated_by,
            'generated_by_user' => $this->whenLoaded('generatedBy', function () {
                return [
                    'id' => $this->generatedBy?->id,
                    'name' => $this->generatedBy?->name,
                    'email' => $this->generatedBy?->email,
                ];
            }),
            'is_active' => (bool) $this->is_active,
            'assigned_at' => $this->assigned_at?->toIso8601String(),
            'last_used_at' => $this->last_used_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
