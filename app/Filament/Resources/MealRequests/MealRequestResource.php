<?php

namespace App\Filament\Resources\MealRequests;

use App\Filament\Resources\MealRequests\Pages\CreateMealRequest;
use App\Filament\Resources\MealRequests\Pages\EditMealRequest;
use App\Filament\Resources\MealRequests\Pages\ListMealRequests;
use App\Filament\Resources\MealRequests\Schemas\MealRequestForm;
use App\Filament\Resources\MealRequests\Tables\MealRequestsTable;
use App\Models\MealRequest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Meal sheets entered by users with manage_meals, waiting for an admin to
 * approve them into the real meal sheet.
 */
class MealRequestResource extends Resource
{
    protected static ?string $model = MealRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $navigationLabel = 'Meal Entries';

    protected static ?string $modelLabel = 'meal entry';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return MealRequestForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MealRequestsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMealRequests::route('/'),
            'create' => CreateMealRequest::route('/create'),
            'edit' => EditMealRequest::route('/{record}/edit'),
        ];
    }

    /** Non-admins only ever see the entries they submitted themselves. */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        return static::isAdmin() ? $query : $query->where('requested_by', auth()->id());
    }

    public static function canViewAny(): bool
    {
        // can() rather than hasPermissionTo(): the latter throws when the
        // permission row does not exist.
        return static::isAdmin() || (bool) auth()->user()?->can('manage_meals');
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    /** Only pending entries can change; admins may fix anyone's. */
    public static function canEdit(Model $record): bool
    {
        return static::canViewAny()
            && $record->isPending()
            && (static::isAdmin() || (int) $record->requested_by === (int) auth()->id());
    }

    public static function canDelete(Model $record): bool
    {
        return static::canEdit($record);
    }

    public static function isAdmin(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    public static function getNavigationBadge(): ?string
    {
        if (! static::isAdmin()) {
            return null;
        }

        $count = MealRequest::where('status', 'pending')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
}
