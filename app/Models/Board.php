<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Support\EmployeeScope;
use App\Support\ServiceBoard;
use Database\Factories\BoardFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Board extends Model
{
    /** @use HasFactory<BoardFactory> */
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = ['title', 'order', 'user_id'];

    /**
     * Boards the user may see: their own private personal board plus the shared
     * boards they belong to (or every shared board with `view_all_boards`).
     * A personal board is never exposed to anyone but its owner, including
     * super-admins.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $canSeeAllShared = $user->hasPermission('view_all_boards');
        $serviceTitles = ServiceBoard::serviceTitles();
        $allowedServiceTitles = ServiceBoard::titlesVisibleTo($user);

        return $query->where(function (Builder $q) use ($user, $canSeeAllShared, $serviceTitles, $allowedServiceTitles) {
            $q->where('user_id', $user->id)
                ->orWhere(function (Builder $shared) use ($user, $canSeeAllShared, $serviceTitles, $allowedServiceTitles) {
                    $shared->whereNull('user_id')
                        ->where(function (Builder $scope) use ($user, $canSeeAllShared, $serviceTitles, $allowedServiceTitles) {
                            // A built-in service board follows its contract-category
                            // permission, not board membership. The unserviced
                            // operational boards keep the membership rule.
                            $scope->whereIn('title', $allowedServiceTitles)
                                ->orWhere(function (Builder $unserviced) use ($user, $canSeeAllShared, $serviceTitles) {
                                    $unserviced->whereNotIn('title', $serviceTitles);
                                    if (! $canSeeAllShared) {
                                        $unserviced->whereHas('users', fn ($members) => $members
                                            ->whereIn('users.id', app(EmployeeScope::class)->visibleUserIds($user)));
                                    }
                                });
                        });
                });
        });
    }

    /** A private personal board: owned by one user, never shared by membership. */
    public function isPersonal(): bool
    {
        return $this->user_id !== null;
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'board_user');
    }
}
