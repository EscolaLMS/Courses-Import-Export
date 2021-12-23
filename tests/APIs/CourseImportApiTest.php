<?php

namespace Tests\APIs;

use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\Courses\Models\Lesson;
use EscolaLms\Courses\Models\Topic;
use EscolaLms\Courses\Models\TopicResource;
use EscolaLms\CoursesImportExport\Database\Seeders\CoursesExportImportPermissionSeeder;
use EscolaLms\CoursesImportExport\Http\Resources\CourseExportResource;
use EscolaLms\CoursesImportExport\Models\Course;
use EscolaLms\CoursesImportExport\Tests\TestCase;
use EscolaLms\TopicTypes\Models\TopicContent\Audio;
use EscolaLms\TopicTypes\Models\TopicContent\Image;
use EscolaLms\TopicTypes\Models\TopicContent\PDF;
use EscolaLms\TopicTypes\Models\TopicContent\RichText;
use EscolaLms\TopicTypes\Models\TopicContent\Video;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use ZanySoft\Zip\Zip;

class CourseImportApiTest extends TestCase
{
    use CreatesUsers;
    use DatabaseTransactions;
    use WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('filesystems.default'));
        $this->seed(CoursesExportImportPermissionSeeder::class);
        $this->user = config('auth.providers.users.model')::factory()->create();
        $this->user->guard_name = 'api';
        $this->user->assignRole('admin');
        $this->dirPath = 'test/import/courses/';

        UploadedFile::fake()->image('course_image.jpg')->storeAs($this->dirPath, 'course_image.jpg');
        UploadedFile::fake()->image('course_poster.jpg')->storeAs($this->dirPath, 'course_poster.jpg');
        UploadedFile::fake()->create('course_video.mp4s')->storeAs($this->dirPath, 'course_video.mp4s');

        UploadedFile::fake()->create('dummy.mp3')->storeAs($this->dirPath, 'topic/1/dummy.mp3');
        UploadedFile::fake()->create('dummy1.pdf')->storeAs($this->dirPath, 'topic/1/resources/dummy1.pdf');
        UploadedFile::fake()->create('dummy2.pdf')->storeAs($this->dirPath, 'topic/1/resources/dummy2.pdf');
        UploadedFile::fake()->image('dummy.jpg')->storeAs($this->dirPath, 'topic/2/dummy.jpg');
        UploadedFile::fake()->image('dummy.png')->storeAs($this->dirPath, 'topic/3/dummy.png');
        UploadedFile::fake()->create('dummy.pdf')->storeAs($this->dirPath, 'topic/4/dummy.pdf');
        UploadedFile::fake()->create('dummy.pdf')->storeAs($this->dirPath, 'topic/4/resources/dummy.pdf');
        UploadedFile::fake()->image('dummy.jpg')->storeAs($this->dirPath, 'topic/4/dummy.jpg');

        $course = Course::factory()->create([
            'author_id' => $this->user->getKey(),
        ]);
        $course->update([
            'image_path' => 'course_image.jpg',
            'video_path' => 'course_video.mp4',
            'poster_path' => 'course_poster.jpg',
        ]);
        $firstLesson = Lesson::factory()->create([
            'course_id' => $course->getKey(),
        ]);
        $topic_audio = Topic::factory()->create([
            'lesson_id' => $firstLesson->getKey(),
        ]);
        $topic_image = Topic::factory()->create([
            'lesson_id' => $firstLesson->getKey(),
        ]);
        $topic_pdf = Topic::factory()->create([
            'lesson_id' => $firstLesson->getKey(),
        ]);
        $topic_video = Topic::factory()->create([
            'lesson_id' => $firstLesson->getKey(),
        ]);
        $topic_richtexts = Topic::factory()->create([
            'lesson_id' => $firstLesson->getKey(),
        ]);

        $topicable_audio = Audio::factory()->create([
            'value' => 'topic/1/dummy.mp3',
        ]);
        $topicable_image = Image::factory()->create([
            'value' => 'topic/2/dummy.jpg',
        ]);
        $topicable_pdf = PDF::factory()->create([
            'value' => 'topic/3/dummy.pdf',
        ]);
        $topicable_video = Video::factory()->create([
            'value' => 'topic/4/dummy.mp4',
            'poster' => 'topic/4/dummy.jpg',
        ]);
        $topicable_richtexts = RichText::factory()->create();

        $topic_audio->topicable()->associate($topicable_audio)->save();
        $topic_image->topicable()->associate($topicable_image)->save();
        $topic_pdf->topicable()->associate($topicable_pdf)->save();
        $topic_video->topicable()->associate($topicable_video)->save();
        $topic_richtexts->topicable()->associate($topicable_richtexts)->save();

        $topic_audio_resource_1 = TopicResource::factory()->create([
            'topic_id' => $topic_audio->getKey(),
            'path' => 'topic/1/resources',
            'name' => 'dummy1.pdf',
        ]);

        $topic_audio_resource_2 = TopicResource::factory()->create([
            'topic_id' => $topic_audio->getKey(),
            'path' => 'topic/1/resources',
            'name' => 'dummy2.pdf',
        ]);

        $topic_video_resource = TopicResource::factory()->create([
            'topic_id' => $topic_video->getKey(),
            'path' => 'topic/4/resources',
            'name' => 'dummy.pdf',
        ]);

        $this->course = $course;

        $courseResource = CourseExportResource::make($course);
        $content = json_encode($courseResource);

        Storage::put($this->dirPath . 'content.json', $content);
        $zip = Zip::create(Storage::path($this->dirPath . 'course-import.zip'));
        $zip->add(Storage::path($this->dirPath), true);
        $zip->close();
    }

    public function testUnauthorizedAccessToImportCourseFromZip(): void
    {
        $response = $this->postJson('/api/admin/courses/zip/import');
        $response->assertUnauthorized();
    }

    public function testAccessToImportCourseFromZip(): void
    {
        $admin = $this->makeAdmin();
        $zipFile = [
            'file' => new UploadedFile(Storage::path($this->dirPath . 'course-import.zip'),
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
