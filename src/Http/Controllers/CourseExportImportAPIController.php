<?php

namespace EscolaLms\CoursesImportExport\Http\Controllers;

use EscolaLms\Core\Http\Controllers\EscolaLmsBaseController;
use EscolaLms\CoursesImportExport\Http\Controllers\Swagger\CourseExportImportAPISwagger;
use EscolaLms\CoursesImportExport\Http\Requests\GetCourseExportAPIRequest;
use EscolaLms\CoursesImportExport\Services\Contracts\ExportImportServiceContract;
use Illuminate\Http\JsonResponse;

/**
 * SWAGGER_VERSION
 * This class should be parent class for other API controllers
 * Class AppBaseController.
 */
class CourseExportImportAPIController extends EscolaLmsBaseController implements CourseExportImportAPISwagger
{
    protected ExportImportServiceContract $exportImportService;

    public function __construct(
        ExportImportServiceContract $exportImportService
    ) {
        $this->exportImportService = $exportImportService;
    }

    public function export(int $course_id, GetCourseExportAPIRequest $request): JsonResponse
    {
        $export = $this->exportImportService->export($course_id);

        return $this->sendResponse($export, __('Export created'));
    }
}
