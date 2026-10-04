<?php

namespace App\Http\Middleware;

use App\Support\EditWindow;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts non-super-admin users to editing a record only during the first
 * hour after it was created.
 *
 * The window is enforced app-wide for every PUT/PATCH request that targets a
 * bound Eloquent record. Super administrators (and holders of the
 * `override_edit_window` permission, who are audited) are exempt, and requests
 * that do not target a timestamped record are left untouched.
 */
class EnforceEditWindow
{
    /**
     * Controllers that enforce the window themselves with finer-grained rules
     * (a subtask may still be completed after the window) are left to them so
     * the app-wide default never overrides their intentional carve-outs.
     */
    private const SELF_ENFORCED_ACTIONS = [
        'App\Http\Controllers\SubtaskController@update',
        'App\Http\Controllers\CommentController@update',
        'App\Http\Controllers\PendingTransactionController@update',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('PUT') && ! $request->isMethod('PATCH')) {
            return $next($request);
        }

        $action = $request->route()?->getActionName();
        if (is_string($action) && in_array($action, self::SELF_ENFORCED_ACTIONS, true)) {
            return $next($request);
        }

        $user = $request->user();
        if ($user === null) {
            return $next($request);
        }

        $record = $this->editedRecord($request);

        if ($record !== null) {
            app(EditWindow::class)->enforce($user, $record);
        }

        return $next($request);
    }

    /** The last bound, timestamped model in the route is the record being edited. */
    private function editedRecord(Request $request): ?Model
    {
        $route = $request->route();

        if ($route === null) {
            return null;
        }

        $record = null;

        foreach ($route->parameters() as $parameter) {
            if ($parameter instanceof Model
                && $parameter->usesTimestamps()
                && $parameter->getAttribute('created_at') !== null) {
                $record = $parameter;
            }
        }

        return $record;
    }
}
