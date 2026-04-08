<?php

namespace Illimi\Gradebook\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illimi\Gradebook\Models\AssessmentTemplate;

class AssessmentTemplateService
{
    public function list(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->query();

        foreach (['subject_id', 'academic_class_id', 'academic_year_id', 'academic_term_id', 'status'] as $field) {
            if (!empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        if (array_key_exists('is_default', $filters) && $filters['is_default'] !== null && $filters['is_default'] !== '') {
            $query->where('is_default', (bool) $filters['is_default']);
        }

        return $query->latest()->paginate($perPage);
    }

    public function findById(string $id): ?AssessmentTemplate
    {
        return $this->query()->find($id);
    }

    public function store(array $data): AssessmentTemplate
    {
        return DB::transaction(function () use ($data) {
            if (!empty($data['is_default'])) {
                $this->clearDefaultForScope($data);
            }

            $template = AssessmentTemplate::create($this->templatePayload($data));
            $this->syncItems($template, $data['items'] ?? []);

            return $this->findById($template->id) ?? $template->fresh();
        });
    }

    public function update(string $id, array $data): ?AssessmentTemplate
    {
        $template = AssessmentTemplate::find($id);

        if (!$template) {
            return null;
        }

        return DB::transaction(function () use ($template, $data) {
            $payload = array_merge($template->only([
                'name',
                'code',
                'description',
                'subject_id',
                'academic_class_id',
                'academic_year_id',
                'academic_term_id',
                'is_default',
                'status',
            ]), $data);

            if (!empty($payload['is_default'])) {
                $this->clearDefaultForScope($payload, $template->id);
            }

            $template->update($this->templatePayload($payload));

            if (array_key_exists('items', $data)) {
                $this->syncItems($template, $data['items'] ?? []);
            }

            return $this->findById($template->id);
        });
    }

    public function delete(string $id): bool
    {
        $template = AssessmentTemplate::find($id);

        if (!$template) {
            return false;
        }

        return (bool) $template->delete();
    }

    public function resolveForContext(array $context): ?AssessmentTemplate
    {
        $templates = $this->query()
            ->where('status', $context['status'] ?? 'active')
            ->get()
            ->filter(function (AssessmentTemplate $template) use ($context) {
                return $this->matchesContext($template, $context);
            })
            ->sortByDesc(function (AssessmentTemplate $template) {
                return collect([
                    $template->subject_id,
                    $template->academic_class_id,
                    $template->academic_year_id,
                    $template->academic_term_id,
                ])->filter()->count();
            })
            ->values();

        return $templates->firstWhere('is_default', false) ?? $templates->first();
    }

    protected function syncItems(AssessmentTemplate $template, array $items): void
    {
        $template->items()->delete();

        foreach (array_values($items) as $index => $item) {
            $template->items()->create([
                'organization_id' => $template->organization_id,
                'label' => $item['label'],
                'code' => $item['code'],
                'component_type' => $item['component_type'],
                'max_score' => $item['max_score'],
                'weight' => $item['weight'] ?? null,
                'position' => $item['position'] ?? $index + 1,
                'is_required' => (bool) ($item['is_required'] ?? false),
                'affects_total' => (bool) ($item['affects_total'] ?? true),
                'settings' => $item['settings'] ?? null,
            ]);
        }
    }

    protected function clearDefaultForScope(array $data, ?string $ignoreId = null): void
    {
        $query = AssessmentTemplate::query()
            ->where('subject_id', $data['subject_id'] ?? null)
            ->where('academic_class_id', $data['academic_class_id'] ?? null)
            ->where('academic_year_id', $data['academic_year_id'] ?? null)
            ->where('academic_term_id', $data['academic_term_id'] ?? null);

        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        $query->update(['is_default' => false]);
    }

    protected function templatePayload(array $data): array
    {
        return [
            'name' => $data['name'],
            'code' => $data['code'] ?? null,
            'description' => $data['description'] ?? null,
            'subject_id' => $data['subject_id'] ?? null,
            'academic_class_id' => $data['academic_class_id'] ?? null,
            'academic_year_id' => $data['academic_year_id'] ?? null,
            'academic_term_id' => $data['academic_term_id'] ?? null,
            'is_default' => (bool) ($data['is_default'] ?? false),
            'status' => $data['status'] ?? 'active',
        ];
    }

    protected function matchesContext(AssessmentTemplate $template, array $context): bool
    {
        foreach (['subject_id', 'academic_class_id', 'academic_year_id', 'academic_term_id'] as $field) {
            if ($template->{$field} !== null && ($context[$field] ?? null) !== $template->{$field}) {
                return false;
            }
        }

        return true;
    }

    protected function query()
    {
        return AssessmentTemplate::query()->with(['subject', 'academicClass', 'items']);
    }
}
