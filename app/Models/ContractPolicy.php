<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContractPolicy extends Model
{
    use HasFactory, Auditable;

    protected $fillable = ['label', 'type', 'header', 'content'];

    public function templates()
    {
        return $this->belongsToMany(ContractTemplate::class, 'contract_policy_contract_template')
            ->withPivot('sort_order')->orderByPivot('sort_order');
    }
}
