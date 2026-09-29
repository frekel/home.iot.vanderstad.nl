<?php

namespace Tests\Feature;

use App\Models\StorageBox;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
