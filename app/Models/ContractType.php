<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Services\Contracts\PlaceholderRegistry;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ContractType extends Model
{
    use HasFactory, SoftDeletes, Auditable;

    protected $fillable = ['name', 'slug', 'description', 'is_system', 'is_active'];

    protected $casts = [
        'is_system' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $type) {
            if (blank($type->slug)) {
                $type->slug = static::uniqueSlug($type->name);
            }
        });
    }

    public static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: Str::slug('custom-type');
        $slug = $base;
        $suffix = 2;

        while (static::withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    public function templates()
    {
        return $this->hasMany(ContractTemplate::class);
    }

    public function contracts()
    {
        return $this->hasMany(Contract::class);
    }

    public function isSystemType(): bool
    {
        return $this->is_system || in_array($this->slug, PlaceholderRegistry::SYSTEM_SLUGS, true);
    }
}
