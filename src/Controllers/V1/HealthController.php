<?php

namespace Illimi\Gradebook\Controllers\V1;

use Codizium\Core\Controllers\BaseController;
use Codizium\Core\Helpers\CoreJsonResponse;
use Illuminate\Http\Request;
use Illimi\Gradebook\Jobs\RunGradebookIntegrityChecksJob;
use Illimi\Gradebook\Services\GradebookHealthService;

class HealthController extends BaseController
{
    public function __construct(
        protected GradebookHealthService $service,
        protected CoreJsonResponse $response
    ) {
    }

    public function summary()
    {
        return $this->response->success(
            $this->service->summary(),
            'Gradebook health summary retrieved successfully'
        );
    }

    public function alerts(Request $request)
    {
        $perPage = max(1, min((int) $request->query('per_page', 20), 100));
        $alerts = $this->service->listAlerts([
            'severity' => $request->query('severity'),
            'type' => $request->query('type'),
            'is_resolved' => $request->query('is_resolved'),
        ], $perPage);

        return $this->response->success($alerts, 'Gradebook alerts retrieved successfully');
    }

    public function run(Request $request)
    {
        $runAsync = $request->boolean('async', true);
        $organizationId = optional(function_exists('organization') ? organization() : null)->id
            ?? auth()->user()?->organization_id;

        if ($runAsync) {
            RunGradebookIntegrityChecksJob::dispatch($organizationId);

            return $this->response->success([
                'queued' => true,
                'organization_id' => $organizationId,
            ], 'Gradebook integrity checks queued successfully');
        }

        return $this->response->success(
            $this->service->runChecks($organizationId),
            'Gradebook integrity checks completed successfully'
        );
    }

    public function resolve(string $id)
    {
        $payload = request()->validate([
            'resolution_note' => ['nullable', 'string', 'max:2000'],
        ]);
        $alert = $this->service->resolveAlert($id, $payload['resolution_note'] ?? null);

        return $this->response->success([
            'id' => $alert->id,
            'is_resolved' => (bool) $alert->is_resolved,
            'resolved_at' => $alert->resolved_at?->toIso8601String(),
            'resolution_note' => $alert->resolution_note,
        ], 'Gradebook alert resolved successfully');
    }

    public function bulkResolve(Request $request)
    {
        $payload = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'uuid'],
            'resolution_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $result = $this->service->resolveAlerts($payload['ids'], $payload['resolution_note'] ?? null);

        return $this->response->success($result, 'Gradebook alerts resolved successfully');
    }
}
