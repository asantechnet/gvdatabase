<?php

namespace App\Models;

use App\Models\Concerns\HasDocumentNumber;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Contract extends Model
{
    use HasDocumentNumber, HasFactory;

    protected $fillable = [
        'template_id',
        'status',
        'start_date',
        'counterparty',
        'is_cancelled',
    ];

    protected $casts = [
        'start_date' => 'date',
        'is_cancelled' => 'boolean',
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
