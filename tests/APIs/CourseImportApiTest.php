<?php

namespace Tests\APIs;

use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\CoursesImportExport\Models\Course;
use EscolaLms\CoursesImportExport\Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use ZanySoft\Zip\Zip;

class CourseImportApiTest extends TestCase
{
    use CreatesUsers;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('filesystems.default'));
    }

    public function testUnauthorizedAccessToImportCourseFromZip(): void
    {
        $response = $this->postJson('/api/admin/courses/zip/import');
        $response->assertUnauthorized();
    }

    public function testAccessToImportCourseFromZip(): void
    {
        $dirPath = 'test/import/courses/';
        $courseData = Course::factory()->make()->toJson();
        Storage::put($dirPath . 'content.json', $courseData);
        $zip = Zip::create(Storage::path($dirPath . 'course-import.zip'));
        $zip->add(Storage::path($dirPath . 'content.json'), true);
        $zip->close();

        $admin = $this->makeAdmin();
        $zipFile = [
            'file' => new UploadedFile(Storage::path($dirPath . 'course-import.zip'),
                'course-import.zip', null, null, true)
        ];

        $response = $this->actingAs($admin, 'api')->postJson('/api/admin/courses/zip/import', $zipFile);
        $response->assertCreated();

        $tutor = $this->makeInstructor();
        $response = $this->actingAs($tutor, 'api')->postJson('/api/admin/courses/zip/import', $zipFile);
        $response->assertForbidden();

        $tutor->givePermissionTo('import course');

        $response = $this->actingAs($tutor, 'api')->postJson('/api/admin/courses/zip/import', $zipFile);
        $response->assertCreated();
    }
}
