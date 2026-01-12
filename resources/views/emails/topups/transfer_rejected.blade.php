<x-mail::message>
# Transferencia rechazada

Tu notificación de transferencia fue rechazada.

**Tenant:** T{{ $topup->tenant_id }}  
**Monto:** ${{ number_format((float)$topup->amount, 2) }} MXN  
**Referencia / rastreo:** {{ $topup->bank_ref ?? '—' }}  
**Folio:** {{ $topup->external_reference ?? '—' }}

**Motivo:** {{ $topup->review_notes ?? '—' }}

Si necesitas ayuda, responde a este correo o abre un ticket.

Gracias,  
{{ config('app.name') }}
</x-mail::message>
