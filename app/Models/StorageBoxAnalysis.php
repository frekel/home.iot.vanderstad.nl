<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StorageBoxAnalysis extends Model
{
    protected $fillable = [
        'photo_batch_id',
        'model',
        'response_id',
        'result',
        'applied_at',
    ];

    protected function casts(): array
    {
        return [
            'result' => 'array',
            'applied_at' => 'datetime',
        ];
    }

    public function box(): BelongsTo
    {
        return $this->belongsTo(StorageBox::class, 'storage_box_id');
    }
}
