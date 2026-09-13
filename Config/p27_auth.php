<?php
return [
    'rate_limits'=>[
        'otp_send'=>['max'=>(int)env('PRIYASA_OTP_SEND_MAX',5),'decay_seconds'=>(int)env('PRIYASA_OTP_SEND_DECAY',600)],
        'otp_verify'=>['max'=>(int)env('PRIYASA_OTP_VERIFY_MAX',8),'decay_seconds'=>(int)env('PRIYASA_OTP_VERIFY_DECAY',600)],
        'otp_resend'=>['max'=>(int)env('PRIYASA_OTP_RESEND_MAX',3),'decay_seconds'=>(int)env('PRIYASA_OTP_RESEND_DECAY',600)],
        'login'=>['max'=>(int)env('PRIYASA_LOGIN_MAX',10),'decay_seconds'=>(int)env('PRIYASA_LOGIN_DECAY',900)],
    ],
];
