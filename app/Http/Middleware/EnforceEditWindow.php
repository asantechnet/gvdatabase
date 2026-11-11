<?php

namespace App\Http\Middleware;

use App\Support\EditWindow;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts employee profile edits to the first hour after the record was
 * created. Super administrators and holders of `override_edit_window` (who are
 * audited) are exempt.
 *
 * The middleware is mounted only on the employee update route. Tasks,
 * contracts, comments, subtasks, finance and master data keep their own
 * behavior; the employee-only scope is an explicit product decision (EW-001).
 */
class EnforceEditWindow
{

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('PUT') && ! $request->isMethod('PATCH')) {
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
