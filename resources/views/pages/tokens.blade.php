@extends('layouts.app')

@section('title', 'Result Tokens | ' . config('app.name'))

@section('content')
    <div class="breadcrumb d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
        <div>
            <h1 class="fw-semibold mb-4 h6 text-primary-light">Result Tokens</h1>
            <div>
                <a href="/" class="text-secondary-light hover-text-primary hover-underline">Dashboard</a>
                <span class="text-secondary-light">/ Gradebook</span>
                <span class="text-secondary-light">/ Tokens</span>
            </div>
        </div>
        <div class="d-flex align-items-center gap-12">
            <span class="badge bg-primary-50 text-primary-600 px-12 py-6" id="tokenRealtimeStatus">Live sync ready</span>
            <button type="button" class="btn btn-outline-primary-600 d-flex align-items-center gap-6" id="openBulkTokenGenerateModal">
                <span class="d-flex text-md"><i class="ri-magic-line"></i></span>
                Generate Tokens
            </button>
            <button type="button" class="btn btn-primary-600 d-flex align-items-center gap-6" id="openTokenCreateModal">
                <span class="d-flex text-md"><i class="ri-add-large-line"></i></span>
                New Token
            </button>
        </div>
    </div>

    <div class="card h-100">
        <div class="card-body p-0 dataTable-wrapper">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-16 px-20 py-12 border-bottom border-neutral-200">
                <div>
                    <h6 class="mb-4">Student Result Tokens</h6>
                    {{-- <p class="mb-0 text-secondary-light">Generate and manage 8-digit + 2-letter access codes for checking published results.</p> --}}
                </div>
                <form class="navbar-search dt-search m-0">
                    <input type="text" class="dt-input bg-transparent radius-4" name="search" placeholder="Search tokens..." />
                    <iconify-icon icon="ion:search-outline" class="icon"></iconify-icon>
                </form>
            </div>
            <div class="table-responsive">
                <table class="table bordered-table mb-0 data-table" id="gradebookTokensTable" data-page-length="10">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Token</th>
                            <th>Class</th>
                            <th>Session</th>
                            <th>Status</th>
                            <th>Assigned</th>
                            <th>Last Used</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($tokens as $token)
                            @php
                                $tokenPayload = [
                                    'id' => $token->id,
                                    'student_id' => $token->student_id,
                                    'academic_class_id' => $token->academic_class_id,
                                    'academic_year_id' => $token->academic_year_id,
                                    'academic_term_id' => $token->academic_term_id,
                                    'code' => $token->code,
                                    'is_active' => (bool) $token->is_active,
                                ];
                            @endphp
                            <tr data-row-id="{{ $token->id }}">
                                <td>
                                    <div class="fw-semibold text-primary-light">{{ $token->student?->full_name ?: 'Unknown student' }}</div>
                                    <div class="text-secondary-light text-xs">{{ $token->student?->admission_number ?: 'No admission number' }}</div>
                                </td>
                                <td>
                                    <span class="fw-semibold">{{ $token->code }}</span>
                                </td>
                                <td>{{ $token->academicClass?->name ?: '—' }}</td>
                                <td>
                                    <div>{{ $token->academicYear?->name ?: '—' }}</div>
                                    <div class="text-secondary-light text-xs">{{ $token->academicTerm?->name ?: '—' }}</div>
                                </td>
                                <td>
                                    <span class="badge {{ $token->is_active ? 'bg-success' : 'bg-secondary' }}">
                                        {{ $token->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td>{{ $token->assigned_at?->format('M d, Y H:i') ?: '—' }}</td>
                                <td>{{ $token->last_used_at?->format('M d, Y H:i') ?: 'Never' }}</td>
                                <td class="text-center">
                                    <div class="dropdown d-inline-block">
                                        <button type="button" class="btn btn-sm btn-outline-primary-600" data-bs-toggle="dropdown" aria-expanded="false">
                                            Actions <i class="ri-arrow-down-s-line ms-4"></i>
                                        </button>
                                        <ul class="dropdown-menu p-8 border-0 shadow-lg">
                                            <li>
                                                <button type="button" class="dropdown-item rounded-3 px-12 py-8 js-token-copy" data-code="{{ $token->code }}">
                                                    <i class="ri-file-copy-line me-8"></i>Copy token
                                                </button>
                                            </li>
                                            <li>
                                                <button type="button" class="dropdown-item rounded-3 px-12 py-8 js-token-edit" data-token='@json($tokenPayload)'>
                                                    <i class="ri-edit-line me-8"></i>Edit
                                                </button>
                                            </li>
                                            <li>
                                                <button type="button" class="dropdown-item rounded-3 px-12 py-8 text-danger js-token-delete" data-id="{{ $token->id }}" data-name="{{ $token->student?->full_name ?: 'this token' }}">
                                                    <i class="ri-delete-bin-line me-8"></i>Delete
                                                </button>
                                            </li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr class="gradebook-empty-row">
                                <td colspan="8" class="text-center py-4">No tokens found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="modal fade right-drawer-modal" id="tokenModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="tokenModalTitle">Create Token</h5>
                        <p class="mb-0 text-sm text-secondary-light">Assign a result token to one student for one reporting scope.</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="tokenForm"
                    data-create-url="{{ route('v1.gradebook.tokens.store', [], false) }}"
                    data-update-url-template="{{ route('v1.gradebook.tokens.update', ['id' => '__ID__'], false) }}">
                    <div class="modal-body">
                        <div class="alert alert-danger d-none" id="tokenModalError"></div>
                        <input type="hidden" name="id" />

                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Student</label>
                                <select class="form-select js-token-student-select" name="student_id" required>
                                    <option value="">Select student</option>
                                    @foreach ($students as $student)
                                        <option value="{{ $student->id }}">
                                            {{ $student->full_name }}{{ $student->admission_number ? ' (' . $student->admission_number . ')' : '' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Academic Year</label>
                                <select class="form-select js-token-year-select" name="academic_year_id" required>
                                    <option value="">Select year</option>
                                    @foreach ($academicYears as $year)
                                        <option value="{{ $year->id }}">{{ $year->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Academic Term</label>
                                <select class="form-select js-token-term-select choice" name="academic_term_id" required>
                                    <option value="">Select term</option>
                                    @foreach ($academicTerms as $term)
                                        <option value="{{ $term->id }}" data-academic-year-id="{{ $term->academic_year_id }}">{{ $term->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12 d-flex align-items-end">
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="tokenIsActive" checked>
                                    <label class="form-check-label" for="tokenIsActive">
                                        Token is active
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer d-flex justify-content-between">
                        <div class="text-secondary-light text-sm">Leave token code empty to auto-generate a new one.</div>
                        <div class="d-flex align-items-center gap-8">
                            <button type="button" class="btn btn-outline-neutral" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary-600" id="tokenSubmitButton">Save Token</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade right-drawer-modal drawer-lg" id="bulkTokenModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title">Bulk Generate Tokens</h5>
                        <p class="mb-0 text-sm text-secondary-light">Generate tokens for all active students in a class for one reporting scope.</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="bulkTokenForm" data-generate-url="{{ route('v1.gradebook.tokens.generate', [], false) }}">
                    <div class="modal-body">
                        <div class="alert alert-danger d-none" id="bulkTokenModalError"></div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Class</label>
                                <select class="form-select js-bulk-class-select" name="academic_class_id" required>
                                    <option value="">Select class</option>
                                    @foreach ($classes as $class)
                                        <option value="{{ $class->id }}">{{ $class->name }}{{ $class->section?->name ? ' - ' . $class->section->name : '' }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Academic Year</label>
                                <select class="form-select js-bulk-year-select" name="academic_year_id" required>
                                    <option value="">Select year</option>
                                    @foreach ($academicYears as $year)
                                        <option value="{{ $year->id }}">{{ $year->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Academic Term</label>
                                <select class="form-select js-bulk-term-select" name="academic_term_id" required>
                                    <option value="">Select term</option>
                                    @foreach ($academicTerms as $term)
                                        <option value="{{ $term->id }}" data-academic-year-id="{{ $term->academic_year_id }}">{{ $term->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6 d-flex align-items-end">
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="replace_existing" value="1" id="bulkReplaceExisting" checked>
                                    <label class="form-check-label" for="bulkReplaceExisting">
                                        Regenerate existing tokens
                                    </label>
                                </div>
                            </div>
                            <div class="col-12 d-flex align-items-end">
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="bulkTokenIsActive" checked>
                                    <label class="form-check-label" for="bulkTokenIsActive">
                                        Mark generated tokens as active
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer d-flex justify-content-between">
                        <div class="text-secondary-light text-sm">Bulk generation also syncs the report code for the selected reporting scope.</div>
                        <div class="d-flex align-items-center gap-8">
                            <button type="button" class="btn btn-outline-neutral" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary-600" id="bulkTokenSubmitButton">Generate Tokens</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function($) {
            if (!$) {
                return;
            }

            const pageTable = document.querySelector('#gradebookTokensTable');
            if (pageTable && window.DataTable) {
                new window.DataTable(pageTable, {
                    pageLength: Number(pageTable.dataset.pageLength || 10),
                    order: [[5, 'desc']]
                });
            }

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const tokenModalElement = document.getElementById('tokenModal');
            const tokenModal = tokenModalElement ? new bootstrap.Modal(tokenModalElement) : null;
            const bulkModalElement = document.getElementById('bulkTokenModal');
            const bulkModal = bulkModalElement ? new bootstrap.Modal(bulkModalElement) : null;
            const tokenForm = document.getElementById('tokenForm');
            const bulkForm = document.getElementById('bulkTokenForm');
            const tokenErrorBox = document.getElementById('tokenModalError');
            const bulkErrorBox = document.getElementById('bulkTokenModalError');
            const tokenSubmitButton = document.getElementById('tokenSubmitButton');
            const bulkSubmitButton = document.getElementById('bulkTokenSubmitButton');
            const realtimeStatus = document.getElementById('tokenRealtimeStatus');
            const updateUrlTemplate = tokenForm?.dataset.updateUrlTemplate;
            const createUrl = tokenForm?.dataset.createUrl;
            const generateUrl = bulkForm?.dataset.generateUrl;
            let reloadTimer = null;

            const buildRoute = (template, id) => String(template || '').replace('__ID__', id);
            const getField = (form, name) => form ? form.querySelector(`[name="${name}"]`) : null;

            const escapeHtml = (value) => $('<div>').text(String(value ?? '')).html();

            const setRealtimeStatus = (message, tone = 'primary') => {
                if (!realtimeStatus) {
                    return;
                }

                realtimeStatus.className = `badge px-12 py-6 bg-${tone === 'success' ? 'success' : (tone === 'warning' ? 'warning text-dark' : 'primary-50 text-primary-600')}`;
                realtimeStatus.textContent = message;
            };

            const clearErrors = (box) => {
                box?.classList.add('d-none');
                if (box) {
                    box.innerHTML = '';
                }
            };

            const showErrors = (box, html) => {
                if (!box) {
                    return;
                }

                box.innerHTML = html;
                box.classList.remove('d-none');
            };

            const requestJson = ({ url, method = 'GET', data = null }) => {
                return $.ajax({
                    url,
                    method,
                    data: data ? JSON.stringify(data) : null,
                    processData: false,
                    contentType: 'application/json; charset=UTF-8',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
            };

            const filterTermsByYear = ($termSelect, selectedYearId = '') => {
                if (!$termSelect || !$termSelect.length) {
                    return;
                }

                const previousValue = $termSelect.val();

                $termSelect.find('option').each(function(index) {
                    const $option = $(this);

                    if (index === 0) {
                        $option.prop('hidden', false).show();
                        return;
                    }

                    const optionYearId = String($option.data('academicYearId') || '');
                    const isVisible = !selectedYearId || !optionYearId || optionYearId === selectedYearId;

                    $option.prop('hidden', !isVisible);

                    if (isVisible) {
                        $option.show();
                    } else {
                        $option.hide();
                    }
                });

                const hasPreviousVisible = previousValue
                    && $termSelect.find(`option[value="${previousValue}"]`).filter(function() {
                        return !$(this).prop('hidden');
                    }).length > 0;

                $termSelect.val(hasPreviousVisible ? previousValue : '');
                $termSelect.trigger('change.select2');
                $termSelect.trigger('change');
            };

            const resetTokenForm = () => {
                tokenForm?.reset();
                tokenForm?.removeAttribute('data-editing-id');
                clearErrors(tokenErrorBox);
                const title = document.getElementById('tokenModalTitle');
                if (title) {
                    title.textContent = 'Create Token';
                }
                if (tokenSubmitButton) {
                    tokenSubmitButton.textContent = 'Save Token';
                    tokenSubmitButton.disabled = false;
                }
                filterTermsByYear($(tokenForm?.querySelector('.js-token-term-select')), $(tokenForm?.querySelector('.js-token-year-select')).val() || '');
            };

            const fillTokenForm = (payload) => {
                resetTokenForm();
                tokenForm?.setAttribute('data-editing-id', payload.id || '');

                const title = document.getElementById('tokenModalTitle');
                if (title) {
                    title.textContent = 'Edit Token';
                }

                if (getField(tokenForm, 'id')) getField(tokenForm, 'id').value = payload.id ?? '';
                if (getField(tokenForm, 'student_id')) getField(tokenForm, 'student_id').value = payload.student_id ?? '';
                if (getField(tokenForm, 'code')) getField(tokenForm, 'code').value = payload.code ?? '';
                if (getField(tokenForm, 'academic_year_id')) getField(tokenForm, 'academic_year_id').value = payload.academic_year_id ?? '';
                filterTermsByYear($(tokenForm?.querySelector('.js-token-term-select')), payload.academic_year_id ?? '');
                if (getField(tokenForm, 'academic_term_id')) getField(tokenForm, 'academic_term_id').value = payload.academic_term_id ?? '';
                if (getField(tokenForm, 'is_active')) getField(tokenForm, 'is_active').checked = Boolean(payload.is_active);

                if (tokenSubmitButton) {
                    tokenSubmitButton.textContent = 'Update Token';
                }
            };

            const resetBulkForm = () => {
                bulkForm?.reset();
                clearErrors(bulkErrorBox);
                if (bulkSubmitButton) {
                    bulkSubmitButton.textContent = 'Generate Tokens';
                    bulkSubmitButton.disabled = false;
                }
                filterTermsByYear($(bulkForm?.querySelector('.js-bulk-term-select')), '');
            };

            $('#openTokenCreateModal').on('click', function() {
                resetTokenForm();
                tokenModal?.show();
            });

            $('#openBulkTokenGenerateModal').on('click', function() {
                resetBulkForm();
                bulkModal?.show();
            });

            $(tokenForm?.querySelector('.js-token-year-select')).on('change', function() {
                filterTermsByYear($(tokenForm?.querySelector('.js-token-term-select')), $(this).val() || '');
            });

            $(bulkForm?.querySelector('.js-bulk-year-select')).on('change', function() {
                filterTermsByYear($(bulkForm?.querySelector('.js-bulk-term-select')), $(this).val() || '');
            });

            $(document).on('click', '.js-token-copy', async function() {
                const code = String($(this).data('code') || '');

                if (!code) {
                    return;
                }

                try {
                    await navigator.clipboard.writeText(code);
                    setRealtimeStatus(`Copied token ${code}`, 'success');
                } catch (_error) {
                    setRealtimeStatus('Unable to copy token automatically', 'warning');
                }
            });

            $(document).on('click', '.js-token-edit', function() {
                let payload = $(this).data('token');
                if (typeof payload === 'string') {
                    try {
                        payload = JSON.parse(payload);
                    } catch (_error) {
                        payload = null;
                    }
                }

                if (!payload) {
                    return;
                }

                fillTokenForm(payload);
                tokenModal?.show();
            });

            $(document).on('click', '.js-token-delete', function() {
                const id = $(this).data('id');
                const name = $(this).data('name') || 'this token';

                Swal.fire({
                    icon: 'warning',
                    title: 'Delete token?',
                    text: `This will remove the token assigned to ${name}.`,
                    showCancelButton: true,
                    confirmButtonText: 'Delete',
                    cancelButtonText: 'Cancel',
                    confirmButtonColor: '#dc3545',
                }).then((result) => {
                    if (!result.isConfirmed) {
                        return;
                    }

                    $.ajax({
                        url: buildRoute(updateUrlTemplate, id),
                        method: 'POST',
                        data: {
                            _method: 'DELETE'
                        },
                        headers: {
                            Accept: 'application/json'
                        }
                    }).done((response) => {
                        setRealtimeStatus(response?.message || 'Token deleted', 'success');
                        Swal.fire({
                            icon: 'success',
                            title: 'Deleted',
                            text: response?.message || 'Token deleted successfully.'
                        });
                    }).fail((xhr) => {
                        Swal.fire({
                            icon: 'error',
                            title: 'Delete failed',
                            html: escapeHtml(xhr?.responseJSON?.message || 'Unable to delete token right now.')
                        });
                    });
                });
            });

            tokenForm?.addEventListener('submit', function(event) {
                event.preventDefault();
                clearErrors(tokenErrorBox);

                const payload = {
                    student_id: getField(tokenForm, 'student_id')?.value || null,
                    academic_year_id: getField(tokenForm, 'academic_year_id')?.value || null,
                    academic_term_id: getField(tokenForm, 'academic_term_id')?.value || null,
                    code: (getField(tokenForm, 'code')?.value || '').trim().toUpperCase() || null,
                    is_active: Boolean(getField(tokenForm, 'is_active')?.checked),
                };

                const editingId = tokenForm.getAttribute('data-editing-id');
                const url = editingId ? buildRoute(updateUrlTemplate, editingId) : createUrl;
                const method = editingId ? 'PUT' : 'POST';

                if (tokenSubmitButton) {
                    tokenSubmitButton.disabled = true;
                    tokenSubmitButton.textContent = editingId ? 'Updating...' : 'Saving...';
                }

                requestJson({
                    url,
                    method,
                    data: payload
                }).done((response) => {
                    tokenModal?.hide();
                    setRealtimeStatus(response?.message || 'Token saved', 'success');
                    Swal.fire({
                        icon: 'success',
                        title: editingId ? 'Token updated' : 'Token created',
                        text: response?.message || 'The token has been saved successfully.'
                    });
                }).fail((xhr) => {
                    const response = xhr?.responseJSON ?? {};
                    const errors = Object.values(response.errors ?? {}).flat();
                    const errorHtml = errors.length
                        ? `<ul class="mb-0 ps-3">${errors.map((error) => `<li>${escapeHtml(error)}</li>`).join('')}</ul>`
                        : `<div>${escapeHtml(response.message || 'Unable to save token.')}</div>`;
                    showErrors(tokenErrorBox, errorHtml);
                }).always(() => {
                    if (tokenSubmitButton) {
                        tokenSubmitButton.disabled = false;
                        tokenSubmitButton.textContent = editingId ? 'Update Token' : 'Save Token';
                    }
                });
            });

            bulkForm?.addEventListener('submit', function(event) {
                event.preventDefault();
                clearErrors(bulkErrorBox);

                const payload = {
                    academic_class_id: getField(bulkForm, 'academic_class_id')?.value || null,
                    academic_year_id: getField(bulkForm, 'academic_year_id')?.value || null,
                    academic_term_id: getField(bulkForm, 'academic_term_id')?.value || null,
                    replace_existing: Boolean(getField(bulkForm, 'replace_existing')?.checked),
                    is_active: Boolean(getField(bulkForm, 'is_active')?.checked),
                };

                if (bulkSubmitButton) {
                    bulkSubmitButton.disabled = true;
                    bulkSubmitButton.textContent = 'Generating...';
                }

                requestJson({
                    url: generateUrl,
                    method: 'POST',
                    data: payload
                }).done((response) => {
                    bulkModal?.hide();
                    setRealtimeStatus(response?.message || 'Tokens generated', 'success');
                    Swal.fire({
                        icon: 'success',
                        title: 'Tokens generated',
                        text: response?.message || `Generated ${response?.data?.generated_count || response?.generated_count || 'tokens'} successfully.`
                    });
                }).fail((xhr) => {
                    const response = xhr?.responseJSON ?? {};
                    const errors = Object.values(response.errors ?? {}).flat();
                    const errorHtml = errors.length
                        ? `<ul class="mb-0 ps-3">${errors.map((error) => `<li>${escapeHtml(error)}</li>`).join('')}</ul>`
                        : `<div>${escapeHtml(response.message || 'Unable to generate tokens.')}</div>`;
                    showErrors(bulkErrorBox, errorHtml);
                }).always(() => {
                    if (bulkSubmitButton) {
                        bulkSubmitButton.disabled = false;
                        bulkSubmitButton.textContent = 'Generate Tokens';
                    }
                });
            });

            window.addEventListener('gradebook:entity.changed', function(event) {
                const payload = event.detail || {};
                if (payload.entity !== 'token') {
                    return;
                }

                const action = String(payload.action || 'updated').replace('_', ' ');
                const actor = payload.actor_name ? ` by ${payload.actor_name}` : '';
                setRealtimeStatus(`Token ${action}${actor}`, 'success');

                window.clearTimeout(reloadTimer);
                reloadTimer = window.setTimeout(() => {
                    window.location.reload();
                }, 900);
            });

            tokenModalElement?.addEventListener('hidden.bs.modal', resetTokenForm);
            bulkModalElement?.addEventListener('hidden.bs.modal', resetBulkForm);

            resetTokenForm();
            resetBulkForm();
        })(window.jQuery);
    </script>
@endpush
