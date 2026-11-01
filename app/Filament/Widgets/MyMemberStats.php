<?php

namespace App\Filament\Widgets;

use App\Models\Member;
use App\Models\MealItem;
use Carbon\Carbon;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Balance and meal count for the member linked to the logged-in user. Meals
 * are counted for the month picked on the dashboard, defaulting to this one.
 * Hidden for admins, who get MembersBalances instead, and for users without
 * a member.
 */
class MyMemberStats extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = -2;

    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user?->member !== null && ! $user->hasRole('admin');
    }

    protected function getStats(): array
    {
        /** @var Member $member */
        $member = auth()->user()->member;

        $month = $this->selectedMonth();
        $balance = (float) $member->balance;

        return [
            Stat::make('Balance', number_format($balance, 2))
                ->description($balance < 0 ? 'You owe the mess' : 'Available credit')
                ->color($balance < 0 ? 'danger' : 'success'),
            Stat::make('Meals', $this->format($this->mealCount($member, $month)))
                ->description($month->format('F Y')),
        ];
    }

    private function selectedMonth(): Carbon
    {
        $month = $this->pageFilters['month'] ?? null;

        // Only accept a well-formed Y-m; anything else falls back to this month.
        return $month && preg_match('/^\d{4}-\d{2}$/', $month)
            ? Carbon::createFromFormat('Y-m-d', "{$month}-01")->startOfDay()
            : Carbon::now()->startOfMonth();
    }

    private function mealCount(Member $member, Carbon $month): float
    {
        return (float) MealItem::query()
            ->where('member_id', $member->id)
            ->whereHas('meal', fn ($meal) => $meal
                ->whereDate('date', '>=', $month->toDateString())
                ->whereDate('date', '<=', $month->copy()->endOfMonth()->toDateString()))
            ->selectRaw('COALESCE(SUM(breakfast + lunch + dinner), 0) as total')
            ->value('total');
    }

    /** Meals can be fractional (half meals), but whole numbers read cleaner. */
    private function format(float $meals): string
    {
        return floor($meals) == $meals ? (string) (int) $meals : number_format($meals, 1);
    }
}
