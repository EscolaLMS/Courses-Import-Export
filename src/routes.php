<?php

use EscolaLms\CoursesImportExport\Http\Controllers\CourseExportImportAPIController;
use EscolaLms\Core\Http\Facades\Route;

// admin endpoints
Route::group(['middleware' => Route::apply(['auth:api']), 'prefix' => 'api/admin'], function () {
    Route::get('courses/{course}/clone', [CourseExportImportAPIController::class, 'clone']);
    Route::get('courses/{course}/export', [CourseExportImportAPIController::class, 'export']);
    Route::post('courses/zip/import', [CourseExportImportAPIController::class, 'import']);
});
