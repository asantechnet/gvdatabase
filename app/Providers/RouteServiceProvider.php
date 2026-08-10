<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "web" routes file.
     *
     * @var string
     */
    protected $webRoutesPath = 'routes/web.php';

    /**
     * The path to your application's "api" routes file.
     *
     * @var string
     */
    protected $apiRoutesPath = 'routes/api.php';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        $this->routes(function () {
            Route::middleware('web')
                ->group(base_path($this->webRoutesPath));

            Route::prefix('api')
                ->middleware('api')
                ->group(base_path($this->apiRoutesPath));
        });
    }
}
