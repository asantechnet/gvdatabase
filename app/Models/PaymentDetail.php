<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentDetail extends Model
{
    use Auditable, HasFactory;

    protected $fillable = ['method', 'instruction'];

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
