<?php
namespace Modules\PriyasaCore\Jobs;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

final class SendEmailOrderNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public int $tries=4;
    public function __construct(public string $email, public string $subject, public string $body, public string $orderId, public string $event) {}
    public function backoff(): array { return [30,120,600]; }
    public function handle(): void {
        Mail::raw($this->body."\n\nOrder: ".$this->orderId, function($mail){ $mail->to($this->email)->subject($this->subject); });
    }
}
