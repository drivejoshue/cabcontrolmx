<?php

namespace App\Mail;

use App\Models\TenantTopup;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TransferTopupNoticeSubmitted extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public TenantTopup $topup) {}

    public function build()
    {
        return $this->subject('Orbana: Notificación de transferencia (Tenant '.$this->topup->tenant_id.')')
            ->markdown('emails.topups.transfer_submitted', [
                'topup' => $this->topup,
            ]);
    }
}
