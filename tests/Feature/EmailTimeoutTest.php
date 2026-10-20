<?php

namespace Tests\Feature;

use App\Notifications\ConfirmarEmailNotification;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Notifications\AnonymousNotifiable;
use Tests\TestCase;

class EmailTimeoutTest extends TestCase
{
    public function test_job_de_email_permite_renderizacao_e_smtp_sem_ultrapassar_reserva(): void
    {
        $notification = new ConfirmarEmailNotification('http://localhost/email/verify?token=teste', false, 5);
        $job = new SendQueuedNotifications(new AnonymousNotifiable, $notification, ['mail']);
        $this->assertSame(90, $job->timeout);
        $this->assertGreaterThan($job->timeout, config('horizon.defaults.emails.timeout'));
        $this->assertGreaterThan(config('horizon.defaults.emails.timeout'), config('queue.connections.redis.retry_after'));
        $this->assertSame(3, $job->tries);
        $this->assertTrue($job->shouldBeEncrypted);
    }
}
