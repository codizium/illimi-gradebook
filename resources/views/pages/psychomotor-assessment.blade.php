@extends('layouts.app')

@push('styles')
    <style>
        .rating-card {
            border: 1px solid #d8dee8;
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.06);
            overflow: hidden;
            background: #fff;
        }

        .rating-table th,
        .rating-table td {
            font-size: 12px;
            vertical-align: middle;
            white-space: nowrap;
        }

        .rating-table select {
            min-width: 76px;
        }

        .rating-save-state {
            margin-top: 6px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.03em;
            text-transform: uppercase;
            color: #98a2b3;
        }

        .rating-save-state[data-state="saving"] {
            color: #b54708;
        }

        .rating-save-state[data-state="saved"] {
            color: #027a48;
        }

        .rating-save-state[data-state="error"] {
            color: #b42318;
        }
    </style>
@endpush

@section('content')
    <div class="breadcrumb d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
        <div>
            <h1 class="fw-semibold mb-4 h6 text-primary-light">Psychomotor Assessment</h1>
            <div>
                <a href="/" class="text-secondary-light hover-text-primary hover-underline">Dashboard</a>
                <a href="{{ route('gradebook.index') }}" class="text-secondary-light hover-text-primary hover-underline">/ Gradebook</a>
                <span class="text-secondary-light">/ {{ $class->name }}</span>
            </div>
        </div>
    </div>

    @include('illimi-gradebook::pages.partials.student-rating-table', [
        'title' => 'Psychomotor Assessment',
        'description' => 'Rate each student from 1 to 5.',
        'group' => 'psychomotor_assessment',
        'items' => $psychomotorAssessmentItems ?? [],
        'backUrl' => route('gradebook.index'),
    ])
@endsection

@push('scripts')
    <script>
        (function() {
            const ratingApiUrl = @json(route('v1.gradebook.student_ratings.store', [], false));
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const rowTimers = new WeakMap();

            const setRowState = (row, state, message) => {
                const indicator = row.querySelector('.rating-save-state');
                if (!indicator) {
                    return;
                }

                indicator.dataset.state = state;
                indicator.textContent = message;
            };

            const buildRatingPayload = (row) => {
                const payload = {
                    student_id: row.dataset.studentId,
                    academic_class_id: row.dataset.classId,
                    academic_year_id: row.dataset.academicYearId,
                    academic_term_id: row.dataset.academicTermId,
                    staff_id: row.dataset.staffId || null,
                    effective_assessment: {},
                    psychomotor_assessment: {},
                };

                row.querySelectorAll('.js-student-rating-input').forEach((input) => {
                    const group = input.dataset.group;
                    const key = input.dataset.key;
                    payload[group][key] = input.value === '' ? null : Number(input.value);
                });

                return payload;
            };

            const saveRatingRow = async (row) => {
                setRowState(row, 'saving', 'Saving');

                try {
                    const response = await fetch(ratingApiUrl, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken || '',
                        },
                        body: JSON.stringify(buildRatingPayload(row)),
                        credentials: 'same-origin',
                    });

                    const result = await response.json();

                    if (!response.ok || !result.success) {
                        throw new Error(result.message || 'Unable to save student rating.');
                    }

                    setRowState(row, 'saved', 'Saved');

                    if (rowTimers.has(row)) {
                        clearTimeout(rowTimers.get(row));
                    }

                    rowTimers.set(row, setTimeout(() => {
                        setRowState(row, 'idle', 'Idle');
                    }, 1500));
                } catch (error) {
                    setRowState(row, 'error', 'Save failed');
                    window.cocoSwal.error(error.message || 'Unable to save student rating.');
                }
            };

            document.querySelectorAll('.js-student-rating-input').forEach((input) => {
                const row = input.closest('.js-student-rating-row');

                input.addEventListener('change', () => {
                    saveRatingRow(row);
                });
            });
        })();
    </script>
@endpush
