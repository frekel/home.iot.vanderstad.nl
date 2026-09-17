<?php

namespace Tests\Feature;

use App\Jobs\BuildFurniture;
use App\Services\FurnitureLayout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Tests\TestCase;

class FurnitureEditorTest extends TestCase
{
    use RefreshDatabase;

    private function change(array $extra = []): array
    {
        return array_replace(['floor' => 'ground', 'id' => '82', 'width' => 130, 'depth' => 40, 'height' => 90, 'base_z' => 0], $extra);
    }

    public function test_dimensions_persist_without_queuing_a_build(): void
    {
        Queue::fake();
        $original = file_get_contents(base_path('assets/blender/furniture.json'));
        $this->putJson('/dashboard/furniture', ['revision' => 0, 'items' => [$this->change()]])
            ->assertOk()->assertJsonPath('revision', 1)->assertJsonPath('status', 'ready');
        Queue::assertNothingPushed();
        $state = $this->getJson('/dashboard/furniture')->assertOk()->json();
        $item = collect($state['items'])->first(fn ($i) => $i['floor'] === 'ground' && $i['id'] === '82');
        $this->assertEquals(1.3, $item['width']);
        $this->assertEquals(.4, $item['depth']);
        $this->assertSame($original, file_get_contents(base_path('assets/blender/furniture.json')));
        $this->assertStringStartsWith('/models/', $state['models']['ground']);
    }

    public function test_build_endpoint_queues_exactly_one_background_build(): void
    {
        Queue::fake();
        $this->putJson('/dashboard/furniture', ['revision' => 0, 'items' => [$this->change()]])->assertOk();
        $this->postJson('/dashboard/furniture/build', ['revision' => 1])
            ->assertAccepted()->assertJsonPath('revision', 1)->assertJsonPath('status', 'queued');
        Queue::assertPushed(BuildFurniture::class, fn ($job) => $job->revision === 1 && $job->connection === 'furniture');
        Queue::assertPushed(BuildFurniture::class, 1);
    }

    public function test_invalid_or_unknown_dimensions_are_rejected_without_saving_anything(): void
    {
        Queue::fake();
        foreach ([['width' => -1], ['height' => 9999], ['base_z' => -1], ['id' => '../../secret'], ['floor' => 'unknown'], ['height' => 400, 'base_z' => 200]] as $invalid) {
            $this->putJson('/dashboard/furniture', ['revision' => 0, 'items' => [$this->change($invalid)]])->assertUnprocessable();
        }
        $this->putJson('/dashboard/furniture', ['revision' => 0, 'items' => [$this->change(), $this->change()]])->assertUnprocessable();
        $this->assertSame(0, DB::table('furniture_layouts')->value('revision'));
        Queue::assertNothingPushed();
    }

    public function test_building_or_stale_revision_cannot_be_overwritten(): void
    {
        Queue::fake();
        $this->putJson('/dashboard/furniture', ['revision' => 0, 'items' => [$this->change()]])->assertOk();
        $this->postJson('/dashboard/furniture/build', ['revision' => 1])->assertAccepted();
        $this->putJson('/dashboard/furniture', ['revision' => 1, 'items' => [$this->change(['width' => 200])]])->assertConflict();
        DB::table('furniture_layouts')->where('id', 1)->update(['status' => 'ready']);
        $this->putJson('/dashboard/furniture', ['revision' => 0, 'items' => [$this->change(['width' => 200])]])->assertConflict();
        $this->postJson('/dashboard/furniture/build', ['revision' => 0])->assertConflict();
        Queue::assertPushed(BuildFurniture::class, 1);
    }

    public function test_failed_build_retains_published_model_and_can_retry_saved_dimensions(): void
    {
        Queue::fake();
        $directory = sys_get_temp_dir().'/furniture-test-'.bin2hex(random_bytes(8));
        config(['furniture.blender' => '/bin/false', 'furniture.build_path' => $directory]);
        DB::table('furniture_layouts')->where('id', 1)->update(['model_revision' => 0]);
        $this->putJson('/dashboard/furniture', ['revision' => 0, 'items' => [$this->change()]])->assertOk();
        $job = new BuildFurniture(1);
        try {
            $job->handle(app(FurnitureLayout::class));
            $this->fail('Failed Blender process must not publish a model.');
        } catch (ProcessFailedException $e) {
            $job->failed($e);
        } finally {
            File::deleteDirectory($directory);
        }
        $this->getJson('/dashboard/furniture')->assertJsonPath('status', 'failed')->assertJsonPath('model_revision', 0);
        $this->postJson('/dashboard/furniture/build', ['revision' => 1])->assertAccepted()->assertJsonPath('revision', 1);
        $this->assertEquals(1.3, app(FurnitureLayout::class)->state()['items'][array_search('82', array_column(app(FurnitureLayout::class)->state()['items'], 'id'))]['width']);
    }

    public function test_partial_models_are_not_published_and_routes_reject_arbitrary_paths(): void
    {
        Queue::fake();
        $directory = sys_get_temp_dir().'/furniture-test-'.bin2hex(random_bytes(8));
        config(['furniture.blender' => '/bin/true', 'furniture.build_path' => $directory]);
        $this->putJson('/dashboard/furniture', ['revision' => 0, 'items' => [$this->change()]])->assertOk();
        $job = new BuildFurniture(1);
        try {
            $job->handle(app(FurnitureLayout::class));
            $this->fail('Missing model files must not be published.');
        } catch (\RuntimeException $e) {
            $job->failed($e);
        } finally {
            File::deleteDirectory($directory);
        }
        $this->getJson('/dashboard/furniture')->assertJsonPath('status', 'failed')->assertJsonPath('model_revision', null);
        $this->get('/dashboard/furniture/models/1/ground')->assertNotFound();
        $this->get('/dashboard/furniture/models/1/input.json')->assertNotFound();
    }
}
