<?php

namespace Amplify\System\Utility;

use Amplify\System\Utility\Listeners\ApiLogListener;
use Amplify\System\Utility\Listeners\MailLogListener;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Http\Client\Events\ConnectionFailed;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        ResponseReceived::class => [
            ApiLogListener::class,
        ],
        ConnectionFailed::class => [
            ApiLogListener::class,
        ],
        MessageSending::class => [
            MailLogListener::class,
        ],
        MessageSent::class => [
            MailLogListener::class,
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
}
