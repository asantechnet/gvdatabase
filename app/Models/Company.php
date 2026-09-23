<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use HasFactory, SoftDeletes, Auditable;

    protected $fillable = [
        'legal_name',
        'trade_name',
        'registration_number',
        'tax_number',
        'national_id',
        'legal_form',
        'email',
        'phone',
        'website',
        'country',
        'province',
        'city',
        'postal_code',
        'address_line',
        'industry',
        'description',
        'representative_name',
        'representative_title',
        'representative_phone',
        'representative_email',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function contracts()
    {
        return $this->hasMany(Contract::class);
    }

    public function outsourcedContractServices()
    {
        return $this->hasMany(ContractService::class, 'outsourcing_company_id');
    }

    public function outsourcingAccruals()
    {
        return $this->hasMany(OutsourcingAccrual::class);
    }

    public function outsourcingSettlements()
    {
        return $this->hasMany(OutsourcingSettlement::class);
    }
}
