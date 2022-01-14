<?php

namespace EscolaLms\CoursesImportExport\Models;

use EscolaLms\Courses\Models\Course as BaseCourse;
use EscolaLms\CoursesImportExport\Enums\CoursesImportExportEnum;
use EscolaLms\Scorm\Services\Contracts\ScormServiceContract;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class Course extends BaseCourse
{
    private function fixPath($key): array
    {
        $value = $this->$key;
        $destination = sprintf('courses/%d/%s', $this->id, basename($value));
        if (strpos($value, $destination) === false && Storage::exists($value)) {
            $result = [$value, $destination];
            if (!Storage::exists($destination)) {
                Storage::move($value, $destination);
            }

            $this->$key = $destination;
            $this->save();

            return $result;
        }

        return [];
    }

    public function fixScormAssetPath(): array
    {
        if (!$this->scormSco) {
            return [];
        }

        $scorm = $this->scormSco->scorm;
        $destination = sprintf('courses/%d/%s', $this->id, basename(CoursesImportExportEnum::SCORM_FILE));

        $scormService = app(ScormServiceContract::class);
        $scormZipPath = $scormService->zipScorm($scorm->getKey());

        if (!Storage::exists($destination)) {
            Storage::move($scormZipPath, $destination);
        }

        return $destination ? [$destination] : [];
    }

    public function fixAssetPaths(): array
    {
        $results = [];
        $results = $results + $this->fixPath('image_path');
        $results = $results + $this->fixPath('video_path');
        $results = $results + $this->fixPath('poster_path');
        $results = $results + $this->fixScormAssetPath();

        foreach ($this->lessons as $lesson) {
            foreach ($lesson->topics as $topic) {
                $topicable = $topic->topicable;
                if (isset($topicable)) {
                    foreach ($topic->topicable->fixAssetPaths() as $fix) {
                        $results = $results + $fix;
                    }
                }

                foreach ($topic->resources as $resource) {
                    $results = $results + $resource->fixAssetPaths();
                }
            }
        }

        return $results;
    }
}
