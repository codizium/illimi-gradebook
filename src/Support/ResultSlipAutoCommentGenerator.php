<?php

namespace Illimi\Gradebook\Support;

use Codizium\Core\Models\Organization;

class ResultSlipAutoCommentGenerator
{
    public function __construct(
        protected ?Organization $organization = null
    ) {
    }

    public static function make(?Organization $organization = null): self
    {
        return new self($organization);
    }

    public function teacher(
        float $averageScore,
        ?int $position = null,
        ?int $classSize = null,
        array $context = []
    ): string
    {
        return $this->generate('teacher', $averageScore, $position, $classSize, $context);
    }

    public function principal(
        float $averageScore,
        ?int $position = null,
        ?int $classSize = null,
        array $context = []
    ): string
    {
        return $this->generate('principal', $averageScore, $position, $classSize, $context);
    }

    protected function generate(string $role, float $averageScore, ?int $position, ?int $classSize, array $context): string
    {
        $seed = $this->seedFromContext($role, $averageScore, $position, $classSize, $context);

        $templates = $this->resolveTemplates($role);

        $base = $this->pickTemplate($templates, $averageScore)
            ?? $this->fallbackBase($role, $averageScore);

        $base = $this->makeNatural($base, $role, $averageScore, $context, $seed);

        $positionPraiseEnabled = (bool) ($this->organization?->getArtifactValue('result_slip_auto_comment_position_praise', true) ?? true);
        $withPosition = $positionPraiseEnabled
            ? $this->appendPositionPraise($base, $role, $averageScore, $position, $classSize)
            : $base;

        return trim($withPosition);
    }

    protected function makeNatural(string $base, string $role, float $averageScore, array $context, int $seed): string
    {
        $name = trim((string) ($context['student_first_name'] ?? $context['student_name'] ?? ''));
        $topSubject = $this->firstNonEmptyString($context['top_subject'] ?? null);
        $weakSubject = $this->firstNonEmptyString($context['weak_subject'] ?? null);

        $salutations = $role === 'principal'
            ? ['Well done', 'Keep it up', 'Great effort', 'Commendable work', 'Good progress']
            : ['Well done', 'Good effort', 'Keep going', 'Nice work', 'Great job'];

        $closings = $role === 'principal'
            ? [
                'Maintain discipline and stay consistent.',
                'Keep a positive attitude to learning.',
                'Let’s improve even more next term.',
                'Keep working hard and stay focused.',
            ]
            : [
                'With more practice, you will improve.',
                'Stay consistent with revision.',
                'Pay attention in class and ask questions.',
                'Let’s aim higher next term.',
            ];

        $strengthLines = $topSubject
            ? [
                "Your performance in {$topSubject} is encouraging.",
                "You did well in {$topSubject}.",
                "Keep the same energy in {$topSubject}.",
            ]
            : [];

        $improveLines = $weakSubject
            ? [
                "Put more effort into {$weakSubject}.",
                "Focus more on {$weakSubject} to boost your overall average.",
                "Work on {$weakSubject} and practice regularly.",
            ]
            : [];

        $prefix = $this->pick($salutations, $seed);
        $suffix = $this->pick($closings, $seed ^ 0x9e3779b9);

        $mid = [];
        if ($topSubject && $weakSubject && $topSubject === $weakSubject) {
            $weakSubject = null;
            $improveLines = [];
        }

        // Only add extra lines when we have subject insights; keep it short on slips.
        if ($topSubject) {
            $mid[] = $this->pick($strengthLines, $seed ^ 0x7f4a7c15);
        }
        if ($weakSubject && $averageScore < 75) {
            $mid[] = $this->pick($improveLines, $seed ^ 0x3c6ef372);
        }

        $address = $name !== '' ? "{$name}, " : '';
        $base = trim($base);
        if (! str_ends_with($base, '.') && ! str_ends_with($base, '!') && ! str_ends_with($base, '?')) {
            $base .= '.';
        }

        $parts = array_values(array_filter([
            "{$address}{$prefix}.",
            $base,
            ...$mid,
            $suffix,
        ], fn ($v) => is_string($v) && trim($v) !== ''));

        // Prevent over-long comments.
        $text = trim(implode(' ', $parts));
        $maxLen = (int) ($this->organization?->getArtifactValue('result_slip_auto_comment_max_length', 220) ?? 220);
        if ($maxLen > 40 && strlen($text) > $maxLen) {
            $text = rtrim(substr($text, 0, $maxLen));
            $text = rtrim($text, " \t\n\r\0\x0B.,;:");
            $text .= '.';
        }

        return $text;
    }

    protected function seedFromContext(string $role, float $averageScore, ?int $position, ?int $classSize, array $context): int
    {
        $seedText = implode('|', array_filter([
            (string) ($this->organization?->id ?? ''),
            $role,
            (string) round($averageScore, 2),
            (string) ($position ?? ''),
            (string) ($classSize ?? ''),
            (string) ($context['admission_number'] ?? $context['student_id'] ?? ''),
            (string) ($context['academic_year'] ?? ''),
            (string) ($context['academic_term'] ?? ''),
        ]));

        // crc32 gives us a stable 32-bit seed across requests.
        return (int) sprintf('%u', crc32($seedText));
    }

    /**
     * @param array<int, string> $choices
     */
    protected function pick(array $choices, int $seed): string
    {
        if (! count($choices)) {
            return '';
        }

        $next = $this->xorshift32($seed);
        $index = $next % count($choices);

        return (string) $choices[$index];
    }

    protected function xorshift32(int $x): int
    {
        $x = $x & 0xffffffff;
        $x ^= ($x << 13) & 0xffffffff;
        $x ^= ($x >> 17) & 0xffffffff;
        $x ^= ($x << 5) & 0xffffffff;

        return $x & 0xffffffff;
    }

    protected function firstNonEmptyString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $value = trim($value);

        return $value !== '' ? $value : null;
    }

    /**
     * @return array<int, array{min?: float, max?: float, text?: string}>
     */
    protected function resolveTemplates(string $role): array
    {
        $artifactKey = $role === 'principal'
            ? 'result_slip_principal_auto_comment_templates'
            : 'result_slip_teacher_auto_comment_templates';

        $raw = $this->organization?->getArtifactValue($artifactKey);
        if (! $raw) {
            return $this->defaultTemplates($role);
        }

        // Allow either array or JSON-string artifact storage.
        $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
        if (! is_array($decoded)) {
            return $this->defaultTemplates($role);
        }

        $clean = [];
        foreach ($decoded as $row) {
            if (! is_array($row)) {
                continue;
            }
            $text = isset($row['text']) ? trim((string) $row['text']) : '';
            if ($text === '') {
                continue;
            }
            $clean[] = [
                'min' => isset($row['min']) ? (float) $row['min'] : null,
                'max' => isset($row['max']) ? (float) $row['max'] : null,
                'text' => $text,
            ];
        }

        return count($clean) ? $clean : $this->defaultTemplates($role);
    }

    /**
     * @param array<int, array{min?: float|null, max?: float|null, text?: string|null}> $templates
     */
    protected function pickTemplate(array $templates, float $averageScore): ?string
    {
        foreach ($templates as $row) {
            $min = array_key_exists('min', $row) ? $row['min'] : null;
            $max = array_key_exists('max', $row) ? $row['max'] : null;
            $text = isset($row['text']) ? trim((string) $row['text']) : '';
            if ($text === '') {
                continue;
            }

            $minOk = $min === null ? true : $averageScore >= (float) $min;
            $maxOk = $max === null ? true : $averageScore <= (float) $max;
            if ($minOk && $maxOk) {
                return $text;
            }
        }

        return null;
    }

    protected function appendPositionPraise(string $base, string $role, float $averageScore, ?int $position, ?int $classSize): string
    {
        if (! $position || $position < 1 || ! $classSize || $classSize < 1) {
            return $base;
        }

        // Only add position praise when the student is performing reasonably well.
        if ($averageScore < 40) {
            return $base;
        }

        $suffix = null;

        if ($position <= 3) {
            $suffix = $role === 'principal'
                ? "You are among the top {$position} students in the class. Keep representing the school well."
                : "You are among the top {$position} in the class. Keep it up and stay focused.";
        } elseif ($position <= 10) {
            $suffix = $role === 'principal'
                ? 'You are within the top 10 in your class. Maintain this momentum.'
                : 'You are within the top 10 in your class. Maintain the effort.';
        } elseif ($classSize >= 20 && ($position / $classSize) <= 0.25) {
            $suffix = $role === 'principal'
                ? 'You are within the top quarter of your class. Keep improving.'
                : 'You are within the top quarter of your class. Push for even better.';
        }

        if (! $suffix) {
            return $base;
        }

        $base = rtrim($base);
        if (! str_ends_with($base, '.') && ! str_ends_with($base, '!') && ! str_ends_with($base, '?')) {
            $base .= '.';
        }

        return $base . ' ' . $suffix;
    }

    protected function fallbackBase(string $role, float $averageScore): string
    {
        if ($role === 'principal') {
            if ($averageScore >= 75) return 'Excellent performance. Keep it up.';
            if ($averageScore >= 60) return 'Very good performance. Aim even higher.';
            if ($averageScore >= 50) return 'Good effort. Improve with consistent practice.';
            if ($averageScore >= 40) return 'Fair performance. More focus and revision needed.';
            return 'Performance needs improvement. Please work harder next term.';
        }

        if ($averageScore >= 75) return 'Excellent work. Keep it up.';
        if ($averageScore >= 60) return 'Very good performance. You can do even better.';
        if ($averageScore >= 50) return 'Good effort. Keep practicing consistently.';
        if ($averageScore >= 40) return 'Fair result. More focus and revision needed.';
        return 'Needs improvement. Please work harder next term.';
    }

    /**
     * Default templates are ordered from high -> low so a simple first-match works.
     *
     * @return array<int, array{min: float, max?: float, text: string}>
     */
    protected function defaultTemplates(string $role): array
    {
        if ($role === 'principal') {
            return [
                ['min' => 85, 'text' => 'Outstanding performance. Keep the standard high.'],
                ['min' => 75, 'max' => 84.99, 'text' => 'Excellent performance. Keep it up.'],
                ['min' => 65, 'max' => 74.99, 'text' => 'Very good performance. Aim even higher.'],
                ['min' => 50, 'max' => 64.99, 'text' => 'Good effort. Improve with consistent practice.'],
                ['min' => 40, 'max' => 49.99, 'text' => 'Fair performance. More focus and revision needed.'],
                ['min' => 0, 'max' => 39.99, 'text' => 'Performance needs improvement. Please work harder next term.'],
            ];
        }

        return [
            ['min' => 85, 'text' => 'Outstanding work. Keep the standard high.'],
            ['min' => 75, 'max' => 84.99, 'text' => 'Excellent work. Keep it up.'],
            ['min' => 65, 'max' => 74.99, 'text' => 'Very good performance. You can do even better.'],
            ['min' => 50, 'max' => 64.99, 'text' => 'Good effort. Keep practicing consistently.'],
            ['min' => 40, 'max' => 49.99, 'text' => 'Fair result. More focus and revision needed.'],
            ['min' => 0, 'max' => 39.99, 'text' => 'Needs improvement. Please work harder next term.'],
        ];
    }
}
