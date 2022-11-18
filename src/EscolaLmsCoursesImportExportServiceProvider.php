<?php

namespace EscolaLms\CoursesImportExport;

use EscolaLms\CoursesImportExport\Services\CloneCourseService;
use EscolaLms\CoursesImportExport\Services\Contracts\CloneCourseServiceContract;
use EscolaLms\CoursesImportExport\Services\Contracts\ExportImportServiceContract;
use EscolaLms\CoursesImportExport\Services\ExportImportService;
use Illuminate\Support\ServiceProvider;

class EscolaLmsCoursesImportExportServiceProvider extends ServiceProvider
{
    public $singletons = [
        ExportImportServiceContract::class => ExportImportService::class,
        CloneCourseServiceContract::class => CloneCourseService::class,
    ];

    public function boot()
    {
        $this->loadRoutesFrom(__DIR__.'/routes.php');
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'course-import-export');
    }

    public function register()
    {
        $this->app->register(AuthServiceProvider::class);
    }
}
