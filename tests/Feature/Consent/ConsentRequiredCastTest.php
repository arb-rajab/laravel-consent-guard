<?php

declare(strict_types=1);

use ArbRajab\ConsentGuard\Consent\ConsentManager;
use ArbRajab\ConsentGuard\Consent\Exceptions\ConsentRequiredException;
use ArbRajab\ConsentGuard\Tests\Fixtures\Person;

beforeEach(function () {
    $this->migrateConsentTable();
    $this->migratePeopleTable();
});

afterEach(function () {
    $this->dropConsentTable();
    $this->dropPeopleTable();
});

it('throws on write when the model has no recorded consent for the cast field\'s purpose', function () {
    $person = Person::create(['name' => 'Ada']);

    expect(fn () => $person->update(['ssn' => '123-45-6789']))
        ->toThrow(ConsentRequiredException::class, 'requires valid, non-withdrawn consent');
});

it('allows write once the model has granted consent for the purpose', function () {
    $person = Person::create(['name' => 'Ada']);
    app(ConsentManager::class)->grant(Person::class, $person->id, 'background_check');

    $person->update(['ssn' => '123-45-6789']);

    expect($person->fresh()->ssn)->toBe('123-45-6789');
});

it('throws on read once consent has been withdrawn, even though the value was already set', function () {
    $person = Person::create(['name' => 'Ada']);
    $manager = app(ConsentManager::class);
    $manager->grant(Person::class, $person->id, 'background_check');
    $person->update(['ssn' => '123-45-6789']);

    $manager->withdraw(Person::class, $person->id, 'background_check');

    expect(fn () => $person->fresh()->ssn)
        ->toThrow(ConsentRequiredException::class, 'requires valid, non-withdrawn consent');
});

it('fails closed on a model that has no determinable identity yet', function () {
    $person = new Person(['name' => 'Ada']);

    expect(fn () => $person->ssn = '123-45-6789')
        ->toThrow(ConsentRequiredException::class, 'Cannot determine the consent subject');
});
