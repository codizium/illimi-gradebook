# Gradebook Reactive + Proactive Blueprint

## 1) Outcomes
- Reactive: every gradebook change is reflected quickly and safely across UI and services.
- Proactive: the system detects gaps early, nudges the right users, and prevents invalid end states.

## 2) Phase Plan

### Phase A: Reactive Core (1-2 sprints)
1. Standardize domain events.
2. Move side effects to queued listeners/jobs.
3. Add operational telemetry and failure alerts.
4. Improve real-time client updates with targeted payloads.

### Phase B: Proactive Guardrails (1 sprint)
1. Add nightly integrity checks.
2. Add deadline/reminder jobs.
3. Add precondition checks before critical actions (report generation/export).

### Phase C: Proactive Intelligence (1-2 sprints)
1. Risk scoring and completion forecasting.
2. Actionable “Ops” dashboard cards and recommendations.

## 3) Event Contract (Reactive Backbone)

Use one envelope for all gradebook events:

```json
{
  "event": "gradebook.assessment.saved",
  "organization_id": "uuid",
  "actor_user_id": "uuid",
  "entity_id": "uuid",
  "entity_type": "assessment",
  "context": {
    "student_id": "uuid",
    "academic_class_id": "uuid",
    "academic_year_id": "uuid",
    "academic_term_id": "uuid",
    "subject_id": "uuid"
  },
  "occurred_at": "ISO-8601"
}
```

Recommended events:
- `gradebook.assessment.saved|updated|deleted`
- `gradebook.rating.saved`
- `gradebook.template.created|updated|deleted`
- `gradebook.report.generated|updated|deleted`
- `gradebook.token.generated|updated|used|deleted`
- `gradebook.integrity.issue.detected`

## 4) Queue/Job Design

Create jobs in `illimi-gradebook/src/Jobs`:
- `RecomputeClassCompletionStatsJob`
- `RefreshReportSnapshotJob`
- `SendGradebookReminderJob`
- `RunGradebookIntegrityChecksJob`
- `PublishGradebookRealtimeJob`

Execution rules:
- Use idempotency keys per `(organization_id, class, year, term, action)`.
- `tries` + exponential backoff.
- Dead-letter behavior for repeated failures.
- Use unique jobs for high-frequency events (assessment save storms).

## 5) Data Additions (Proactive Layer)

Add tables/migrations:
- `illimi_gradebook_health_checks`
  - `organization_id`, `check_name`, `status`, `meta (json)`, `checked_at`
- `illimi_gradebook_alerts`
  - `organization_id`, `type`, `severity`, `context (json)`, `is_resolved`, `resolved_at`
- `illimi_gradebook_completion_snapshots`
  - `organization_id`, `academic_class_id`, `academic_year_id`, `academic_term_id`, `completion_percent`, `captured_at`

Optional:
- `illimi_gradebook_recommendations` for generated “next best actions”.

## 6) Integrity Checks (Nightly + On-demand)

Implement in `RunGradebookIntegrityChecksJob`:
1. Students in class with no assessment rows for active term.
2. Assessment rows with missing template items.
3. Reports older than latest assessment/rating update (stale reports).
4. Active tokens without matching report code.
5. Ratings partial completeness (effective/psychomotor gaps).
6. Duplicate scope records (same student/class/year/term).

Emit `gradebook.integrity.issue.detected` + persist in alerts table.

## 7) Proactive Workflow Rules

Before report generation:
- Require minimum completion threshold (configurable, e.g. 80%).
- Block generation if critical integrity issues exist.
- Return structured error with fix suggestions.

Before token export:
- Warn when token/report scope has stale or missing report payloads.

Before final term publish (if applicable):
- Require both effective + psychomotor ratings completeness.

## 8) API and Service Changes

### Backend (illimi-gradebook)
- Add `HealthController`:
  - `GET /api/v1/gradebook/health`
  - `GET /api/v1/gradebook/alerts`
  - `POST /api/v1/gradebook/health/run`
- Add service methods:
  - `GradebookHealthService::runChecks(...)`
  - `GradebookOpsService::classCompletion(...)`
  - `ReportService::isStale(...)`

### Existing services
- `AssessmentService`: dispatch recompute + stale-report marking jobs.
- `StudentRatingService`: dispatch stale-report marking jobs.
- `TokenService`: verify/sync report code in scoped transaction.
- `ReportService`: include `is_stale`, `last_source_update_at`.

## 9) Frontend (edu-portal) UX Enhancements

### Gradebook pages
- `Pages/Gradebook/Sheet.jsx`:
  - show inline “missing students/items” hints.
  - show class completion trend badge.
- `Pages/Gradebook/Reports.jsx`:
  - stale badge + “refresh stale only” action.
- `Pages/Gradebook/Tokens.jsx`:
  - warning chip for tokens with stale/missing report snapshots.

### New Ops page
- `Pages/Gradebook/Ops.jsx`:
  - Cards: completion %, stale reports, unresolved alerts, failed jobs.
  - Actions: run checks, notify teachers, regenerate reports.

## 10) Real-time Strategy

For Reverb payloads:
- Send minimal diff payloads (`entity`, `scope`, `change_type`).
- Client decides whether to `router.reload` partial props.
- Debounce repeated reloads on bursty updates.

## 11) Observability & SLOs

Track:
- `gradebook_assessment_save_latency_ms`
- `gradebook_report_generate_latency_ms`
- `gradebook_job_failure_rate`
- `gradebook_completion_percent_by_class`
- `gradebook_stale_report_count`

Target SLO examples:
- 99% of assessment saves reflected in UI within 5s.
- <1% failed gradebook jobs/day.
- 95% reports freshness within 1h of source update.

## 12) Config Flags (Safe Rollout)

Add to `illimi-gradebook/config/gradebook.php`:
- `ops.enabled`
- `ops.require_completion_threshold`
- `ops.completion_threshold_percent`
- `ops.health_checks_schedule`
- `ops.reminder_schedule`

Feature-flag each rule before enforcing hard blocks.

## 13) Suggested Implementation Order (Concrete)

1. Add event envelope + listener/job wiring.
2. Add health/alert tables + nightly integrity job.
3. Add stale report detection + badges in Reports page.
4. Add completion threshold gate on report generation.
5. Add Ops API + Ops dashboard page.
6. Add recommendation engine (risk scoring/next actions).

## 14) First Deliverable (MVP in 1 sprint)

MVP scope:
- Integrity checks + alerts persistence.
- Stale report flagging and regenerate action.
- Completion threshold warning (soft fail first).
- Ops summary endpoint + minimal dashboard card.

This gives immediate proactive value without heavy ML or complex infra.
