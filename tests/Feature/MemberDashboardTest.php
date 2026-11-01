<?php

use App\Filament\Widgets\MembersBalances;
use App\Filament\Widgets\MyMemberStats;
use App\Filament\Widgets\MyRecentTransactions;
use App\Models\Meal;
use App\Models\MealItem;
use App\Models\Member;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

afterEach(fn () => Carbon::setTestNow());

beforeEach(function () {
    Carbon::setTestNow('2026-11-15 10:00:00');

    Role::findOrCreate('admin', 'web');
    foreach (['manage_meals', 'manage_expenses', 'manage_payments', 'manage_members', 'view_reports'] as $p) {
        Permission::findOrCreate($p, 'web');
    }

    $this->user = User::factory()->create();
    $this->member = Member::create([
        'name' => 'Rahim',
        'balance' => -250.5,
        'join_date' => '2026-01-01',
        'user_id' => $this->user->id,
    ]);
    $this->actingAs($this->user);
});

function dashboardMeal(Member $member, string $date, float $b, float $l, float $d): void
{
    $meal = Meal::firstOrCreate(['date' => $date]);
    MealItem::create(['meal_id' => $meal->id, 'member_id' => $member->id, 'breakfast' => $b, 'lunch' => $l, 'dinner' => $d]);
}

it('shows balance, meals this month and total meals', function () {
    dashboardMeal($this->member, '2026-10-20', 1, 1, 1);   // last month
    dashboardMeal($this->member, '2026-11-02', 0.5, 1, 1); // this month
    dashboardMeal($this->member, '2026-11-10', 1, 0, 1);

    $other = Member::create(['name' => 'Other', 'balance' => 0, 'join_date' => '2026-01-01']);
    dashboardMeal($other, '2026-11-10', 5, 5, 5);

    Livewire::test(MyMemberStats::class)
        ->assertSee('-250.50')
        ->assertSee('You owe the mess')
        ->assertSee('4.5')   // this month
        ->assertSee('7.5');  // all time
});

it('lists only the member\'s last 10 transactions, newest first', function () {
    foreach (range(1, 12) as $i) {
        Payment::create([
            'note' => "Txn {$i}",
            'amount' => $i * 10,
            'type' => $i % 2 ? 'in' : 'out',
            'member_id' => $this->member->id,
            'created_at' => Carbon::now()->subDays(12 - $i),
        ]);
    }
    $other = Member::create(['name' => 'Other', 'balance' => 0, 'join_date' => '2026-01-01']);
    Payment::create(['note' => 'Not mine', 'amount' => 1, 'type' => 'in', 'member_id' => $other->id]);

    Livewire::test(MyRecentTransactions::class)
        ->assertSeeInOrder(['Txn 12', 'Txn 11', 'Txn 3'])
        ->assertDontSee('Txn 2')
        ->assertDontSee('Txn 1<')
        ->assertDontSee('Not mine');
});

it('hides the widgets for a user without a member', function () {
    $this->actingAs(User::factory()->create());

    expect(MyMemberStats::canView())->toBeFalse()
        ->and(MyRecentTransactions::canView())->toBeFalse();

    $this->get('/admin')->assertSuccessful();
});

it('renders the dashboard for a member user', function () {
    $this->get('/admin')->assertSuccessful();
});

it('shows admins every active member balance instead of personal widgets', function () {
    Member::create(['name' => 'Karim', 'balance' => 120, 'join_date' => '2026-01-01']);
    Member::create(['name' => 'Gone', 'balance' => 99, 'join_date' => '2026-01-01', 'status' => 'inactive']);
    $this->user->assignRole('admin');

    expect(MembersBalances::canView())->toBeTrue()
        ->and(MyMemberStats::canView())->toBeFalse()
        ->and(MyRecentTransactions::canView())->toBeFalse();

    Livewire::test(MembersBalances::class)
        ->assertSeeInOrder(['Karim', '120.00', 'Rahim', '-250.50'])
        ->assertDontSee('Gone');

    $this->get('/admin')->assertSuccessful();
});

it('hides the balances table from non-admins', function () {
    expect(MembersBalances::canView())->toBeFalse();
});
