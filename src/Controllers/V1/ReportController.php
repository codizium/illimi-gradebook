<?php

namespace Illimi\Gradebook\Controllers\V1;

use Codizium\Core\Controllers\BaseController;
use Codizium\Core\Helpers\CoreJsonResponse;
use Codizium\Core\Traits\SecureResponse;
use Illuminate\Http\Request;
use Illimi\Gradebook\Events\GradebookEntityChanged;
use Illimi\Gradebook\Requests\StoreReportRequest;
use Illimi\Gradebook\Requests\UpdateReportRequest;
use Illimi\Gradebook\Resources\ReportCollection;
use Illimi\Gradebook\Resources\ReportResource;
use Illimi\Gradebook\Services\ReportService;

class ReportController extends BaseController
{
    use SecureResponse;

    public function __construct(
        protected ReportService $service,
        protected CoreJsonResponse $response
    ) {
    }

    public function index(Request $request)
    {
        $perPage = max(1, min((int) $request->query('per_page', 15), 100));
        $filters = array_filter([
            'student_id' => $request->query('student_id'),
            'academic_class_id' => $request->query('academic_class_id'),
            'academic_year_id' => $request->query('academic_year_id'),
            'academic_term_id' => $request->query('academic_term_id'),
        ], fn ($value) => $value !== null && $value !== '');

        $reports = $this->service->list($filters, $perPage);

        return $this->respondWithSecurity(new ReportCollection($reports), 'Reports retrieved successfully', 200, $request);
    }

    public function store(StoreReportRequest $request)
    {
        $report = $this->service->store($request->validated());
        event(new GradebookEntityChanged('report', 'saved', $this->broadcastPayload($report)));

        return $this->respondWithSecurity(new ReportResource($report), 'Report saved successfully', 201, $request);
    }

    public function generate(StoreReportRequest $request)
    {
        $report = $this->service->generate($request->validated());
        event(new GradebookEntityChanged('report', 'generated', $this->broadcastPayload($report)));

        return $this->respondWithSecurity(new ReportResource($report), 'Report generated successfully', 201, $request);
    }

    public function show(Request $request, string $id)
    {
        $report = $this->service->findById($id);

        if (!$report) {
            return $this->respondErrorWithSecurity('Report not found', 404, [], $request);
        }

        return $this->respondWithSecurity(new ReportResource($report), 'Report retrieved successfully', 200, $request);
    }

    public function update(UpdateReportRequest $request, string $id)
    {
        $report = $this->service->update($id, $request->validated());

        if (!$report) {
            return $this->respondErrorWithSecurity('Report not found', 404, [], $request);
        }

        event(new GradebookEntityChanged('report', 'updated', $this->broadcastPayload($report)));

        return $this->respondWithSecurity(new ReportResource($report), 'Report updated successfully', 200, $request);
    }

    public function destroy(Request $request, string $id)
    {
        $report = $this->service->findById($id);
        $deleted = $this->service->delete($id);

        if (!$deleted) {
            return $this->respondErrorWithSecurity('Report not found', 404, [], $request);
        }

        if ($report) {
            event(new GradebookEntityChanged('report', 'deleted', $this->broadcastPayload($report)));
        }

        return $this->respondWithSecurity(null, 'Report deleted successfully', 200, $request);
    }

    protected function broadcastPayload($report): array
    {
        $payload = is_array($report->payload) ? $report->payload : [];

        return [
            'id' => $report->id,
            'organization_id' => $report->organization_id,
            'code' => $report->code,
            'student_id' => $report->student_id,
            'student_name' => data_get($payload, 'student.full_name'),
            'admission_number' => data_get($payload, 'student.admission_number'),
            'academic_class_id' => $report->academic_class_id,
            'academic_class_name' => trim(implode(' - ', array_filter([
                data_get($payload, 'class.name'),
                data_get($payload, 'class.section_name'),
            ]))),
            'academic_year_id' => $report->academic_year_id,
            'academic_year_name' => data_get($payload, 'academic_year.name'),
            'academic_term_id' => $report->academic_term_id,
            'academic_term_name' => data_get($payload, 'academic_term.name'),
            'template_name' => data_get($payload, 'template.name'),
            'total_score' => data_get($payload, 'summary.overall_total'),
            'average_score' => data_get($payload, 'summary.average_score'),
            'position' => data_get($payload, 'summary.position'),
            'created_at' => $report->created_at?->toIso8601String(),
            'updated_at' => $report->updated_at?->toIso8601String(),
        ];
    }
}
