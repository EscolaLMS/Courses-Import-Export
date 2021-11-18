<?php

use EscolaLms\CoursesImportExport\Http\Controllers\CourseExportImportAPIController;
use Illuminate\Support\Facades\Route;

// admin endpoints
Route::group(['middleware' => ['auth:api'], 'prefix' => 'api/admin'], function () {
    Route::get('courses/{course}/export', [CourseExportImportAPIController::class, 'export']);
});
