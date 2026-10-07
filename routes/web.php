<?php

use App\Http\Controllers\AudioController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\PhotoController;
use App\Http\Controllers\RoomTextPhotoController;
use App\Livewire\Auth\Login;
use App\Livewire\Pages\Archiv;
use App\Livewire\Pages\Aufnahme;
use App\Livewire\Pages\Jetzt;
use App\Livewire\Pages\Kuenstler;
use App\Livewire\Pages\KuenstlerDetail;
use App\Livewire\Pages\Profil;
use App\Livewire\Pages\RaumText;
use App\Livewire\Pages\Stadt;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Handy-Oberflaeche (docs/grundgeruest.md, Bildschirme)
|--------------------------------------------------------------------------
|
| Vier Reiter: Jetzt, Archiv, Kuenstler, Profil. Anmeldung unter /anmelden (E-Mail + Passwort oder Passkey ueber
| die Routen des Pakets laravel/passkeys). Die Verwaltung liegt unter /admin (Filament, AdminPanelProvider).
|
*/

Route::redirect('/', '/jetzt');

Route::middleware('guest')->group(function (): void {
    Route::get('anmelden', Login::class)->name('login');
});

Route::middleware('auth')->group(function (): void {
    Route::get('jetzt', Jetzt::class)->name('jetzt');
    Route::get('aufnahme/{capture}', Aufnahme::class)->name('aufnahme');
    Route::get('fotos/{photo}', PhotoController::class)->middleware('signed')->name('fotos.show');
    Route::get('audio/{guide}', AudioController::class)->middleware('signed')->name('audio.show');
    Route::get('archiv', Archiv::class)->name('archiv');
    Route::get('raumtext/{roomText}', RaumText::class)->name('raumtext');
    Route::get('raumtexte/{roomText}/foto', RoomTextPhotoController::class)->middleware('signed')->name('raumtexte.foto');
    Route::get('kuenstler', Kuenstler::class)->name('kuenstler');
    Route::get('kuenstler/{artist}', KuenstlerDetail::class)->name('kuenstler.show');
    Route::get('stadt', Stadt::class)->name('stadt');
    Route::get('profil', Profil::class)->name('profil');
    Route::post('abmelden', LogoutController::class)->name('logout');
});
