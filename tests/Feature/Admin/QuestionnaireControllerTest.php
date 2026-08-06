<?php

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Models\QuestionnaireResponse;
use App\Support\Permission\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

uses(RefreshDatabase::class);

/** Roadmap task 5.8. */
function useInMemoryMainConnectionForQuestionnaire(): void
{
    InMemoryConnection::setup('main', [
        'nuke_authors' => MainSchema::nukeAuthors(),
        'estebian' => MainSchema::estebian(),
    ]);
}

beforeEach(function () {
    useInMemoryMainConnectionForQuestionnaire();
});

it('lists questionnaire responses for a permitted admin', function () {
    $admin = AdminUser::on('main')->create(['aid' => 'staff', 'password' => 'x']);
    $admin->givePermissionTo(Permission::firstOrCreate(['name' => 'questionnaire.listallquest', 'guard_name' => 'admin']));
    $this->actingAs($admin, 'admin');

    QuestionnaireResponse::create(['username' => 'Ahmed', 'mobile' => '0100', 'email' => 'a@example.com']);

    $this->get(route('admin.questionnaire.index'))->assertOk()->assertSee('Ahmed');
});

it('shows one response\'s full remarks fields', function () {
    $admin = AdminUser::on('main')->create(['aid' => 'staff', 'password' => 'x']);
    $admin->givePermissionTo(Permission::firstOrCreate(['name' => 'questionnaire.listquest', 'guard_name' => 'admin']));
    $this->actingAs($admin, 'admin');

    $response = QuestionnaireResponse::create(['username' => 'Ahmed', 'remarks3' => 'Fiqh specialization']);

    $this->get(route('admin.questionnaire.show', $response))->assertOk()->assertSee('Fiqh specialization');
});

it('rejects an admin without either questionnaire permission', function () {
    $admin = AdminUser::on('main')->create(['aid' => 'nobody', 'password' => 'x']);
    $this->actingAs($admin, 'admin');

    $this->get(route('admin.questionnaire.index'))->assertForbidden();
});
