<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Asset extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    public const CONDITION_NEW = 'new';

    public const CONDITION_GOOD = 'good';

    public const CONDITION_FAIR = 'fair';

    public const CONDITION_POOR = 'poor';

    public const CONDITION_DAMAGED = 'damaged';

    protected $fillable = [
        'serial_number',
        'item',
        'quantity', 'price',
        'model',
        'condition',
        'office_id',
    ];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'price' => 'decimal:2'];
    }

    public static function conditions(): array
    {
        return [
            self::CONDITION_NEW,
            self::CONDITION_GOOD,
            self::CONDITION_FAIR,
            self::CONDITION_POOR,
            self::CONDITION_DAMAGED,
        ];
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(AssetAllocation::class);
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }
}
