<?php

namespace Illimi\Gradebook\Services;

use Codizium\Core\Models\Organization;
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
        $scope = [
            'student_id' => $data['student_id'],
            'academic_class_id' => $data['academic_class_id'],
            'academic_year_id' => $data['academic_year_id'],
            'academic_term_id' => $data['academic_term_id'],
        ];

        $existing = StudentRating::query()->where($scope)->first();
        $effectiveSource = array_key_exists('effective_assessment', $data)
            ? ($data['effective_assessment'] ?? [])
            : ($existing?->effective_assessment ?? []);
        $psychomotorSource = array_key_exists('psychomotor_assessment', $data)
            ? ($data['psychomotor_assessment'] ?? [])
            : ($existing?->psychomotor_assessment ?? []);

        $organization = $this->resolveOrganization($data['organization_id'] ?? null);
        $effectiveItems = $this->effectiveItems($organization);
        $psychomotorItems = $this->psychomotorItems($organization);

        $payload = [
            'organization_id' => $data['organization_id'] ?? null,
            'staff_id' => $data['staff_id'] ?? null,
            'effective_assessment' => $this->normalizeRatings($effectiveSource, $effectiveItems),
            'psychomotor_assessment' => $this->normalizeRatings($psychomotorSource, $psychomotorItems),
        ];

        return StudentRating::query()->updateOrCreate($scope, $payload);
    }

    public function effectiveItems(?Organization $organization = null): array
    {
        return $this->resolveConfiguredItems(
            $organization,
            'gradebook_effective_traits_items',
            self::EFFECTIVE_ASSESSMENT_ITEMS
        );
    }

    public function psychomotorItems(?Organization $organization = null): array
    {
        return $this->resolveConfiguredItems(
            $organization,
            'gradebook_psychomotor_skills_items',
            self::PSYCHOMOTOR_ASSESSMENT_ITEMS
        );
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

    protected function resolveOrganization(?string $organizationId): ?Organization
    {
        if ($organizationId) {
            return Organization::query()->whereKey($organizationId)->first();
        }

        return function_exists('organization') ? organization() : null;
    }

    /**
     * @param array<string, string> $fallback
     * @return array<string, string>
     */
    protected function resolveConfiguredItems(?Organization $organization, string $artifactKey, array $fallback): array
    {
        if (! $organization) {
            return $fallback;
        }

        $raw = $organization->getArtifactValue($artifactKey);
        if (! $raw) {
            return $fallback;
        }

        $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
        if (! is_array($decoded)) {
            return $fallback;
        }

        $items = [];

        // Supported formats:
        // 1) { "key": "Label", ... }
        // 2) [ { "key": "attentiveness", "label": "Attentiveness" }, ... ]
        $isAssoc = array_keys($decoded) !== range(0, count($decoded) - 1);
        if ($isAssoc) {
            foreach ($decoded as $key => $label) {
                $key = trim((string) $key);
                $label = trim((string) $label);
                if ($key === '' || $label === '') {
                    continue;
                }
                $items[$key] = $label;
            }
        } else {
            foreach ($decoded as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $key = trim((string) ($row['key'] ?? ''));
                $label = trim((string) ($row['label'] ?? $row['name'] ?? ''));
                if ($key === '' || $label === '') {
                    continue;
                }
                $items[$key] = $label;
            }
        }

        return count($items) ? $items : $fallback;
    }
}
