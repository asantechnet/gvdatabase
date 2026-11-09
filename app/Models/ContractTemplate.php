<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContractTemplate extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description', 'is_active', 'created_by',
        'contract_type_id',
        'service',
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

    public function versions()
    {
        return $this->hasMany(ContractTemplateVersion::class);
    }

    /**
     * Only customer and employment templates are versioned. Other agreements
     * (including custom contract types) keep one directly editable document.
     */
    public function usesVersioning(): bool
    {
        return in_array($this->contractType?->slug, ['customer', 'employment'], true);
    }

    public function getTypeSlugAttribute(): ?string
    {
        return $this->contractType?->slug;
    }
}
