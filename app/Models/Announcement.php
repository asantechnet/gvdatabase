<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A company-wide announcement composed by an authorized employee. Delivery
 * stays synchronized when its sender corrects the announcement or audience.
 */
class Announcement extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'sender_id',
        'title',
        'message',
        'labels',
        'audience',
    ];

    protected function casts(): array
    {
        return [
            'labels' => 'array',
            'audience' => 'array',
        ];
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
