<?php

namespace Illimi\Gradebook\Controllers\V1;

use Codizium\Core\Controllers\BaseController;
use Codizium\Core\Helpers\CoreJsonResponse;
use Codizium\Core\Traits\SecureResponse;
use Illimi\Gradebook\Requests\StoreStudentRatingRequest;
use Illimi\Gradebook\Resources\StudentRatingResource;
use Illimi\Gradebook\Services\StudentRatingService;

class StudentRatingController extends BaseController
{
    use SecureResponse;

    public function __construct(
        protected StudentRatingService $service,
        protected CoreJsonResponse $response
    ) {
    }

    public function store(StoreStudentRatingRequest $request)
    {
        $payload = $request->validated();
        $payload['organization_id'] = optional(function_exists('organization') ? organization() : null)->id
            ?? auth()->user()?->organization_id;

        $rating = $this->service->store($payload);

        return $this->respondWithSecurity(new StudentRatingResource($rating), 'Student rating saved successfully', 201, $request);
    }
}
