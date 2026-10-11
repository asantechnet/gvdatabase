<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContractDocumentDesign extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = ['contract_type_id', 'service', 'letterhead', 'cover_design', 'safe_x', 'safe_y', 'safe_width', 'safe_height'];

    protected function casts(): array
    {
        return ['safe_x' => 'float', 'safe_y' => 'float', 'safe_width' => 'float', 'safe_height' => 'float'];
    }

    public function contractType(): BelongsTo
    {
        return $this->belongsTo(ContractType::class);
    }
}
