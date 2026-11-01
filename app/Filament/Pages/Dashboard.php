<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\MyMemberStats;
use App\Models\Meal;
use Carbon\Carbon;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;

/**
 * Adds a month picker that MyMemberStats reads to count the member's meals.
 * Admins don't see member stats, so they don't get the picker either.
 */
class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('month')
                ->label('Meals for month')
                ->options(self::monthOptions())
                ->default(Carbon::now()->format('Y-m'))
                ->selectablePlaceholder(false),
        ]);
    }

    public function getFiltersFormContentComponent(): Component
    {
        return parent::getFiltersFormContentComponent()
            ->visible(fn () => MyMemberStats::canView());
    }

    /**
     * Months in which the logged-in member has meals, newest first. The
     * current month is always offered so the default selection is valid.
     */
    public static function monthOptions(): array
    {
        $member = auth()->user()?->member;

        $mealDates = $member
            ? Meal::query()
                ->whereHas('mealItems', fn ($items) => $items->where('member_id', $member->id))
                ->pluck('date')
            : collect();

        return $mealDates
            ->map(fn ($date) => Carbon::parse($date)->startOfMonth())
            ->push(Carbon::now()->startOfMonth())
            ->unique(fn (Carbon $month) => $month->format('Y-m'))
            ->sortByDesc(fn (Carbon $month) => $month->timestamp)
            ->mapWithKeys(fn (Carbon $month) => [$month->format('Y-m') => $month->format('F Y')])
            ->all();
    }
}
