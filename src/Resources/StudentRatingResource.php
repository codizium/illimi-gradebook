<?php

namespace Illimi\Gradebook\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illimi\Gradebook\Services\StudentRatingService;

class StudentRatingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $service = app(StudentRatingService::class);

        return [
            'id' => $this->id,
            'student_id' => $this->student_id,
            'academic_class_id' => $this->academic_class_id,
            'academic_year_id' => $this->academic_year_id,
            'academic_term_id' => $this->academic_term_id,
            'staff_id' => $this->staff_id,
            'effective_assessment' => collect($service->effectiveItems())->map(function ($label, $key) use ($service) {
                $value = $this->effective_assessment[$key] ?? null;

                return [
                    'key' => $key,
                    'label' => $label,
                    'value' => $value,
                    'rating' => $service->ratingLabel($value),
                ];
            })->values(),
            'psychomotor_assessment' => collect($service->psychomotorItems())->map(function ($label, $key) use ($service) {
                $value = $this->psychomotor_assessment[$key] ?? null;

                return [
                    'key' => $key,
                    'label' => $label,
                    'value' => $value,
                    'rating' => $service->ratingLabel($value),
                ];
            })->values(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
