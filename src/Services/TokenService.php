<?php

namespace Illimi\Gradebook\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Illimi\Gradebook\Models\Report;
use Illimi\Gradebook\Models\Token;
use Illimi\Students\Models\Student;

class TokenService
{
    public function __construct(
        protected ReportService $reportService
    ) {
    }

    public function list(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->query();

        foreach ([
            'student_id',
            'academic_class_id',
            'academic_year_id',
            'academic_term_id',
            'code',
        ] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== null && $filters['is_active'] !== '') {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        return $query->latest()->paginate($perPage);
    }

    public function findById(string $id): ?Token
    {
        return $this->query()->find($id);
    }

    public function store(array $data): Token
    {
        $payload = $this->normalizePayload($data);

        $token = Token::query()->updateOrCreate([
            'student_id' => $payload['student_id'],
            'academic_class_id' => $payload['academic_class_id'],
            'academic_year_id' => $payload['academic_year_id'],
            'academic_term_id' => $payload['academic_term_id'],
        ], $payload);

        $this->syncReportCode($payload, $token->code);

        return $this->findById($token->id) ?? $token->fresh();
    }

    public function update(string $id, array $data): ?Token
    {
        $token = Token::find($id);

        if (! $token) {
            return null;
        }

        $payload = $this->normalizePayload(array_merge($token->only([
            'organization_id',
            'student_id',
            'academic_class_id',
            'academic_year_id',
            'academic_term_id',
            'code',
            'generated_by',
            'is_active',
            'assigned_at',
            'last_used_at',
        ]), $data));

        $token->update($payload);
        $this->syncReportCode($payload, $token->code);

        return $this->findById($token->id);
    }

    public function delete(string $id): bool
    {
        $token = Token::find($id);

        if (! $token) {
            return false;
        }

        Report::query()
            ->where('student_id', $token->student_id)
            ->where('academic_class_id', $token->academic_class_id)
            ->where('academic_year_id', $token->academic_year_id)
            ->where('academic_term_id', $token->academic_term_id)
            ->update(['code' => null]);

        return (bool) $token->delete();
    }

    public function generate(array $data): Collection
    {
        $organizationId = $data['organization_id'] ?? $this->organizationId();
        $replaceExisting = (bool) ($data['replace_existing'] ?? true);
        $studentIds = collect($data['student_ids'] ?? [])->filter()->unique()->values();

        $students = Student::query()
            ->when($organizationId, fn ($query) => $query->where('organization_id', $organizationId))
            ->where('class_id', $data['academic_class_id'])
            ->where('status', Student::STATUS_ACTIVE)
            ->when($studentIds->isNotEmpty(), fn ($query) => $query->whereIn('id', $studentIds))
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id']);

        return $students->map(function (Student $student) use ($data, $organizationId, $replaceExisting) {
            return $this->ensureForScope([
                'organization_id' => $organizationId,
                'student_id' => $student->id,
                'academic_class_id' => $data['academic_class_id'],
                'academic_year_id' => $data['academic_year_id'],
                'academic_term_id' => $data['academic_term_id'],
                'generated_by' => auth()->id(),
                'is_active' => $data['is_active'] ?? true,
            ], $replaceExisting);
        })->values();
    }

    public function ensureForScope(array $scope, bool $replaceExisting = false): Token
    {
        $payload = $this->normalizePayload($scope);

        $token = Token::query()->where([
            'student_id' => $payload['student_id'],
            'academic_class_id' => $payload['academic_class_id'],
            'academic_year_id' => $payload['academic_year_id'],
            'academic_term_id' => $payload['academic_term_id'],
        ])->first();

        if ($token && ! $replaceExisting) {
            if (! $token->is_active && array_key_exists('is_active', $payload)) {
                $token->update(['is_active' => (bool) $payload['is_active']]);
            }

            $this->syncReportCode($payload, $token->code);

            return $this->findById($token->id) ?? $token->fresh();
        }

        if ($token) {
            $payload['code'] = $this->generateUniqueCode();
            $payload['assigned_at'] = now();
            $token->update($payload);
        } else {
            $token = Token::create($payload);
        }

        $this->syncReportCode($payload, $token->code);

        return $this->findById($token->id) ?? $token->fresh();
    }

    public function markAsUsed(Token $token): void
    {
        $token->forceFill([
            'last_used_at' => now(),
        ])->save();
    }

    protected function normalizePayload(array $data): array
    {
        $code = strtoupper(trim((string) ($data['code'] ?? '')));
        $organizationId = $data['organization_id'] ?? $this->organizationId();
        $student = Student::query()
            ->when($organizationId, fn ($query) => $query->where('organization_id', $organizationId))
            ->find($data['student_id']);

        if (! $student) {
            throw ValidationException::withMessages([
                'student_id' => ['The selected student could not be found.'],
            ]);
        }

        if (! $student->class_id) {
            throw ValidationException::withMessages([
                'student_id' => ['The selected student does not have a class assigned.'],
            ]);
        }

        return [
            'organization_id' => $organizationId,
            'student_id' => $student->id,
            'academic_class_id' => $student->class_id,
            'academic_year_id' => $data['academic_year_id'],
            'academic_term_id' => $data['academic_term_id'],
            'code' => $code !== '' ? $code : $this->generateUniqueCode(),
            'generated_by' => $data['generated_by'] ?? auth()->id(),
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
            'assigned_at' => $data['assigned_at'] ?? now(),
            'last_used_at' => $data['last_used_at'] ?? null,
        ];
    }

    protected function generateUniqueCode(): string
    {
        do {
            $digits = str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
            $letters = chr(random_int(65, 90)) . chr(random_int(65, 90));
            $code = $digits . $letters;
        } while (Token::query()->where('code', $code)->exists());

        return $code;
    }

    protected function syncReportCode(array $scope, string $code): Report
    {
        return $this->reportService->store([
            'organization_id' => $scope['organization_id'] ?? $this->organizationId(),
            'student_id' => $scope['student_id'],
            'academic_class_id' => $scope['academic_class_id'],
            'academic_year_id' => $scope['academic_year_id'],
            'academic_term_id' => $scope['academic_term_id'],
            'code' => $code,
        ]);
    }

    protected function organizationId(): ?string
    {
        return optional(function_exists('organization') ? organization() : null)->id
            ?? auth()->user()?->organization_id;
    }

    protected function query()
    {
        return Token::query()->with([
            'student',
            'academicClass',
            'academicYear',
            'academicTerm',
            'generatedBy',
        ]);
    }
}
