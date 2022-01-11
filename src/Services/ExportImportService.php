<?php

namespace EscolaLms\CoursesImportExport\Services;

use EscolaLms\Courses\Http\Requests\CreateTopicAPIRequest;
use EscolaLms\Courses\Models\Lesson;
use EscolaLms\Courses\Models\Topic;
use EscolaLms\Courses\Repositories\Contracts\CourseRepositoryContract;
use EscolaLms\Courses\Repositories\Contracts\LessonRepositoryContract;
use EscolaLms\Courses\Repositories\Contracts\TopicRepositoryContract;
use EscolaLms\Courses\Repositories\Contracts\TopicResourceRepositoryContract;
use EscolaLms\CoursesImportExport\Http\Resources\CourseExportResource;
use EscolaLms\CoursesImportExport\Models\Course;
use EscolaLms\CoursesImportExport\Services\Contracts\ExportImportServiceContract;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use ZanySoft\Zip\Zip;

class ExportImportService implements ExportImportServiceContract
{
    private CourseRepositoryContract $courseRepository;
    private LessonRepositoryContract $lessonRepository;
    private TopicRepositoryContract $topicRepository;
    private TopicResourceRepositoryContract $topicResourceRepo;

    public function __construct(
        CourseRepositoryContract $courseRepository,
        LessonRepositoryContract $lessonRepository,
        TopicRepositoryContract $topicRepository,
        TopicResourceRepositoryContract $topicResourceRepository
    ) {
        $this->courseRepository = $courseRepository;
        $this->lessonRepository = $lessonRepository;
        $this->topicRepository = $topicRepository;
        $this->topicResourceRepo = $topicResourceRepository;
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

        $course = Course::with(['lessons.topics.topicable', 'scormSco'])->findOrFail($courseId);
        $this->createExportJson($course, $dirName);

        $zipUrl = $this->createZipFromFolder($dirName);

        return $zipUrl;
    }

    public function import(UploadedFile $zipFile): Model
    {
        $dirPath = 'imports' . DIRECTORY_SEPARATOR . 'courses' . DIRECTORY_SEPARATOR . uniqid(rand(), true);
        $dirFullPath = $this->extractZipFile($zipFile, $dirPath);

        try {
            $content = json_decode(File::get($dirFullPath . DIRECTORY_SEPARATOR . 'content.json'), true);
            $course = DB::transaction(function () use ($content, $dirFullPath) {
                return $this->createCourseFromImport($content, $dirFullPath);
            });
        } catch (Exception $e) {
            Log::error('[' . self::class . '] ' . $e->getMessage());
            throw new Exception(__('Invalid data'));
        } finally {
            Storage::deleteDirectory($dirPath);
        }

        return $course;
    }

    private function extractZipFile(UploadedFile $zipFile, string $dirPath): string
    {
        if (Storage::exists($dirPath)) {
            Storage::deleteDirectory($dirPath);
        }

        $dirFullPath = Storage::path($dirPath);
        Zip::open($zipFile)->extract($dirFullPath);

        return $dirFullPath;
    }

    private function createCourseFromImport(array $courseData, string $dirFullPath): Model
    {
        $courseData = $this->addFilesToArrayBasedOnPath($courseData, $dirFullPath);
        $courseValidator = Validator::make($courseData, Course::$rules);
        $course = $this->courseRepository->create($courseValidator->validate());

        if (isset($courseData['lessons']) && is_array($courseData['lessons'])) {
            foreach ($courseData['lessons'] as $lesson) {
                $lesson['course_id'] = $course->getKey();
                $this->createLessonFromImport($lesson, $dirFullPath);
            }
        }

        return $course;
    }

    private function createLessonFromImport(array $lessonData, string $dirFullPath): Model
    {
        $lessonValidator = Validator::make($lessonData, Lesson::$rules);
        $lesson = $this->lessonRepository->create($lessonValidator->validate());

        if (isset($lessonData['topics']) && is_array($lessonData['topics'])) {
            foreach ($lessonData['topics'] as $topic) {
                $topic['lesson_id'] = $lesson->getKey();
                $this->createTopicFromImport(array_filter($topic), $dirFullPath);
            }
        }

        return $lesson;
    }

    private function createTopicFromImport(array $topicData, string $dirFullPath): Model
    {
        $topicData = array_merge($topicData, $topicData['topicable'] ?? []);
        unset($topicData['topicable']);

        $request = new CreateTopicAPIRequest($topicData);
        $request->setValidator(Validator::make($topicData, $request->rules()));

        foreach (['value', 'poster'] as $key) {
            if (isset($topicData[$key]) && File::exists($dirFullPath . DIRECTORY_SEPARATOR . $topicData[$key])) {
                $request->files->add([
                    $key => new UploadedFile(
                        $dirFullPath . DIRECTORY_SEPARATOR . $topicData[$key],
                        $topicData[$key],
                        null,
                        null,
                        true
                    )
                ]);
            }
        }

        $topic = $this->topicRepository->createFromRequest($request);

        if (isset($topicData['resources']) && is_array($topicData['resources'])) {
            $this->createTopicResources($topicData['resources'], $topic, $dirFullPath);
        }

        return $topic;
    }

    private function createTopicResources(array $resources, Topic $topic, string $dirFullPath): array
    {
        $createdResources = [];
        foreach ($resources as $resource) {
            if (isset($resource['path'])
                && isset($resource['name'])
                && File::exists($dirFullPath . DIRECTORY_SEPARATOR . $resource['path'])
            ) {
                $createdResources[] = $this->topicResourceRepo->storeUploadedResourceForTopic(
                    $topic,
                    new UploadedFile($dirFullPath . DIRECTORY_SEPARATOR . $resource['path'], $resource['name'])
                );
            }
        }

        return $createdResources;
    }

    private function addFilesToArrayBasedOnPath(array $data, string $dirFullPath): array
    {
        foreach ($data as $key => $value) {
            if (Str::endsWith($key, '_path') && File::exists($dirFullPath . DIRECTORY_SEPARATOR . $value)) {
                $fileKey = Str::before($key, '_path');
                $data[$fileKey] = new UploadedFile(
                    $dirFullPath . DIRECTORY_SEPARATOR . $value,
                    $value,
                    null,
                    null,
                    true
                );
                unset($data[$key]);
            }
        }

        return $data;
    }
}
