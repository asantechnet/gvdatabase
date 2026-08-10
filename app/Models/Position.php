<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Position extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'salary',
        'status',
        'is_sys_user',
    ];

    protected $casts = [
        'is_sys_user' => 'boolean',
    ];

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }

    public function permissions()
    {
        return $this->belongsToMany(Permission::class)
            ->using(PermissionPosition::class)
            ->withTimestamps();
    }

    public function users()
    {
        return $this->belongsToMany(User::class)
            ->using(PositionUser::class)
            ->withPivot('assigned_at', 'ended_at')
            ->withTimestamps();
    }
}
