<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContractTemplate extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'name',
        'type',
        'cover_text',
        'content',
    ];

    protected $casts = [
        'content' => 'array', // Store Quill editor content as JSON
    ];

    public function contracts()
    {
        return $this->hasMany(Contract::class, 'template_id');
    }
}
