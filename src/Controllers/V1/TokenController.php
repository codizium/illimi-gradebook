<?php

namespace Illimi\Gradebook\Controllers\V1;

use Codizium\Core\Controllers\BaseController;
use Codizium\Core\Helpers\CoreJsonResponse;
use Codizium\Core\Traits\SecureResponse;
use Illuminate\Http\Request;
use Illimi\Gradebook\Events\GradebookEntityChanged;
use Illimi\Gradebook\Requests\GenerateTokenRequest;
use Illimi\Gradebook\Requests\StoreTokenRequest;
use Illimi\Gradebook\Requests\UpdateTokenRequest;
use Illimi\Gradebook\Resources\TokenCollection;
use Illimi\Gradebook\Resources\TokenResource;
use Illimi\Gradebook\Services\TokenService;

class TokenController extends BaseController
{
    use SecureResponse;

    public function __construct(
        protected TokenService $service,
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
            'code' => $request->query('code'),
            'is_active' => $request->query('is_active'),
        ], fn ($value) => $value !== null && $value !== '');

        $tokens = $this->service->list($filters, $perPage);

        return $this->respondWithSecurity(new TokenCollection($tokens), 'Tokens retrieved successfully', 200, $request);
    }

    public function store(StoreTokenRequest $request)
    {
        $token = $this->service->store($request->validated());
        event(new GradebookEntityChanged('token', 'created', (new TokenResource($token))->resolve()));

        return $this->respondWithSecurity(new TokenResource($token), 'Token created successfully', 201, $request);
    }

    public function show(Request $request, string $id)
    {
        $token = $this->service->findById($id);

        if (! $token) {
            return $this->respondErrorWithSecurity('Token not found', 404, [], $request);
        }

        return $this->respondWithSecurity(new TokenResource($token), 'Token retrieved successfully', 200, $request);
    }

    public function update(UpdateTokenRequest $request, string $id)
    {
        $token = $this->service->update($id, $request->validated());

        if (! $token) {
            return $this->respondErrorWithSecurity('Token not found', 404, [], $request);
        }

        event(new GradebookEntityChanged('token', 'updated', (new TokenResource($token))->resolve()));

        return $this->respondWithSecurity(new TokenResource($token), 'Token updated successfully', 200, $request);
    }

    public function destroy(Request $request, string $id)
    {
        $token = $this->service->findById($id);
        $deleted = $this->service->delete($id);

        if (! $deleted) {
            return $this->respondErrorWithSecurity('Token not found', 404, [], $request);
        }

        if ($token) {
            event(new GradebookEntityChanged('token', 'deleted', (new TokenResource($token))->resolve()));
        }

        return $this->respondWithSecurity(null, 'Token deleted successfully', 200, $request);
    }

    public function generate(GenerateTokenRequest $request)
    {
        $tokens = $this->service->generate($request->validated());
        $organizationId = auth()->user()?->organization_id;

        event(new GradebookEntityChanged('token', 'bulk_generated', [
            'organization_id' => $organizationId,
            'generated_count' => $tokens->count(),
            'academic_class_id' => $request->validated('academic_class_id'),
            'academic_year_id' => $request->validated('academic_year_id'),
            'academic_term_id' => $request->validated('academic_term_id'),
        ]));

        return $this->respondWithSecurity([
            'generated_count' => $tokens->count(),
            'tokens' => $tokens->map(fn ($token) => (new TokenResource($token))->resolve())->all(),
        ], 'Tokens generated successfully', 200, $request);
    }
}
