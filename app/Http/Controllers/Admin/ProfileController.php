<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function edit()
    {
        return view('admin.profile.edit_tabler');
    }

    public function update(Request $request)
    {
        $u = $request->user();

        $data = $request->validate([
            'name' => ['required','string','max:120'],
            'email' => ['required','email','max:190', Rule::unique('users','email')->ignore($u->id)],
            'password' => ['nullable','string','min:8','confirmed'],
        ]);

        $u->name = $data['name'];
        $u->email = $data['email'];

        if (!empty($data['password'])) {
            $u->password = Hash::make($data['password']);
        }

        $u->save();

        return back()->with('ok','Cambios guardados.');
    }
}
