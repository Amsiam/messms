<?php

namespace App\Filament\Widgets;

use App\Models\Member;
use App\Models\MealItem;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Balance and meal totals for the member linked to the logged-in user.
 * Hidden for admins, who get MembersBalances instead, and for users without
 * a member.
 */
class MyMemberStats extends StatsOverviewWidget
{
    protected static ?int $sort = -2;

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user?->member !== null && ! $user->hasRole('admin');
    }

    protected function getStats(): array
    {
        /** @var Member $member */
        $member = auth()->user()->member;

        $mealsThisMonth = $this->mealCount($member, Carbon::now()->startOfMonth());
        $mealsAllTime = $this->mealCount($member);
        $balance = (float) $member->balance;

        return [
            Stat::make('Balance', number_format($balance, 2))
                ->description($balance < 0 ? 'You owe the mess' : 'Available credit')
                ->color($balance < 0 ? 'danger' : 'success'),
            Stat::make('Meals this month', $this->format($mealsThisMonth))
                ->description(Carbon::now()->format('F Y')),
            Stat::make('Total meals', $this->format($mealsAllTime))
                ->description('Since joining'),
        ];
    }

    private function mealCount(Member $member, ?Carbon $from = null): float
    {
        return (float) MealItem::query()
            ->where('member_id', $member->id)
            ->when($from, fn ($query) => $query->whereHas(
                'meal',
                fn ($meal) => $meal->whereDate('date', '>=', $from->toDateString()),
            ))
            ->selectRaw('COALESCE(SUM(breakfast + lunch + dinner), 0) as total')
            ->value('total');
    }

    /** Meals can be fractional (half meals), but whole numbers read cleaner. */
    private function format(float $meals): string
    {
        return floor($meals) == $meals ? (string) (int) $meals : number_format($meals, 1);
    }
}
