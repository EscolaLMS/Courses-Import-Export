<?php

namespace EscolaLms\CoursesImportExport\Enums;

use EscolaLms\Core\Enums\BasicEnum;

class CoursesImportExportPermissionsEnum extends BasicEnum
{
    const COURSES_IMPORT = 'courses-import-export_import';
    const COURSES_EXPORT = 'courses-import-export_export';
    const COURSES_EXPORT_OWNED = 'courses-import-export_export-owned';
}
