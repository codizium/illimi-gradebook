<?php

namespace Illimi\Gradebook\Controllers\Web;

use Codizium\Core\Controllers\BaseController;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illimi\Gradebook\Models\Token;

class GradebookWebController extends BaseController
{
    protected function currentRoleContext(): string
    {
        $user = auth()->user();
        if (! $user) {
            return 'admin';
        }

        if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['admin', 'super-admin', 'principal', 'organization-admin'])) {
            return 'admin';
        }

        if (method_exists($user, 'hasRole') && $user->hasRole('teacher')) {
            return 'teacher';
        }

        if (method_exists($user, 'hasRole') && $user->hasRole('student')) {
            return 'student';
        }

        if (method_exists($user, 'hasRole') && $user->hasRole('parent')) {
            return 'parent';
        }

        return 'admin';
    }

    protected function organizationId(): ?string
    {
        return optional(function_exists('organization') ? organization() : null)->id
            ?? auth()->user()?->organization_id;
    }

    protected function queryFor(string $modelClass): Builder
    {
        $query = $modelClass::query();
        $roleContext = $this->currentRoleContext();

        if ($roleContext === 'teacher' && method_exists($modelClass, 'scopeTeacher')) {
            $query->teacher();
        } elseif ($roleContext === 'student' && method_exists($modelClass, 'scopeStudent')) {
            $query->student();
        } elseif ($roleContext === 'parent' && method_exists($modelClass, 'scopeParent')) {
            $query->parent();
        }

        $query->when(
            $this->organizationId(),
            fn (Builder $q, string $organizationId) => $q->where('organization_id', $organizationId)
        );

        return $query;
    }

    public function dashboard()
    {
        return \Inertia\Inertia::render('Gradebook/Dashboard', [
            'apiBase' => '/api/v1/gradebook',
        ]);
    }

    public function assessments()
    {
        return \Inertia\Inertia::render('Gradebook/Index', [
            'apiBase' => '/api/v1/gradebook',
        ]);
    }

    public function show(string $subject, string $class)
    {
        return \Inertia\Inertia::render('Gradebook/Sheet', [
            'apiBase' => '/api/v1/gradebook',
            'subjectId' => $subject,
            'classId' => $class,
        ]);
    }

    public function effectiveAssessment(string $class)
    {
        return \Inertia\Inertia::render('Gradebook/EffectiveAssessment', [
            'apiBase' => '/api/v1/gradebook',
            'classId' => $class,
        ]);
    }

    public function psychomotorAssessment(string $class)
    {
        return \Inertia\Inertia::render('Gradebook/PsychomotorAssessment', [
            'apiBase' => '/api/v1/gradebook',
            'classId' => $class,
        ]);
    }

    public function reports()
    {
        return \Inertia\Inertia::render('Gradebook/Reports', [
            'apiBase' => '/api/v1/gradebook',
        ]);
    }

    public function tokens()
    {
        return \Inertia\Inertia::render('Gradebook/Tokens', [
            'apiBase' => '/api/v1/gradebook',
        ]);
    }

    public function templates()
    {
        return \Inertia\Inertia::render('Gradebook/Templates', [
            'apiBase' => '/api/v1/gradebook',
        ]);
    }

    public function downloadToken(string $token)
    {
        $record = $this->queryFor(Token::class)
            ->with(['student', 'academicClass.section', 'academicYear', 'academicTerm'])
            ->where('id', $token)
            ->firstOrFail();

        $tokens = collect([$record]);

        $meta = [
            'scope' => 'token',
            'generated_at' => now()->toIso8601String(),
        ];

        $fileName = 'Token_' . ($record->student?->full_name ? str_replace(' ', '_', $record->student->full_name) : $record->student_id)
            . '_' . now()->format('Y-m-d_His');

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('illimi-gradebook::pdf.tokens', [
            'tokens' => $tokens,
            'meta' => $meta,
        ]);

        return $pdf->download($fileName . '.pdf');
    }

    public function exportTokens(Request $request)
    {
        $scope = (string) $request->query('scope', 'all'); // all | class | student
        $studentId = $request->query('student_id');
        $classId = $request->query('academic_class_id');
        $yearId = $request->query('academic_year_id');
        $termId = $request->query('academic_term_id');

        if (!in_array($scope, ['all', 'class', 'student'], true)) {
            $scope = 'all';
        }

        if ($scope === 'student' && empty($studentId)) {
            return redirect()->back()->with('error', 'Select a student to export.');
        }
        if ($scope === 'class' && empty($classId)) {
            return redirect()->back()->with('error', 'Select a class to export.');
        }

        $query = $this->queryFor(Token::class)
            ->with(['student', 'academicClass.section', 'academicYear', 'academicTerm'])
            ->latest();

        if (!empty($yearId)) {
            $query->where('academic_year_id', $yearId);
        }
        if (!empty($termId)) {
            $query->where('academic_term_id', $termId);
        }

        if ($scope === 'student') {
            $query->where('student_id', $studentId);
        } elseif ($scope === 'class') {
            $query->where('academic_class_id', $classId);
        }

        $tokens = $query->get();
        if ($tokens->isEmpty()) {
            return redirect()->back()->with('error', 'No tokens found for this export.');
        }

        $meta = [
            'scope' => $scope,
            'generated_at' => now()->toIso8601String(),
        ];

        $fileName = 'Tokens_' . strtoupper($scope) . '_' . now()->format('Y-m-d_His');

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('illimi-gradebook::pdf.tokens', [
            'tokens' => $tokens,
            'meta' => $meta,
        ]);

        return $pdf->download($fileName . '.pdf');
    }
}
