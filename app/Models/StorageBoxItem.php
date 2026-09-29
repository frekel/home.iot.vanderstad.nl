<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StorageBoxItem extends Model
{
    protected $fillable = [
        'name',
        'quantity',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }

    public function box(): BelongsTo
    {
        return $this->belongsTo(StorageBox::class, 'storage_box_id');
    }
}
