<?php

namespace App\Mail;

use App\Models\TenantTopup;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TransferTopupApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public TenantTopup $topup) {}

    public function build()
    {
        return $this->from('soporte@orbana.mx', 'Orbana Soporte')
            ->subject('Transferencia aprobada y saldo acreditado')
            ->markdown('emails.topups.transfer_approved', [
                'topup' => $this->topup,
            ]);
    }
}
