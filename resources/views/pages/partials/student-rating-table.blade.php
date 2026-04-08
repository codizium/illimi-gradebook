<div class="card rating-card">
    <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-12">
        <div>
            <h6 class="mb-1">{{ $title }}</h6>
            <p class="mb-0 text-secondary-light">{{ $description }}</p>
        </div>
        <div class="d-flex flex-wrap gap-8">
            @isset($backUrl)
                <a href="{{ $backUrl }}" class="btn btn-sm btn-outline-primary-600">Back</a>
            @endisset
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered mb-0 rating-table">
                <thead>
                    <tr>
                        <th>Student</th>
                        @foreach ($items as $key => $label)
                            <th>{{ $label }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($ratingRows as $row)
                        <tr
                            data-student-id="{{ $row->student->id }}"
                            data-class-id="{{ $class->id }}"
                            data-academic-year-id="{{ $selectedAcademicYearId }}"
                            data-academic-term-id="{{ $selectedAcademicTermId }}"
                            data-staff-id="{{ $currentStaffId }}"
                            class="js-student-rating-row"
                        >
                            <td>
                                <div class="fw-semibold">{{ $row->student->full_name }}</div>
                                <div class="text-secondary-light text-xs">{{ $row->student->admission_number ?: '—' }}</div>
                                <div class="rating-save-state" data-state="idle">Idle</div>
                            </td>
                            @foreach ($row->{$group} as $item)
                                <td>
                                    <select
                                        class="form-select form-select-sm js-student-rating-input"
                                        data-group="{{ $group }}"
                                        data-key="{{ $item->key }}"
                                    >
                                        <option value="">—</option>
                                        @for ($i = 1; $i <= 5; $i++)
                                            <option value="{{ $i }}" @selected((int) $item->value === $i)>{{ $i }}</option>
                                        @endfor
                                    </select>
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
