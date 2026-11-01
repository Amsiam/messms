<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\MyMemberStats;
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

    /** The current month and the eleven before it, newest first. */
    public static function monthOptions(): array
    {
        return collect(range(0, 11))
            ->map(fn (int $i) => Carbon::now()->startOfMonth()->subMonths($i))
            ->mapWithKeys(fn (Carbon $month) => [$month->format('Y-m') => $month->format('F Y')])
            ->all();
    }
}
