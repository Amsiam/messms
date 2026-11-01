<?php

namespace App\Filament\Resources\MealRequests\Pages;

use App\Filament\Resources\MealRequests\MealRequestResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMealRequest extends EditRecord
{
    protected static string $resource = MealRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
