<?php

namespace EscolaLms\CoursesImportExport\Services;

use EscolaLms\CoursesImportExport\Jobs\CloneCourse;
use EscolaLms\CoursesImportExport\Services\Contracts\CloneCourseServiceContract;
use Exception;

class CloneCourseService implements CloneCourseServiceContract
{
    /**
     * @throws Exception
     */
    public function clone(int $id)
    {
        CloneCourse::dispatch($id)->onQueue('course_clone');
    }
}
