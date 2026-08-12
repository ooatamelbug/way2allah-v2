<?php

use App\Domain\Admin\Models\QuestionnaireResponse;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

/**
 * Wave C ("Public Locations & Da'wah Registration Surfaces"). Replaces
 * `wizard.php` — no `.htaccess` rule, raw-path-only. Covers the confirmed
 * quirks: the `password`-named phone field, the discarded `rpassword`,
 * and the no-redirect-after-insert (same view rendered on both GET/POST).
 */
beforeEach(function () {
    InMemoryConnection::setup('main', [
        'estebian' => MainSchema::estebian(),
    ]);
});

it('show: renders the empty 4-step form with every field name legacy uses', function () {
    $response = $this->get('/wizard.php');

    $response->assertOk()
        ->assertSee('استبيان الدعاة')
        ->assertSee('name="username"', false)
        ->assertSee('name="password"', false)
        ->assertSee('name="rpassword"', false)
        ->assertSee('name="facebook"', false)
        ->assertSee('name="email"', false)
        ->assertSee('name="remarks1"', false)
        ->assertSee('name="remarks11"', false);
});

it('store: inserts a response, writing the "password" field to the mobile column (confirmed legacy field-naming quirk)', function () {
    $this->post('/wizard.php', [
        'username' => 'Test Preacher',
        'password' => '0100000000',
        'rpassword' => '0100000000',
        'facebook' => 'https://facebook.com/test',
        'email' => 'test@example.com',
        'remarks1' => 'Degree',
        'remarks11' => 'Suggestion',
    ])->assertOk();

    $response = QuestionnaireResponse::first();
    expect($response->username)->toBe('Test Preacher')
        ->and($response->mobile)->toBe('0100000000')
        ->and($response->facebook)->toBe('https://facebook.com/test')
        ->and($response->email)->toBe('test@example.com')
        ->and($response->remarks1)->toBe('Degree')
        ->and($response->remarks11)->toBe('Suggestion');
});

it('store: does NOT persist rpassword anywhere — legacy collects it but never references it in the INSERT', function () {
    $this->post('/wizard.php', [
        'username' => 'Test',
        'password' => '0100000000',
        'rpassword' => 'THIS-MUST-NOT-BE-STORED',
        'email' => 'test@example.com',
    ]);

    $response = QuestionnaireResponse::first();
    expect((array) $response->getAttributes())->not->toContain('THIS-MUST-NOT-BE-STORED');
});

it('store: renders the same empty form again, not a redirect or a success message — legacy falls through to the identical HTML after INSERT', function () {
    $response = $this->post('/wizard.php', [
        'username' => 'Test',
        'password' => '0100000000',
        'email' => 'test@example.com',
    ]);

    $response->assertOk(); // not a 3xx redirect
    $response->assertSee('name="username"', false);
    $response->assertDontSee('Test'); // the submitted value is not echoed back into the re-rendered form
});
