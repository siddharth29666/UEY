<?php

namespace App\Services;

use App\DTOs\PromoValidationResult;
use App\Models\PromoCode;
use App\Models\PromoCodeUsage;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PromoService
{
    /**
     * Validate a promo code and calculate discount / final fare.
     */
    public function validatePromo(User $rider, string $code, int $vehicleTypeId, float $estimatedFare, bool $lock = false): PromoValidationResult
    {
        $code = strtoupper(trim($code));

        // 1. Fetch promo configuration
        $query = PromoCode::where('code', $code);
        if ($lock) {
            $query->lockForUpdate();
        }
        $promo = $query->first();

        // 2. Exists and Active check
        if (!$promo || !$promo->is_active) {
            return new PromoValidationResult(false, 'Promo code is invalid or unavailable.');
        }

        // 3. Expiry check
        if ($promo->expires_at && $promo->expires_at->isPast()) {
            return new PromoValidationResult(false, 'Promo code is invalid or unavailable.');
        }

        // 4. Global usage limit check (status in ['reserved', 'completed'])
        $globalUsages = DB::table('promo_code_usages')
            ->where('promo_code_id', $promo->id)
            ->whereIn('status', ['reserved', 'completed'])
            ->count();

        if ($promo->usage_limit !== null && $globalUsages >= $promo->usage_limit) {
            return new PromoValidationResult(false, 'Promo code is invalid or unavailable.');
        }

        // 5. Per-user usage limit check
        $userUsages = DB::table('promo_code_usages')
            ->where('promo_code_id', $promo->id)
            ->where('user_id', $rider->id)
            ->whereIn('status', ['reserved', 'completed'])
            ->count();

        if ($promo->per_user_limit !== null && $userUsages >= $promo->per_user_limit) {
            return new PromoValidationResult(false, 'Promo code is invalid or unavailable.');
        }

        // 6. Vehicle eligibility check
        if (!empty($promo->ride_eligibility)) {
            if (!in_array($vehicleTypeId, $promo->ride_eligibility)) {
                return new PromoValidationResult(false, 'Promo code is invalid or unavailable.');
            }
        }

        // 7. First ride only check
        if ($promo->first_ride_only) {
            $hasRides = DB::table('rides')
                ->where('rider_id', $rider->id)
                ->where('status', 'completed')
                ->exists();

            if ($hasRides) {
                return new PromoValidationResult(false, 'Promo code is invalid or unavailable.');
            }
        }

        // 8. Minimum fare check
        if ($estimatedFare < (float) $promo->min_fare) {
            return new PromoValidationResult(false, 'Promo code is invalid or unavailable.');
        }

        // 9. Calculate discount
        $discountAmount = $this->calculateDiscount($promo, $estimatedFare);
        $finalFare = max(0.00, $estimatedFare - $discountAmount);

        return new PromoValidationResult(
            isValid: true,
            discountAmount: $discountAmount,
            finalFare: $finalFare,
            promoCode: $promo
        );
    }

    /**
     * Calculate the discount amount for a promo code configuration.
     */
    public function calculateDiscount(PromoCode $promo, float $fare): float
    {
        if ($promo->discount_type === 'flat') {
            $discount = (float) $promo->discount_value;
        } else {
            $discount = ($promo->discount_value / 100) * $fare;
            if ($promo->max_discount !== null) {
                $discount = min($discount, (float) $promo->max_discount);
            }
        }

        return round(min($discount, $fare), 2);
    }

    /**
     * Reserve a promo code usage inside a transaction.
     */
    public function reservePromo(User $rider, string $code, int $vehicleTypeId, float $estimatedFare, int $rideId): PromoCodeUsage
    {
        // 1. Re-validate under lock
        $result = $this->validatePromo($rider, $code, $vehicleTypeId, $estimatedFare, true);

        if (!$result->isValid) {
            throw new \Exception('Promo code is invalid or unavailable.');
        }

        $promo = $result->promoCode;

        // 2. Create usage record with status 'reserved'
        $usage = PromoCodeUsage::create([
            'promo_code_id' => $promo->id,
            'user_id' => $rider->id,
            'ride_id' => $rideId,
            'discount_amount' => $result->discountAmount,
            'status' => 'reserved',
        ]);

        // 3. Update the cached used_count inside the transaction
        $promo->increment('used_count');

        return $usage;
    }

    /**
     * Complete a promo code usage.
     */
    public function completePromo(int $rideId, float $actualFare): void
    {
        $usage = PromoCodeUsage::where('ride_id', $rideId)
            ->where('status', 'reserved')
            ->first();

        if (!$usage) {
            return;
        }

        $promo = $usage->promoCode;

        // Recalculate discount based on actual fare
        $actualDiscount = $this->calculateDiscount($promo, $actualFare);

        $usage->update([
            'status' => 'completed',
            'discount_amount' => $actualDiscount,
        ]);
    }

    /**
     * Cancel/release a promo code usage.
     */
    public function cancelPromo(int $rideId): void
    {
        $usage = PromoCodeUsage::where('ride_id', $rideId)
            ->whereIn('status', ['reserved', 'completed'])
            ->first();

        if (!$usage) {
            return;
        }

        $usage->update(['status' => 'cancelled']);

        // Decrement the used_count of the promo code inside a transaction
        $promo = $usage->promoCode;
        if ($promo) {
            $promo->decrement('used_count');
        }
    }
}
