<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('furniture_items', function (Blueprint $table) {
            $table->id();
            $table->string('floor_id', 32);
            $table->string('item_id', 64);
            $table->string('kind', 64);
            $table->string('model_kind', 64)->nullable();
            $table->string('source_shape')->nullable();
            $table->decimal('x', 10, 5);
            $table->decimal('y', 10, 5);
            $table->decimal('rotation', 10, 5)->default(0);
            $table->decimal('width', 10, 5);
            $table->decimal('depth', 10, 5);
            $table->decimal('height', 10, 5);
            $table->decimal('base_z', 10, 5)->default(0);
            $table->json('metadata')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->unique(['floor_id', 'item_id']);
            $table->foreign('floor_id')->references('id')->on('house_floors')->cascadeOnDelete();
        });

        $legacy = json_decode(
            file_get_contents(base_path('assets/blender/furniture.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        )['items'];

        // Items added after the original traced furniture file. These values are
        // only bootstrap defaults for a fresh installation. Existing production
        // values from furniture_layouts.overrides always win below.
        $legacy = array_merge($legacy, [
            ['id'=>'257','floor'=>'upper','kind'=>'cabinet','source_shape'=>'dresser','measured'=>true,'x'=>-.0349,'y'=>.3992,'width'=>.71,'depth'=>.34,'height'=>.87,'base_z'=>0,'rotation'=>180],
            ['id'=>'258','floor'=>'upper','kind'=>'tv','source_shape'=>'flat_tv','standing'=>true,'measured'=>true,'x'=>-.0349,'y'=>.3992,'width'=>.62,'depth'=>.10,'height'=>.40,'base_z'=>.87,'rotation'=>180],
            ['id'=>'256-mirror','floor'=>'upper','kind'=>'mirror','source_shape'=>'user_specified','measured'=>true,'x'=>2.7297,'y'=>.2933,'width'=>1.5135,'depth'=>.03,'height'=>.75,'base_z'=>.67,'rotation'=>180],
            ['id'=>'256-radiator','floor'=>'upper','kind'=>'Wandradiator','model_kind'=>'wall_radiator','source_shape'=>'user_specified','measured'=>true,'x'=>1.9628,'y'=>1.1687,'width'=>.75,'depth'=>.10,'height'=>.60,'base_z'=>.15,'rotation'=>90],
            ['id'=>'259','floor'=>'upper','kind'=>'Hangend bureaublad','model_kind'=>'hanging_desk','source_shape'=>'user_measured','measured'=>true,'x'=>-2.00,'y'=>2.35,'width'=>1.20,'depth'=>.80,'height'=>.04,'base_z'=>.96,'rotation'=>180],
            ['id'=>'260','floor'=>'upper','kind'=>'Monitor','model_kind'=>'monitor','source_shape'=>'user_measured','measured'=>true,'x'=>-2.27,'y'=>2.67,'width'=>.54,'depth'=>.15,'height'=>.25,'base_z'=>1.00,'rotation'=>0],
            ['id'=>'261','floor'=>'upper','kind'=>'Monitor','model_kind'=>'monitor','source_shape'=>'user_measured','measured'=>true,'x'=>-1.73,'y'=>2.67,'width'=>.54,'depth'=>.15,'height'=>.25,'base_z'=>1.00,'rotation'=>0],
            ['id'=>'262','floor'=>'upper','kind'=>'Laptop','model_kind'=>'laptop','source_shape'=>'user_measured','measured'=>true,'x'=>-2.00,'y'=>2.20,'width'=>.36,'depth'=>.29,'height'=>.25,'base_z'=>1.00,'rotation'=>180],
            ['id'=>'263','floor'=>'upper','kind'=>'Kledingkast','model_kind'=>'wardrobe_drawers','source_shape'=>'user_measured','measured'=>true,'x'=>-3.30,'y'=>2.435,'width'=>1.40,'depth'=>.63,'height'=>1.88,'base_z'=>0,'rotation'=>180],
            ['id'=>'264','floor'=>'upper','kind'=>'MICKE bureau','model_kind'=>'micke_desk','source_shape'=>'user_measured','measured'=>true,'x'=>-3.335,'y'=>.5633,'width'=>.73,'depth'=>.57,'height'=>.75,'base_z'=>0,'rotation'=>0],
            ['id'=>'265','floor'=>'upper','kind'=>'MICKE bureau','model_kind'=>'micke_desk','source_shape'=>'user_measured','measured'=>true,'x'=>-2.605,'y'=>.5633,'width'=>.73,'depth'=>.57,'height'=>.75,'base_z'=>0,'rotation'=>0],
            ['id'=>'266','floor'=>'upper','kind'=>'MICKE bureau','model_kind'=>'micke_desk','source_shape'=>'user_measured','measured'=>true,'x'=>-3.335,'y'=>.5633,'width'=>.73,'depth'=>.57,'height'=>.75,'base_z'=>.75,'rotation'=>0],
            ['id'=>'267','floor'=>'upper','kind'=>'MICKE bureau','model_kind'=>'micke_desk','source_shape'=>'user_measured','measured'=>true,'x'=>-2.605,'y'=>.5633,'width'=>.73,'depth'=>.57,'height'=>.75,'base_z'=>.75,'rotation'=>0],
            ['id'=>'268','floor'=>'upper','kind'=>'3D-printer','model_kind'=>'kobra4','source_shape'=>'Anycubic Kobra 4','measured'=>true,'x'=>-3.335,'y'=>.5633,'width'=>.40,'depth'=>.55,'height'=>.50,'base_z'=>0,'rotation'=>0],
            ['id'=>'269','floor'=>'upper','kind'=>'3D-printer','model_kind'=>'kobra4','source_shape'=>'Anycubic Kobra 4','measured'=>true,'x'=>-2.605,'y'=>.5633,'width'=>.40,'depth'=>.55,'height'=>.50,'base_z'=>0,'rotation'=>0],
            ['id'=>'270','floor'=>'upper','kind'=>'3D-printer','model_kind'=>'kobra4','source_shape'=>'Anycubic Kobra 4','measured'=>true,'x'=>-3.335,'y'=>.5633,'width'=>.40,'depth'=>.55,'height'=>.50,'base_z'=>.75,'rotation'=>0],
            ['id'=>'271','floor'=>'upper','kind'=>'3D-printer','model_kind'=>'kobra4','source_shape'=>'Anycubic Kobra 4','measured'=>true,'x'=>-2.605,'y'=>.5633,'width'=>.40,'depth'=>.55,'height'=>.50,'base_z'=>.75,'rotation'=>0],
            ['id'=>'bijspringer-skadis-1','floor'=>'upper','kind'=>'SKADIS met filament','model_kind'=>'skadis_filament','source_shape'=>'user_measured','measured'=>true,'x'=>-1.918,'y'=>.2933,'width'=>.55,'depth'=>.03,'height'=>.55,'base_z'=>.45,'rotation'=>0],
            ['id'=>'bijspringer-skadis-2','floor'=>'upper','kind'=>'SKADIS met filament','model_kind'=>'skadis_filament','source_shape'=>'user_measured','measured'=>true,'x'=>-1.918,'y'=>.2933,'width'=>.55,'depth'=>.03,'height'=>.55,'base_z'=>1.00,'rotation'=>0],
            ['id'=>'laundry-basket','floor'=>'attic','kind'=>'Wasmand','model_kind'=>'laundry_basket','source_shape'=>'user_specified','measured'=>true,'x'=>-1.65,'y'=>.90,'width'=>.30,'depth'=>.30,'height'=>.80,'base_z'=>0,'rotation'=>0],
            ['id'=>'washok-rack-right','floor'=>'attic','kind'=>'Stellingkast washok rechts','model_kind'=>'storage_rack','source_shape'=>'user_specified','measured'=>true,'x'=>3.10,'y'=>-.915,'width'=>3.67,'depth'=>.60,'height'=>1.80,'base_z'=>0,'rotation'=>90],
            ['id'=>'washok-rack-bottom','floor'=>'attic','kind'=>'Stellingkast washok onder','model_kind'=>'storage_rack','source_shape'=>'user_specified','measured'=>true,'x'=>2.30,'y'=>-2.45,'width'=>1.00,'depth'=>.60,'height'=>1.80,'base_z'=>0,'rotation'=>0],
            ['id'=>'washok-rack-left','floor'=>'attic','kind'=>'Stellingkast washok links','model_kind'=>'storage_rack','source_shape'=>'user_specified','measured'=>true,'x'=>1.50,'y'=>-.975,'width'=>3.55,'depth'=>.60,'height'=>1.80,'base_z'=>0,'rotation'=>90],
        ]);

        $layout = DB::table('furniture_layouts')->find(1);
        $stored = $layout
            ? json_decode($layout->overrides, true, flags: JSON_THROW_ON_ERROR)
            : [];

        $core = array_flip([
            'id', 'floor', 'kind', 'model_kind', 'source_shape',
            'x', 'y', 'rotation', 'width', 'depth', 'height', 'base_z',
        ]);

        foreach ($legacy as $index => $item) {
            $key = $item['floor'].':'.$item['id'];
            $values = is_array($stored[$key] ?? null) ? $stored[$key] : [];
            $baseZ = $item['base_z'] ?? match ($item['kind']) {
                'tv' => .9,
                'computer' => .78,
                default => 0,
            };

            // Production measurements and placements are authoritative. Legacy
            // values only fill fields that do not yet exist (fresh installs).
            $value = fn (string $field, mixed $fallback) => (float) ($values[$field] ?? $fallback);
            $metadata = array_diff_key($item, $core);

            // Grouping of these three attic items was removed before the DB became
            // authoritative; preserve them as independent editable furniture.
            if ($item['floor'] === 'attic' && in_array((string) $item['id'], ['191', '261', '201'], true)) {
                unset($metadata['group'], $metadata['group_name']);
            }

            // Z-905's all-black appearance is item data, not renderer logic.
            if ($item['floor'] === 'attic' && (string) $item['id'] === '905') {
                $metadata['material_override'] = 'dark';
            }

            DB::table('furniture_items')->insert([
                'floor_id' => $item['floor'],
                'item_id' => (string) $item['id'],
                'kind' => $item['kind'],
                'model_kind' => $item['model_kind'] ?? null,
                'source_shape' => $item['source_shape'] ?? null,
                'x' => $value('x', $item['x']),
                'y' => $value('y', $item['y']),
                'rotation' => $value('rotation', $item['rotation'] ?? 0),
                'width' => $value('width', $item['width']),
                'depth' => $value('depth', $item['depth']),
                'height' => $value('height', $item['height']),
                'base_z' => $value('base_z', $baseZ),
                'metadata' => $metadata ? json_encode($metadata, JSON_THROW_ON_ERROR) : null,
                'sort_order' => $index + 1,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('furniture_items');
    }
};
