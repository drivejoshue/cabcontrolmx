<x-mail::message>
# Recibimos tu notificación de transferencia

Tu notificación fue registrada correctamente y quedó **en revisión**.  
El saldo se acreditará cuando un administrador valide el pago.

**Tenant ID:** {{ $topup->tenant_id }}  
**Monto:** ${{ number_format((float) $topup->amount, 2) }} MXN  
**Referencia / Rastreo:** {{ $topup->bank_ref ?? '—' }}  
**Folio:** {{ $topup->external_reference ?? '—' }}

Si necesitas aclaración, responde a este correo o contacta a soporte.

Gracias,  
Orbana
</x-mail::message>
