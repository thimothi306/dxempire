<?php

namespace App\Providers;

use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        \App\Events\ProductReceived::class => [
            \App\Listeners\NotifyQcTeam::class,
        ],
        \App\Events\StockAdded::class      => [
            \App\Listeners\NotifyPartnersOnStockAdded::class,
        ],
        \App\Events\ProductRejected::class => [],
        \App\Events\OrderApproved::class   => [
            \App\Listeners\NotifyWarehouseOnOrderApproved::class,
            \App\Listeners\NotifyPartnerOnOrderApproved::class,
        ],
        \App\Events\OrderDispatched::class => [
            \App\Listeners\NotifyDealerOnOrderDispatched::class,
        ],
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     *
     * @return bool
     */
    public function shouldDiscoverEvents()
    {
        return false;
    }
}
