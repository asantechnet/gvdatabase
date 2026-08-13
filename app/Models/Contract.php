<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasDocumentNumber;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Contract extends Model
{
    use HasDocumentNumber, HasFactory, Auditable;

    protected $fillable = [
        'template_id',
        'status',
        'start_date',
        'end_date',
        'counterparty',
        'counterparty_company',
        'counterparty_email',
        'counterparty_contact',
        'counterparty_address',
        'is_cancelled',
        'cancelled_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_cancelled' => 'boolean',
        'cancelled_at' => 'immutable_datetime',
    ];

    protected function documentNumberColumn(): string
    {
        return 'contract_number';
    }

    protected function documentNumberPrefix(): string
    {
        return 'CTR';
    }

    public function template()
    {
        return $this->belongsTo(ContractTemplate::class);
    }

    public function getNameAttribute()
    {
        return $this->template->name;
    }

    public function getTypeAttribute()
    {
        return $this->template->type;
    }
}
