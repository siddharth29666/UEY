<?php

namespace App\Http\Controllers;

use App\Enums\WalletTransactionType;
use App\Http\Requests\ApplyReferralRequest;
use App\Http\Requests\InviteFriendRequest;
use App\Http\Resources\ReferralBonusResource;
use App\Http\Resources\ReferralHistoryResource;
use App\Http\Resources\ReferralResource;
use App\Models\Referral;
use App\Services\ReferralService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ReferralController extends Controller
{
    public function __construct(
        protected ReferralService $referralService
    ) {}

    #[OA\Post(
        path: '/referrals/apply',
        summary: 'Apply Referral Code',
        description: 'Applies an invitation referral code before the first ride is completed.',
        security: [['bearerAuth' => []]],
        tags: ['Referral System'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/ApplyReferralRequest')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Referral code applied successfully.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Referral code has been successfully applied to your account.'),
                        new OA\Property(property: 'referral', ref: '#/components/schemas/Referral'),
                    ]
                )
            ),
            new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationErrorResponse'),
        ]
    )]
    public function apply(ApplyReferralRequest $request): JsonResponse
    {
        try {
            $referral = $this->referralService->applyReferralCode($request->user(), $request->input('referral_code'));

            return response()->json([
                'success' => true,
                'message' => 'Referral code has been successfully applied to your account.',
                'referral' => new ReferralResource($referral),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    #[OA\Post(
        path: '/referrals/invite',
        summary: 'Invite Friend',
        description: 'Generates a referral invitation sharing package for a friend.',
        security: [['bearerAuth' => []]],
        tags: ['Referral System'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/InviteFriendRequest')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Invitation content generated.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'referral_code', type: 'string', example: 'UEY4K8PZ'),
                        new OA\Property(property: 'invitation_message', type: 'string', example: 'Use my code UEY4K8PZ to get a discount...'),
                        new OA\Property(property: 'share_url', type: 'string', example: 'https://uey.mobility/download?code=UEY4K8PZ'),
                    ]
                )
            ),
            new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationErrorResponse'),
        ]
    )]
    public function invite(InviteFriendRequest $request): JsonResponse
    {
        $user = $request->user();
        $code = $user->referral_code;

        $shareUrl = "https://uey.mobility/download?code={$code}";
        $message = "Use my referral code {$code} to sign up and get a bonus on UEY Premium Mobility! Download here: {$shareUrl}";

        return response()->json([
            'success' => true,
            'referral_code' => $code,
            'invitation_message' => $message,
            'share_url' => $shareUrl,
        ]);
    }

    #[OA\Get(
        path: '/referrals/code',
        summary: 'Get My Referral Code',
        description: 'Retrieves the authenticated user\'s unique referral code.',
        security: [['bearerAuth' => []]],
        tags: ['Referral System'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Referral code retrieved.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'referral_code', type: 'string', example: 'UEY4K8PZ'),
                    ]
                )
            ),
            new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
        ]
    )]
    public function code(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'referral_code' => $request->user()->referral_code,
        ]);
    }

    #[OA\Get(
        path: '/referrals/history',
        summary: 'Referral History List',
        description: 'Retrieves a list of all users referred by the authenticated user.',
        security: [['bearerAuth' => []]],
        tags: ['Referral System'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Referral history retrieved.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'referrals',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/ReferralHistoryResource')
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
        ]
    )]
    public function history(Request $request): JsonResponse
    {
        $referrals = Referral::with('referredUser')
            ->where('referrer_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'referrals' => ReferralHistoryResource::collection($referrals),
        ]);
    }

    #[OA\Get(
        path: '/referrals/summary',
        summary: 'Referral Summary Stats',
        description: 'Retrieves totals, pending count, and completed statistics.',
        security: [['bearerAuth' => []]],
        tags: ['Referral System'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Summary retrieved.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'total_referred', type: 'integer', example: 12),
                        new OA\Property(property: 'completed_referrals', type: 'integer', example: 5),
                        new OA\Property(property: 'pending_referrals', type: 'integer', example: 7),
                        new OA\Property(property: 'total_earnings', type: 'number', format: 'float', example: 50.00),
                    ]
                )
            ),
            new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
        ]
    )]
    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();
        $totalReferred = Referral::where('referrer_id', $user->id)->count();
        $completed = Referral::where('referrer_id', $user->id)->where('status', 'completed')->count();
        $pending = $totalReferred - $completed;

        $totalEarnings = (float) Referral::where('referrer_id', $user->id)
            ->whereNotNull('rewarded_at')
            ->sum('referrer_bonus');

        return response()->json([
            'success' => true,
            'total_referred' => $totalReferred,
            'completed_referrals' => $completed,
            'pending_referrals' => $pending,
            'total_earnings' => $totalEarnings,
        ]);
    }

    #[OA\Get(
        path: '/referrals/earnings',
        summary: 'Referral Earnings History Ledger',
        description: 'Retrieves the transaction ledger credits from referral bonuses.',
        security: [['bearerAuth' => []]],
        tags: ['Referral System'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Earnings history retrieved.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'earnings',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/ReferralBonusResource')
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
        ]
    )]
    public function earnings(Request $request): JsonResponse
    {
        $user = $request->user();
        $wallet = $user->wallet;

        $txs = collect();
        if ($wallet) {
            $txs = $wallet->transactions()
                ->where('transaction_type', WalletTransactionType::REFERRAL_BONUS)
                ->orderBy('created_at', 'desc')
                ->get();
        }

        return response()->json([
            'success' => true,
            'earnings' => ReferralBonusResource::collection($txs),
        ]);
    }
}
