<?php

namespace App\Services;

use App\Models\Meal;
use App\Models\MealRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Turns an approved meal request into the real meal sheet for its date.
 *
 * Each requested row overwrites that member's meal item for the date, or
 * creates it. Members the request does not mention keep whatever they had,
 * so a request can correct a few rows of an auto-generated sheet.
 */
class MealRequestApprover
{
    public function approve(MealRequest $request, ?User $by = null): Meal
    {
        return DB::transaction(function () use ($request, $by) {
            $request = MealRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();

            if (! $request->isPending()) {
                throw new InvalidArgumentException("Meal request #{$request->id} is already {$request->status}.");
            }

            $meal = Meal::whereDate('date', $request->date)->first()
                ?? Meal::create(['date' => $request->date->toDateString()]);

            foreach ($request->items as $item) {
                $meal->mealItems()->updateOrCreate(
                    ['member_id' => $item->member_id],
                    $item->only(['breakfast', 'lunch', 'dinner']),
                );
            }

            $this->decide($request, 'approved', $by);

            return $meal;
        });
    }

    public function reject(MealRequest $request, ?User $by = null): void
    {
        if (! $request->isPending()) {
            throw new InvalidArgumentException("Meal request #{$request->id} is already {$request->status}.");
        }

        $this->decide($request, 'rejected', $by);
    }

    private function decide(MealRequest $request, string $status, ?User $by): void
    {
        $request->update([
            'status' => $status,
            'approved_by' => $by?->id,
            'approved_at' => now(),
        ]);
    }
}
