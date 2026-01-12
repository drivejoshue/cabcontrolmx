<?php

namespace App\Http\Controllers\SysAdmin;

use App\Http\Controllers\Controller;
use App\Models\ProviderProfile;
use Illuminate\Http\Request;

class ProviderProfileController extends Controller
{
    public function index()
    {
        $items = ProviderProfile::query()->orderByDesc('id')->paginate(20);
        return view('sysadmin.provider_profiles.index', compact('items'));
    }

    public function create()
    {
        $item = new ProviderProfile(['active' => true, 'country' => 'México']);
        return view('sysadmin.provider_profiles.form', compact('item'));
    }

    public function store(Request $r)
    {
        $data = $this->validateData($r);

        // Si marcan active=1, opcionalmente desactiva otros (regla: solo uno activo)
        if (!empty($data['active'])) {
            ProviderProfile::query()->where('active', 1)->update(['active' => 0]);
        }

        ProviderProfile::create($data);

        return redirect()->route('sysadmin.provider-profiles.index')
            ->with('ok', 'Proveedor creado.');
    }

    public function edit(ProviderProfile $provider_profile)
    {
        $item = $provider_profile;
        return view('sysadmin.provider_profiles.form', compact('item'));
    }

    public function update(Request $r, ProviderProfile $provider_profile)
    {
        $data = $this->validateData($r);

        if (!empty($data['active'])) {
            ProviderProfile::query()
                ->where('active', 1)
                ->where('id', '<>', $provider_profile->id)
                ->update(['active' => 0]);
        }

        $provider_profile->update($data);

        return redirect()->route('sysadmin.provider-profiles.index')
            ->with('ok', 'Proveedor actualizado.');
    }

    public function destroy(ProviderProfile $provider_profile)
    {
        $provider_profile->delete();

        return redirect()->route('sysadmin.provider-profiles.index')
            ->with('ok', 'Proveedor eliminado.');
    }

    private function validateData(Request $r): array
    {
        return $r->validate([
            'active'        => ['nullable','boolean'],

            'display_name'  => ['required','string','max:190'],
            'contact_name'  => ['required','string','max:190'],
            'phone'         => ['nullable','string','max:50'],
            'email_support' => ['nullable','email','max:190'],
            'email_admin'   => ['nullable','email','max:190'],

            'address_line1' => ['nullable','string','max:190'],
            'address_line2' => ['nullable','string','max:190'],
            'city'          => ['nullable','string','max:120'],
            'state'         => ['nullable','string','max:120'],
            'country'       => ['nullable','string','max:120'],
            'postal_code'   => ['nullable','string','max:20'],

            'legal_name'    => ['nullable','string','max:190'],
            'rfc'           => ['nullable','string','max:30'],
            'tax_regime'    => ['nullable','string','max:120'],
            'fiscal_address'=> ['nullable','string','max:2000'],
            'cfdi_use_default' => ['nullable','string','max:50'],
            'tax_zip'       => ['nullable','string','max:20'],

            'acc1_bank'        => ['nullable','string','max:120'],
            'acc1_beneficiary' => ['nullable','string','max:190'],
            'acc1_account'     => ['nullable','string','max:50'],
            'acc1_clabe'       => ['nullable','string','max:30'],

            'acc2_bank'        => ['nullable','string','max:120'],
            'acc2_beneficiary' => ['nullable','string','max:190'],
            'acc2_account'     => ['nullable','string','max:50'],
            'acc2_clabe'       => ['nullable','string','max:30'],
        ]);
    }
}
