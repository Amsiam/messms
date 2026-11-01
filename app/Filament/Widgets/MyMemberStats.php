<?php

namespace App\Filament\Widgets;

use App\Models\Meal;
use App\Models\Member;
use App\Models\MealItem;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

/**
 * Balance and meal count for the member linked to the logged-in user. Meals
 * are counted for the month picked in the meal card, defaulting to this one.
 * Hidden for admins, who get MembersBalances instead, and for users without
 * a member.
 */
class MyMemberStats extends StatsOverviewWidget
{
    protected static ?int $sort = -2;

    protected ?string $pollingInterval = null;

    /** Selected month as Y-m. */
    public string $month = '';

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user?->member !== null && ! $user->hasRole('admin');
    }

    public function mount(): void
    {
        $this->month = Carbon::now()->format('Y-m');
    }

    protected function getStats(): array
    {
        /** @var Member $member */
        $member = auth()->user()->member;

        $options = static::monthOptions($member);
        $month = $this->selectedMonth($options);
        $balance = (float) $member->balance;

        return [
            Stat::make('Balance', number_format($balance, 2))
                ->description($balance < 0 ? 'You owe the mess' : 'Available credit')
                ->color($balance < 0 ? 'danger' : 'success'),
            Stat::make('Meals', $this->format($this->mealCount($member, $month)))
                ->description($this->monthSelect($options)),
        ];
    }

    /**
     * Months in which the member has meals, newest first. The current month is
     * always offered so the default selection is valid.
     */
    public static function monthOptions(Member $member): array
    {
        return Meal::query()
            ->whereHas('mealItems', fn ($items) => $items->where('member_id', $member->id))
            ->pluck('date')
            ->map(fn ($date) => Carbon::parse($date)->startOfMonth())
            ->push(Carbon::now()->startOfMonth())
            ->unique(fn (Carbon $month) => $month->format('Y-m'))
            ->sortByDesc(fn (Carbon $month) => $month->timestamp)
            ->mapWithKeys(fn (Carbon $month) => [$month->format('Y-m') => $month->format('F Y')])
            ->all();
    }

    /** The picked month if it is one of the offered ones, else this month. */
    private function selectedMonth(array $options): Carbon
    {
        return isset($options[$this->month])
            ? Carbon::createFromFormat('Y-m-d', "{$this->month}-01")->startOfDay()
            : Carbon::now()->startOfMonth();
    }

    private function monthSelect(array $options): HtmlString
    {
        return new HtmlString(Blade::render(<<<'BLADE'
            <x-filament::input.wrapper style="margin-top: 0.25rem">
                <x-filament::input.select wire:model.live="month" aria-label="Meals for month">
                    @foreach ($options as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
            BLADE, ['options' => $options]));
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
