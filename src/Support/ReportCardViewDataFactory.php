<?php

namespace Illimi\Gradebook\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Carbon\CarbonInterface;
use Codizium\Core\Models\Organization;
use Illuminate\Support\Collection;
use Illimi\Academics\Models\GradeScale;
use Illimi\Gradebook\Models\Report;
use Illimi\Gradebook\Models\Assessment;
use Illimi\Gradebook\Services\StudentRatingService;
use Illimi\Gradebook\Support\ResultSlipAutoCommentGenerator;

class ReportCardViewDataFactory
{
    public static function make(Report $report, ?Organization $organization, CarbonInterface $generatedAt): array
    {
        $payload = is_array($report->payload) ? $report->payload : [];

        return static::build($payload, $organization, $generatedAt, [
            'organization_id' => $report->organization_id,
            'student_first_name' => $report->student?->first_name,
            'student_last_name' => $report->student?->last_name,
            'student_full_name' => $report->student?->full_name,
            'student_admission_number' => $report->student?->admission_number,
            'class_name' => $report->academicClass?->name,
            'class_section_name' => $report->academicClass?->section?->name,
            'year_name' => $report->academicYear?->name,
            'term_name' => $report->academicTerm?->name,
            'token' => $report->code,
            'principal_signature_url' => $organization?->getAttachmentUrl('principal_signature'),
            'class_teacher_signature_url' => $report->academicClass?->getAttachmentUrl('class_teacher_signature'),
        ]);
    }

    public static function makeFromPayload(
        array $payload,
        ?Organization $organization,
        CarbonInterface $generatedAt,
        array $context = []
    ): array {
        return static::build($payload, $organization, $generatedAt, $context);
    }

    protected static function build(
        array $payload,
        ?Organization $organization,
        CarbonInterface $generatedAt,
        array $context = []
    ): array {
        $organizationId = $context['organization_id'] ?? data_get($payload, 'ids.organization_id') ?? data_get($payload, 'organization.id');
        

        $summary = is_array(data_get($payload, 'summary')) ? data_get($payload, 'summary') : [];
        $assessments = collect(is_array(data_get($payload, 'assessments')) ? data_get($payload, 'assessments') : []);
        $ratings = is_array(data_get($payload, 'ratings')) ? data_get($payload, 'ratings') : [];
        $templateItems = collect(is_array(data_get($payload, 'template.items')) ? data_get($payload, 'template.items') : []);
        $reportOrganization = is_array(data_get($payload, 'organization')) ? data_get($payload, 'organization') : [];
        $presentation = is_array(data_get($payload, 'presentation')) ? data_get($payload, 'presentation') : [];
        $template = data_get($payload, 'template') ?? [];

        $firstFilled = static function (...$values): ?string {
            foreach ($values as $value) {
                if ($value === null) {
                    continue;
                }

                $string = trim((string) $value);
                if ($string !== '') {
                    return $string;
                }
            }

            return null;
        };

        $studentFirstName = trim((string) (($context['student_first_name'] ?? null) ?? data_get($payload, 'student.first_name') ?? ''));
        $studentLastName = trim((string) (($context['student_last_name'] ?? null) ?? data_get($payload, 'student.last_name') ?? ''));
        $studentName = trim($studentFirstName . ' ' . $studentLastName);
        if ($studentName === '') {
            $studentName = trim((string) (($context['student_full_name'] ?? null) ?? data_get($payload, 'student.full_name') ?? data_get($payload, 'student.name') ?? ''));
        }

        $overallTotal = (float) data_get($summary, 'overall_total', data_get($summary, 'total_score', 0));
        $averageScore = (float) data_get($summary, 'average_score', data_get($summary, 'average', 0));

        $formatNumber = static function ($value, string $fallback = '—'): string {
            if ($value === '' || $value === null) {
                return $fallback;
            }

            return is_numeric($value) ? number_format((float) $value, 2) : $fallback;
        };

        $formatClassName = static function (?string $className, ?string $sectionName = null): string {
            $name = trim((string) $className);
            $section = trim((string) $sectionName);

            if ($name === '' && $section === '') {
                return '—';
            }

            if ($name === '') {
                return $section;
            }

            return $section !== '' ? "{$name} - {$section}" : $name;
        };

        $embedQrBrand = static function (?string $svg, string $brand = 'iLLIMI'): ?string {
            if (! is_string($svg) || trim($svg) === '' || ! str_contains($svg, '</svg>')) {
                return $svg;
            }

            $overlay = <<<SVG
<rect x="35%" y="43.5%" width="30%" height="13%" rx="4" ry="4" fill="#ffffff"/>
<text x="50%" y="50.8%" text-anchor="middle" dominant-baseline="middle" font-size="24" font-weight="700" font-family="Arial, Helvetica, sans-serif" fill="#000000">{$brand}</text>
SVG;

            return str_replace('</svg>', $overlay . '</svg>', $svg);
        };

        $componentColumns = $templateItems
            ->map(function ($item, int $index) use ($firstFilled) {
                return [
                    'id' => data_get($item, 'id'),
                    'key' => $firstFilled(data_get($item, 'key'), data_get($item, 'code'), data_get($item, 'id'), data_get($item, 'label')) ?? ('component_' . ($index + 1)),
                    'index' => $index + 1,
                    'code' => $firstFilled(data_get($item, 'code')),
                    'label' => $firstFilled(data_get($item, 'label'), data_get($item, 'code')) ?? '—',
                    'component_type' => data_get($item, 'component_type'),
                    'max_score' => data_get($item, 'max_score'),
                ];
            })
            ->values();

        $effectiveRatings = collect(is_array(data_get($ratings, 'effective')) ? data_get($ratings, 'effective') : [])->values();
        $psychomotorRatings = collect(is_array(data_get($ratings, 'psychomotor')) ? data_get($ratings, 'psychomotor') : [])->values();

        $shouldAutoGenerateEffective = (bool) ($organization?->getArtifactValue('result_slip_auto_generate_effective_traits', true) ?? true);
        $shouldAutoGeneratePsychomotor = (bool) ($organization?->getArtifactValue('result_slip_auto_generate_psychomotor_skills', true) ?? true);

        if (
            $organization &&
            (
                ($effectiveRatings->isEmpty() && $shouldAutoGenerateEffective) ||
                ($psychomotorRatings->isEmpty() && $shouldAutoGeneratePsychomotor)
            )
        ) {
            $ratingService = app(StudentRatingService::class);

            $seedBase = implode('|', array_filter([
                (string) $organization->id,
                (string) ($context['student_id'] ?? data_get($payload, 'ids.student_id') ?? data_get($payload, 'student.id') ?? ''),
                (string) ($context['class_id'] ?? data_get($payload, 'ids.academic_class_id') ?? ''),
                (string) ($context['year_id'] ?? data_get($payload, 'ids.academic_year_id') ?? ''),
                (string) ($context['term_id'] ?? data_get($payload, 'ids.academic_term_id') ?? ''),
            ]));
            $seed = (int) sprintf('%u', crc32($seedBase));

            $generateNumericRating = static function (float $avg, int $seed, string $key): int {
                $base = 1;
                if ($avg >= 75) $base = 5;
                elseif ($avg >= 60) $base = 4;
                elseif ($avg >= 50) $base = 3;
                elseif ($avg >= 40) $base = 2;

                $kSeed = (int) sprintf('%u', crc32($seed . '|' . $key));
                $delta = ($kSeed % 3) - 1; // -1, 0, +1

                if ($base >= 4 && $delta < 0) $delta = 0;
                if ($base <= 2 && $delta > 0) $delta = 0;

                return max(1, min(5, $base + $delta));
            };

            if ($effectiveRatings->isEmpty() && $shouldAutoGenerateEffective) {
                $effectiveRatings = collect($ratingService->effectiveItems($organization))
                    ->map(function (string $label, string $key) use ($ratingService, $averageScore, $seed, $generateNumericRating) {
                        $value = $generateNumericRating($averageScore, $seed, 'e:' . $key);

                        return [
                            'label' => $label,
                            'value' => $value,
                            'grade' => $ratingService->ratingLabel($value),
                        ];
                    })
                    ->values();
            }

            if ($psychomotorRatings->isEmpty() && $shouldAutoGeneratePsychomotor) {
                $psychomotorRatings = collect($ratingService->psychomotorItems($organization))
                    ->map(function (string $label, string $key) use ($ratingService, $averageScore, $seed, $generateNumericRating) {
                        $value = $generateNumericRating($averageScore, $seed, 'p:' . $key);

                        return [
                            'label' => $label,
                            'value' => $value,
                            'grade' => $ratingService->ratingLabel($value),
                        ];
                    })
                    ->values();
            }
        }

        $traitRows = max($effectiveRatings->count(), $psychomotorRatings->count(), 1);

        $assessmentRows = $assessments->map(function (array $assessment, int $index) use ($componentColumns, $formatNumber, $firstFilled) {
            $assessmentComponents = collect(is_array(data_get($assessment, 'components')) ? data_get($assessment, 'components') : []);
            $componentScores = $componentColumns->map(function (array $column) use ($assessment, $formatNumber, $assessmentComponents) {
                $componentId = $column['id'] ?? null;
                $componentKey = $column['key'] ?? null;
                $value = $componentId ? data_get($assessment, 'scores.' . $componentId) : null;

                if ($value === null && $componentKey) {
                    $value = data_get($assessment, 'scores.' . $componentKey);
                }

                if ($value === null && $componentKey) {
                    $matchedComponent = $assessmentComponents->first(function ($component) use ($componentKey) {
                        $candidate = trim((string) (data_get($component, 'key') ?? data_get($component, 'code') ?? data_get($component, 'label') ?? ''));

                        return $candidate !== '' && $candidate === $componentKey;
                    });

                    $value = data_get($matchedComponent, 'score');
                }

                if ($value === null && data_get($column, 'component_type') === 'exam') {
                    $value = data_get($assessment, 'exam_total', data_get($assessment, 'exams'));
                }

                return [
                    'id' => $column['id'],
                    'label' => $column['label'],
                    'code' => $column['code'],
                    'component_type' => $column['component_type'],
                    'score' => $formatNumber($value),
                ];
            })->values()->all();

            $rawTotal = data_get($assessment, 'total_score', data_get($assessment, 'total', 0));
            $rawTotalFloat = is_numeric($rawTotal) ? (float) $rawTotal : 0.0;

            return [
                'index' => $index + 1,
                'subject' => (string) ($firstFilled(
                    data_get($assessment, 'subject_name'),
                    data_get($assessment, 'subject.name'),
                    data_get($assessment, 'name'),
                    data_get($assessment, 'subject_name')
                ) ?? '—'),
                'componentScores' => $componentScores,
                'total_raw' => $rawTotalFloat,
                'total' => $formatNumber($rawTotalFloat, '0.00'),
                'grade' => (string) ($firstFilled(
                    data_get($assessment, 'graded'),
                    data_get($assessment, 'grade'),
                    data_get($assessment, 'grade_scale.code')
                ) ?? '—'),
                'position' => (string) ($firstFilled(
                    data_get($assessment, 'position'),
                    data_get($assessment, 'subject_position')
                ) ?? '—'),
                'remark' => (string) ($firstFilled(
                    data_get($assessment, 'remark'),
                    data_get($assessment, 'grade_scale.description')
                ) ?? '—'),
            ];
        })->all();

        $traitTableRows = [];
        for ($index = 0; $index < $traitRows; $index++) {
            $traitTableRows[] = [
                'effective_label' => (string) data_get($effectiveRatings->get($index), 'label', '—'),
                'effective_grade' => (string) data_get($effectiveRatings->get($index), 'grade', '—'),
                'psychomotor_label' => (string) data_get($psychomotorRatings->get($index), 'label', '—'),
                'psychomotor_grade' => (string) data_get($psychomotorRatings->get($index), 'grade', '—'),
            ];
        }

        $gradeScales = GradeScale::query()
            ->when($organizationId, fn ($query) => $query->where('organization_id', $organizationId))
            ->orderByDesc('max_score')
            ->orderByDesc('min_score')
            ->get(['id', 'name', 'code', 'min_score', 'max_score', 'description']);

        // If grade scales exist, compute grade codes from subject totals to avoid inconsistent payload values.
        if ($gradeScales->isNotEmpty()) {
            $assessmentRows = array_map(function (array $row) use ($gradeScales) {
                $score = $row['total_raw'] ?? null;
                if (! is_numeric($score)) {
                    return $row;
                }

                $score = (float) $score;

                /** @var GradeScale|null $matched */
                $matched = $gradeScales->first(function (GradeScale $scale) use ($score) {
                    $min = $scale->min_score;
                    $max = $scale->max_score;

                    $minOk = $min === null ? true : $score >= (float) $min;
                    $maxOk = $max === null ? true : $score <= (float) $max;

                    return $minOk && $maxOk;
                });

                if (! $matched) {
                    return $row;
                }

                $row['grade'] = trim((string) ($matched->code ?? $matched->name ?? $row['grade'] ?? '—')) ?: ($row['grade'] ?? '—');

                return $row;
            }, $assessmentRows);
        }

        $gradeScaleRows = $gradeScales
            ->map(fn (GradeScale $gradeScale) => [
                'code' => trim((string) ($gradeScale->code ?? $gradeScale->name ?? '—')) ?: '—',
                'name' => $firstFilled($gradeScale->name, $gradeScale->description) ?? '—',
                'range' => collect([
                    $gradeScale->max_score !== null ? rtrim(rtrim(number_format((float) $gradeScale->max_score, 2), '0'), '.') : null,
                    $gradeScale->min_score !== null ? rtrim(rtrim(number_format((float) $gradeScale->min_score, 2), '0'), '.') : null,
                ])->filter(fn ($value) => $value !== null && $value !== '')->implode(' - '),
                'remark' => $firstFilled($gradeScale->description, $gradeScale->name) ?? '—',
            ])
            ->values();

        if ($gradeScaleRows->isEmpty()) {
            $gradeScaleRows = $assessments
                ->map(fn (array $assessment) => [
                    'code' => $firstFilled(data_get($assessment, 'grade_scale.code'), data_get($assessment, 'grade'), data_get($assessment, 'graded')) ?? '—',
                    'name' => $firstFilled(data_get($assessment, 'grade_scale.name'), data_get($assessment, 'grade_scale.description')) ?? '—',
                    'range' => collect([
                        data_get($assessment, 'grade_scale.max_score'),
                        data_get($assessment, 'grade_scale.min_score'),
                    ])->filter(fn ($value) => $value !== null && $value !== '')->map(function ($value) {
                        return is_numeric($value)
                            ? rtrim(rtrim(number_format((float) $value, 2), '0'), '.')
                            : trim((string) $value);
                    })->implode(' - '),
                    'remark' => $firstFilled(data_get($assessment, 'grade_scale.description'), data_get($assessment, 'remark')) ?? '—',
                ])
                ->filter(fn (array $row) => ($row['code'] ?? '—') !== '—')
                ->unique('code')
                ->values();
        }

        $assessmentCount = max(1, (int) data_get($summary, 'assessment_count', count($assessmentRows)));

        $organizationName = $firstFilled(
            data_get($reportOrganization, 'name'),
            $organization?->getArtifactValue('text_portal_name', $organization?->name),
            $organization?->name
        );
        $reportTitle = $firstFilled(
            data_get($presentation, 'title'),
            $organization?->getArtifactValue('report_card_title'),
            $organization?->getArtifactValue('school_report_title'),
            $firstFilled(data_get($payload, 'academic_term.name'), data_get($payload, 'term.name'))
                ? trim(($firstFilled(data_get($payload, 'academic_term.name'), data_get($payload, 'term.name')) ?? '') . ' Term Report Card')
                : null,
            'Report Card'
        );
        $reportFooter = $firstFilled(
            data_get($presentation, 'footer'),
            $organization?->getArtifactValue('report_card_footer'),
            $organization?->getArtifactValue('school_report_footer')
        );
        $teacherComment = $firstFilled(
            data_get($payload, 'comment'),
            data_get($payload, 'remarks.teacher'),
            data_get($payload, 'remarks.class_teacher'),
            data_get($summary, 'remark')
        );
        $principalComment = $firstFilled(
            data_get($payload, 'remarks.principal'),
            data_get($payload, 'remarks.head_teacher'),
            data_get($payload, 'remarks.headmaster'),
            data_get($payload, 'remarks.headmistress')
        );

        $slipSettings = [
            'show_average' => (bool) ($organization?->getArtifactValue('result_slip_show_average', true) ?? true),
            'show_position' => (bool) ($organization?->getArtifactValue('result_slip_show_position', true) ?? true),
            'show_subject_position' => (bool) ($organization?->getArtifactValue('result_slip_show_subject_position', true) ?? true),
            'show_total_score' => (bool) ($organization?->getArtifactValue('result_slip_show_total_score', true) ?? true),
            'show_qrcode' => (bool) ($organization?->getArtifactValue('result_slip_show_qrcode', true) ?? true),
            'show_effective_traits' => (bool) ($organization?->getArtifactValue('result_slip_show_effective_traits', true) ?? true),
            'show_psychomotor_skills' => (bool) ($organization?->getArtifactValue('result_slip_show_psychomotor_skills', true) ?? true),
            'auto_generate_effective_traits' => (bool) ($organization?->getArtifactValue('result_slip_auto_generate_effective_traits', true) ?? true),
            'auto_generate_psychomotor_skills' => (bool) ($organization?->getArtifactValue('result_slip_auto_generate_psychomotor_skills', true) ?? true),
            'teacher_comment_mode' => (string) ($organization?->getArtifactValue('result_slip_teacher_comment_mode', 'auto') ?? 'auto'),
            'principal_comment_mode' => (string) ($organization?->getArtifactValue('result_slip_principal_comment_mode', 'manual') ?? 'manual'),
        ];

        $positionInt = null;
        $classSizeInt = null;
        $positionRaw = data_get($summary, 'position') ?? data_get($summary, 'position_in_class') ?? data_get($summary, 'class_position') ?? data_get($summary, 'rank');
        $classSizeRaw = data_get($summary, 'pupil_count') ?? data_get($summary, 'out_of') ?? data_get($summary, 'class_size') ?? data_get($summary, 'total_students');
        if (is_numeric($positionRaw)) {
            $positionInt = max(1, (int) $positionRaw);
        }
        if (is_numeric($classSizeRaw)) {
            $classSizeInt = max(1, (int) $classSizeRaw);
        }

        $commentGenerator = ResultSlipAutoCommentGenerator::make($organization);

        $subjectScores = $assessments
            ->map(function (array $assessment) use ($firstFilled) {
                $name = (string) ($firstFilled(
                    data_get($assessment, 'subject_name'),
                    data_get($assessment, 'subject.name'),
                    data_get($assessment, 'name')
                ) ?? '');
                $score = data_get($assessment, 'total_score', data_get($assessment, 'total', null));

                return [
                    'subject' => trim($name),
                    'score' => is_numeric($score) ? (float) $score : null,
                ];
            })
            ->filter(fn (array $row) => ($row['subject'] ?? '') !== '' && $row['score'] !== null)
            ->values();

        $topSubject = $subjectScores->sortByDesc('score')->first()['subject'] ?? null;
        $weakSubject = $subjectScores->sortBy('score')->first()['subject'] ?? null;

        $commentContext = [
            'student_first_name' => $studentFirstName,
            'student_name' => $studentName !== '' ? $studentName : null,
            'admission_number' => $admissionNumber ?? null,
            'academic_year' => (string) (($context['year_name'] ?? null) ?? data_get($payload, 'academic_year.name') ?? ''),
            'academic_term' => (string) (($context['term_name'] ?? null) ?? data_get($payload, 'academic_term.name') ?? ''),
            'top_subject' => $topSubject,
            'weak_subject' => $weakSubject,
        ];

        $resolvedTeacherComment = null;
        if ($slipSettings['teacher_comment_mode'] === 'manual') {
            $resolvedTeacherComment = $teacherComment ?: null;
        }
        if ($slipSettings['teacher_comment_mode'] === 'auto' || ! $resolvedTeacherComment) {
            $resolvedTeacherComment = $commentGenerator->teacher($averageScore, $positionInt, $classSizeInt, $commentContext);
        }

        $resolvedPrincipalComment = null;
        if ($slipSettings['principal_comment_mode'] === 'manual') {
            $resolvedPrincipalComment = $principalComment ?: null;
        }
        if ($slipSettings['principal_comment_mode'] === 'auto' || ! $resolvedPrincipalComment) {
            $resolvedPrincipalComment = $commentGenerator->principal($averageScore, $positionInt, $classSizeInt, $commentContext);
        }

        $verificationUrl = null;
        $verificationText = null;
        $qrSvg = null;
        $admissionNumber = (string) (($context['student_admission_number'] ?? null) ?? data_get($payload, 'student.admission_number') ?? '—');
        $token = (string) (($context['token'] ?? null) ?? data_get($payload, 'token') ?? data_get($summary, 'token') ?? '—');

        if ($admissionNumber !== '—' && $token !== '—' && $slipSettings['show_qrcode']) {
            $verificationUrl = route('academics.results.check.view', [
                'admission_number' => $admissionNumber,
                'token' => $token,
            ]);

            $verificationText = implode(' | ', array_filter([
                $admissionNumber,
                $studentName !== '' ? $studentName : null,
                data_get($payload, 'academic_year.name'),
                data_get($payload, 'academic_term.name'),
                $token,
            ]));

            $renderer = new ImageRenderer(
                new RendererStyle(512),
                new SvgImageBackEnd
            );

            $qrSvg = $embedQrBrand((new Writer($renderer))->writeString($verificationUrl));
        }

        return [
            'generatedAt' => $generatedAt,
            'organizationName' => (string) ($organizationName ?? ''),
            'organizationAddress' => (string) ($firstFilled(data_get($reportOrganization, 'address'), $organization?->address) ?? ''),
            'organizationEmail' => (string) ($firstFilled(data_get($reportOrganization, 'email'), $organization?->email) ?? ''),
            'organizationPhone' => (string) ($firstFilled(data_get($reportOrganization, 'phone'), $organization?->phone) ?? ''),
            'organizationLogo' => data_get($reportOrganization, 'logo') ?? $organization?->getArtifactValue('brand_logo') ?? $organization?->getAttachmentUrl('logo') ?? null,
            'reportTitle' => (string) ($reportTitle ?? 'Report Card'),
            'reportFooter' => (string) ($reportFooter ?? ''),
            'studentName' => $studentName !== '' ? $studentName : '—',
            'className' => $formatClassName(
                ($context['class_name'] ?? null) ?? data_get($payload, 'class.name'),
                ($context['class_section_name'] ?? null) ?? data_get($payload, 'class.section_name') ?? data_get($payload, 'class.section.name') ?? data_get($payload, 'section.name'),
            ),
            'yearName' => (string) (($context['year_name'] ?? null) ?? data_get($payload, 'academic_year.name') ?? '—'),
            'termName' => (string) (($context['term_name'] ?? null) ?? data_get($payload, 'academic_term.name') ?? '—'),
            'templateName' => $template['name'] ?? '—',
            'admissionNumber' => $admissionNumber,
            'token' => $token,
            'averageScore' => $formatNumber($averageScore, '0.00') . '%',
            'overallTotal' => $formatNumber($overallTotal, '0.00'),
            'classPosition' => (string) (data_get($summary, 'position') ?? data_get($summary, 'position_in_class') ?? data_get($summary, 'class_position') ?? data_get($summary, 'rank') ?? '—'),
            'classSize' => (string) (data_get($summary, 'pupil_count') ?? data_get($summary, 'out_of') ?? data_get($summary, 'class_size') ?? data_get($summary, 'total_students') ?? '—'),
            'comment' => (string) ($resolvedTeacherComment ?? '—'),
            'teacherComment' => (string) ($resolvedTeacherComment ?? '—'),
            'principalComment' => (string) ($resolvedPrincipalComment ?? '—'),
            'principalSignatureUrl' => (string) ($context['principal_signature_url'] ?? $organization?->getAttachmentUrl('principal_signature') ?? ''),
            'classTeacherSignatureUrl' => (string) ($context['class_teacher_signature_url'] ?? ''),
            'slipSettings' => $slipSettings,
            'verificationUrl' => $verificationUrl,
            'verificationText' => $verificationText,
            'qrSvg' => $qrSvg,
            'summaryRows' => [
                ['label' => 'Subjects Offered', 'value' => (string) (int) data_get($summary, 'assessment_count', count($assessmentRows))],
                ['label' => 'Overall Total', 'value' => $formatNumber($overallTotal, '0.00')],
                ['label' => 'Average', 'value' => $formatNumber($averageScore, '0.00') . '%'],
                ['label' => 'Class Average', 'value' => $formatNumber($assessmentCount > 0 ? round($overallTotal / $assessmentCount, 2) : 0, '0.00')],
            ],
            'gradeScaleRows' => $gradeScaleRows->all(),
            'componentColumns' => $componentColumns->all(),
            'assessmentRows' => $assessmentRows,
            'traitRows' => $traitTableRows,
        ];
    }
}
