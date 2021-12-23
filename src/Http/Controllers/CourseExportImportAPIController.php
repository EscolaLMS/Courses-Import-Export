<?php

namespace EscolaLms\CoursesImportExport\Http\Controllers;

use EscolaLms\Core\Http\Controllers\EscolaLmsBaseController;
use EscolaLms\Courses\Http\Resources\CourseSimpleResource;
use EscolaLms\CoursesImportExport\Http\Controllers\Swagger\CourseExportImportAPISwagger;
use EscolaLms\CoursesImportExport\Http\Requests\CourseImportAPIRequest;
use EscolaLms\CoursesImportExport\Http\Requests\GetCourseExportAPIRequest;
use EscolaLms\CoursesImportExport\Services\Contracts\ExportImportServiceContract;
use Exception;
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

    public function import(CourseImportAPIRequest $request): JsonResponse
    {
        try {
            $course = $this->exportImportService->import($request->file('file'));

            return $this->sendResponseForResource(CourseSimpleResource::make($course), __('Course imported successfully'));
        }  catch (Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
    }
}
