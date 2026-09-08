<?php

namespace Tests\Feature;

use App\Jobs\ReadinessProbe;
use App\Mail\OperationalMessage;
use App\Models\Booking;
use App\Models\Event;
use App\Models\User;
use App\Services\OperationalEmailService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ProductionReadinessTest extends TestCase
{
    public function test_health_reports_application_dependencies_without_details(): void
    {
        $this->getJson('/api/v1/health')->assertOk()->assertExactJson(['status' => 'ok', 'checks' => ['database' => 'ok']]);
    }

    public function test_operational_email_is_queued(): void
    {
        Mail::fake();
        $booking = new Booking;
        $user = new User(['email' => 'customer@example.test']);
        $event = new Event(['title' => 'Evento']);
        $booking->setRelation('user', $user);
        $booking->setRelation('event', $event);
        $booking->booking_code = 'EVT-TEST';
        app(OperationalEmailService::class)->bookingConfirmed($booking);
        Mail::assertQueued(OperationalMessage::class);
    }

    public function test_queue_probe_and_failed_job_storage_are_operational(): void
    {
        Queue::fake();

        $this->artisan('app:queue-smoke')->assertSuccessful();

        Queue::assertPushed(ReadinessProbe::class);
        (new ReadinessProbe)->handle();
        $this->assertNotNull(Cache::get('readiness:queue:last_ok'));
    }

    public function test_cors_allows_the_frontend_analytics_header(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'http://localhost:5173',
            'Access-Control-Request-Method' => 'GET',
            'Access-Control-Request-Headers' => 'authorization,x-analytics-session',
        ])->options('/api/v1/organizer/dashboard')
            ->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173');

        $allowedHeaders = (string) $response->headers->get('Access-Control-Allow-Headers');
        $this->assertStringContainsString('authorization', $allowedHeaders);
        $this->assertStringContainsString('x-analytics-session', $allowedHeaders);
    }
}
