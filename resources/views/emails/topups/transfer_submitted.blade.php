<x-mail::message>
# Notificación de transferencia recibida

Se registró una notificación de transferencia y quedó en revisión.

**Tenant ID:** {{ $topup->tenant_id }}  
**Monto:** ${{ number_format((float) $topup->amount, 2) }} MXN  
**Referencia / Rastreo:** {{ $topup->bank_ref ?? '—' }}  
**Fecha reportada:** {{ optional($topup->deposited_at)->format('Y-m-d H:i:s') ?? '—' }}  
**Topup ID:** {{ $topup->id }}  
**External Ref:** {{ $topup->external_reference ?? '—' }}

@php
  $transfer = (array)($topup->meta['transfer'] ?? []);
  $acc = (array)($transfer['account_snapshot'] ?? []);
  $submitted = (array)($topup->meta['submitted_by'] ?? []);
@endphp

## Cuenta destino (snapshot)

**Banco:** {{ $acc['bank'] ?? '—' }}  
**Beneficiario:** {{ $acc['beneficiary'] ?? '—' }}  
**CLABE:** {{ $acc['clabe'] ?? '—' }}  
@if(!empty($acc['account']))
**Cuenta:** {{ $acc['account'] }}
@endif

## Evidencia

**Comprobante:** {{ !empty($topup->proof_path) ? 'Adjunto/archivo cargado' : 'No adjuntó comprobante' }}  
@if(!empty($topup->proof_path))
**Ruta:** {{ $topup->proof_path }}
@endif

## Audit

**Usuario:** {{ $submitted['email'] ?? '—' }} (ID: {{ $submitted['user_id'] ?? '—' }})  
**IP:** {{ $submitted['ip'] ?? '—' }}

Gracias,  
Orbana
</x-mail::message>
