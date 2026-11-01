<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Carbon;
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
        'currency_id',
        'acquisition_date',
        'useful_life_years',
        'salvage_value',
        'model',
        'condition',
        'office_id',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'price' => 'decimal:2',
            'acquisition_date' => 'date',
            'useful_life_years' => 'integer',
            'salvage_value' => 'decimal:2',
        ];
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

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    /** `price` is the unit price, so the register total is quantity × price. */
    public function totalValue(): float
    {
        return round((int) $this->quantity * (float) $this->price, 2);
    }

    /**
     * Straight-line book value using completed months. A missing useful life
     * (or acquisition date) means the item is not depreciated.
     */
    public function bookValue(?CarbonInterface $asOf = null): ?float
    {
        if (! $this->useful_life_years || ! $this->acquisition_date) {
            return null;
        }

        $lifeMonths = (int) $this->useful_life_years * 12;
        if ($lifeMonths <= 0) {
            return null;
        }

        $asOf = $asOf ? Carbon::parse($asOf) : now();
        $elapsedMonths = (int) floor($this->acquisition_date->copy()->startOfDay()->diffInMonths($asOf->copy()->startOfDay()));
        $elapsedMonths = max(0, $elapsedMonths);
        $ratio = min(1, $elapsedMonths / $lifeMonths);

        $total = $this->totalValue();
        $salvage = min($total, max(0.0, (float) $this->salvage_value));
        $book = $total - (($total - $salvage) * $ratio);

        return round(max($salvage, $book), 2);
    }
}
