<?php

namespace App\Filament\Resources\Payments\Pages;

use App\Filament\Resources\Payments\PaymentResource;
use App\Models\Member;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManagePayments extends ManageRecords
{
    protected static string $resource = PaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->databaseTransaction(true)
                ->using(function (array $data, string $model) {
                    $deductFromId = $data['type'] == 'in' ? ($data['deduct_from_member_id'] ?? null) : null;
                    unset($data['deduct_from_member_id']);

                    if ($data['type'] == 'in') {
                        Member::find($data['member_id'])->increment('balance', $data['amount']);
                    } else {
                        Member::find($data['member_id'])->decrement('balance', $data['amount']);
                    }

                    $payment = $model::create($data);

                    // The receiver (usually the manager) now holds this cash, so
                    // it is recorded against them as an 'out' payment.
                    if ($deductFromId) {
                        $payer = Member::find($data['member_id']);

                        $model::create([
                            'note' => trim("Received from {$payer->name}. " . ($data['note'] ?? '')),
                            'amount' => $data['amount'],
                            'type' => 'out',
                            'member_id' => $deductFromId,
                        ]);

                        Member::find($deductFromId)->decrement('balance', $data['amount']);
                    }

                    return $payment;
                }),
        ];
    }
}
