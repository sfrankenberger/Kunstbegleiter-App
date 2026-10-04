<?php

use App\Livewire\Auth\Login;
use App\Models\User;
use Livewire\Livewire;

test('login page loads', function () {
    $this->get('/anmelden')->assertOk()->assertSee('Anmelden')->assertSee('Mit Passkey anmelden');
});

test('guests are sent to the login page', function () {
    $this->get('/jetzt')->assertRedirect('/anmelden');
    $this->get('/')->assertRedirect('/jetzt');
});

test('a user signs in with email and password', function () {
    $user = User::factory()->create(['email' => 'sebastian@example.com']);

    Livewire::test(Login::class)
        ->set('email', 'Sebastian@example.com')
        ->set('password', 'password')
        ->call('login')
        ->assertHasNoErrors()
        ->assertRedirect('/jetzt');

    $this->assertAuthenticatedAs($user);
});

test('a wrong password is rejected', function () {
    User::factory()->create(['email' => 'sebastian@example.com']);

    Livewire::test(Login::class)
        ->set('email', 'sebastian@example.com')
        ->set('password', 'falsch')
        ->call('login')
        ->assertHasErrors(['email']);

    $this->assertGuest();
});

test('a user signs out', function () {
    $this->actingAs(User::factory()->create())->post('/abmelden')->assertRedirect('/anmelden');
    $this->assertGuest();
});

test('passkey routes of the package are registered', function () {
    $this->getJson('/passkeys/login/options')->assertOk()->assertJsonStructure(['options' => ['challenge']]);
    $this->actingAs(User::factory()->create())->getJson('/user/passkeys/options')->assertOk()->assertJsonStructure(['options' => ['challenge', 'rp', 'user']]);
});
