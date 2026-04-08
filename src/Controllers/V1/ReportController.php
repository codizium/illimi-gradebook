<?php

namespace Illimi\Gradebook\Controllers\V1;

use Codizium\Core\Controllers\BaseController;
use Codizium\Core\Helpers\CoreJsonResponse;
use Illuminate\Http\Request;
use Illimi\Gradebook\Events\GradebookEntityChanged;
use Illimi\Gradebook\Requests\StoreReportRequest;
use Illimi\Gradebook\Requests\UpdateReportRequest;
use Illimi\Gradebook\Resources\ReportCollection;
use Illimi\Gradebook\Resources\ReportResource;
use Illimi\Gradebook\Services\ReportService;

class ReportController extends BaseController
{
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

        return $this->response->success(new ReportCollection($reports), 'Reports retrieved successfully');
    }

    public function store(StoreReportRequest $request)
    {
        $report = $this->service->store($request->validated());
        event(new GradebookEntityChanged('report', 'saved', (new ReportResource($report))->resolve()));

        return $this->response->success(new ReportResource($report), 'Report saved successfully', 201);
    }

    public function generate(StoreReportRequest $request)
    {
        $report = $this->service->generate($request->validated());
        event(new GradebookEntityChanged('report', 'generated', (new ReportResource($report))->resolve()));

        return $this->response->success(new ReportResource($report), 'Report generated successfully', 201);
    }

    public function show(string $id)
    {
        $report = $this->service->findById($id);

        if (!$report) {
            return $this->response->error('Report not found', 404);
        }

        return $this->response->success(new ReportResource($report), 'Report retrieved successfully');
    }

    public function update(UpdateReportRequest $request, string $id)
    {
        $report = $this->service->update($id, $request->validated());

        if (!$report) {
            return $this->response->error('Report not found', 404);
        }

        event(new GradebookEntityChanged('report', 'updated', (new ReportResource($report))->resolve()));

        return $this->response->success(new ReportResource($report), 'Report updated successfully');
    }

    public function destroy(string $id)
    {
        $report = $this->service->findById($id);
        $deleted = $this->service->delete($id);

        if (!$deleted) {
            return $this->response->error('Report not found', 404);
        }

        if ($report) {
            event(new GradebookEntityChanged('report', 'deleted', (new ReportResource($report))->resolve()));
        }

        return $this->response->success(null, 'Report deleted successfully');
    }
}
