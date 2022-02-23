<?php

namespace EscolaLms\CoursesImportExport\Services;

use EscolaLms\Courses\Models\Course;
use EscolaLms\CoursesImportExport\Events\CloneCourseStartedEvent;
use EscolaLms\CoursesImportExport\Jobs\CloneCourse;
use EscolaLms\CoursesImportExport\Services\Contracts\CloneCourseServiceContract;
use Exception;

class CloneCourseService implements CloneCourseServiceContract
{
    /**
     * @throws Exception
     */
    public function clone(Course $course): void
    {
        CloneCourseStartedEvent::dispatch(auth()->user(), $course);
        CloneCourse::dispatch($course);
    }
}
