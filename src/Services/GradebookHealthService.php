<?php

namespace Illimi\Gradebook\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Illimi\Gradebook\Models\Alert;
use Illimi\Gradebook\Models\Assessment;
use Illimi\Gradebook\Models\HealthCheck;
use Illimi\Gradebook\Models\Report;
use Illimi\Gradebook\Models\StudentRating;
use Illimi\Gradebook\Models\Token;
use Illuminate\Validation\ValidationException;

class GradebookHealthService
{
    public function runChecks(?string $organizationId = null): array
    {
        $orgId = $organizationId ?? $this->organizationId();
        $checks = [
            $this->checkDuplicateReports($orgId),
            $this->checkDuplicateTokens($orgId),
            $this->checkTokenReportCodeMismatch($orgId),
            $this->checkStaleReports($orgId),
        ];

        foreach ($checks as $check) {
            HealthCheck::query()->create([
                'organization_id' => $orgId,
                'check_name' => $check['check_name'],
                'status' => $check['status'],
                'meta' => $check['meta'],
                'checked_at' => now(),
            ]);

            if (($check['meta']['count'] ?? 0) > 0) {
                Alert::query()->create([
                    'organization_id' => $orgId,
                    'type' => $check['check_name'],
                    'severity' => $check['severity'],
                    'context' => $check['meta'],
                    'is_resolved' => false,
                    'detected_at' => now(),
                ]);
            }
        }

        return [
            'organization_id' => $orgId,
            'checked_at' => now()->toIso8601String(),
            'checks' => $checks,
            'issues_count' => collect($checks)->sum(fn ($check) => (int) ($check['meta']['count'] ?? 0)),
        ];
    }

    public function summary(?string $organizationId = null): array
    {
        $orgId = $organizationId ?? $this->organizationId();
        $unresolved = $this->alertsQuery($orgId)->where('is_resolved', false);

        return [
            'organization_id' => $orgId,
            'latest_check_at' => HealthCheck::query()
                ->when($orgId, fn ($query) => $query->where('organization_id', $orgId))
                ->max('checked_at'),
            'alerts' => [
                'total_unresolved' => (clone $unresolved)->count(),
                'critical' => (clone $unresolved)->where('severity', 'critical')->count(),
                'warning' => (clone $unresolved)->where('severity', 'warning')->count(),
            ],
            'recent_checks' => HealthCheck::query()
                ->when($orgId, fn ($query) => $query->where('organization_id', $orgId))
                ->latest('checked_at')
                ->limit(10)
                ->get()
                ->map(fn ($row) => [
                    'check_name' => $row->check_name,
                    'status' => $row->status,
                    'meta' => $row->meta,
                    'checked_at' => $row->checked_at?->toIso8601String(),
                ])
                ->values(),
        ];
    }

    public function listAlerts(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $orgId = $filters['organization_id'] ?? $this->organizationId();
        $query = $this->alertsQuery($orgId);

        if (array_key_exists('is_resolved', $filters) && $filters['is_resolved'] !== null && $filters['is_resolved'] !== '') {
            $query->where('is_resolved', (bool) $filters['is_resolved']);
        }

        if (!empty($filters['severity'])) {
            $query->where('severity', $filters['severity']);
        }

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        return $query->latest('detected_at')->paginate($perPage);
    }

    public function resolveAlert(string $id, ?string $note = null): Alert
    {
        $alert = $this->alertsQuery()->find($id);

        if (! $alert) {
            throw ValidationException::withMessages([
                'alert' => ['The selected alert could not be found.'],
            ]);
        }

        if (! $alert->is_resolved) {
            $alert->update([
                'is_resolved' => true,
                'resolved_at' => now(),
                'resolution_note' => $note,
            ]);
        } elseif ($note !== null && $note !== '') {
            $alert->update(['resolution_note' => $note]);
        }

        return $alert->fresh();
    }

    public function resolveAlerts(array $ids, ?string $note = null): array
    {
        $targetIds = collect($ids)->filter()->unique()->values();
        if ($targetIds->isEmpty()) {
            throw ValidationException::withMessages([
                'ids' => ['Provide at least one alert id.'],
            ]);
        }

        $alerts = $this->alertsQuery()
            ->whereIn('id', $targetIds)
            ->get();

        if ($alerts->isEmpty()) {
            throw ValidationException::withMessages([
                'ids' => ['No matching alerts were found.'],
            ]);
        }

        $resolvedCount = 0;
        $alreadyResolvedCount = 0;

        $alerts->each(function (Alert $alert) use (&$resolvedCount, &$alreadyResolvedCount, $note) {
            if ($alert->is_resolved) {
                $alreadyResolvedCount++;
                return;
            }

            $alert->update([
                'is_resolved' => true,
                'resolved_at' => now(),
                'resolution_note' => $note,
            ]);
            $resolvedCount++;
            return;
        });

        if ($note !== null && $note !== '') {
            $alerts->filter(fn (Alert $alert) => $alert->is_resolved)
                ->each(function (Alert $alert) use ($note) {
                    $alert->update(['resolution_note' => $note]);
                });
        }

        return [
            'requested_count' => $targetIds->count(),
            'matched_count' => $alerts->count(),
            'resolved_count' => $resolvedCount,
            'already_resolved_count' => $alreadyResolvedCount,
        ];
    }

    protected function checkDuplicateReports(?string $organizationId): array
    {
        $duplicates = $this->reportsQuery($organizationId)
            ->selectRaw('student_id, academic_class_id, academic_year_id, academic_term_id, count(*) as aggregate_count')
            ->groupBy('student_id', 'academic_class_id', 'academic_year_id', 'academic_term_id')
            ->havingRaw('count(*) > 1')
            ->get();

        return $this->result('duplicate_reports', $duplicates, 'critical');
    }

    protected function checkDuplicateTokens(?string $organizationId): array
    {
        $duplicates = $this->tokensQuery($organizationId)
            ->selectRaw('student_id, academic_class_id, academic_year_id, academic_term_id, count(*) as aggregate_count')
            ->groupBy('student_id', 'academic_class_id', 'academic_year_id', 'academic_term_id')
            ->havingRaw('count(*) > 1')
            ->get();

        return $this->result('duplicate_tokens', $duplicates, 'critical');
    }

    protected function checkTokenReportCodeMismatch(?string $organizationId): array
    {
        $tokenRows = $this->tokensQuery($organizationId)
            ->get(['id', 'student_id', 'academic_class_id', 'academic_year_id', 'academic_term_id', 'code']);

        $reportCodeMap = $this->reportsQuery($organizationId)
            ->get(['student_id', 'academic_class_id', 'academic_year_id', 'academic_term_id', 'code'])
            ->keyBy(fn ($row) => $row->student_id.'|'.$row->academic_class_id.'|'.$row->academic_year_id.'|'.$row->academic_term_id);

        $mismatch = $tokenRows->filter(function ($token) use ($reportCodeMap) {
            $key = $token->student_id.'|'.$token->academic_class_id.'|'.$token->academic_year_id.'|'.$token->academic_term_id;
            $reportCode = $reportCodeMap->get($key)?->code;

            return $reportCode !== $token->code;
        })->values();

        return $this->result('token_report_code_mismatch', $mismatch, 'warning');
    }

    protected function checkStaleReports(?string $organizationId): array
    {
        $reports = $this->reportsQuery($organizationId)
            ->get(['id', 'student_id', 'academic_class_id', 'academic_year_id', 'academic_term_id', 'updated_at']);

        $stale = $reports->filter(function ($report) use ($organizationId) {
            $latestAssessmentUpdate = $this->assessmentsQuery($organizationId)
                ->where('student_id', $report->student_id)
                ->where('academic_class_id', $report->academic_class_id)
                ->where('academic_year_id', $report->academic_year_id)
                ->where('academic_term_id', $report->academic_term_id)
                ->max('updated_at');

            $latestRatingUpdate = $this->ratingsQuery($organizationId)
                ->where('student_id', $report->student_id)
                ->where('academic_class_id', $report->academic_class_id)
                ->where('academic_year_id', $report->academic_year_id)
                ->where('academic_term_id', $report->academic_term_id)
                ->max('updated_at');

            $sourceLatest = collect([$latestAssessmentUpdate, $latestRatingUpdate])
                ->filter()
                ->max();

            if (!$sourceLatest) {
                return false;
            }

            return $report->updated_at < $sourceLatest;
        })->values();

        return $this->result('stale_reports', $stale, 'warning');
    }

    protected function result(string $checkName, $rows, string $severity): array
    {
        $count = is_countable($rows) ? count($rows) : 0;

        return [
            'check_name' => $checkName,
            'status' => $count > 0 ? 'issues_found' : 'ok',
            'severity' => $severity,
            'meta' => [
                'count' => $count,
                'sample' => collect($rows)->take(20)->values(),
            ],
        ];
    }

    protected function alertsQuery(?string $organizationId = null)
    {
        return Alert::query()
            ->when($organizationId, fn ($query) => $query->where('organization_id', $organizationId));
    }

    protected function reportsQuery(?string $organizationId = null)
    {
        return Report::query()
            ->when($organizationId, fn ($query) => $query->where('organization_id', $organizationId));
    }

    protected function tokensQuery(?string $organizationId = null)
    {
        return Token::query()
            ->when($organizationId, fn ($query) => $query->where('organization_id', $organizationId));
    }

    protected function assessmentsQuery(?string $organizationId = null)
    {
        return Assessment::query()
            ->when($organizationId, fn ($query) => $query->where('organization_id', $organizationId));
    }

    protected function ratingsQuery(?string $organizationId = null)
    {
        return StudentRating::query()
            ->when($organizationId, fn ($query) => $query->where('organization_id', $organizationId));
    }

    protected function organizationId(): ?string
    {
        return optional(function_exists('organization') ? organization() : null)->id
            ?? auth()->user()?->organization_id;
    }
}
