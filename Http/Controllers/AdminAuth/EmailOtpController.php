<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Http\Controllers\AdminAuth;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Modules\PriyasaCore\Support\ApiResponse;

final class EmailOtpController
{
    public function send(Request $request): JsonResponse
    {
        $validated = $request->validate(['email' => ['required','email','max:255']]);
        $email = Str::lower(trim($validated['email']));
        $ip = $request->ip() ?? 'unknown';
        $rateKey = 'priyasa:admin-otp:' . hash('sha256', $email . '|' . $ip);

        if (!cache()->add($rateKey, true, now()->addSeconds((int) config('priyasacore.admin_otp.resend_after', 60)))) {
            return ApiResponse::ok(['retry_after' => (int) config('priyasacore.admin_otp.resend_after', 60)], 'If the account is eligible, an OTP will be available shortly.');
        }

        $userModel = config('auth.providers.users.model', 'App\\Models\\User');
        $user = $userModel::query()->whereRaw('LOWER(email) = ?', [$email])->first();
        $pivotEligible = $user && DB::connection('priyasa')->table('priyasa_admin_user_role as ur')->join('priyasa_admin_roles as r', 'r.id', '=', 'ur.role_id')->where('ur.admin_user_id', $user->getKey())->whereIn('r.slug', ['super_admin', 'admin'])->exists();
        $eligible = $user && !((bool) ($user->is_blocked ?? false)) && in_array((string)($user->status ?? 'active'), ['active','approved'], true) && (in_array((string)($user->role ?? ''), ['super_admin', 'admin'], true) || $pivotEligible);

        if (!$eligible) {
            return ApiResponse::ok(['retry_after' => (int) config('priyasacore.admin_otp.resend_after', 60)], 'If the account is eligible, an OTP will be available shortly.');
        }

        $otp = (string) random_int(100000, 999999);
        $requestId = (string) Str::uuid();
        DB::connection('priyasa')->table('priyasa_admin_otp_requests')->where('email', $email)->whereNull('verified_at')->update(['invalidated_at' => now()]);
        DB::connection('priyasa')->table('priyasa_admin_otp_requests')->insert([
            'id' => $requestId,
            'user_id' => $user->getKey(),
            'email' => $email,
            'otp_hash' => Hash::make($otp),
            'attempts' => 0,
            'max_attempts' => (int) config('priyasacore.admin_otp.max_attempts', 5),
            'expires_at' => now()->addSeconds((int) config('priyasacore.admin_otp.expiry', 300)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $ttl=(int)config('priyasacore.admin_otp.expiry',300);
        Mail::html('<!doctype html><html><body style="margin:0;background:#f6f7fb;font-family:Arial,Helvetica,sans-serif"><div style="max-width:560px;margin:0 auto;padding:24px 12px"><div style="background:#fff;border:1px solid #eceef4;border-radius:18px;overflow:hidden"><div style="background:#ec1f63;color:#fff;padding:22px 26px"><div style="font-size:25px;font-weight:900;letter-spacing:.08em">PRIYASA</div><div style="font-size:11px;margin-top:5px;opacity:.9">ADMIN SECURITY</div></div><div style="padding:30px 26px"><div style="font-size:11px;font-weight:800;letter-spacing:.08em;color:#ec1f63">VERIFICATION CODE</div><h1 style="font-size:24px;color:#171a2b;margin:8px 0 12px">Confirm your admin sign-in</h1><p style="font-size:14px;line-height:1.6;color:#687080">Use the one-time code below to continue to the PRIYASA control center.</p><div style="font-size:34px;letter-spacing:.35em;font-weight:900;text-align:center;background:#fafbfe;border:1px dashed #dfe3ec;border-radius:14px;padding:18px 10px;margin:22px 0;color:#171a2b">'.$otp.'</div><p style="font-size:12px;color:#8a91a1">Expires in <strong>'.ceil($ttl/60).' minutes</strong>. Never share this code.</p></div><div style="padding:18px 26px;background:#fafbfe;border-top:1px solid #eceef4;font-size:11px;color:#8a91a1">PRIYASA Commerce Automation</div></div></div></body></html>', function ($message) use ($email): void { $message->to($email)->subject('PRIYASA Admin — Verification code'); });

        return ApiResponse::ok([
            'request_id' => $requestId,
            'expires_in' => (int) config('priyasacore.admin_otp.expiry', 300),
            'retry_after' => (int) config('priyasacore.admin_otp.resend_after', 60),
        ], 'If the account is eligible, an OTP has been sent.');
    }

    public function verify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'request_id' => ['required','uuid'],
            'email' => ['required','email','max:255'],
            'otp' => ['required','digits:6'],
        ]);
        $email = Str::lower(trim($validated['email']));
        $row = DB::connection('priyasa')->table('priyasa_admin_otp_requests')->where('id', $validated['request_id'])->where('email', $email)->first();
        if (!$row || $row->verified_at || $row->invalidated_at || now()->greaterThan($row->expires_at)) {
            return ApiResponse::error('Invalid or expired OTP.', 422, ['otp' => ['Invalid or expired OTP.']]);
        }
        if ((int) $row->attempts >= (int) $row->max_attempts) {
            return ApiResponse::error('Too many OTP attempts. Request a new OTP.', 429, ['otp' => ['Too many attempts.']]);
        }
        if (!Hash::check($validated['otp'], $row->otp_hash)) {
            DB::connection('priyasa')->table('priyasa_admin_otp_requests')->where('id', $row->id)->increment('attempts');
            return ApiResponse::error('Invalid or expired OTP.', 422, ['otp' => ['Invalid or expired OTP.']]);
        }

        $userModel = config('auth.providers.users.model', 'App\\Models\\User');
        $user = $userModel::query()->find($row->user_id);
        $pivotEligible = $user && DB::connection('priyasa')->table('priyasa_admin_user_role as ur')->join('priyasa_admin_roles as r', 'r.id', '=', 'ur.role_id')->where('ur.admin_user_id', $user->getKey())->whereIn('r.slug', ['super_admin', 'admin'])->exists();
        $eligible = $user && !((bool) ($user->is_blocked ?? false)) && in_array((string)($user->status ?? 'active'), ['active','approved'], true) && (in_array((string)($user->role ?? ''), ['super_admin', 'admin'], true) || $pivotEligible);
        if (!$eligible) return ApiResponse::error('Admin account is not authorized.', 403);

        $verified = DB::connection('priyasa')->transaction(function () use ($row): bool {
            $locked = DB::connection('priyasa')->table('priyasa_admin_otp_requests')->where('id', $row->id)->lockForUpdate()->first();
            if (!$locked || $locked->verified_at || $locked->invalidated_at || now()->greaterThan($locked->expires_at) || (int)$locked->attempts >= (int)$locked->max_attempts) return false;
            DB::connection('priyasa')->table('priyasa_admin_otp_requests')->where('id', $row->id)->update(['verified_at' => now(), 'updated_at' => now()]);
            return true;
        });
        if (!$verified) return ApiResponse::error('Invalid or expired OTP.', 422, ['otp' => ['Invalid or expired OTP.']]);
        $token = $user->createToken('priyasa-admin')->plainTextToken;

        return ApiResponse::ok([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->getKey(),
                'name' => $user->name ?? trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')),
                'email' => $user->email,
            ],
        ], 'Admin login successful.');
    }
}
