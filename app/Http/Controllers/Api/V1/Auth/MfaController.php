<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Services\Ntb\Uam\MfaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MfaController extends Controller
{
    public function __construct(private readonly MfaService $mfa) {}

    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401);

        return response()->json($this->mfa->status($user));
    }

    public function enroll(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401);

        $data = $request->validate([
            'method' => ['required', 'in:email,sms'],
        ]);

        return response()->json($this->mfa->startEnrollment($user, $data['method']));
    }

    public function verifyEnrollment(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401);

        $data = $request->validate([
            'challenge_id' => ['required', 'integer'],
            'code' => ['required', 'string', 'size:6'],
        ]);

        $method = $this->mfa->verifyEnrollment($user, $data['challenge_id'], $data['code']);
        $request->session()->put('mfa_verified', true);
        $request->session()->forget('mfa_enrollment_required');

        return response()->json([
            'method' => $method,
            'status' => $this->mfa->status($user->fresh()),
        ]);
    }

    public function resendLoginChallenge(Request $request): JsonResponse
    {
        $userId = $request->session()->get('mfa_pending_user_id');
        abort_unless($userId, 401);

        $user = $request->user() ?? \App\Models\User::query()->findOrFail($userId);
        $challenge = $this->mfa->createLoginChallenge($user);
        $request->session()->put('mfa_challenge_id', $challenge['challenge_id']);

        return response()->json($challenge);
    }
}
