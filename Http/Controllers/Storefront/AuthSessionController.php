<?php
namespace Modules\PriyasaCore\Http\Controllers\Storefront;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\PriyasaCore\Services\AuthSessionService;
use Modules\PriyasaCore\Services\StorefrontApiContract;
use RuntimeException;

final class AuthSessionController extends Controller
{
    public function show(Request $request, StorefrontApiContract $contract, AuthSessionService $sessions)
    {
        $user = $request->user();
        if (!$user) return response()->json($contract->error('AUTH_REQUIRED','Authentication required.',[],401),401);
        return response()->json($contract->success('auth.session', $sessions->summary($user)));
    }

    public function rotate(Request $request, StorefrontApiContract $contract, AuthSessionService $sessions)
    {
        $user = $request->user();
        if (!$user) return response()->json($contract->error('AUTH_REQUIRED','Authentication required.',[],401),401);
        try {
            $token = $sessions->rotateCurrent($user, (string)$request->input('device_name','mobile'));
            return response()->json($contract->success('auth.token.rotated',['access_token'=>$token,'token_type'=>'Bearer']));
        } catch (RuntimeException $e) {
            return response()->json($contract->error('TOKEN_AUTH_UNAVAILABLE',$e->getMessage(),[],501),501);
        }
    }

    public function logout(Request $request, StorefrontApiContract $contract, AuthSessionService $sessions)
    {
        $user = $request->user();
        if (!$user) return response()->json($contract->error('AUTH_REQUIRED','Authentication required.',[],401),401);
        $sessions->revokeCurrent($user);
        return response()->json($contract->success('auth.logout',['logged_out'=>true]));
    }

    public function logoutAll(Request $request, StorefrontApiContract $contract, AuthSessionService $sessions)
    {
        $user = $request->user();
        if (!$user) return response()->json($contract->error('AUTH_REQUIRED','Authentication required.',[],401),401);
        $count = $sessions->revokeAll($user);
        return response()->json($contract->success('auth.logout_all',['logged_out'=>true,'revoked_sessions'=>$count]));
    }
}
