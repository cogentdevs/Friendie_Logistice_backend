<?php

namespace Tests\Support;

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;

final class SafetyServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (! $this->app->environment('testing')) {
            throw new \RuntimeException('Test safety provider must never run outside testing.');
        }

        Mail::fake();
        Notification::fake();
        Queue::fake();
        Bus::fake();
        Http::preventStrayRequests();
    }
}
