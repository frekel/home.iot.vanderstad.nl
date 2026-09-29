<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StorageBoxPhoto extends Model
{
    protected $fillable = [
        'path',
        'original_name',
    ];

    public function box(): BelongsTo
    {
        return $this->belongsTo(StorageBox::class, 'storage_box_id');
    }
}
