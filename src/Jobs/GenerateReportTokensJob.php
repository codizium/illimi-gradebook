<?php

namespace Illimi\Gradebook\Jobs;

use Illimi\Gradebook\Services\TokenService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateReportTokensJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 2;
    public int $timeout = 300; // token generation for large classes can be slow

    /**
     * @param  array  $data  Must include: organization_id, academic_class_id,
     *                       academic_year_id, academic_term_id.
     *                       Optional: student_ids (array), replace_existing (bool),
     *                       is_active (bool), requested_by (user ID).
     */
    public function __construct(public array $data) {}

    public function handle(TokenService $tokenService): void
    {
        $tokens = $tokenService->generate($this->data);

        Log::info('Report tokens generated via queue', [
            'organization_id'   => $this->data['organization_id'] ?? null,
            'academic_class_id' => $this->data['academic_class_id'] ?? null,
            'academic_year_id'  => $this->data['academic_year_id'] ?? null,
            'academic_term_id'  => $this->data['academic_term_id'] ?? null,
            'count'             => $tokens->count(),
        ]);

        if (! empty($this->data['requested_by'])) {
            $user = \Codizium\Core\Models\User::find($this->data['requested_by']);
            $user?->notify(new \Illimi\Gradebook\Notifications\ReportTokensGeneratedNotification(
                $tokens->count(),
                $this->data
            ));
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('GenerateReportTokensJob failed', [
            'data'  => $this->data,
            'error' => $e->getMessage(),
        ]);
    }
}
