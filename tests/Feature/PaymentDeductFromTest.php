<?php

use App\Filament\Resources\Payments\Pages\ManagePayments;
use App\Models\Member;
use App\Models\Payment;
use App\Models\User;
use Filament\Actions\CreateAction;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('admin', 'web');
    $user = User::factory()->create();
    $user->assignRole('admin');
    $this->actingAs($user);

    $this->payer = Member::create(['name' => 'Payer', 'balance' => 0, 'join_date' => '2026-01-01']);
    $this->manager = Member::create(['name' => 'Manager', 'balance' => 0, 'join_date' => '2026-01-01']);
});

it('subtracts the amount from the receiving member when chosen', function () {
    Livewire::test(ManagePayments::class)
        ->callAction(CreateAction::class, [
            'note' => 'Cash',
            'amount' => 500,
            'member_id' => $this->payer->id,
            'type' => 'in',
            'deduct_from_member_id' => $this->manager->id,
        ])
        ->assertHasNoActionErrors();

    expect($this->payer->fresh()->balance)->toEqual(500)
        ->and($this->manager->fresh()->balance)->toEqual(-500)
        ->and(Payment::where('member_id', $this->manager->id)->where('type', 'out')->value('note'))
        ->toBe('Received from Payer. Cash');
});

it('leaves other members alone when no receiver is chosen', function () {
    Livewire::test(ManagePayments::class)
        ->callAction(CreateAction::class, [
            'amount' => 300,
            'member_id' => $this->payer->id,
            'type' => 'in',
        ])
        ->assertHasNoActionErrors();

    expect($this->payer->fresh()->balance)->toEqual(300)
        ->and($this->manager->fresh()->balance)->toEqual(0)
        ->and(Payment::count())->toBe(1);
});
