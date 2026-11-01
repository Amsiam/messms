<?php

namespace App\Filament\Resources\MealRequests\Tables;

use App\Filament\Resources\MealRequests\MealRequestResource;
use App\Models\MealRequest;
use App\Services\MealRequestApprover;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class MealRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query
                ->withCount('items')
                ->withSum('items as breakfast_total', 'breakfast')
                ->withSum('items as lunch_total', 'lunch')
                ->withSum('items as dinner_total', 'dinner'))
            ->columns([
                TextColumn::make('date')->date('d M Y')->sortable(),
                TextColumn::make('items_count')->label('Members'),
                TextColumn::make('total_meals')
                    ->label('Total meals')
                    ->state(fn (MealRequest $record) => $record->breakfast_total + $record->lunch_total + $record->dinner_total),
                TextColumn::make('requestedBy.name')
                    ->label('Entered by')
                    ->visible(fn () => MealRequestResource::isAdmin()),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                    }),
                TextColumn::make('approvedBy.name')
                    ->label('Decided by')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Entered at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'pending' => 'Pending',
                    'approved' => 'Approved',
                    'rejected' => 'Rejected',
                ]),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription(fn (MealRequest $record) => "These meals will replace the recorded meals of the listed members on {$record->date->format('d M Y')}.")
                    ->visible(fn (MealRequest $record) => $record->isPending() && MealRequestResource::isAdmin())
                    ->action(function (MealRequest $record) {
                        app(MealRequestApprover::class)->approve($record, auth()->user());

                        Notification::make()
                            ->success()
                            ->title('Meal entry approved')
                            ->body("Meals for {$record->date->format('d M Y')} have been updated.")
                            ->send();
                    }),
                Action::make('reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (MealRequest $record) => $record->isPending() && MealRequestResource::isAdmin())
                    ->action(function (MealRequest $record) {
                        app(MealRequestApprover::class)->reject($record, auth()->user());

                        Notification::make()
                            ->warning()
                            ->title('Meal entry rejected')
                            ->send();
                    }),
                DeleteAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
