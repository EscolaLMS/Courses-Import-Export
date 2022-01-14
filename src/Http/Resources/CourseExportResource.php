<?php

namespace EscolaLms\CoursesImportExport\Http\Resources;

use EscolaLms\Auth\Traits\ResourceExtandable;
use EscolaLms\Courses\Models\Course;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseExportResource extends JsonResource
{
    use ResourceExtandable;

    public static function sanitizePath(string $path = null): string
    {
        return isset($path) ? preg_replace('/courses\/[0-9]+\//', '', $path) : "";
    }

    public function __construct(Course $resource)
    {
        parent::__construct($resource);
    }

    public function getResource(): Course
    {
        return $this->resource;
    }

    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     */
    public function toArray($request): array
    {
        $this->withoutWrapping();

        $course = $this->getResource();

        $fields = [
            'title' => $course->title,
            'summary' => $course->summary,
            'image_path' => self::sanitizePath($course->image_path),
            'video_path' => self::sanitizePath($course->video_path),
            'base_price' => $course->base_price,
            'duration' => $course->duration,
            // 'author_id' => $course->author_id,
            'scorm_sco' => $this->when($course->scorm_sco_id !== null, fn () => CourseScormScoExportResource::make($course)),
            'active' => $course->active,
            'subtitle' => $course->subtitle,
            'language' => $course->language,
            'description' => $course->description,
            'level' => $course->level,
            'lessons' => LessonExportResource::collection($course->lessons->sortBy('order')),
            'poster_path' => self::sanitizePath($course->poster_path),
        ];

        return self::apply($fields, $this);
    }
}
