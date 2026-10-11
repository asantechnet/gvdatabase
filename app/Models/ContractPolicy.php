<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContractPolicy extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = ['label', 'type', 'service', 'position_id', 'country_id', 'language_id', 'is_active', 'header', 'content'];

    protected $casts = ['is_active' => 'boolean'];

    public function templates()
    {
        return $this->belongsToMany(ContractTemplate::class, 'contract_policy_contract_template')
            ->withPivot('sort_order')->orderByPivot('sort_order');
    }

    public function position()
    {
        return $this->belongsTo(Position::class);
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function language()
    {
        return $this->belongsTo(Language::class);
    }
}
