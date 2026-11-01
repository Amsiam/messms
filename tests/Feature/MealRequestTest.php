<?php

use App\Filament\Resources\MealRequests\MealRequestResource;
use App\Filament\Resources\MealRequests\Pages\CreateMealRequest;
use App\Filament\Resources\MealRequests\Pages\ListMealRequests;
use App\Filament\Resources\Meals\MealResource;
use App\Models\Meal;
use App\Models\MealItem;
use App\Models\MealRequest;
use App\Models\Member;
use App\Models\User;
use App\Services\MealRequestApprover;
use Carbon\Carbon;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Carbon::setTestNow('2026-11-15 10:00:00');

    Role::findOrCreate('admin', 'web');
    Role::findOrCreate('member', 'web');
    foreach (['manage_meals', 'manage_expenses', 'manage_payments', 'manage_members', 'view_reports'] as $p) {
        Permission::findOrCreate($p, 'web');
    }

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->manager = User::factory()->create();
    $this->manager->assignRole('member');
    $this->manager->givePermissionTo('manage_meals');

    $this->alice = Member::create(['name' => 'Alice', 'balance' => 0, 'join_date' => '2026-01-01']);
    $this->bob = Member::create(['name' => 'Bob', 'balance' => 0, 'join_date' => '2026-01-01']);
});

afterEach(fn () => Carbon::setTestNow());

function pendingRequest(User $by, string $date, array $rows): MealRequest
{
    $request = MealRequest::create(['date' => $date, 'requested_by' => $by->id]);
    foreach ($rows as $memberId => [$b, $l, $d]) {
        $request->items()->create(['member_id' => $memberId, 'breakfast' => $b, 'lunch' => $l, 'dinner' => $d]);
    }

    return $request;
}

it('sends manage_meals users to meal entries instead of the meal sheet', function () {
    $this->actingAs($this->manager);

    expect(MealRequestResource::canViewAny())->toBeTrue()
        ->and(MealResource::canViewAny())->toBeFalse();

    $this->get('/admin/meal-requests')->assertSuccessful();
    $this->get('/admin/meal-requests/create')->assertSuccessful();
    $this->get('/admin/meals')->assertForbidden();
});

it('keeps meal entries away from users without manage_meals', function () {
    $plain = User::factory()->create();
    $plain->assignRole('member');
    $this->actingAs($plain);

    expect(MealRequestResource::canViewAny())->toBeFalse();
});

it('prefills the entry form with the date\'s current sheet', function () {
    $meal = Meal::create(['date' => '2026-11-15']);
    MealItem::create(['meal_id' => $meal->id, 'member_id' => $this->alice->id, 'breakfast' => 1, 'lunch' => 1, 'dinner' => 0]);

    $this->actingAs($this->manager);

    $items = array_values(Livewire::test(CreateMealRequest::class)->get('data.items'));

    expect($items)->toHaveCount(2)
        ->and($items[0])->toMatchArray(['member_id' => $this->alice->id, 'breakfast' => 1.0, 'lunch' => 1.0, 'dinner' => 0])
        ->and($items[1])->toMatchArray(['member_id' => $this->bob->id, 'breakfast' => 0, 'lunch' => 0, 'dinner' => 0]);
});

it('saves a submitted entry as pending without touching the meal sheet', function () {
    $this->actingAs($this->manager);

    Livewire::test(CreateMealRequest::class)
        ->fillForm(['date' => '2026-11-14'])
        ->call('create')
        ->assertHasNoFormErrors();

    $request = MealRequest::sole();

    expect($request->status)->toBe('pending')
        ->and($request->requested_by)->toBe($this->manager->id)
        ->and($request->date->toDateString())->toBe('2026-11-14')
        ->and($request->items)->toHaveCount(2)
        ->and(Meal::count())->toBe(0);
});

it('writes the meals on approval, overwriting only the listed members', function () {
    $meal = Meal::create(['date' => '2026-11-10']);
    MealItem::create(['meal_id' => $meal->id, 'member_id' => $this->alice->id, 'breakfast' => 1, 'lunch' => 1, 'dinner' => 1]);
    MealItem::create(['meal_id' => $meal->id, 'member_id' => $this->bob->id, 'breakfast' => 1, 'lunch' => 1, 'dinner' => 1]);

    $request = pendingRequest($this->manager, '2026-11-10', [$this->alice->id => [0, 2, 0.5]]);

    $this->actingAs($this->admin);
    Livewire::test(ListMealRequests::class)->callTableAction('approve', $request);

    $items = $meal->mealItems()->get()->keyBy('member_id');

    expect($request->fresh()->status)->toBe('approved')
        ->and($request->fresh()->approved_by)->toBe($this->admin->id)
        ->and($items[$this->alice->id]->only(['breakfast', 'lunch', 'dinner']))->toEqual(['breakfast' => 0, 'lunch' => 2, 'dinner' => 0.5])
        ->and($items[$this->bob->id]->lunch)->toEqual(1)
        ->and(Meal::count())->toBe(1);
});

it('creates the meal sheet on approval when the date has none', function () {
    $request = pendingRequest($this->manager, '2026-11-12', [$this->bob->id => [1, 0, 1]]);

    app(MealRequestApprover::class)->approve($request, $this->admin);

    expect(Meal::whereDate('date', '2026-11-12')->sole()->mealItems()->sole()->member_id)->toBe($this->bob->id);
});

it('leaves the meal sheet alone on rejection and refuses a second decision', function () {
    $request = pendingRequest($this->manager, '2026-11-12', [$this->bob->id => [1, 0, 1]]);

    $this->actingAs($this->admin);
    Livewire::test(ListMealRequests::class)->callTableAction('reject', $request);

    expect($request->fresh()->status)->toBe('rejected')
        ->and(Meal::count())->toBe(0);

    app(MealRequestApprover::class)->approve($request->fresh(), $this->admin);
})->throws(InvalidArgumentException::class);

it('only lets admins approve, and shows managers only their own entries', function () {
    $mine = pendingRequest($this->manager, '2026-11-12', []);
    $other = User::factory()->create();
    $theirs = pendingRequest($other, '2026-11-13', []);

    $this->actingAs($this->manager);

    Livewire::test(ListMealRequests::class)
        ->assertCanSeeTableRecords([$mine])
        ->assertCanNotSeeTableRecords([$theirs])
        ->assertTableActionHidden('approve', $mine)
        ->assertTableActionHidden('reject', $mine);

    expect(MealRequestResource::canEdit($mine))->toBeTrue();

    $mine->update(['status' => 'approved']);
    expect(MealRequestResource::canEdit($mine->fresh()))->toBeFalse();
});
