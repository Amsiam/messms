<?php

namespace App\Filament\Widgets;

use App\Models\Member;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Admin-only overview of every active member's balance.
 */
class MembersBalances extends TableWidget
{
    protected static ?int $sort = -2;

    protected int | string | array $columnSpan = 'full';

    protected static ?string $heading = 'Member balances';

    public static function canView(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Member::active()->orderBy('name'))
            ->paginated(false)
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('balance')
                    ->formatStateUsing(fn ($state) => number_format($state, 2))
                    ->color(fn ($state) => $state < 0 ? 'danger' : 'success')
                    ->sortable()
                    ->alignEnd(),
            ])
            ->emptyStateHeading('No active members');
    }
}
