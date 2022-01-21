<?php

namespace EscolaLms\CoursesImportExport\Enums;

use EscolaLms\Core\Enums\BasicEnum;

class CoursesImportExportPermissionsEnum extends BasicEnum
{
    public const COURSES_IMPORT = 'courses-import-export_import';
    public const COURSES_EXPORT = 'courses-import-export_export';
    public const COURSES_EXPORT_OWNED = 'courses-import-export_export_authored';
}
