<p>Hola {{ $lead->contact_name }},</p>

<p>Gracias por contactar a <b>Orbana</b>. Ya recibimos tu mensaje y nos comunicaremos contigo lo antes posible.</p>

<p><b>Resumen:</b></p>
<ul>
  <li>Central: {{ $lead->central_name ?: '—' }}</li>
  <li>Ciudad/Estado: {{ trim(($lead->city ?: '').' / '.($lead->state ?: ''), ' /') ?: '—' }}</li>
</ul>

<p>Si necesitas agregar información, responde a este correo.</p>

<p>— Orbana</p>
