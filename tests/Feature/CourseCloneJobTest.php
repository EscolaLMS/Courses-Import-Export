<?php

namespace EscolaLms\CoursesImportExport\Tests\Feature;


use EscolaLms\Categories\Models\Category;
use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\Courses\Models\Course;
use EscolaLms\Courses\Models\Lesson;
use EscolaLms\Courses\Models\Topic;
use EscolaLms\CoursesImportExport\Events\CloneCourseFailedEvent;
use EscolaLms\CoursesImportExport\Events\CloneCourseFinishedEvent;
use EscolaLms\CoursesImportExport\Events\CloneCourseStartedEvent;
use EscolaLms\CoursesImportExport\Jobs\CloneCourse;
use EscolaLms\CoursesImportExport\Services\Contracts\ExportImportServiceContract;
use EscolaLms\CoursesImportExport\Tests\TestCase;
use EscolaLms\HeadlessH5P\Models\H5PContent;
use EscolaLms\HeadlessH5P\Repositories\Contracts\H5PContentRepositoryContract;
use EscolaLms\Scorm\Services\Contracts\ScormServiceContract;
use EscolaLms\Tags\Models\Tag;
use EscolaLms\TopicTypes\Models\TopicContent\Audio;
use EscolaLms\TopicTypes\Models\TopicContent\H5P;
use EscolaLms\TopicTypes\Models\TopicContent\Image;
use EscolaLms\TopicTypes\Models\TopicContent\PDF;
use EscolaLms\TopicTypes\Models\TopicContent\ScormSco;
use EscolaLms\TopicTypes\Models\TopicContent\Video;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Peopleaps\Scorm\Model\ScormScoModel;

class CourseCloneJobTest extends TestCase
{
    use CreatesUsers, DatabaseTransactions, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('clone');
        Event::fake();
    }

    public function testCloneCourseJobFailed(): void
    {
        $user = $this->makeAdmin();
        $this->be($user);

        $course = $this->createInvalidCourse();

        $job = new CloneCourse($course, $user);
        $job->handle(app()->make(ExportImportServiceContract::class));

        Event::assertNotDispatched(CloneCourseFinishedEvent::class);
        Event::assertDispatched(CloneCourseFailedEvent::class, fn($elem) => $elem->getCourse()->getKey() === $course->getKey());
    }

    public function testCloneCourseJob(): void
    {
        $user = $this->makeAdmin();
        $this->be($user);
        $course = $this->createCourse();

        $job = new CloneCourse($course, $user);
        $job->handle(app()->make(ExportImportServiceContract::class));

        $cloned = Course::all()->last();

        $this->assertEquals($course->title, $cloned->title);
        $this->assertEquals($course->summary, $cloned->summary);

        $this->assertEquals(1, $course->categories()->count());
        $this->assertEquals($course->categories()->count(), $cloned->categories()->count());
        $this->assertEquals($course->categories()->first()->getKey(), $course->categories()->first()->getKey());

        $this->assertEquals(2, $course->tags()->count());
        $this->assertEquals($course->tags()->count(), $cloned->tags()->count());
        $this->assertEquals($course->tags()->first()->getKey(), $course->tags()->first()->getKey());

        $this->assertEquals(1, $course->lessons()->count());
        $this->assertEquals($course->lessons()->count(), $cloned->lessons()->count());
        $this->assertEquals(
            6,
            $course->lessons()->with('topics')->get()->map(fn($elem) => $elem->topics)->flatten()->count()
        );
        $this->assertEquals(
            $course->lessons()->with('topics')->get()->map(fn($elem) => $elem->topics)->flatten()->count(),
            $cloned->lessons()->with('topics')->get()->map(fn($elem) => $elem->topics)->flatten()->count()
        );

        $this->assertNotEquals($course->scorm_sco_id, $cloned->scorm_sco_id);
        $this->assertNotEquals($course->image_path, $cloned->image_path);
        $this->assertNotEquals($course->poster_path, $cloned->poster_path);
        $this->assertNotEquals($course->video_path, $cloned->video_path);

        Storage::exists($course->image_path);
        Storage::exists($course->poster_path);
        Storage::exists($course->video_path);

        Storage::exists($cloned->image_path);
        Storage::exists($cloned->poster_path);
        Storage::exists($cloned->video_path);

        Event::assertDispatched(CloneCourseStartedEvent::class, fn($elem) => $elem->getCourse()->getKey() === $course->getKey());
        Event::assertDispatched(CloneCourseFinishedEvent::class, fn($elem) => $elem->getCourse()->getKey() === $cloned->getKey());
        Event::assertNotDispatched(CloneCourseFailedEvent::class);
    }

    private function createInvalidCourse(): Course
    {
        $course = $this->createCourse();
        $lesson = $course->lessons->first();
        $topic = Topic::factory()->create([
            'lesson_id' => $lesson->id,
        ]);
        $topicable = H5P::factory()->create([
            'value' => 555
        ]);
        $topic->topicable()->associate($topicable)->save();
        $course->save();

        return $course;
    }

    private function createCourse(): Course
    {
        Storage::put('dummy.mp4', 'Some dummy data');
        Storage::put('dummy.mp3', 'Some dummy data');
        Storage::put('dummy.jpg', 'Some dummy data');
        Storage::put('dummy.png', 'Some dummy data');
        Storage::put('dummy.pdf', 'Some dummy data');

        $scormSco = $this->getScormSco();

        $course = Course::factory()
            ->has(
                Category::factory()
                    ->count(1)
                    ->state(fn () => ['parent_id' => null])
            )
            ->has(Tag::factory()->count(2))
            ->create([
                'author_id' => $this->makeAdmin()->id,
                'scorm_sco_id' => $scormSco->getKey()
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
        $topic_scorm_sco = Topic::factory()->create([
            'lesson_id' => $lesson->id,
        ]);
        $topic_h5p = Topic::factory()->create([
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
        $topicable_scorm_sco = ScormSco::factory()->create([
            'value' => $scormSco->getKey(),
        ]);
        $topicable_h5p = H5P::factory()->create([
            'value' => $this->createH5PContent()->getKey()
        ]);

        $topic_audio->topicable()->associate($topicable_audio)->save();
        $topic_image->topicable()->associate($topicable_image)->save();
        $topic_pdf->topicable()->associate($topicable_pdf)->save();
        $topic_video->topicable()->associate($topicable_video)->save();
        $topic_scorm_sco->topicable()->associate($topicable_scorm_sco)->save();
        $topic_h5p->topicable()->associate($topicable_h5p)->save();

        return $course;
    }

    private function createScorm()
    {
        $mockPath = __DIR__ . '/../mocks/scorm.zip';
        $tmpPath = __DIR__ . '/../mocks/tmp.zip';
        copy($mockPath, $tmpPath);

        $file =  new UploadedFile($tmpPath, basename($mockPath), 'application/zip', null, true);

        try {
            $scormService = app()->make(ScormServiceContract::class);
            return $scormService->uploadScormArchive($file);
        } catch (\Exception $err) {
            echo $err->getMessage();
        } finally {
            if (is_file($tmpPath)) {
                unlink($tmpPath);
            }
        }

        return null;
    }

    private function getScormSco(): ScormScoModel
    {
        $scorm = $this->createScorm();

        return $scorm['model']->scos->first();
    }

    private function createH5PContent(): ?H5PContent
    {
        $h5Path = realpath(__DIR__ . '/../mocks/hp5.h5p');
        $tmpPath = __DIR__ . '/../mocks/tmp.h5p';
        copy($h5Path, $tmpPath);

        $file =  new UploadedFile($tmpPath, basename($h5Path), 'application/zip', null, true);

        try {
            $h5pContentRepository = app()->make(H5PContentRepositoryContract::class);
            return $h5pContentRepository->upload($file);
        } catch (\Exception $err) {
            echo $err->getMessage();
        } finally {
            if (is_file($tmpPath)) {
                unlink($tmpPath);
            }
        }

        return null;
    }
}
