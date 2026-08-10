<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class PermissionPosition extends Pivot
{
    protected $table = 'permission_position';

    protected $fillable = [
        'permission_id',
        'position_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function permission()
    {
        return $this->belongsTo(Permission::class);
    }

    public function position()
    {
        return $this->belongsTo(Position::class);
    }
}
