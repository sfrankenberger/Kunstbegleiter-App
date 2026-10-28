<?php

use App\Mail\ErrorAlertMail;
use App\Models\User;
use App\Support\ErrorAlert;
use Illuminate\Support\Facades\Mail;

test('unexpected exceptions mail the admins once per place and hour', function () {
    Mail::fake();
    $admin = User::factory()->admin()->create();
    User::factory()->create();

    $error = new RuntimeException('Probe kaputt');

    expect(app(ErrorAlert::class)->report($error))->toBeTrue()
        ->and(app(ErrorAlert::class)->report($error))->toBeFalse();

    Mail::assertSentCount(1);
    Mail::assertSent(ErrorAlertMail::class, fn (ErrorAlertMail $mail) => $mail->hasTo($admin->email));
});

test('the switch turns alerts off', function () {
    Mail::fake();
    User::factory()->admin()->create();
    config()->set('museumguide.alert_exceptions', false);

    expect(app(ErrorAlert::class)->report(new RuntimeException('aus')))->toBeFalse();
    Mail::assertNothingSent();
});
