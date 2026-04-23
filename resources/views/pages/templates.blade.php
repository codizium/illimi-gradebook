@extends('layouts.app')

@section('title', 'Assessment Templates | ' . config('app.name'))

@section('content')
    <div class="breadcrumb d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
        <div>
            <h1 class="fw-semibold mb-4 h6 text-primary-light">Assessment Templates</h1>
            <div>
                <a href="/" class="text-secondary-light hover-text-primary hover-underline">Dashboard</a>
                <span class="text-secondary-light">/ Gradebook</span>
                <span class="text-secondary-light">/ Templates</span>
            </div>
        </div>
        <div class="d-flex align-items-center gap-12">
            <span class="badge bg-primary-50 text-primary-600 px-12 py-6" id="templateRealtimeStatus">Live sync ready</span>
            <button type="button" class="btn btn-primary-600 d-flex align-items-center gap-6" id="openGradebookTemplateCreateModal">
                <span class="d-flex text-md"><i class="ri-add-large-line"></i></span>
                New Template
            </button>
        </div>
    </div>

    <div class="card h-100">
        <div class="card-body p-0 dataTable-wrapper">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-16 px-20 py-12 border-bottom border-neutral-200">
                <div>
                    <h6 class="mb-4">Gradebook Templates</h6>
                    <p class="mb-0 text-secondary-light">Create  assessment structures and keep them synced in real time.</p>
                </div>
                <form class="navbar-search dt-search m-0">
                    <input type="text" class="dt-input bg-transparent radius-4" name="search" placeholder="Search templates..." />
                    <iconify-icon icon="ion:search-outline" class="icon"></iconify-icon>
                </form>
            </div>
            <div class="table-responsive">
                <table class="table bordered-table mb-0 data-table" id="gradebookTemplatesTable" data-page-length="10">
                    <thead>
                        <tr>
                            <th>Template</th>
                            <th>Subject</th>
                            <th>Class</th>
                            <th>Year</th>
                            <th>Term</th>
                            <th>Items</th>
                            <th>Default</th>
                            <th>Status</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($templates as $template)
                            @php
                                $templatePayload = [
                                    'id' => $template->id,
                                    'name' => $template->name,
                                    'code' => $template->code,
                                    'description' => $template->description,
                                    'subject_id' => $template->subject_id,
                                    'academic_class_id' => $template->academic_class_id,
                                    'academic_year_id' => $template->academic_year_id,
                                    'academic_term_id' => $template->academic_term_id,
                                    'is_default' => $template->is_default ? 1 : 0,
                                    'status' => $template->status,
                                    'items' => $template->items
                                        ->map(fn($item) => [
                                            'id' => $item->id,
                                            'label' => $item->label,
                                            'code' => $item->code,
                                            'component_type' => $item->component_type,
                                            'max_score' => (float) $item->max_score,
                                            'weight' => $item->weight !== null ? (float) $item->weight : null,
                                            'position' => $item->position,
                                            'is_required' => (bool) $item->is_required,
                                            'affects_total' => (bool) $item->affects_total,
                                        ])
                                        ->values()
                                        ->all(),
                                ];
                            @endphp
                            <tr data-row-id="{{ $template->id }}">
                                <td>
                                    <div class="fw-semibold text-primary-light">{{ $template->name }}</div>
                                    <div class="text-secondary-light text-xs">{{ $template->code ?: 'No code' }}</div>
                                </td>
                                <td>{{ $template->subject?->name ?: 'All subjects' }}</td>
                                <td>{{ $template->academicClass?->name ?: 'All classes' }}</td>
                                <td>{{ $template->academicYear?->name ?: 'All years' }}</td>
                                <td>{{ $template->academicTerm?->name ?: 'All terms' }}</td>
                                <td>{{ $template->items->count() }}</td>
                                <td>
                                    @if ($template->is_default)
                                        <span class="badge bg-success">Default</span>
                                    @else
                                        <span class="badge bg-neutral-200 text-secondary-light">No</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge {{ $template->status === 'active' ? 'bg-primary-600' : 'bg-secondary' }}">
                                        {{ ucfirst($template->status ?: 'draft') }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    <div class="dropdown d-inline-block">
                                        <button type="button" class="btn btn-sm btn-outline-primary-600" data-bs-toggle="dropdown" aria-expanded="false">
                                            Actions <i class="ri-arrow-down-s-line ms-4"></i>
                                        </button>
                                        <ul class="dropdown-menu p-8 border-0 shadow-lg">
                                            <li>
                                                <button type="button" class="dropdown-item rounded-3 px-12 py-8 js-gb-template-edit" data-template='@json($templatePayload)'>
                                                    <i class="ri-edit-line me-8"></i>Edit
                                                </button>
                                            </li>
                                            <li>
                                                <button type="button" class="dropdown-item rounded-3 px-12 py-8 text-danger js-gb-template-delete" data-id="{{ $template->id }}" data-name="{{ $template->name }}">
                                                    <i class="ri-delete-bin-line me-8"></i>Delete
                                                </button>
                                            </li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr class="gradebook-empty-row">
                                <td colspan="9" class="text-center py-4">No templates found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="modal fade right-drawer-modal drawer-lg" id="gradebookTemplateModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="gradebookTemplateModalTitle">Create Assessment Template</h5>
                        <p class="mb-0 text-sm text-secondary-light">Define the workbook structure for a tenant, class, subject, term, or year.</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="gradebookTemplateForm"
                    data-create-url="{{ route('v1.gradebook.templates.store', [], false) }}"
                    data-update-url-template="{{ route('v1.gradebook.templates.update', ['template' => '__ID__'], false) }}">
                    <div class="modal-body">
                        <div class="alert alert-danger d-none" id="gradebookTemplateModalError"></div>
                        <input type="hidden" name="id" />

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Template Name</label>
                                <input type="text" class="form-control" name="name" placeholder="Primary CA + Exam Template" required />
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Code</label>
                                <input type="text" class="form-control" name="code" placeholder="PRY-CA-EXAM" />
                            </div>
                            <div class="col-12">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" name="description" rows="2" placeholder="Optional notes for how this workbook should be used."></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Subject</label>
                                <select class="form-select" name="subject_id">
                                    <option value="">All subjects</option>
                                    @foreach ($subjects as $subject)
                                        <option value="{{ $subject->id }}">{{ $subject->name }}{{ $subject->code ? ' (' . $subject->code . ')' : '' }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Class</label>
                                <select class="form-select" name="academic_class_id">
                                    <option value="">All classes</option>
                                    @foreach ($classes as $class)
                                        <option value="{{ $class->id }}">{{ $class->name }}{{ $class->section?->name ? ' - ' . $class->section->name : '' }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Academic Year</label>
                                <select class="form-select js-template-year-select" name="academic_year_id">
                                    <option value="">All years</option>
                                    @foreach ($academicYears as $year)
                                        <option value="{{ $year->id }}">{{ $year->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Academic Term</label>
                                <select class="form-select js-template-term-select" name="academic_term_id">
                                    <option value="">All terms</option>
                                    @foreach ($academicTerms as $term)
                                        <option value="{{ $term->id }}" data-academic-year-id="{{ $term->academic_year_id }}">{{ $term->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status">
                                    <option value="active">Active</option>
                                    <option value="draft">Draft</option>
                                    <option value="archived">Archived</option>
                                </select>
                            </div>
                            <div class="col-md-6 d-flex align-items-end">
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="is_default" value="1" id="gradebookTemplateIsDefault">
                                    <label class="form-check-label" for="gradebookTemplateIsDefault">
                                        Use as default for this scope
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="border-top border-neutral-200 mt-24 pt-24">
                            <div class="d-flex align-items-center justify-content-between gap-12 mb-16">
                                <div>
                                    <h6 class="mb-4">Template Items</h6>
                                    <p class="mb-0 text-secondary-light text-sm">Add the assessment components that make up the workbook and total score.</p>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-primary-600" id="gradebookAddTemplateItem">
                                    <i class="ri-add-line me-4"></i>Add Item
                                </button>
                            </div>

                            <div id="gradebookTemplateItems" class="d-flex flex-column gap-12"></div>
                            <div class="rounded-12 border border-dashed border-neutral-300 px-16 py-20 text-center text-secondary-light text-sm" id="gradebookTemplateItemsEmpty">
                                No template items yet. Add at least one item to save this template.
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer d-flex justify-content-between">
                        <div class="text-secondary-light text-sm" id="gradebookTemplateFormHint">Changes are saved through the gradebook API and broadcast live.</div>
                        <div class="d-flex align-items-center gap-8">
                            <button type="button" class="btn btn-outline-neutral" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary-600" id="gradebookTemplateSubmitButton">Save Template</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <template id="gradebookTemplateItemTemplate">
        <div class="rounded-12 border border-neutral-200 p-16 js-template-item-card">
            <div class="d-flex align-items-center justify-content-between gap-12 mb-12">
                <div class="fw-semibold text-primary-light">Workbook Item</div>
                <button type="button" class="btn btn-sm btn-outline-danger-600 js-remove-template-item">
                    <i class="ri-delete-bin-line me-4"></i>Remove
                </button>
            </div>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Label</label>
                    <input type="text" class="form-control js-item-label" placeholder="Assignment 1" required />
                </div>
                <div class="col-md-3">
                    <label class="form-label">Code</label>
                    <input type="text" class="form-control js-item-code" placeholder="A1" required />
                </div>
                <div class="col-md-3">
                    <label class="form-label">Type</label>
                    <select class="form-select js-item-component-type">
                        <option value="continuous_assessment">Continuous Assessment</option>
                        <option value="exam">Exam</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Position</label>
                    <input type="number" min="1" step="1" class="form-control js-item-position" />
                </div>
                <div class="col-md-3">
                    <label class="form-label">Max Score</label>
                    <input type="number" min="0" step="0.01" class="form-control js-item-max-score" value="10" required />
                </div>
                <div class="col-md-3">
                    <label class="form-label">Weight</label>
                    <input type="number" min="0" step="0.01" class="form-control js-item-weight" placeholder="Optional" />
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <div class="form-check mb-2">
                        <input class="form-check-input js-item-required" type="checkbox" value="1" />
                        <label class="form-check-label">Required</label>
                    </div>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <div class="form-check mb-2">
                        <input class="form-check-input js-item-affects-total" type="checkbox" value="1" checked />
                        <label class="form-check-label">Affects Total</label>
                    </div>
                </div>
            </div>
        </div>
    </template>
@endsection

@push('scripts')
    <script>
        (function($) {
            if (!$) {
                return;
            }

            const pageTable = document.querySelector('#gradebookTemplatesTable');
            if (pageTable && window.DataTable) {
                new window.DataTable(pageTable, {
                    pageLength: Number(pageTable.dataset.pageLength || 10),
                    order: [[0, 'asc']]
                });
            }

            const modalElement = document.getElementById('gradebookTemplateModal');
            const modal = modalElement ? new bootstrap.Modal(modalElement) : null;
            const form = document.getElementById('gradebookTemplateForm');
            const itemsContainer = document.getElementById('gradebookTemplateItems');
            const itemsEmptyState = document.getElementById('gradebookTemplateItemsEmpty');
            const itemTemplate = document.getElementById('gradebookTemplateItemTemplate');
            const errorBox = document.getElementById('gradebookTemplateModalError');
            const realtimeStatus = document.getElementById('templateRealtimeStatus');
            const submitButton = document.getElementById('gradebookTemplateSubmitButton');
            const yearSelect = form?.querySelector('.js-template-year-select');
            const termSelect = form?.querySelector('.js-template-term-select');
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

            const buildRoute = (template, id) => String(template || '').replace('__ID__', id);
            const createUrl = form?.dataset.createUrl;
            const updateUrlTemplate = form?.dataset.updateUrlTemplate;
            const getField = (name) => form ? form.querySelector(`[name="${name}"]`) : null;

            const clearErrors = () => {
                errorBox?.classList.add('d-none');
                if (errorBox) {
                    errorBox.innerHTML = '';
                }
            };

            const showErrors = (html) => {
                if (!errorBox) {
                    return;
                }

                errorBox.innerHTML = html;
                errorBox.classList.remove('d-none');
            };

            const escapeHtml = (value) => $('<div>').text(String(value ?? '')).html();

            const setRealtimeStatus = (message, tone = 'primary') => {
                if (!realtimeStatus) {
                    return;
                }

                realtimeStatus.className = `badge px-12 py-6 bg-${tone === 'success' ? 'success' : (tone === 'warning' ? 'warning text-dark' : 'primary-50 text-primary-600')}`;
                realtimeStatus.textContent = message;
            };

            const updateItemsEmptyState = () => {
                if (!itemsEmptyState || !itemsContainer) {
                    return;
                }

                itemsEmptyState.classList.toggle('d-none', Boolean(itemsContainer.querySelector('.js-template-item-card')));
            };

            const filterTermsByYear = (selectedYearId = '') => {
                if (!termSelect) {
                    return;
                }

                const previousValue = termSelect.value;

                Array.from(termSelect.options).forEach((option, index) => {
                    if (index === 0) {
                        option.hidden = false;
                        return;
                    }

                    const optionYearId = option.dataset.academicYearId || '';
                    option.hidden = Boolean(selectedYearId) && Boolean(optionYearId) && optionYearId !== selectedYearId;
                });

                if (previousValue) {
                    const currentOption = Array.from(termSelect.options).find((option) => option.value === previousValue && !option.hidden);
                    termSelect.value = currentOption ? previousValue : '';
                }
            };

            const appendItemCard = (item = {}) => {
                if (!itemTemplate || !itemsContainer) {
                    return;
                }

                const fragment = itemTemplate.content.cloneNode(true);
                const card = fragment.querySelector('.js-template-item-card');
                card.querySelector('.js-item-label').value = item.label ?? '';
                card.querySelector('.js-item-code').value = item.code ?? '';
                card.querySelector('.js-item-component-type').value = item.component_type ?? 'continuous_assessment';
                card.querySelector('.js-item-position').value = item.position ?? (itemsContainer.children.length + 1);
                card.querySelector('.js-item-max-score').value = item.max_score ?? 10;
                card.querySelector('.js-item-weight').value = item.weight ?? '';
                card.querySelector('.js-item-required').checked = Boolean(item.is_required);
                card.querySelector('.js-item-affects-total').checked = item.affects_total !== false;
                itemsContainer.appendChild(fragment);
                updateItemsEmptyState();
            };

            const collectItems = () => {
                const cards = Array.from(itemsContainer?.querySelectorAll('.js-template-item-card') ?? []);

                if (!cards.length) {
                    throw new Error('Add at least one template item before saving.');
                }

                return cards.map((card, index) => {
                    const value = (selector) => card.querySelector(selector)?.value?.trim() ?? '';
                    const checked = (selector) => Boolean(card.querySelector(selector)?.checked);

                    return {
                        label: value('.js-item-label'),
                        code: value('.js-item-code'),
                        component_type: value('.js-item-component-type') || 'continuous_assessment',
                        max_score: value('.js-item-max-score'),
                        weight: value('.js-item-weight') || null,
                        position: value('.js-item-position') || (index + 1),
                        is_required: checked('.js-item-required'),
                        affects_total: checked('.js-item-affects-total'),
                    };
                });
            };

            const resetForm = () => {
                form?.reset();
                clearErrors();
                form?.removeAttribute('data-editing-id');

                const idField = getField('id');
                const statusField = getField('status');

                if (idField) {
                    idField.value = '';
                }

                if (statusField) {
                    statusField.value = 'active';
                }

                if (itemsContainer) {
                    itemsContainer.innerHTML = '';
                }
                appendItemCard({ label: 'Assignment 1', code: 'A1', component_type: 'continuous_assessment', max_score: 10, position: 1, is_required: true, affects_total: true });
                appendItemCard({ label: 'Exam', code: 'EXAM', component_type: 'exam', max_score: 60, position: 2, is_required: true, affects_total: true });
                filterTermsByYear('');
                const title = document.getElementById('gradebookTemplateModalTitle');
                if (title) {
                    title.textContent = 'Create Assessment Template';
                }
                if (submitButton) {
                    submitButton.textContent = 'Save Template';
                }
            };

            const fillForm = (payload) => {
                resetForm();
                form?.setAttribute('data-editing-id', payload.id);

                const idField = getField('id');
                const nameField = getField('name');
                const codeField = getField('code');
                const descriptionField = getField('description');
                const subjectField = getField('subject_id');
                const classField = getField('academic_class_id');
                const yearField = getField('academic_year_id');
                const termField = getField('academic_term_id');
                const statusField = getField('status');
                const defaultField = getField('is_default');

                if (idField) idField.value = payload.id ?? '';
                if (nameField) nameField.value = payload.name ?? '';
                if (codeField) codeField.value = payload.code ?? '';
                if (descriptionField) descriptionField.value = payload.description ?? '';
                if (subjectField) subjectField.value = payload.subject_id ?? '';
                if (classField) classField.value = payload.academic_class_id ?? '';
                if (yearField) yearField.value = payload.academic_year_id ?? '';
                filterTermsByYear(payload.academic_year_id ?? '');
                if (termField) termField.value = payload.academic_term_id ?? '';
                if (statusField) statusField.value = payload.status ?? 'active';
                if (defaultField) defaultField.checked = Boolean(payload.is_default);
                if (itemsContainer) {
                    itemsContainer.innerHTML = '';
                }
                (payload.items ?? []).forEach((item) => appendItemCard(item));
                updateItemsEmptyState();

                const title = document.getElementById('gradebookTemplateModalTitle');
                if (title) {
                    title.textContent = `Edit ${payload.name ?? 'Template'}`;
                }
                if (submitButton) {
                    submitButton.textContent = 'Update Template';
                }
            };

            const openCreateModal = () => {
                resetForm();
                modal?.show();
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

            $('#openGradebookTemplateCreateModal').on('click', openCreateModal);
            $('#gradebookAddTemplateItem').on('click', function() {
                appendItemCard({});
            });

            $(document).on('click', '.js-remove-template-item', function() {
                $(this).closest('.js-template-item-card').remove();
                updateItemsEmptyState();
            });

            $(document).on('click', '.js-gb-template-edit', function() {
                let payload = $(this).data('template');
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

                fillForm(payload);
                modal?.show();
            });

            $(document).on('click', '.js-gb-template-delete', function() {
                const id = $(this).data('id');
                const name = $(this).data('name') || 'this template';

                Swal.fire({
                    icon: 'warning',
                    title: 'Delete template?',
                    text: `This will permanently remove ${name}.`,
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
                        setRealtimeStatus(response?.message || 'Template deleted', 'success');
                        Swal.fire({
                            icon: 'success',
                            title: 'Deleted',
                            text: response?.message || 'Template deleted successfully.'
                        });
                    }).fail((xhr) => {
                        Swal.fire({
                            icon: 'error',
                            title: 'Delete failed',
                            html: escapeHtml(xhr?.responseJSON?.message || 'Unable to delete template right now.')
                        });
                    });
                });
            });

            yearSelect?.on('change', function() {
                filterTermsByYear(this.value);
            });

            form?.on('submit', function(event) {
                event.preventDefault();
                clearErrors();

                let payload;

                try {
                    payload = {
                        name: form.querySelector('[name="name"]').value.trim(),
                        code: form.querySelector('[name="code"]').value.trim() || null,
                        description: form.querySelector('[name="description"]').value.trim() || null,
                        subject_id: form.querySelector('[name="subject_id"]').value || null,
                        academic_class_id: form.querySelector('[name="academic_class_id"]').value || null,
                        academic_year_id: form.querySelector('[name="academic_year_id"]').value || null,
                        academic_term_id: form.querySelector('[name="academic_term_id"]').value || null,
                        status: form.querySelector('[name="status"]').value || 'active',
                        is_default: form.querySelector('[name="is_default"]').checked,
                        items: collectItems(),
                    };
                } catch (error) {
                    showErrors(`<div>${escapeHtml(error.message)}</div>`);
                    return;
                }

                const editingId = form.getAttribute('data-editing-id');
                const url = editingId ? buildRoute(updateUrlTemplate, editingId) : createUrl;
                const method = editingId ? 'PUT' : 'POST';

                if (submitButton) {
                    submitButton.disabled = true;
                    submitButton.textContent = editingId ? 'Updating...' : 'Saving...';
                }

                requestJson({
                    url,
                    method,
                    data: payload
                }).done((response) => {
                    modal?.hide();
                    setRealtimeStatus(response?.message || 'Template saved', 'success');
                    Swal.fire({
                        icon: 'success',
                        title: editingId ? 'Template updated' : 'Template created',
                        text: response?.message || 'The template has been saved successfully.'
                    });
                }).fail((xhr) => {
                    const response = xhr?.responseJSON ?? {};
                    const errors = Object.values(response.errors ?? {}).flat();
                    const errorHtml = errors.length
                        ? `<ul class="mb-0 ps-3">${errors.map((error) => `<li>${escapeHtml(error)}</li>`).join('')}</ul>`
                        : `<div>${escapeHtml(response.message || 'Unable to save template.')}</div>`;
                    showErrors(errorHtml);
                }).always(() => {
                    if (submitButton) {
                        submitButton.disabled = false;
                        submitButton.textContent = editingId ? 'Update Template' : 'Save Template';
                    }
                });
            });

            window.on('gradebook:entity.changed', function(event) {
                const payload = event.detail || {};
                if (payload.entity !== 'assessment_template') {
                    return;
                }

                const action = String(payload.action || 'updated');
                const actor = payload.actor_name ? ` by ${payload.actor_name}` : '';
                setRealtimeStatus(`Template ${action}${actor}`, 'success');

                window.setTimeout(() => {
                    setRealtimeStatus('Live sync ready', 'primary');
                }, 2200);
            });

            modalElement?.on('hidden.bs.modal', function() {
                resetForm();
            });

            resetForm();
        })(window.jQuery);
    </script>
@endpush
