<?php
namespace Modules\PriyasaCore\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class AuthSessionService
{
    public function summary($user): array
    {
        $tokens = [];
        try {
            if (method_exists($user, 'tokens')) {
                $tokens = $user->tokens()->latest('last_used_at')->get(['id','name','created_at','last_used_at','expires_at'])->map(fn($t) => [
                    'id'=>(string)$t->id,'name'=>$t->name,'created_at'=>$t->created_at,'last_used_at'=>$t->last_used_at,'expires_at'=>$t->expires_at,
                ])->values()->all();
            }
        } catch (\Throwable) {}
        return ['user_id'=>$user->id,'sessions'=>$tokens];
    }

    public function rotateCurrent($user, string $tokenName='mobile'): string
    {
        if (!method_exists($user, 'createToken')) throw new RuntimeException('Token authentication is not configured.');
        $current = method_exists($user, 'currentAccessToken') ? $user->currentAccessToken() : null;
        return DB::connection('priyasa')->transaction(function () use ($user, $current, $tokenName) {
            if ($current) $current->delete();
            $token = $user->createToken($tokenName);
            return $token->plainTextToken;
        });
    }

    public function revokeCurrent($user): void
    {
        $current = method_exists($user, 'currentAccessToken') ? $user->currentAccessToken() : null;
        if ($current) $current->delete();
    }

    public function revokeAll($user): int
    {
        if (!method_exists($user, 'tokens')) return 0;
        return (int)$user->tokens()->delete();
    }
}
