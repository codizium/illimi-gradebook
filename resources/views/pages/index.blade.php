@extends('layouts.app')

@section('content')
    <div class="breadcrumb d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
        <div>
            <h1 class="fw-semibold mb-4 h6 text-primary-light">Gradebook</h1>
            <div>
                <a href="/" class="text-secondary-light hover-text-primary hover-underline">Dashboard</a>
                <span class="text-secondary-light">/ Gradebook</span>
            </div>
        </div>
    </div>

    <div class="card h-100">
        <div class="card-body p-0 dataTable-wrapper">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-16 px-20 py-12 border-bottom border-neutral-200">
                <div>
                    <h6 class="mb-4">Subject Gradebooks</h6>
                    <p class="mb-0 text-secondary-light">Pick a class-bound subject and jump straight into score entry.</p>
                </div>
                <form class="navbar-search dt-search m-0">
                    <input type="text" class="dt-input bg-transparent radius-4" name="search" placeholder="Search subject or class..." />
                    <iconify-icon icon="ion:search-outline" class="icon"></iconify-icon>
                </form>
            </div>

            <div class="p-0">
                <table class="table bordered-table mb-0 data-table" data-page-length="10">
                    <thead>
                        <tr>
                            <th>S/N</th>
                            <th>Subject</th>
                            <th>Code</th>
                            <th>Class</th>
                            <th>Section</th>
                            <th>Teachers</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($subjectClassRows as $row)
                            @php
                                $teacherNames = $row->teachers
                                    ->map(fn ($teacher) => $teacher->full_name ?? trim(($teacher->first_name ?? '') . ' ' . ($teacher->last_name ?? '')))
                                    ->filter()
                                    ->values();
                            @endphp
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td class="fw-semibold text-primary-light">{{ $row->subject->name }}</td>
                                <td>{{ $row->subject->code ?: '—' }}</td>
                                <td>{{ $row->class->name }}</td>
                                <td>{{ $row->class->section?->name ?: '—' }}</td>
                                <td>{{ $teacherNames->isNotEmpty() ? $teacherNames->join(', ') : '—' }}</td>
                                <td class="text-center">
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-primary-600 dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                            Actions
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li>
                                                <a class="dropdown-item" href="{{ route('gradebook.assessments.show', ['subject' => $row->subject->id, 'class' => $row->class->id]) }}">
                                                    Enter Gradebook
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item" href="{{ route('gradebook.ratings.effective', ['class' => $row->class->id]) }}">
                                                    Effective Assessment
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item" href="{{ route('gradebook.ratings.psychomotor', ['class' => $row->class->id]) }}">
                                                    Psychomotor
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-24 text-secondary-light">No class subjects available yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
