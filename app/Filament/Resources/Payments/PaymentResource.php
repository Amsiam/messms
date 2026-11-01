<?php

namespace App\Filament\Resources\Payments;

use App\Models\Member;
use App\Filament\Resources\Members\Tables\MembersTable;
use App\Filament\Resources\Payments\Pages\ManagePayments;
use App\Models\Payment;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\ModalTableSelect;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\QueryBuilder;
use Filament\Tables\Filters\QueryBuilder\Constraints\DateConstraint;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Date;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make()->schema([
                    TextInput::make('note'),
                    TextInput::make('amount')->required()->numeric(2),
                    Select::make('member_id')
                        ->options(fn ($get) => Member::activeOrSelected($get('member_id'))),
                    Select::make('type')->options([
                        'in' => 'In',
                        'out' => 'Out',
                    ])->required()->default('out')->live(),
                    Select::make('deduct_from_member_id')
                        ->label('Received by (deduct from)')
                        ->helperText('Optional. The member, e.g. the manager, who took this money. The same amount is subtracted from their balance.')
                        ->options(fn ($get) => Member::activeOrSelected()->except($get('member_id')))
                        ->visible(fn ($get, string $operation) => $operation === 'create' && $get('type') === 'in'),
                ])
                    ->columnSpanFull()
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('note'),
                TextColumn::make('amount')
                    ->numeric()
                    ->sortable()
                    ->summarize([
                        Sum::make()
                    ]),

                TextColumn::make('member.name'),
                TextColumn::make('type'),
                TextColumn::make('created_at')
                    ->dateTime()
            ])->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('member')->relationship('member', 'name', fn ($query) => $query->where('status', 'active')),
                SelectFilter::make('type')->options([
                    'in' => 'In',
                    'out' => 'Out',
                ]),
                Filter::make('created_at')
                    ->schema([
                        DatePicker::make('from')
                            ->label('From'),
                        DatePicker::make('to')
                            ->label('To'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->whereBetween('created_at', [
                                Date::parse($data['from'])->startOfDay(),
                                Date::parse($data['to'])->endOfDay(),
                            ]);
                    })

            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePayments::route('/'),
        ];
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }
}
