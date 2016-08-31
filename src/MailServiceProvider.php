<?php

namespace Orchestra\Notifier;

use Orchestra\Notifier\Events\CssInliner;
use Illuminate\Mail\Events\MessageSending;
use Orchestra\Support\Providers\Traits\EventProvider;
use Illuminate\Mail\MailServiceProvider as ServiceProvider;

class MailServiceProvider extends ServiceProvider
{
    use EventProvider;

    /**
     * The event handler mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        MessageSending::class => [CssInliner::class],
    ];

    /**
     * The subscriber classes to register.
     *
     * @var array
     */
    protected $subscribe = [];

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        parent::register();

        $this->registerEventListeners($this->app->make('events'));
    }
}
