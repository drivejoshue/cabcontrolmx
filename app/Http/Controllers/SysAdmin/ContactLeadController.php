<?php

namespace App\Http\Controllers\SysAdmin;

use App\Http\Controllers\Controller;
use App\Models\ContactLead;


   class ContactLeadController extends Controller
{
    public function index()
    {
        // filtros opcionales (coinciden con los del blade index)
        $q = trim((string)request('q',''));
        $status = request('status');

        $leads = ContactLead::query()
            ->when($status, fn($qq) => $qq->where('status',$status))
            ->when($q !== '', function ($qq) use ($q) {
                $qq->where(function ($w) use ($q) {
                    $w->where('contact_name','like',"%$q%")
                      ->orWhere('contact_email','like',"%$q%")
                      ->orWhere('contact_phone','like',"%$q%")
                      ->orWhere('central_name','like',"%$q%")
                      ->orWhere('city','like',"%$q%")
                      ->orWhere('state','like',"%$q%");
                });
            })
            ->latest()
            ->paginate(30)
            ->appends(request()->query());

        return view('sysadmin.leads.index', compact('leads'));
    }

    public function show(ContactLead $lead)
    {
        return view('sysadmin.leads.show', compact('lead'));
    }

    public function updateStatus(Request $request, ContactLead $lead)
    {
        $data = $request->validate([
            'status' => 'required|in:new,contacted,closed'
        ]);

        $lead->update(['status' => $data['status']]);

        return back()->with('status','Estado actualizado.');
    }


   public function store(Request $r)
    {
        // 1) Honeypot simple (campo oculto "website")
        if (trim((string) $r->input('website')) !== '') {
            return response()->json(['ok' => true]); // silenciar bots
        }

        // 2) Gate por header (clave pública, NO es secreto)
        $provided = $r->header('X-PUBLIC-KEY', '');
        $expected = config('orbana.public_contact_key'); // ver config abajo
        if (!$expected || $provided !== $expected) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // 3) Validación payload
        $data = $r->validate([
            'contact_name'  => 'required|string|max:120',
            'contact_email' => 'required|email|max:160',
            'contact_phone' => 'nullable|string|max:40',
            'central_name'  => 'nullable|string|max:160',
            'city'          => 'nullable|string|max:120',
            'state'         => 'nullable|string|max:120',
            'message'       => 'nullable|string|max:4000',
        ]);

        // 4) (Opcional) Persistir
        // DB::table('contact_messages')->insert([...$data, 'created_at'=>now()]);

        // 5) Notificar por correo (opcional)
        // Mail::to(config('orbana.sales_email'))->send(new ContactMessageMail($data));

        // 6) Log operativo
        Log::info('Landing contact', [
            'from' => $r->ip(),
            'ua'   => $r->userAgent(),
            'data' => $data,
        ]);

        return response()->json(['ok' => true]);
    }
}
