@extends('layouts.app')

@push('styles')
    <style>
        .gradebook-sheet-card {
            border: 1px solid #d8dee8;
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.06);
            overflow: hidden;
        }

        .gradebook-sheet-toolbar {
            background: linear-gradient(180deg, #f8fafc 0%, #eef2f7 100%);
        }

        .gradebook-sheet-wrap {
            background:
                linear-gradient(#eef2f7 1px, transparent 1px),
                linear-gradient(90deg, #eef2f7 1px, transparent 1px);
            background-size: 100% 42px, 120px 100%;
            background-color: #f8fafc;
            padding: 16px;
        }

        .gradebook-sheet {
            min-width: 1100px;
            background: #fff;
            border: 1px solid #cfd8e3;
        }

        .gradebook-sheet thead th {
            background: #eef3f8;
            color: #344054;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            border: 1px solid #cfd8e3;
            vertical-align: middle;
            white-space: nowrap;
            position: sticky;
            top: 0;
            z-index: 2;
        }

        .gradebook-sheet thead tr:first-child th {
            background: #dde6f0;
        }

        .gradebook-sheet td {
            border: 1px solid #d9e1ea;
            background: #fff;
            vertical-align: middle;
            padding: 8px 10px;
        }

        .gradebook-sheet tbody tr:hover td {
            background: #f8fbff;
        }

        .gradebook-sheet .sheet-index {
            width: 56px;
            text-align: center;
            background: #f8fafc;
            color: #667085;
            font-weight: 700;
        }

        .gradebook-sheet .sheet-student {
            min-width: 260px;
            position: sticky;
            left: 0;
            z-index: 1;
            background: #fff;
            box-shadow: 1px 0 0 #d9e1ea;
        }

        .gradebook-sheet thead .sheet-student {
            z-index: 3;
        }

        .gradebook-sheet .sheet-number {
            min-width: 92px;
            text-align: center;
        }

        .gradebook-sheet .sheet-header-note {
            display: block;
            margin-top: 4px;
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 0;
            text-transform: none;
            color: #667085;
        }

        .gradebook-sheet .sheet-metric {
            min-width: 96px;
            text-align: center;
            font-weight: 700;
            color: #1d2939;
            background: #fbfdff;
        }

        .gradebook-sheet .sheet-text {
            min-width: 120px;
        }

        .gradebook-sheet .grade-input {
            min-width: 74px;
            height: 34px;
            border-radius: 0;
            border: 1px solid #cbd5e1;
            background: #fffef7;
            text-align: right;
            font-weight: 600;
            font-variant-numeric: tabular-nums;
            box-shadow: none;
        }

        .gradebook-sheet .grade-input:focus {
            background: #fff;
            border-color: #3b82f6;
            box-shadow: inset 0 0 0 1px #3b82f6;
        }

        .gradebook-student-name {
            font-weight: 700;
            color: #101828;
        }

        .gradebook-student-meta {
            font-size: 12px;
            color: #667085;
        }

        .sheet-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 999px;
            background: #eff6ff;
            color: #1d4ed8;
            font-size: 12px;
            font-weight: 700;
        }

        .sheet-label {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #667085;
        }

        .sheet-save-state {
            margin-top: 6px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.03em;
            text-transform: uppercase;
            color: #98a2b3;
        }

        .sheet-save-state[data-state="saving"] {
            color: #b54708;
        }

        .sheet-save-state[data-state="saved"] {
            color: #027a48;
        }

        .sheet-save-state[data-state="error"] {
            color: #b42318;
        }

        .uppercase{
            text-transform: uppercase !important;
        }

    </style>
@endpush

@section('content')
    <div class="breadcrumb d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
        <div>
            <h1 class="fw-semibold mb-4 h6 text-primary-light">{{ $subject->name }} Gradebook</h1>
            <div>
                <a href="/" class="text-secondary-light hover-text-primary hover-underline">Dashboard</a>
                <a href="{{ route('gradebook.index') }}" class="text-secondary-light hover-text-primary hover-underline">/ Gradebook</a>
                <span class="text-secondary-light">/ {{ $class->name }}</span><span class="sheet-pill">{{ $class->section?->name ?: 'No Section' }}</span>
            </div>
        </div>
    </div>

    <div class="card h-100 gradebook-sheet-card">
        <div class="card-body p-0">
            <div class="gradebook-sheet-toolbar d-flex align-items-center justify-content-between flex-wrap gap-16 px-20 py-14 border-bottom border-neutral-200">
                <div>
                    <div class="sheet-label mb-6">Worksheet</div>
                    <h6 class="mb-4">{{ $subject->name }} - {{ $class->name }} <span class="sheet-pill">{{ $class->section?->name ?: 'No Section' }}</span></h6>
                    <p class="mb-0 text-secondary-light">
                        Enter student scores row by row using
                        {{ $resolvedTemplate?->name ? '"' . $resolvedTemplate->name . '"' : 'the legacy workbook layout' }}.
                    </p>
                </div>
                <div class="d-flex flex-wrap gap-8">
                    <span class="sheet-pill">{{ $termsForYear->firstWhere('id', $selectedAcademicTermId)?->name ?: 'No term selected' }}</span>
                    <a
                        href="{{ route('gradebook.ratings.effective', ['class' => $class->id, 'academic_year_id' => $selectedAcademicYearId, 'academic_term_id' => $selectedAcademicTermId]) }}"
                        class="btn btn-sm btn-outline-primary-600"
                    >
                        Effective Assessment
                    </a>
                    <a
                        href="{{ route('gradebook.ratings.psychomotor', ['class' => $class->id, 'academic_year_id' => $selectedAcademicYearId, 'academic_term_id' => $selectedAcademicTermId]) }}"
                        class="btn btn-sm btn-outline-primary-600"
                    >
                        Psychomotor
                    </a>
                    @if ($resolvedTemplate)
                        <span class="sheet-pill">{{ $resolvedTemplate->code ?: 'Active Template' }}</span>
                    @else
                        <span class="sheet-pill">Legacy Layout</span>
                    @endif
                </div>
            </div>

            <div class="gradebook-sheet-wrap">
                <div class="table-responsive">
                <table class="table mb-0 align-middle gradebook-sheet">
                    <thead>
                        <tr>
                            <th rowspan="2" class="sheet-index">S/N</th>
                            <th rowspan="2" class="sheet-student">Student</th>
                            <th colspan="{{ max($continuousAssessmentItems->count(), 1) + 1 }}" class="text-center">Continuous Assessment</th>
                            @foreach ($nonContinuousItems as $item)
                                <th rowspan="2" class="sheet-number">
                                    {{ $item->code ?: $item->label }}
                                    @if ($item->max_score !== null)
                                        <span class="sheet-header-note">/ {{ number_format((float) $item->max_score, 2) }}</span>
                                    @endif
                                </th>
                            @endforeach
                            <th rowspan="2" class="sheet-number">Total</th>
                            <th rowspan="2" class="sheet-text">Grade</th>
                            <th rowspan="2" class="sheet-text">Remark</th>
                        </tr>
                        <tr>
                            @forelse ($continuousAssessmentItems as $item)
                                <th class="sheet-number">
                                    {{ $item->code ?: $item->label }}
                                    @if ($item->max_score !== null)
                                        <span class="sheet-header-note">/ {{ number_format((float) $item->max_score, 2) }}</span>
                                    @endif
                                </th>
                            @empty
                                <th class="sheet-number">CA</th>
                            @endforelse
                            <th class="sheet-number">Total CA</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($gradebookRows as $row)
                            <tr
                                data-student-id="{{ $row->student->id }}"
                                data-subject-id="{{ $subject->id }}"
                                data-class-id="{{ $class->id }}"
                                data-academic-year-id="{{ $selectedAcademicYearId }}"
                                data-academic-term-id="{{ $selectedAcademicTermId }}"
                                data-staff-id="{{ $subject->teachers->first()?->id }}"
                                data-template-id="{{ $row->template?->id ?: $resolvedTemplate?->id }}"
                                data-saving="false"
                            >
                                <td class="sheet-index">{{ $loop->iteration }}</td>
                                <td class="sheet-student">
                                    <div class="gradebook-student-name">{{ $row->student->full_name }}</div>
                                    <div class="gradebook-student-meta">{{ $row->student->admission_number ?: $row->student->student_number ?: '—' }}</div>
                                    <div class="sheet-save-state" data-state="idle">Idle</div>
                                </td>
                                @foreach ($row->items->where('component_type', 'continuous_assessment') as $item)
                                    <td class="sheet-number">
                                        <input
                                            type="number"
                                            class="form-control form-control-sm grade-input"
                                            step="0.01"
                                            min="0"
                                            value="{{ $item->score }}"
                                            data-template-item-id="{{ $item->id }}"
                                            data-component-type="{{ $item->component_type }}"
                                            data-affects-total="{{ $item->affects_total ? '1' : '0' }}"
                                            data-max-score="{{ $item->max_score !== null ? (float) $item->max_score : '' }}"
                                            title="{{ $item->max_score !== null ? 'Maximum score: ' . number_format((float) $item->max_score, 2) : '' }}"
                                        >
                                    </td>
                                @endforeach
                                <td class="sheet-metric js-total-ca">{{ number_format($row->total_ca, 2) }}</td>
                                @foreach ($row->items->where('component_type', '!=', 'continuous_assessment') as $item)
                                    <td class="sheet-number">
                                        <input
                                            type="number"
                                            class="form-control form-control-sm grade-input"
                                            step="0.01"
                                            min="0"
                                            value="{{ $item->score }}"
                                            data-template-item-id="{{ $item->id }}"
                                            data-component-type="{{ $item->component_type }}"
                                            data-affects-total="{{ $item->affects_total ? '1' : '0' }}"
                                            data-max-score="{{ $item->max_score !== null ? (float) $item->max_score : '' }}"
                                            title="{{ $item->max_score !== null ? 'Maximum score: ' . number_format((float) $item->max_score, 2) : '' }}"
                                        >
                                    </td>
                                @endforeach
                                <td class="sheet-metric js-total">{{ number_format($row->total, 2) }}</td>
                                <td class="sheet-text js-grade uppercase">{{ $row->grade ?: 'F' }}</td>
                                <td class="sheet-text js-remark">{{ $row->remark ?: 'Fail' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ 6 + max($continuousAssessmentItems->count(), 1) + $nonContinuousItems->count() }}" class="text-center py-24 text-secondary-light">No students found for this class.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                </div>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
    <script>
        (function() {
            const apiUrl = @json(route('v1.gradebook.assessments.store', [], false));
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const rowTimers = new WeakMap();

            const numberValue = (value) => {
                const parsed = parseFloat(value);
                return Number.isFinite(parsed) ? parsed : 0;
            };

            const setRowState = (row, state, message) => {
                const indicator = row.querySelector('.sheet-save-state');
                if (!indicator) {
                    return;
                }

                indicator.dataset.state = state;
                indicator.textContent = message;
            };

            const updateRowTotals = (row) => {
                const inputs = Array.from(row.querySelectorAll('.grade-input'));
                const totalCa = inputs
                    .filter((input) => input.dataset.componentType === 'continuous_assessment')
                    .reduce((sum, input) => sum + numberValue(input.value), 0);
                const total = inputs
                    .filter((input) => input.dataset.affectsTotal !== '0')
                    .reduce((sum, input) => sum + numberValue(input.value), 0);

                row.querySelector('.js-total-ca').textContent = totalCa.toFixed(2);
                row.querySelector('.js-total').textContent = total.toFixed(2);
            };

            const validateInput = (input) => {
                const score = numberValue(input.value);
                const maxScore = input.dataset.maxScore === '' ? null : numberValue(input.dataset.maxScore);

                if (score < 0) {
                    return 'Score cannot be less than 0.';
                }

                if (maxScore !== null && score > maxScore) {
                    return `Score cannot be greater than ${maxScore.toFixed(2)} for this column.`;
                }

                return null;
            };

            const validateRow = (row) => {
                const inputs = Array.from(row.querySelectorAll('.grade-input'));

                for (const input of inputs) {
                    const error = validateInput(input);
                    if (error) {
                        input.focus();
                        input.select?.();
                        return error;
                    }
                }

                return null;
            };

            const buildPayload = (row) => {
                const items = Array.from(row.querySelectorAll('.grade-input')).map((input) => ({
                    template_item_id: input.dataset.templateItemId,
                    score: numberValue(input.value),
                }));

                return {
                    student_id: row.dataset.studentId,
                    subject_id: row.dataset.subjectId,
                    academic_class_id: row.dataset.classId,
                    academic_year_id: row.dataset.academicYearId,
                    academic_term_id: row.dataset.academicTermId,
                    template_id: row.dataset.templateId || null,
                    staff_id: row.dataset.staffId || null,
                    items,
                };
            };

            const saveRow = async (row) => {
                if (row.dataset.saving === 'true') {
                    return;
                }

                const validationError = validateRow(row);
                if (validationError) {
                    setRowState(row, 'error', 'Invalid score');
                    window.cocoSwal.error(validationError);
                    return;
                }

                row.dataset.saving = 'true';
                setRowState(row, 'saving', 'Saving');

                try {
                    const response = await fetch(apiUrl, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken || '',
                        },
                        body: JSON.stringify(buildPayload(row)),
                        credentials: 'same-origin',
                    });

                    const result = await response.json();

                    if (!response.ok || !result.success) {
                        throw new Error(result.message || 'Unable to save assessment.');
                    }

                    row.querySelector('.js-grade').textContent = result.data?.graded || '—';
                    row.querySelector('.js-remark').textContent = result.data?.grade_scale?.description || 'Saved';
                    updateRowTotals(row);
                    setRowState(row, 'saved', 'Saved');

                    if (rowTimers.has(row)) {
                        clearTimeout(rowTimers.get(row));
                    }

                    rowTimers.set(row, setTimeout(() => {
                        setRowState(row, 'idle', 'Idle');
                    }, 1500));
                } catch (error) {
                    setRowState(row, 'error', 'Save failed');
                    window.cocoSwal.error(error.message || 'Unable to save assessment.');
                } finally {
                    row.dataset.saving = 'false';
                }
            };

            document.querySelectorAll('.grade-input').forEach((input) => {
                const row = input.closest('tr');

                input.addEventListener('input', () => {
                    const maxScore = input.dataset.maxScore === '' ? null : numberValue(input.dataset.maxScore);
                    const currentValue = numberValue(input.value);

                    if (maxScore !== null && currentValue > maxScore) {
                        input.classList.add('is-invalid');
                    } else {
                        input.classList.remove('is-invalid');
                    }

                    updateRowTotals(row);
                    setRowState(row, 'idle', 'Pending');
                });

                input.addEventListener('blur', () => {
                    const error = validateInput(input);
                    if (error) {
                        input.classList.add('is-invalid');
                        setRowState(row, 'error', 'Invalid score');
                        window.cocoSwal.error(error);
                        return;
                    }

                    input.classList.remove('is-invalid');
                    saveRow(row);
                });
            });

        })();
    </script>
@endpush
