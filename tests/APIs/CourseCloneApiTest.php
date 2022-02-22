<?php

namespace EscolaLms\CoursesImportExport\Tests\APIs;

use EscolaLms\Categories\Models\Category;
use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\Courses\Models\Course;
use EscolaLms\Courses\Models\Lesson;
use EscolaLms\Courses\Models\Topic;
use EscolaLms\CoursesImportExport\Database\Seeders\CoursesExportImportPermissionSeeder;
use EscolaLms\CoursesImportExport\Jobs\CloneCourse;
use EscolaLms\CoursesImportExport\Tests\TestCase;
use EscolaLms\Tags\Models\Tag;
use EscolaLms\TopicTypes\Models\TopicContent\Audio;
use EscolaLms\TopicTypes\Models\TopicContent\Image;
use EscolaLms\TopicTypes\Models\TopicContent\PDF;
use EscolaLms\TopicTypes\Models\TopicContent\Video;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

class CourseCloneApiTest extends TestCase
{
    use CreatesUsers,DatabaseTransactions, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CoursesExportImportPermissionSeeder::class);

        Storage::fake(config('filesystems.default'));
        Storage::put('dummy.mp4', 'Some dummy data');
        Storage::put('dummy.mp3', 'Some dummy data');
        Storage::put('dummy.jpg', 'Some dummy data');
        Storage::put('dummy.png', 'Some dummy data');
        Storage::put('dummy.pdf', 'Some dummy data');

        $course = Course::factory()
            ->has(
                Category::factory()
                    ->count(1)
                    ->state(fn () => ['parent_id' => null])
            )
            ->has(Tag::factory()->count(2))
            ->create([
                'author_id' => $this->makeAdmin()->id,
            ]);
        $lesson = Lesson::factory()->create([
            'course_id' => $course->id,
        ]);
        $topic_audio = Topic::factory()->create([
            'lesson_id' => $lesson->id,
        ]);
        $topic_image = Topic::factory()->create([
            'lesson_id' => $lesson->id,
        ]);
        $topic_pdf = Topic::factory()->create([
            'lesson_id' => $lesson->id,
        ]);
        $topic_video = Topic::factory()->create([
            'lesson_id' => $lesson->id,
        ]);

        $topicable_audio = Audio::factory()->create([
            'value' => 'dummy.mp3',
        ]);
        $topicable_image = Image::factory()->create([
            'value' => 'dummy.jpg',
        ]);
        $topicable_pdf = PDF::factory()->create([
            'value' => 'dummy.pdf',
        ]);
        $topicable_video = Video::factory()->create([
            'value' => 'dummy.mp4',
            'poster' => 'dummy.png',
        ]);

        $topic_audio->topicable()->associate($topicable_audio)->save();
        $topic_image->topicable()->associate($topicable_image)->save();
        $topic_pdf->topicable()->associate($topicable_pdf)->save();
        $topic_video->topicable()->associate($topicable_video)->save();

        $this->course = $course;
    }

    public function testCloneCourse()
    {
        Queue::fake();
        $admin = $this->makeAdmin();

        $this->actingAs($admin, 'api')
            ->json('GET', '/api/admin/courses/' . $this->course->getKey() . '/clone')
            ->assertOk();

        Queue::assertPushed(CloneCourse::class);
    }

    public function testCloneNonExistentCourse()
    {
        $admin = $this->makeAdmin();
        $this->actingAs($admin, 'api')
            ->json('GET', '/api/admin/courses/-1/clone')
            ->assertNotFound();
    }
}
