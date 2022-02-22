<?php

namespace EscolaLms\CoursesImportExport\Jobs;

use EscolaLms\CoursesImportExport\Services\Contracts\ExportImportServiceContract;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\UploadedFile;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CloneCourse implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private int $courseId;

    /**
     * @param int $courseId
     */
    public function __construct(int $courseId)
    {
        $this->courseId = $courseId;
    }

    public function handle(ExportImportServiceContract $exportImportService)
    {
        $fileDir = $exportImportService->export($this->courseId, false);
        $file = $this->createFileToExport($fileDir);
        $exportImportService->import($file);
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
