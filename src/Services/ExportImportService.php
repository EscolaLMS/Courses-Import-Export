<?php

namespace EscolaLms\CoursesImportExport\Services;

use EscolaLms\Courses\Repositories\Contracts\CourseRepositoryContract;
use EscolaLms\CoursesImportExport\Http\Resources\CourseExportResource;
use EscolaLms\CoursesImportExport\Models\Course;
use EscolaLms\CoursesImportExport\Services\Contracts\ExportImportServiceContract;
use Illuminate\Support\Facades\Storage;
use ZanySoft\Zip\Zip;

class ExportImportService implements ExportImportServiceContract
{
    private CourseRepositoryContract $courseRepository;

    public function __construct(
        CourseRepositoryContract $courseRepository
    ) {
        $this->courseRepository = $courseRepository;
    }

    private function fixAllPathsBeforeZipping(int $courseId): void
    {
        $course = Course::findOrFail($courseId);
        $course->fixAssetPaths();
    }

    private function createExportJson(Course $course, $dirName): void
    {
        $program = CourseExportResource::make($course);

        $json = json_encode($program);

        Storage::put($dirName.'/content/content.json', $json);
    }

    private function copyCourseFilesToExportFolder(int $courseId): string
    {
        $dirName = 'exports/courses/'.$courseId;

        if (Storage::exists($dirName)) {
            Storage::deleteDirectory($dirName);
        }

        Storage::makeDirectory($dirName);

        $dirFrom = 'courses/'.$courseId;
        $dirTo = 'exports/courses/'.$courseId.'/content';
        $fromFiles = Storage::allFiles($dirFrom);

        foreach ($fromFiles as $fromFile) {
            $toFile = str_replace($dirFrom, $dirTo, $fromFile);
            Storage::copy($fromFile, $toFile);
        }

        return $dirName;
    }

    private function createZipFromFolder($dirName): string
    {
        $filename = uniqid(rand(), true).'.zip';

        $dirPath = Storage::path($dirName);
        $zip = Zip::create($dirPath.'/'.$filename);
        $zip->add($dirPath.'/content', true);
        $zip->close();

        Storage::deleteDirectory($dirName.'/content');

        return Storage::url($dirName.'/'.$filename);
    }

    public function export($courseId): string
    {
        $this->fixAllPathsBeforeZipping($courseId);
        $dirName = $this->copyCourseFilesToExportFolder($courseId);

        // $course = $this->courseRepository->findWith($courseId, ['*'], ['lessons.topics.topicable', 'scorm.scos']);

        $course = Course::with(['lessons.topics.topicable', 'scorm.scos'])->findOrFail($courseId);
        $this->createExportJson($course, $dirName);

        $zipUrl = $this->createZipFromFolder($dirName);

        return $zipUrl;
    }

    public function import($courseId): Course
    {
        return new Course();
    }
}
