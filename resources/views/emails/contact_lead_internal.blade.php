<h2>Nuevo contacto (Landing Orbana)</h2>

<p><b>Nombre:</b> {{ $lead->contact_name }}</p>
<p><b>Email:</b> {{ $lead->contact_email }}</p>
<p><b>Teléfono:</b> {{ $lead->contact_phone ?: '—' }}</p>

<p><b>Central:</b> {{ $lead->central_name ?: '—' }}</p>
<p><b>Ciudad/Estado:</b> {{ trim(($lead->city ?: '').' / '.($lead->state ?: ''), ' /') ?: '—' }}</p>

<hr>

<p><b>Mensaje:</b></p>
<p>{!! nl2br(e($lead->message ?? '—')) !!}</p>

<hr>
<p><small>Fuente: {{ $lead->source }} | IP: {{ $lead->ip }} | {{ $lead->created_at }}</small></p>
