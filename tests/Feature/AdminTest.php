<?php

use App\Filament\Resources\Users\UserResource;
use App\Filament\Support\ActivitiesRelationManager;
use App\Models\User;

test('admins open the panel and the user list', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get('/admin')->assertOk();
    $this->actingAs($admin)->get('/admin/nutzer')->assertOk()->assertSee($admin->name);
    $this->actingAs($admin)->get('/admin/nutzer/'.$admin->getKey().'/bearbeiten')->assertOk()->assertSee('Vorwissen-Profil');
    expect(UserResource::getRelations())->toContain(ActivitiesRelationManager::class);
});

test('users without admin flag are kept out of the panel', function () {
    $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
});

test('backups page is hidden without BACKUP_APP and shown with it', function () {
    $admin = User::factory()->admin()->create();

    config()->set('backup-restore.app', null);
    $this->actingAs($admin)->get('/admin/datensicherungen')->assertForbidden();

    config()->set('backup-restore.app', 'kunst-staging');
    $this->actingAs($admin)->get('/admin/datensicherungen')->assertOk()->assertSee('kunst-staging');
});
