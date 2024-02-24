<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Files extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'id',
        'name',
        'path',
        'size',
        'mime',
        'user_id',
        'collection_id'
    ];

    protected $casts = [
        'id' => 'string'
    ];

    public function collection()
    {
        return $this->belongsTo(Collections::class);
    }
}
