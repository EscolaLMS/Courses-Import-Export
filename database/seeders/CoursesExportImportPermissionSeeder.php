<?php

namespace EscolaLms\CoursesImportExport\Database\Seeders;

use EscolaLms\Core\Enums\UserRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class CoursesExportImportPermissionSeeder extends Seeder
{
    public function run()
    {
        // create permissions
        $admin = Role::findOrCreate(UserRole::ADMIN, 'api');

        Permission::findOrCreate('export course', 'api');
        Permission::findOrCreate('import course', 'api');

        $admin->givePermissionTo(['export course', 'import course']);
    }
}
