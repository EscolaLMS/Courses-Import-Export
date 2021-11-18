<?php

namespace EscolaLms\CoursesImportExport\Policies;

use EscolaLms\Core\Models\User;
use EscolaLms\CoursesImportExport\Models\Course;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class CoursesExportPolicy
{
    use HandlesAuthorization;

    /**
     * @return bool
     */
    public function export(User $user, Course $course)
    {
        if ($user->hasRole('admin')) {
            return true;
        }
        if ($user->can('export course') && $course->author_id === $user->id) {
            return true;
        }
        if ($user->can('export course') && $course->author_id !== $user->id) {
            return Response::deny('You do not own this course.');
        }

        return false;
    }
}
