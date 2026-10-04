<?php

use App\Http\Controllers\Auth\LogoutController;
use App\Livewire\Auth\Login;
use App\Livewire\Pages\Archiv;
use App\Livewire\Pages\Entdecken;
use App\Livewire\Pages\Jetzt;
use App\Livewire\Pages\Profil;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Handy-Oberflaeche (docs/grundgeruest.md, Bildschirme)
|--------------------------------------------------------------------------
|
| Vier Reiter: Jetzt, Archiv, Entdecken, Profil. Anmeldung unter /anmelden (E-Mail + Passwort oder Passkey ueber
| die Routen des Pakets laravel/passkeys). Die Verwaltung liegt unter /admin (Filament, AdminPanelProvider).
|
*/

Route::redirect('/', '/jetzt');

Route::middleware('guest')->group(function (): void {
    Route::get('anmelden', Login::class)->name('login');
});

Route::middleware('auth')->group(function (): void {
    Route::get('jetzt', Jetzt::class)->name('jetzt');
    Route::get('archiv', Archiv::class)->name('archiv');
    Route::get('entdecken', Entdecken::class)->name('entdecken');
    Route::get('profil', Profil::class)->name('profil');
    Route::post('abmelden', LogoutController::class)->name('logout');
});
