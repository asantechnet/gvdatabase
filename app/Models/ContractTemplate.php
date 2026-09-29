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
        'service_id',
        'package_id',
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

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    /** The package this template generates the agreement for, if any. */
    public function package()
    {
        return $this->belongsTo(Package::class);
    }
    public function services()
    {
        return $this->belongsToMany(Service::class, 'contract_template_services')
            ->withPivot('service_variant_id')->withTimestamps();
    }
    public function versions(){ return $this->hasMany(ContractTemplateVersion::class); }

    public function getTypeSlugAttribute(): ?string
    {
        return $this->contractType?->slug;
    }
}
