<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class PositionUser extends Pivot
{
    protected $table = 'position_user';

    protected $fillable = [
        'position_id',
        'user_id',
        'assigned_at',
        'ended_at',
    ];

    protected $casts = [
        'assigned_at' => 'date',
        'ended_at' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function position()
    {
        return $this->belongsTo(Position::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
