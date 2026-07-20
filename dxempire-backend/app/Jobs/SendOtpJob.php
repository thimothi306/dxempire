<?php

namespace App\Jobs;

use App\Integrations\Sms\Msg91Service;
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

    public function __construct(string $phone, string $otp)
    {
        $this->phone = $phone;
        $this->otp   = $otp;
    }

    public function handle(Msg91Service $sms): void
    {
        $sms->sendOtp($this->phone, $this->otp);
    }
}
