<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('kunst:user creates a user and prints the password', function () {
    $this->artisan('kunst:user', ['email' => 'Martha@Example.com', '--name' => 'Martha', '--admin' => true])
        ->expectsOutputToContain('Nutzer angelegt: Martha <martha@example.com> (Admin)')
        ->expectsOutputToContain('Passwort: ')
        ->assertSuccessful();

    $user = User::query()->firstWhere('email', 'martha@example.com');
    expect($user)->not->toBeNull()->and($user->is_admin)->toBeTrue();
});

test('kunst:user updates an existing user and can reset the password', function () {
    $user = User::factory()->create(['email' => 'sebastian@example.com', 'name' => 'Alt']);
    $oldHash = $user->password;

    $this->artisan('kunst:user', ['email' => 'sebastian@example.com', '--name' => 'Sebastian', '--password' => true])
        ->expectsOutputToContain('Nutzer aktualisiert: Sebastian')
        ->expectsOutputToContain('Neues Passwort: ')
        ->assertSuccessful();

    expect($user->fresh()->name)->toBe('Sebastian')
        ->and($user->fresh()->password)->not->toBe($oldHash)
        ->and(Hash::check('password', $user->fresh()->password))->toBeFalse();
});

test('kunst:user rejects an invalid address', function () {
    $this->artisan('kunst:user', ['email' => 'keine-adresse'])->assertFailed();
});
