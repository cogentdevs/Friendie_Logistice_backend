<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SafetyEnvironmentTest extends TestCase
{
    use DatabaseTransactions;

    public function test_database_and_filesystem_are_isolated(): void
    {
        $this->assertTrue(app()->environment('testing'));
        $this->assertMatchesRegularExpression('/^friendie_logistics_testing_[0-9_]+$/', DB::connection()->getDatabaseName());
        $this->assertNotSame('friendie_logistics', DB::connection()->getDatabaseName());
        $this->assertNotSame(base_path('public'), public_path());
        $this->assertNotSame(base_path('storage'), storage_path());

        DB::table('contact_messages')->insert([
            'name' => 'Synthetic safety test', 'email' => 'safety@example.test',
            'phone' => 'synthetic', 'subject' => 'Isolation verification',
            'message' => 'Rolled back after this test.', 'date' => '2026-11-11',
        ]);
        $this->assertSame(1, DB::table('contact_messages')->count());
        Storage::disk('public')->put('safety-check.txt', 'synthetic');
        $this->assertSame('synthetic', Storage::disk('public')->get('safety-check.txt'));
        $routes = collect(app('router')->getRoutes())->map(fn ($route) => $route->uri());
        $this->assertTrue($routes->contains('/'));
        $this->assertTrue($routes->contains('admin/dashboard'));
        $this->assertTrue($routes->contains('login'));
        Mail::assertNothingSent();
        Notification::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_unmocked_laravel_http_requests_are_blocked(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('without a matching fake');
        Http::get('https://outbound-must-be-blocked.invalid');
    }
}
