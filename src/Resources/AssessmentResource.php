<?php

namespace Illimi\Gradebook\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssessmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'student_id' => $this->student_id,
            'student' => $this->whenLoaded('student', function () {
                return [
                    'id' => $this->student?->id,
                    'full_name' => $this->student?->full_name,
                    'admission_number' => $this->student?->admission_number,
                    'email' => $this->student?->email,
                ];
            }),
            'subject_id' => $this->subject_id,
            'subject' => $this->whenLoaded('subject', function () {
                return [
                    'id' => $this->subject?->id,
                    'name' => $this->subject?->name,
                    'code' => $this->subject?->code,
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
            'template_id' => $this->template_id,
            'template' => $this->whenLoaded('template', fn () => [
                'id' => $this->template?->id,
                'name' => $this->template?->name,
                'code' => $this->template?->code,
            ]),
            'grade_scale_id' => $this->grade_scale_id,
            'grade_scale' => $this->whenLoaded('gradeScale', function () {
                return [
                    'id' => $this->gradeScale?->id,
                    'name' => $this->gradeScale?->name,
                    'code' => $this->gradeScale?->code,
                    'description' => $this->gradeScale?->description,
                    'min_score' => $this->gradeScale?->min_score !== null ? (float) $this->gradeScale->min_score : null,
                    'max_score' => $this->gradeScale?->max_score !== null ? (float) $this->gradeScale->max_score : null,
                ];
            }),
            'staff_id' => $this->staff_id,
            'staff' => $this->whenLoaded('staff', function () {
                return [
                    'id' => $this->staff?->id,
                    'full_name' => $this->staff?->full_name,
                    'email' => $this->staff?->email,
                    'role' => $this->staff?->role,
                ];
            }),
            'assignment1' => (float) $this->assignment1,
            'assignment2' => (float) $this->assignment2,
            'test1' => (float) $this->test1,
            'test2' => (float) $this->test2,
            'exams' => (float) $this->exams,
            'continuous_assessment_total' => (float) $this->continuous_assessment_total,
            'total_score' => (float) $this->total_score,
            'graded' => $this->graded,
            'items' => $this->whenLoaded('items', function () {
                return $this->items->map(fn ($item) => [
                    'id' => $item->id,
                    'template_item_id' => $item->template_item_id,
                    'template_item' => [
                        'id' => $item->templateItem?->id,
                        'label' => $item->templateItem?->label,
                        'code' => $item->templateItem?->code,
                        'component_type' => $item->templateItem?->component_type,
                    ],
                    'score' => $item->score !== null ? (float) $item->score : null,
                ])->values();
            }),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
