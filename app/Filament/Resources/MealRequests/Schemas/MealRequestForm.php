<?php

namespace App\Filament\Resources\MealRequests\Schemas;

use App\Models\Meal;
use App\Models\Member;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class MealRequestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('date')
                    ->required()
                    ->default(now())
                    ->live()
                    // Picking another date reloads that date's current sheet, so
                    // the entry starts from what is already recorded.
                    ->afterStateUpdated(fn ($state, $set, string $operation) => $operation === 'create' && $state
                        ? $set('items', static::sheetFor($state))
                        : null),
                Repeater::make('items')
                    ->relationship()
                    ->label('Meals')
                    ->default(fn () => static::sheetFor(now()))
                    ->schema([
                        Select::make('member_id')
                            // No searchable(): see MealForm.
                            ->options(fn ($get) => Member::activeOrSelected($get('member_id')))
                            ->label('Member')
                            ->required()
                            ->distinct(),
                        TextInput::make('breakfast')->numeric(2)->required()->default('0'),
                        TextInput::make('lunch')->numeric(2)->required()->default('0'),
                        TextInput::make('dinner')->numeric(2)->required()->default('0'),
                    ])->columns(4),
            ])->columns(1);
    }

    /**
     * One row per active member with the meals currently recorded for the
     * date, or zeros where nothing is recorded yet.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function sheetFor($date): array
    {
        $existing = Meal::with('mealItems')
            ->whereDate('date', Carbon::parse($date)->toDateString())
            ->first()
            ?->mealItems
            ->keyBy('member_id');

        return Member::active()->orderBy('name')->get()
            ->mapWithKeys(fn (Member $member) => ['row-' . $member->id => [
                'member_id' => $member->id,
                'breakfast' => $existing?->get($member->id)?->breakfast ?? 0,
                'lunch' => $existing?->get($member->id)?->lunch ?? 0,
                'dinner' => $existing?->get($member->id)?->dinner ?? 0,
            ]])
            ->all();
    }
}
