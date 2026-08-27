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
        'contract_type_id',
        'position_id',
        'cover_text',
        'content',
    ];

    protected $casts = [
        'content' => 'string', // Canonical storage is sanitized HTML (TipTap-compatible)
    ];

    public function contractType()
    {
        return $this->belongsTo(ContractType::class);
    }

    public function contracts()
    {
        return $this->hasMany(Contract::class, 'template_id');
    }

    public function position()
    {
        return $this->belongsTo(Position::class);
    }

    public function policies()
    {
        return $this->belongsToMany(ContractPolicy::class, 'contract_policy_contract_template')
            ->withPivot('sort_order')->orderByPivot('sort_order');
    }

    public function getTypeSlugAttribute(): ?string
    {
        return $this->contractType?->slug;
    }
}
