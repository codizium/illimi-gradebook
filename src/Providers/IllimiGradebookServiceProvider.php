<?php

namespace Illimi\Gradebook\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illimi\Gradebook\IllimiGradebook;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

class IllimiGradebookServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../config/gradebook.php',
            'gradebook'
        );

        $this->app->singleton('illimi-gradebook', function () {
            return new IllimiGradebook();
        });
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../../resources/lang', 'illimi-gradebook');
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', config('gradebook.view_namespace', 'illimi-gradebook'));

        Route::middleware('web')
            ->group(function () {
                $this->loadRoutesFrom(__DIR__.'/../../routes/web.php');
            });

        Route::middleware($this->apiRouteMiddleware())
            ->group(function () {
                $this->loadRoutesFrom(__DIR__.'/../../routes/api.php');
            });

        $this->publishes([
            __DIR__.'/../../config/gradebook.php' => config_path('gradebook.php'),
        ], 'illimi-gradebook-config');

        $this->publishes([
            __DIR__.'/../../resources/views' => resource_path('views/vendor/illimi-gradebook'),
        ], 'illimi-gradebook-views');

        $this->registerMenu();
    }

    protected function registerMenu()
    {
        if (class_exists(\Illimi\IllimiCore\Facades\IllimiCore::class)) {
            $nav = \Illimi\IllimiCore\Facades\IllimiCore::navigation();

            // Admin Gradebook
            $nav->register('gradebook', [
                'label' => 'Gradebook',
                'icon' => 'ri-file-chart-line',
                'category' => 'academics',
                'priority' => 40,
                'roles' => ['admin', 'super-admin'],
                'feature' => 'results_management',
                'children' => [
                    ['label' => 'Dashboard', 'route' => 'gradebook.index'],
                    ['label' => 'Assessments', 'route' => 'gradebook.assessments.index'],
                    // ['label' => 'Templates', 'route' => 'gradebook.templates.index'],
                    ['label' => 'Reports', 'route' => 'gradebook.reports.index'],
                    ['label' => 'Tokens', 'route' => 'gradebook.tokens.index'],
                ],
            ]);

            // Teacher Gradebook Shortcut
            $nav->register('teacher-gradebook', [
                'label' => 'Assessments',
                'icon' => 'ri-file-chart-line',
                'category' => 'academics',
                'priority' => 41,
                'roles' => ['teacher'],
                'route' => 'gradebook.assessments.index',
            ]);
        }
    }

    protected function apiRouteMiddleware(): array
    {
        $middleware = ['api'];

        if (class_exists(EnsureFrontendRequestsAreStateful::class)) {
            $middleware[] = EnsureFrontendRequestsAreStateful::class;
        }

        return $middleware;
    }
}
