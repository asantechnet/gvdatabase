<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Hides the lowest-id ("first") record — the seeded system / super-admin
 * account — from listing and selection queries.
 *
 * This is a *local* scope applied explicitly by the controllers that list or
 * offer records, so authentication (login by email) and direct route model
 * binding keep working for the account itself.
 */
trait ExcludesPrimaryRecord
{
    /** The lowest-id non-trashed record, if any. */
    public static function primaryRecordId(): ?int
    {
        $id = static::query()->min((new static)->getKeyName());

        return $id !== null ? (int) $id : null;
    }

    public function scopeExcludePrimaryRecord(Builder $query): Builder
    {
        $id = static::primaryRecordId();

        return $id === null ? $query : $query->whereKeyNot($id);
    }
}
