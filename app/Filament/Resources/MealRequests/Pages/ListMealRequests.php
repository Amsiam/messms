<?php

namespace App\Filament\Resources\MealRequests\Pages;

use App\Filament\Resources\MealRequests\MealRequestResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMealRequests extends ListRecords
{
    protected static string $resource = MealRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
