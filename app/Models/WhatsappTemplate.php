<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Support\WhatsappAudience;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A reusable WhatsApp message template.
 *
 * `audience` names the record type the message is written for and decides both
 * which placeholders are valid and who may read or change the row. `message` is
 * plain text — WhatsApp renders no markup beyond its own inline markers — with
 * `{{token}}` placeholders filled from the target record at send time.
 */
class WhatsappTemplate extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'audience',
        'title',
        'labels',
        'message',
        'active',
        'sort_order',
    ];

    protected $casts = [
        'labels' => 'array',
        'active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /** @param Builder<self> $query */
    public function scopeAudience(Builder $query, ?string $audience): void
    {
        $query->when(WhatsappAudience::isValid($audience), fn (Builder $query) => $query->where('audience', $audience));
    }

    /** @param Builder<self> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('active', true);
    }

    /** @param Builder<self> $query */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('title');
    }
}
