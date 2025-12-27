<?php

namespace App\Http\Controllers\SysAdmin;

use App\Http\Controllers\Controller;
use App\Models\ContactLead;

class ContactLeadController extends Controller
{
    public function index()
    {
        $leads = ContactLead::latest()->paginate(30);
        return view('sysadmin.leads.index', compact('leads'));
    }

    public function show(ContactLead $lead)
    {
        return view('sysadmin.leads.show', compact('lead'));
    }

    public function updateStatus(ContactLead $lead)
    {   $request->validate([
  'status' => 'required|in:new,contacted,closed'
        ]);

        $lead->update([
            'status' => request('status')
        ]);

        return back();
    }
}
