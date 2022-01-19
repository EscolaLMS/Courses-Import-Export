<?php

namespace Tests\APIs;

use EscolaLms\Categories\Models\Category;
use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\Courses\Models\Lesson;
use EscolaLms\Courses\Models\Topic;
use EscolaLms\Courses\Models\TopicResource;
use EscolaLms\CoursesImportExport\Database\Seeders\CoursesExportImportPermissionSeeder;
use EscolaLms\CoursesImportExport\Enums\CoursesImportExportPermissionsEnum;
use EscolaLms\CoursesImportExport\Http\Resources\CourseExportResource;
use EscolaLms\CoursesImportExport\Models\Course;
use EscolaLms\CoursesImportExport\Tests\TestCase;
use EscolaLms\Tags\Models\Tag;
use EscolaLms\TopicTypes\Models\Contracts\TopicFileContentContract;
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

        $video = new UploadedFile(realpath(
            __DIR__ . '/../mocks/1.mp4'), '1.mp4', 'video/mp4', null, true
        );
        $video->storeAs($this->dirPath, 'dummy.mp4');

        $pdf = new UploadedFile(realpath(
            __DIR__ . '/../mocks/1.pdf'), '1.pdf', 'application/pdf', null, true
        );
        $pdf->storeAs($this->dirPath, 'dummy.pdf');

        $pdf2 = new UploadedFile(realpath(
            __DIR__ . '/../mocks/1.pdf'), '1.pdf', 'application/pdf', null, true
        );
        $pdf2->storeAs($this->dirPath . 'resources', 'dummy2.pdf');

        $mp3= new UploadedFile(realpath(
            __DIR__ . '/../mocks/1.mp3'), '1.mp3', 'audio/mp3', null, true
        );
        $mp3->storeAs($this->dirPath, 'dummy.mp3');

        UploadedFile::fake()->image('course_image.jpg')->storeAs($this->dirPath, 'course_image.jpg');
        UploadedFile::fake()->image('course_poster.png')->storeAs($this->dirPath, 'course_poster.png');
        UploadedFile::fake()->image('dummy.jpg')->storeAs($this->dirPath, 'topic/2/dummy.jpg');
        UploadedFile::fake()->image('dummy.png')->storeAs($this->dirPath, 'topic/3/dummy.png');
        UploadedFile::fake()->image('dummy.jpg')->storeAs($this->dirPath, 'topic/4/dummy.jpg');
        UploadedFile::fake()->image('dummy.jpg')->storeAs($this->dirPath, 'topic/4/resources/dummy.jpg');
        UploadedFile::fake()->image('dummy.jpg')->storeAs($this->dirPath, 'topic/4/resources/dummy2.jpg');
        UploadedFile::fake()->image('icon.png')->storeAs($this->dirPath, 'categories/icon.png');

        $course = Course::factory()
            ->has(
                Category::factory()
                    ->count(1)
                    ->state(fn () => ['parent_id' => null])
            )
            ->has(Tag::factory()->count(2))
            ->create([
            'author_id' => $this->user->getKey(),
        ]);

        $course->update([
            'image_path' => 'course_image.jpg',
            'video_path' => 'dummy.mp4',
            'poster_path' => 'course_poster.png',
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
            'value' => 'dummy.mp3',
        ]);
        $topicable_image = Image::factory()->create([
            'value' => 'topic/2/dummy.jpg',
        ]);
        $topicable_pdf = PDF::factory()->create([
            'value' => 'dummy.pdf',
        ]);
        $topicable_video = Video::factory()->create([
            'value' => 'dummy.mp4',
            'poster' => 'topic/4/dummy.jpg',
        ]);
        $topicable_richtexts = RichText::factory()->create();

        $topic_audio->topicable()->associate($topicable_audio)->save();
        $topic_image->topicable()->associate($topicable_image)->save();
        $topic_pdf->topicable()->associate($topicable_pdf)->save();
        $topic_video->topicable()->associate($topicable_video)->save();
        $topic_richtexts->topicable()->associate($topicable_richtexts)->save();

        TopicResource::factory()->create([
            'topic_id' => $topic_audio->getKey(),
            'path' => 'resources/dummy2.pdf',
            'name' => 'dummy2.pdf',
        ]);

        TopicResource::factory()->create([
            'topic_id' => $topic_video->getKey(),
            'path' => 'topic/4/resources/dummy.jpg',
            'name' => 'dummy.jpg',
        ]);

        TopicResource::factory()->create([
            'topic_id' => $topic_video->getKey(),
            'path' => 'topic/4/resources/dummy2.jpg',
            'name' => 'dummy2.jpg',
        ]);

        $this->course = $course;
        $courseResource = CourseExportResource::make($course);
        $content = json_decode($courseResource->toJson(), true);
        $content['categories'][] = [
            'name' => $this->faker->name,
            'slug' => $this->faker->slug . $this->faker->numberBetween(),
            'is_active' => false,
            'parent' => null,
            'icon' => 'categories/icon.png',
            'icon_class' => null
        ];
        $this->content = json_encode($content);

        Storage::put($this->dirPath . 'content.json', $this->content);
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
        $tutor = $this->makeInstructor();
        $response = $this->actingAs($tutor, 'api')->postJson('/api/admin/courses/zip/import', [
            'file' => new UploadedFile(Storage::path($this->dirPath . 'course-import.zip'),
                'course-import.zip', null, null, true)
        ]);

        $response->assertForbidden();

        $zipFile = [
            'file' => new UploadedFile(Storage::path($this->dirPath . 'course-import.zip'),
                'course-import.zip', null, null, true)
        ];

        $tutor->givePermissionTo(CoursesImportExportPermissionsEnum::COURSES_IMPORT);

        $response = $this->actingAs($tutor, 'api')->postJson('/api/admin/courses/zip/import', $zipFile);
        $response->assertCreated();
    }

    public function testImportCourseFromZip(): void
    {
        $admin = $this->makeAdmin();
        $this->response = $this->actingAs($admin, 'api')->postJson('/api/admin/courses/zip/import', [
            'file' => new UploadedFile(Storage::path($this->dirPath . 'course-import.zip'),
                'course-import.zip', null, null, true)
        ]);
        $this->response->assertCreated();

        $this->response->assertJsonFragment([
           'title' => $this->course->title,
        ]);

        $responseData = $this->response->getData()->data;
        Storage::assertExists($responseData->image_path);
        Storage::assertExists($responseData->video_path);
        Storage::assertExists($responseData->poster_path);

        $lesson = Lesson::where('course_id', $responseData->id)->first();
        $topics = $lesson->topics;

        foreach ($topics as $topic) {
            $this->assertNotEmpty($topic->topicable->value);
            if ($topic->topicable instanceof TopicFileContentContract) {
                Storage::assertExists($topic->topicable->value);
            }

            foreach ($topic->resources as $resource) {
                Storage::assertExists($resource->path);
            }
        }

        $this->assertCount(2, $responseData->categories);
        $this->assertCount(2, $responseData->tags);

        $categories = Course::find($responseData->id)->categories;
        foreach ($categories as $category) {
            Storage::assertExists($category->icon);
        }
    }

    public function testErrorImportCourseFromZip(): void
    {
        Storage::put('invalid/invalid-content.json', 'Some dummy data');
        $zip = Zip::create(Storage::path('invalid/course-import.zip'));
        $zip->add(Storage::path('invalid/'), true);
        $zip->close();

        $admin = $this->makeAdmin();
        $response = $this->actingAs($admin, 'api')->postJson('/api/admin/courses/zip/import', [
            'file' => new UploadedFile(Storage::path('invalid/course-import.zip'),
                'course-import.zip', null, null, true)
        ]);

        $response->assertStatus(400);
    }

    public function testImportCourseWithScormScoAndH5P(): void
    {
        $admin = $this->makeAdmin();

        $courseZip = new UploadedFile(realpath(
            __DIR__ . '/../mocks/course_sco.zip'), 'course.zip', null, null, true
        );

        $response = $this->actingAs($admin, 'api')->postJson('/api/admin/courses/zip/import', [
            'file' => $courseZip
        ])->assertCreated();

        $data = $response->getData()->data;
        $lesson = $data->lessons[0];
        $topic = $lesson->topics[0];

        $this->assertDatabaseHas('courses', [
            'id' => $data->id,
            'title' => $data->title,
            'author_id' => $admin->getKey(),
            'scorm_sco_id' => $data->scorm_sco_id
        ]);
        $this->assertDatabaseHas('lessons', [
            'id' => $lesson->id,
            'title' => $lesson->title,
            'course_id' => $data->id,
        ]);
        $this->assertDatabaseHas('topics', [
            'id' => $topic->id,
            'title' => $topic->title,
            'lesson_id' => $topic->lesson_id,
        ]);

        $this->assertEquals(3, count($data->lessons));
        $this->assertEquals(7, count($data->lessons[0]->topics));
    }
}
