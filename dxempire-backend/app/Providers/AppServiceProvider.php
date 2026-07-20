<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Force HTTPS in production (behind load balancer / Nginx proxy)
        if (app()->environment('production')) {
            URL::forceScheme('https');
        }

        // Prevent lazy loading N+1 in local dev — fail loudly (off in testing so middleware works cleanly)
        Model::preventLazyLoading(app()->environment('local'));

        // Remove the "data" wrapper from API resources for consistency
        // (our ApiResponse trait already wraps, so keep this off)
        JsonResource::withoutWrapping();
    }
}
