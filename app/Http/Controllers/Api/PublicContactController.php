<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContactLead;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class PublicContactController extends Controller
{
    public function store(Request $request)
    {
        // 1) Header key (anti-spam básico)
        $key = (string) $request->header('X-PUBLIC-KEY', '');
        $expected = (string) config('services.public_contact.key', '');

        if (!$expected || !hash_equals($expected, $key)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // 2) Honeypot (campo oculto en el landing)
        if ($request->filled('website')) {
            return response()->json(['message' => 'Ok'], 200);
        }

        // 3) Validación
        $data = $request->validate([
            'contact_name'  => 'required|string|max:120',
            'contact_email' => 'required|email|max:160',
            'contact_phone' => 'nullable|string|max:40',

            'central_name'  => 'nullable|string|max:160',
            'city'          => 'nullable|string|max:120',
            'state'         => 'nullable|string|max:120',

            'message'       => 'nullable|string|max:2000',

            'source'        => 'nullable|string|max:40',
        ]);

        // 4) Normalización mínima
        $data['source'] = $data['source'] ?? 'landing';
        $data['status'] = 'new';
        $data['ip'] = $request->ip();
        $data['user_agent'] = Str::limit((string) $request->userAgent(), 255);

        // 5) Guardar en DB
        $lead = ContactLead::create($data);

        // 6) Notificar a Orbana (tu correo interno)
        $to = (string) config('services.public_contact.to', 'contacto@orbana.mx');
        Mail::send('emails.contact_lead_internal', ['lead' => $lead], function ($m) use ($to) {
            $m->to($to)->subject('Nuevo contacto desde Landing Orbana');
        });

        // 7) Respuesta automática al cliente (opcional pero recomendado)
        Mail::send('emails.contact_lead_autoreply', ['lead' => $lead], function ($m) use ($lead) {
            $m->to($lead->contact_email)
              ->subject('Recibimos tu mensaje · Orbana');
        });

        return response()->json([
            'ok' => true,
            'id' => $lead->id,
        ], 201);
    }
}
