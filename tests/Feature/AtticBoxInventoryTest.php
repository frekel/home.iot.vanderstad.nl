<?php

namespace Tests\Feature;

use App\Models\StorageBox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AtticBoxInventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_overview_is_available(): void
    {
        $this->get('/inventory/zolder/kisten')
            ->assertOk()
            ->assertSee('Kisten op zolder')
            ->assertSee('Kistnummer');
    }

    public function test_opening_a_number_creates_the_box_once_and_redirects_to_it(): void
    {
        $this->post('/inventory/zolder/kisten/open', ['number' => 23])
            ->assertRedirect('/inventory/zolder/kisten/23');

        $this->post('/inventory/zolder/kisten/open', ['number' => 23])
            ->assertRedirect('/inventory/zolder/kisten/23');

        $this->assertDatabaseCount('storage_boxes', 1);
        $this->assertDatabaseHas('storage_boxes', [
            'location' => 'zolder',
            'number' => 23,
        ]);
    }

    public function test_items_can_be_added_and_found_from_the_overview(): void
    {
        $box = StorageBox::create(['location' => 'zolder', 'number' => 12]);

        $this->post('/inventory/zolder/kisten/12/items', [
            'name' => 'HDMI-kabel',
            'quantity' => 4,
            'notes' => 'Twee korte en twee lange kabels',
        ])->assertRedirect();

        $this->assertDatabaseHas('storage_box_items', [
            'storage_box_id' => $box->id,
            'name' => 'HDMI-kabel',
            'quantity' => 4,
        ]);

        $this->get('/inventory/zolder/kisten?q=HDMI')
            ->assertOk()
            ->assertSee('Kist 12');
    }

    public function test_item_from_another_box_cannot_be_changed_through_the_wrong_box(): void
    {
        $box12 = StorageBox::create(['location' => 'zolder', 'number' => 12]);
        StorageBox::create(['location' => 'zolder', 'number' => 13]);
        $item = $box12->items()->create(['name' => 'Adapter', 'quantity' => 1]);

        $this->put('/inventory/zolder/kisten/13/items/'.$item->id, [
            'name' => 'Verkeerd',
            'quantity' => 9,
        ])->assertNotFound();

        $this->assertDatabaseHas('storage_box_items', [
            'id' => $item->id,
            'name' => 'Adapter',
            'quantity' => 1,
        ]);
    }

    public function test_uploaded_photos_are_grouped_as_one_private_analysis_batch(): void
    {
        Storage::fake('local');
        $box = StorageBox::create(['location' => 'zolder', 'number' => 7]);

        $this->post('/inventory/zolder/kisten/7/photos', [
            'photos' => [
                UploadedFile::fake()->image('bovenkant.jpg'),
                UploadedFile::fake()->image('zijkant.jpg'),
            ],
        ])->assertRedirect();

        $photos = $box->photos()->get();
        $this->assertCount(2, $photos);
        $this->assertNotNull($photos[0]->batch_id);
        $this->assertSame($photos[0]->batch_id, $photos[1]->batch_id);
        Storage::disk('local')->assertExists($photos[0]->path);
    }


    public function test_box_can_be_named_and_found_by_name(): void
    {
        StorageBox::create(['location' => 'zolder', 'number' => 44]);

        $this->patch('/inventory/zolder/kisten/44', [
            'name' => 'Kerstspullen',
        ])->assertRedirect();

        $this->assertDatabaseHas('storage_boxes', [
            'location' => 'zolder',
            'number' => 44,
            'name' => 'Kerstspullen',
        ]);

        $this->get('/inventory/zolder/kisten?q=Kerst')
            ->assertOk()
            ->assertSee('Kist 44')
            ->assertSee('Kerstspullen');
    }

    public function test_part_of_an_item_can_be_moved_to_another_box(): void
    {
        $source = StorageBox::create(['location' => 'zolder', 'number' => 10]);
        $target = StorageBox::create(['location' => 'zolder', 'number' => 11]);
        $item = $source->items()->create([
            'name' => 'HDMI-kabel',
            'quantity' => 5,
            'notes' => 'Zwart',
        ]);

        $this->post('/inventory/zolder/kisten/10/items/'.$item->id.'/move', [
            'target_number' => 11,
            'quantity' => 2,
        ])->assertRedirect();

        $this->assertDatabaseHas('storage_box_items', [
            'storage_box_id' => $source->id,
            'name' => 'HDMI-kabel',
            'quantity' => 3,
        ]);
        $this->assertDatabaseHas('storage_box_items', [
            'storage_box_id' => $target->id,
            'name' => 'HDMI-kabel',
            'quantity' => 2,
            'notes' => 'Zwart',
        ]);
    }

    public function test_moving_all_of_an_item_creates_the_destination_box_and_removes_source_item(): void
    {
        $source = StorageBox::create(['location' => 'zolder', 'number' => 20]);
        $item = $source->items()->create([
            'name' => 'Verlengsnoer',
            'quantity' => 2,
        ]);

        $this->post('/inventory/zolder/kisten/20/items/'.$item->id.'/move', [
            'target_number' => 21,
            'quantity' => 2,
        ])->assertRedirect();

        $target = StorageBox::query()
            ->where('location', 'zolder')
            ->where('number', 21)
            ->firstOrFail();

        $this->assertDatabaseMissing('storage_box_items', ['id' => $item->id]);
        $this->assertDatabaseHas('storage_box_items', [
            'storage_box_id' => $target->id,
            'name' => 'Verlengsnoer',
            'quantity' => 2,
        ]);
    }

    public function test_uploaded_photo_can_be_viewed_only_through_its_own_box(): void
    {
        Storage::fake('local');
        $box = StorageBox::create(['location' => 'zolder', 'number' => 7]);
        StorageBox::create(['location' => 'zolder', 'number' => 8]);

        $this->post('/inventory/zolder/kisten/7/photos', [
            'photos' => [UploadedFile::fake()->image('inhoud.jpg')],
        ])->assertRedirect();

        $photo = $box->photos()->firstOrFail();

        $this->get('/inventory/zolder/kisten/7/photos/'.$photo->id)->assertOk();
        $this->get('/inventory/zolder/kisten/8/photos/'.$photo->id)->assertNotFound();
    }

    public function test_uploaded_photo_can_be_deleted_with_its_file(): void
    {
        Storage::fake('local');
        $box = StorageBox::create(['location' => 'zolder', 'number' => 7]);

        $this->post('/inventory/zolder/kisten/7/photos', [
            'photos' => [UploadedFile::fake()->image('inhoud.jpg')],
        ])->assertRedirect();

        $photo = $box->photos()->firstOrFail();
        Storage::disk('local')->assertExists($photo->path);

        $this->delete('/inventory/zolder/kisten/7/photos/'.$photo->id)
            ->assertRedirect();

        $this->assertDatabaseMissing('storage_box_photos', ['id' => $photo->id]);
        Storage::disk('local')->assertMissing($photo->path);
    }

    public function test_ai_analysis_is_reviewed_before_it_changes_inventory(): void
    {
        Storage::fake('local');
        config([
            'services.openai.api_key' => 'test-key',
            'services.openai.inventory_model' => 'gpt-5.6-terra',
        ]);

        $box = StorageBox::create(['location' => 'zolder', 'number' => 23]);
        $box->items()->create(['name' => 'Bestaand item', 'quantity' => 1]);

        $this->post('/inventory/zolder/kisten/23/photos', [
            'photos' => [UploadedFile::fake()->image('inhoud.jpg', 1200, 900)],
        ])->assertRedirect();

        Http::fake([
            'api.openai.com/v1/responses' => Http::response([
                'id' => 'resp_test',
                'model' => 'gpt-5.6-terra',
                'output' => [[
                    'type' => 'message',
                    'content' => [[
                        'type' => 'output_text',
                        'text' => json_encode([
                            'items' => [[
                                'name' => 'HDMI-kabel',
                                'quantity' => 3,
                                'notes' => 'Zwarte kabels',
                                'confidence' => 'high',
                            ]],
                            'warnings' => [],
                        ]),
                    ]],
                ]],
            ], 200),
        ]);

        $response = $this->post('/inventory/zolder/kisten/23/analyse');
        $analysis = $box->analyses()->firstOrFail();

        $response->assertRedirect('/inventory/zolder/kisten/23/analyse/'.$analysis->id);
        $this->assertDatabaseMissing('storage_box_items', ['name' => 'HDMI-kabel']);
        $this->assertDatabaseHas('storage_box_items', ['name' => 'Bestaand item']);

        Http::assertSent(fn ($request) =>
            $request->url() === 'https://api.openai.com/v1/responses'
            && $request->hasHeader('Authorization', 'Bearer test-key')
            && collect($request['input'][0]['content'])->contains(fn ($part) => ($part['type'] ?? null) === 'input_image')
            && data_get($request->data(), 'text.format.type') === 'json_schema'
        );

        $this->post('/inventory/zolder/kisten/23/analyse/'.$analysis->id, [
            'mode' => 'merge',
            'items' => [[
                'include' => '1',
                'name' => 'HDMI-kabel',
                'quantity' => 3,
                'notes' => 'Gecontroleerd',
            ]],
        ])->assertRedirect('/inventory/zolder/kisten/23');

        $this->assertDatabaseHas('storage_box_items', [
            'storage_box_id' => $box->id,
            'name' => 'HDMI-kabel',
            'quantity' => 3,
            'notes' => 'Gecontroleerd',
        ]);
        $this->assertDatabaseHas('storage_box_items', ['name' => 'Bestaand item']);
        $this->assertNotNull($analysis->fresh()->applied_at);
    }

    public function test_replace_mode_removes_items_not_in_the_confirmed_ai_result(): void
    {
        $box = StorageBox::create(['location' => 'zolder', 'number' => 31]);
        $box->items()->create(['name' => 'Oud item', 'quantity' => 1]);
        $analysis = $box->analyses()->create([
            'model' => 'test',
            'result' => ['items' => [], 'warnings' => []],
        ]);

        $this->post('/inventory/zolder/kisten/31/analyse/'.$analysis->id, [
            'mode' => 'replace',
        ])->assertRedirect('/inventory/zolder/kisten/31');

        $this->assertDatabaseMissing('storage_box_items', ['name' => 'Oud item']);
    }
}
