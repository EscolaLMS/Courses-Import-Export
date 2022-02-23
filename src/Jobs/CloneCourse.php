<?php

namespace EscolaLms\CoursesImportExport\Jobs;

use EscolaLms\Courses\Models\Course;
use EscolaLms\CoursesImportExport\Events\CloneCourseFailedEvent;
use EscolaLms\CoursesImportExport\Events\CloneCourseFinishedEvent;
use EscolaLms\CoursesImportExport\Services\Contracts\ExportImportServiceContract;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\UploadedFile;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CloneCourse implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private Course $course;

    /**
     * @param int $courseId
     */
    public function __construct(Course $course)
    {
        $this->course = $course;
    }

    public function handle(ExportImportServiceContract $exportImportService): bool
    {
        try {
            $fileDir = $exportImportService->export($this->course->getKey(), false);
            $file = $this->createFileToExport($fileDir);
            $course = $exportImportService->import($file);

            CloneCourseFinishedEvent::dispatch(auth()->user(), $course);

            return true;

        } catch (Exception $exception) {
            CloneCourseFailedEvent::dispatch(auth()->user(), $this->course);
        }

        return false;
    }

    private function createFileToExport(string $fileDir): UploadedFile
    {
        return new UploadedFile(
            $fileDir,
            'export.zip',
            null,
            null,
            true
        );
    }
}
