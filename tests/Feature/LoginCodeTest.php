<?php

use App\Livewire\Auth\Login;
use App\Livewire\Pages\Profil;
use App\Mail\LoginCodeMail;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

test('a code from the mail signs in, a wrong one does not, and the password can be reset in the profile', function () {
    Mail::fake();
    $user = User::factory()->create(['email' => 'clemens@example.com']);

    $component = Livewire::test(Login::class)
        ->call('useCode')
        ->set('email', 'Clemens@Example.com')
        ->call('sendCode')
        ->assertSet('codeSent', true)
        ->assertSee('Code');

    $code = null;
    Mail::assertSent(LoginCodeMail::class, function (LoginCodeMail $mail) use (&$code) {
        $code = $mail->code;

        return $mail->hasTo('clemens@example.com');
    });

    $component->set('code', $code === '000000' ? '111111' : '000000')->call('loginWithCode')->assertHasErrors('code');
    $this->assertGuest();

    $component->set('code', $code)->call('loginWithCode')->assertRedirect(route('jetzt'));
    $this->assertAuthenticatedAs($user);

    Livewire::actingAs($user)->test(Profil::class)
        ->assertDontSee('Kosten diesen Monat')
        ->set('new_password', 'neues-passwort')
        ->set('new_password_confirmation', 'neues-passwort')
        ->call('savePassword')
        ->assertSet('passwordSaved', true);
    expect(Hash::check('neues-passwort', $user->fresh()->password))->toBeTrue();
});

test('an unknown address gets the same answer and no mail', function () {
    Mail::fake();

    Livewire::test(Login::class)->call('useCode')->set('email', 'niemand@example.com')->call('sendCode')->assertSet('codeSent', true);

    Mail::assertNothingSent();
});
