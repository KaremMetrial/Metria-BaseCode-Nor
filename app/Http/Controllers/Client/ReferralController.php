<?php

namespace App\Http\Controllers\Client;

use App\Actions\Client\Referrals\ClaimReferral;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\ListReferralsRequest;
use App\Http\Resources\Client\ReferralResource;
use App\Http\Resources\Shared\ShareLinkResource;
use App\Models\Referral;
use App\Models\ReferralCode;
use App\Models\ShareLink;
use App\Services\Links\ShareLinks;
use App\Support\ApiResponse;
use App\Support\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ReferralController extends Controller
{
    /**
     * Read my referral summary.
     *
     * Read-only: a new account has no referral code until it creates an invitation or attributed share link.
     * Registered referrals do not qualify for points. Rewards are disabled and no points are issued.
     */
    public function show(Request $request, ShareLinks $links): JsonResponse
    {
        $user = $request->user();
        $code = ReferralCode::query()->where('user_id', $user->id)->first();
        $link = $code ? ShareLink::query()->where('user_id', $user->id)->where('referral_code_id', $code->id)->where('target_type', 'home')->whereNull('revoked_at')->orderBy('id')->first() : null;
        $claimed = Referral::query()->where('referred_user_id', $user->id)->exists();

        return ApiResponse::success([
            'code' => $code?->code,
            'share_url' => $link ? $links->url($link) : null,
            'registered_count' => Referral::query()->where('referrer_id', $user->id)->count(),
            'rewards_enabled' => false,
            'has_claimed' => $claimed,
            /** @var bool */
            'can_claim' => ! $claimed && $user->hasVerifiedPhone() && $user->profile_completed_at === null && $user->referral_claim_expires_at?->isFuture() === true,
            'claim_expires_at' => $user->referral_claim_expires_at?->toIso8601String(),
        ]);
    }

    /**
     * Create or retrieve my invitation link.
     *
     * Requires a completed client profile. Repeated requests return the existing active home invitation.
     * A revoked invitation is replaced with a new link; the account referral code stays unchanged.
     */
    public function store(Request $request, ShareLinks $links): JsonResponse
    {
        return ApiResponse::success(new ShareLinkResource($links->referralLink($request->user())));
    }

    /**
     * Claim a referral code during registration.
     *
     * Requires a newly created, phone-verified client before first profile completion and within 24 hours of account creation.
     * Repeating the same code returns the original claim; a different code returns 409. No points are awarded.
     */
    public function claim(Request $request, ClaimReferral $claim): JsonResponse
    {
        $data = $request->validate(['referral_code' => ['required', 'string', 'regex:/^[A-Fa-f0-9]{16}$/']]);

        return ApiResponse::success(new ReferralResource($claim->execute($request->user(), $data['referral_code'])));
    }

    /**
     * List my registered referrals.
     *
     * Returns only claim references and registration timestamps, never invitee names, phone numbers or account IDs.
     * Registered means attribution only; the rewards program is not active.
     */
    public function index(ListReferralsRequest $request, ListQuery $filters): JsonResponse
    {
        return ApiResponse::paginated(ReferralResource::class, $filters->apply(Referral::query()->where('referrer_id', $request->user()->id), $request)->paginate($request->perPage()));
    }
}
