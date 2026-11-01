<?php

namespace App\Filament\Widgets;

use App\Models\Payment;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * The last ten payments (deposits and charges) of the member linked to the
 * logged-in user. Hidden for admins.
 */
class MyRecentTransactions extends TableWidget
{
    protected static ?int $sort = -1;

    protected int | string | array $columnSpan = 'full';

    protected static ?string $heading = 'Last 10 transactions';

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user?->member !== null && ! $user->hasRole('admin');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Payment::query()
                    ->where('member_id', auth()->user()->member->id)
                    ->latest()
                    ->latest('id')
                    ->limit(10)
            )
            ->paginated(false)
            ->columns([
                TextColumn::make('created_at')->label('Date')->dateTime('d M Y, h:i A'),
                TextColumn::make('note')->placeholder('-')->wrap(),
                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'in' ? 'Deposit' : 'Charge')
                    ->color(fn (string $state) => $state === 'in' ? 'success' : 'danger'),
                TextColumn::make('amount')
                    ->formatStateUsing(fn ($state, Payment $record) => ($record->type === 'in' ? '+' : '-') . number_format($state, 2))
                    ->color(fn (Payment $record) => $record->type === 'in' ? 'success' : 'danger')
                    ->alignEnd(),
            ])
            ->emptyStateHeading('No transactions yet');
    }
}
