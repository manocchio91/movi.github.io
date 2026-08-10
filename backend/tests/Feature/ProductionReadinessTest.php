<?php

namespace Tests\Feature;

use App\Mail\OperationalMessage;
use App\Models\Booking;
use App\Models\Event;
use App\Models\User;
use App\Services\OperationalEmailService;
use Illuminate\Support\Facades\Mail;
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
}
