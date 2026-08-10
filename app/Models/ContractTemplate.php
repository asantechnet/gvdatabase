<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContractTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'cover_text',
        'content',
    ];

    protected $casts = [
        'content' => 'array', // Store Quill editor content as JSON
    ];
}
