<?php

namespace Illimi\Gradebook\Managers;

class GradebookModuleManager
{
    public function sideMenu(): array
    {
        return [
            [
                'label' => 'Gradebook',
                'icon' => 'ri-file-chart-line',
                'route' => 'javascript:void(0)',
                'roles' => ['admin'],
                'children' => [
                    ['label' => 'Dashboard', 'route' => 'gradebook.index'],
                    ['label' => 'Assessments', 'route' => 'gradebook.assessments.index'],
                    // ['label' => 'Templates', 'route' => 'gradebook.templates.index'],
                    ['label' => 'Reports', 'route' => 'gradebook.reports.index'],
                    ['label' => 'Tokens', 'route' => 'gradebook.tokens.index'],
                ],
            ],
            [
                'label' => 'Assessments',
                'route' => 'gradebook.assessments.index',
                'icon' => 'ri-file-chart-line',
                'roles' => ['teacher'],
            ],
        ];
    }
}
