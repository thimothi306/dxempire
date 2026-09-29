<?php

namespace App\Jobs;

use App\Integrations\Sms\SmsLoginService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendOtpJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public string $phone;
    public string $otp;
    public string $purpose;

    public function __construct(string $phone, string $otp, string $purpose = 'partner')
    {
        $this->phone   = $phone;
        $this->otp     = $otp;
        $this->purpose = $purpose;
    }

    public function handle(SmsLoginService $sms): void
    {
        match ($this->purpose) {
            'password_reset' => $sms->sendPasswordResetOtp($this->phone, $this->otp),
            default          => $sms->sendPartnerOtp($this->phone, $this->otp),
        };
    }
}
