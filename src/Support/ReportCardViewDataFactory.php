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

            return [
                'index' => $index + 1,
                'subject' => (string) ($firstFilled(
                    data_get($assessment, 'subject_name'),
                    data_get($assessment, 'subject.name'),
                    data_get($assessment, 'name'),
                    data_get($assessment, 'subject_name')
                ) ?? '—'),
                'componentScores' => $componentScores,
                'total' => $formatNumber(data_get($assessment, 'total_score', data_get($assessment, 'total', 0)), '0.00'),
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

        $gradeScaleRows = GradeScale::query()
            ->when($organizationId, fn ($query) => $query->where('organization_id', $organizationId))
            ->orderByDesc('max_score')
            ->orderByDesc('min_score')
            ->get(['id', 'name', 'code', 'min_score', 'max_score', 'description'])
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

        $overallTotal = (float) data_get($summary, 'overall_total', data_get($summary, 'total_score', 0));
        $averageScore = (float) data_get($summary, 'average_score', data_get($summary, 'average', 0));
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

        $verificationUrl = null;
        $verificationText = null;
        $qrSvg = null;
        $admissionNumber = (string) (($context['student_admission_number'] ?? null) ?? data_get($payload, 'student.admission_number') ?? '—');
        $token = (string) (($context['token'] ?? null) ?? data_get($payload, 'token') ?? data_get($summary, 'token') ?? '—');

        if ($admissionNumber !== '—' && $token !== '—') {
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
            'comment' => (string) ($teacherComment ?? '—'),
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
