<?php

/*
|--------------------------------------------------------------------------
| Domain Boundary Enforcement (Blueprint v1.0 §2, Roadmap task 0.8)
|--------------------------------------------------------------------------
|
| Pest arch tests are the sole boundary-enforcement mechanism for Wave 0 —
| Deptrac is deferred (Blueprint Part III) and added only if these prove
| insufficient. Both rules below are the two named explicitly in §2 as the
| minimum required before CI is considered wired up.
|
*/

// NOTE: ->expect([...]) with an array of multiple namespaces silently
// no-ops on ->not->toUse() in the installed pest-plugin-arch version
// (verified empirically — a deliberate violation in one of the array's
// namespaces did not fail the build). One arch() call per single
// namespace string is the form confirmed to actually catch violations —
// do not collapse these back into an array form without re-verifying.

arch('Content controllers must not query Eloquent models directly')
    ->expect('App\Domain\Content\Http\Controllers')
    ->not->toUse('Illuminate\Database\Eloquent\Model');

arch('Admin controllers must not query Eloquent models directly')
    ->expect('App\Domain\Admin\Http\Controllers')
    ->not->toUse('Illuminate\Database\Eloquent\Model');

arch('Engagement controllers must not query Eloquent models directly')
    ->expect('App\Domain\Engagement\Http\Controllers')
    ->not->toUse('Illuminate\Database\Eloquent\Model');

arch('Pages controllers must not query Eloquent models directly')
    ->expect('App\Domain\Pages\Http\Controllers')
    ->not->toUse('Illuminate\Database\Eloquent\Model');

arch('the Content domain must not depend on the Admin domain\'s internals')
    ->expect('App\Domain\Content')
    ->not->toUse('App\Domain\Admin');

// F01 / B6c-1: the strict stored-URL rule is shared by Admin (B6a/B6b) and Content (B6c); it must depend on neither.
arch('the shared SafeExternalUrl rule depends on no domain')
    ->expect('App\Support\Http\SafeExternalUrl')
    ->not->toUse('App\Domain');

arch('the Admin domain does not depend on the public advertising foundation')
    ->expect('App\Domain\Admin')
    ->not->toUse('App\Domain\Content\Ads');
