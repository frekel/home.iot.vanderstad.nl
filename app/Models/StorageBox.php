<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StorageBox extends Model
{
    protected $fillable = [
        'location',
        'number',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'number' => 'integer',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(StorageBoxItem::class)->orderBy('name');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(StorageBoxPhoto::class)->latest();
    }
}
