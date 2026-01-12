<x-mail::message>
# Transferencia aprobada

Tu notificación de transferencia fue validada y el saldo fue acreditado.

**Tenant:** T{{ $topup->tenant_id }}  
**Monto:** ${{ number_format((float)$topup->amount, 2) }} MXN  
**Referencia / rastreo:** {{ $topup->bank_ref ?? '—' }}  
**Folio:** {{ $topup->external_reference ?? '—' }}  
**Fecha acreditación:** {{ $topup->credited_at?->format('Y-m-d H:i') ?? now()->format('Y-m-d H:i') }}

@if(!empty($topup->review_notes))
**Notas:** {{ $topup->review_notes }}
@endif

Gracias,  
{{ config('app.name') }}
</x-mail::message>
