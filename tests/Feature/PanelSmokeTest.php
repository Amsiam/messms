<?php

use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('admin', 'web');
    foreach (['manage_meals', 'manage_expenses', 'manage_payments', 'manage_members', 'view_reports'] as $p) {
        Permission::findOrCreate($p, 'web');
    }
    $user = User::factory()->create();
    $user->assignRole('admin');
    $this->actingAs($user);
});

/** Every admin page renders after the Filament 4.12 upgrade. */
it('renders every panel page', function (string $url) {
    $this->get($url)->assertSuccessful();
})->with([
    '/admin',
    '/admin/members',
    '/admin/meals',
    '/admin/meal-schedules',
    '/admin/meal-requests',
    '/admin/meal-requests/create',
    '/admin/expenses',
    '/admin/payments',
    '/admin/expense-requests',
    '/admin/payment-requests',
    '/admin/users',
    '/admin/report-page',
]);
