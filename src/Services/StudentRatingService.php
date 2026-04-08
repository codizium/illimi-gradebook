<?php

namespace Illimi\Gradebook\Services;

use Illuminate\Support\Collection;
use Illimi\Gradebook\Models\StudentRating;

class StudentRatingService
{
    public const EFFECTIVE_ASSESSMENT_ITEMS = [
        'attentiveness' => 'Attentiveness',
        'conduct' => 'Conduct',
        'neatness' => 'Neatness',
        'politeness' => 'Politeness',
        'punctuality' => 'Punctuality',
        'relationship' => 'Relationship',
    ];

    public const PSYCHOMOTOR_ASSESSMENT_ITEMS = [
        'assignment' => 'Assignment',
        'construction' => 'Construction',
        'fluency' => 'Fluency',
        'hand_writing' => 'Hand Writing',
        'sport_and_games' => 'Sport and Games',
    ];

    public function ratingsForContext(array $criteria): Collection
    {
        return StudentRating::query()
            ->where('academic_class_id', $criteria['academic_class_id'])
            ->where('academic_year_id', $criteria['academic_year_id'])
            ->where('academic_term_id', $criteria['academic_term_id'])
            ->get()
            ->keyBy('student_id');
    }

    public function store(array $data): StudentRating
    {
        $payload = [
            'organization_id' => $data['organization_id'] ?? null,
            'staff_id' => $data['staff_id'] ?? null,
            'effective_assessment' => $this->normalizeRatings($data['effective_assessment'] ?? [], self::EFFECTIVE_ASSESSMENT_ITEMS),
            'psychomotor_assessment' => $this->normalizeRatings($data['psychomotor_assessment'] ?? [], self::PSYCHOMOTOR_ASSESSMENT_ITEMS),
        ];

        return StudentRating::query()->updateOrCreate([
            'student_id' => $data['student_id'],
            'academic_class_id' => $data['academic_class_id'],
            'academic_year_id' => $data['academic_year_id'],
            'academic_term_id' => $data['academic_term_id'],
        ], $payload);
    }

    public function effectiveItems(): array
    {
        return self::EFFECTIVE_ASSESSMENT_ITEMS;
    }

    public function psychomotorItems(): array
    {
        return self::PSYCHOMOTOR_ASSESSMENT_ITEMS;
    }

    public function ratingLabel(?int $value): ?string
    {
        return match ($value) {
            5 => 'A',
            4 => 'B',
            3 => 'C',
            2 => 'D',
            1 => 'E',
            default => null,
        };
    }

    protected function normalizeRatings(array $ratings, array $items): array
    {
        $normalized = [];

        foreach ($items as $key => $label) {
            $value = $ratings[$key] ?? null;
            $normalized[$key] = $value === null || $value === '' ? null : (int) $value;
        }

        return $normalized;
    }
}
