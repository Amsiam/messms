<?php

namespace App\Filament\Resources\MealRequests\Pages;

use App\Filament\Resources\MealRequests\MealRequestResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMealRequest extends CreateRecord
{
    protected static string $resource = MealRequestResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return [
            ...$data,
            'requested_by' => auth()->id(),
            'status' => 'pending',
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
