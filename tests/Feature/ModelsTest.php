<?php

use App\Models\AiCall;
use App\Models\Artist;
use App\Models\Artwork;
use App\Models\AudioGuide;
use App\Models\Capture;
use App\Models\CapturePhoto;
use App\Models\City;
use App\Models\CityTip;
use App\Models\Epoch;
use App\Models\Exhibition;
use App\Models\FactSheet;
use App\Models\KnowledgeItem;
use App\Models\Museum;
use App\Models\Pairing;
use App\Models\Research;
use App\Models\User;
use App\Models\Visit;
use Database\Seeders\EpochSeeder;
use Spatie\Activitylog\Models\Activity;

test('every factory creates a record', function (string $model) {
    expect($model::factory()->create())->toBeInstanceOf($model);
})->with([
    User::class, Pairing::class, City::class, Museum::class, Exhibition::class, Artist::class, Epoch::class,
    Artwork::class, Visit::class, Capture::class, CapturePhoto::class, Research::class, AudioGuide::class,
    FactSheet::class, KnowledgeItem::class, CityTip::class, AiCall::class,
]);

test('trashing a visit takes captures, photos and guides along and restoring brings them back', function () {
    $visit = Visit::factory()->create();
    $capture = Capture::factory()->for($visit)->create();
    $photo = CapturePhoto::factory()->for($capture)->create();
    $guide = AudioGuide::factory()->for($capture)->create();
    $tip = CityTip::factory()->for($visit)->create();

    $visit->delete();

    expect($capture->fresh()->trashed())->toBeTrue()
        ->and($photo->fresh()->trashed())->toBeTrue()
        ->and($guide->fresh()->trashed())->toBeTrue()
        ->and($tip->fresh()->trashed())->toBeTrue();

    $visit->restore();

    expect($capture->fresh()->trashed())->toBeFalse()
        ->and($photo->fresh()->trashed())->toBeFalse()
        ->and($guide->fresh()->trashed())->toBeFalse()
        ->and($tip->fresh()->trashed())->toBeFalse();
});

test('trashing an artwork leaves the captures in place', function () {
    $capture = Capture::factory()->done()->create();

    $capture->artwork->delete();

    expect($capture->fresh()->trashed())->toBeFalse()
        ->and($capture->fresh()->artwork_id)->not->toBeNull();
});

test('changes are logged with the acting user', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $museum = Museum::factory()->create(['name' => 'Alt']);
    $museum->update(['name' => 'Neu']);

    $log = Activity::query()->where('subject_type', Museum::class)->where('subject_id', $museum->getKey())->get();

    expect($log)->toHaveCount(2)
        ->and($log->last()->event)->toBe('updated')
        ->and($log->last()->causer_id)->toBe($user->getKey())
        ->and($log->last()->attribute_changes['attributes']['name'])->toBe('Neu')
        ->and($museum->changeLog()->count())->toBe(2);
});

test('passwords never reach the change log', function () {
    $user = User::factory()->create();
    $user->update(['password' => 'neues-passwort', 'name' => 'Neu']);

    $last = Activity::query()->where('subject_type', User::class)->latest('id')->first();

    expect($last->attribute_changes['attributes'])->toHaveKey('name')->not->toHaveKey('password');
});

test('the active visit is the one still valid and extend pushes it forward', function () {
    config()->set('museumguide.visit.minutes', 30);
    $user = User::factory()->create();
    Visit::factory()->for($user)->expired()->create();
    $visit = Visit::factory()->for($user)->create(['valid_until' => now()->addMinutes(5)]);

    expect($user->activeVisit()?->is($visit))->toBeTrue();

    $visit->extend();

    expect($visit->fresh()->remainingMinutes())->toBeGreaterThanOrEqual(29);
});

test('monthly costs and limit per user', function () {
    $user = User::factory()->create();
    config()->set('museumguide.costs.monthly_limit_cents', 3000);
    AiCall::factory()->for($user)->create(['cost_cents' => 120]);
    AiCall::factory()->for($user)->create(['cost_cents' => 80, 'created_at' => now()->subMonths(2)]);

    expect(AiCall::monthCents($user))->toBe(120)
        ->and($user->monthlyLimitCents())->toBe(3000)
        ->and(User::factory()->create(['monthly_budget_cents' => 500])->monthlyLimitCents())->toBe(500);
});

test('pairings find the partner regardless of order', function () {
    [$a, $b] = User::factory()->count(2)->create();
    Pairing::factory()->active()->create(Pairing::between($b, $a) + ['requested_by_id' => $b->getKey()]);

    expect($a->partner()?->is($b))->toBeTrue()
        ->and($b->partner()?->is($a))->toBeTrue()
        ->and(User::factory()->create()->partner())->toBeNull();
});

test('the epoch seeder is repeatable', function () {
    $this->seed(EpochSeeder::class);
    $this->seed(EpochSeeder::class);

    expect(Epoch::query()->count())->toBe(count(EpochSeeder::EPOCHS))
        ->and(Epoch::query()->where('slug', 'jugendstil')->exists())->toBeTrue();
});

test('cities get a slug and artists a sort name', function () {
    expect(City::factory()->create(['name' => 'Wien', 'country_code' => 'AT'])->slug)->toBe('wien-at')
        ->and(Artist::factory()->create(['name' => 'Gustav Klimt'])->sort_name)->toBe('Klimt, Gustav');
});
