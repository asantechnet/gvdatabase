<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportTicket extends Model
{
    use HasFactory;

    public const CATEGORIES = ['technical', 'account', 'billing', 'feature_request', 'other'];

    public const SEVERITIES = ['low', 'normal', 'high', 'critical'];

    protected $fillable = [
        'user_id',
        'ticket_number',
        'category',
        'severity',
        'subject',
        'description',
        'page_url',
        'status',
        'recipient_email',
        'submitted_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(SupportTicketAttachment::class);
    }

    public function categoryLabel(): string
    {
        return match ($this->category) {
            'feature_request' => 'Feature request',
            default => ucfirst($this->category),
        };
    }
}
