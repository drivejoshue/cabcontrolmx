<?php

namespace App\Http\Controllers\SysAdmin;

use App\Http\Controllers\Controller;
use App\Models\BillingPlan;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BillingPlanController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string)$request->get('q', ''));

        $plans = BillingPlan::query()
            ->when($q !== '', function ($qq) use ($q) {
                $qq->where('code', 'like', "%{$q}%")
                   ->orWhere('name', 'like', "%{$q}%");
            })
            ->orderByDesc('active')
            ->orderBy('billing_model')
            ->orderBy('code')
            ->paginate(20)
            ->withQueryString();

        return view('sysadmin.billing_plans.index', compact('plans', 'q'));
    }

    public function create()
    {
        $plan = new BillingPlan([
            'billing_model' => 'per_vehicle',
            'currency' => 'MXN',
            'base_monthly_fee' => 0,
            'included_vehicles' => 5,
            'price_per_vehicle' => 299,
            'active' => 1,
        ]);

        return view('sysadmin.billing_plans.form', [
            'plan' => $plan,
            'mode' => 'create',
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validatePayload($request);

        BillingPlan::create($data);

        return redirect()
            ->route('sysadmin.billing-plans.index')
            ->with('ok', 'Plan creado.');
    }

    public function edit(BillingPlan $billing_plan)
    {
        return view('sysadmin.billing_plans.form', [
            'plan' => $billing_plan,
            'mode' => 'edit',
        ]);
    }

    public function update(Request $request, BillingPlan $billing_plan)
    {
        $data = $this->validatePayload($request, $billing_plan->id);

        $billing_plan->update($data);

        return redirect()
            ->route('sysadmin.billing-plans.index')
            ->with('ok', 'Plan actualizado.');
    }

    public function destroy(BillingPlan $billing_plan)
    {
        // seguridad simple: evita borrar el plan base
        if ($billing_plan->code === 'PV_STARTER') {
            return back()->with('err', 'No se puede eliminar el plan PV_STARTER. Desactívalo si ya no aplica.');
        }

        $billing_plan->delete();

        return back()->with('ok', 'Plan eliminado.');
    }

    private function validatePayload(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'code' => [
                'required','string','max:50',
                Rule::unique('billing_plans', 'code')->ignore($ignoreId),
            ],
            'name' => ['required','string','max:120'],
            'billing_model' => ['required', Rule::in(['per_vehicle','commission'])],
            'currency' => ['required','string','max:10'],

            'base_monthly_fee' => ['required','numeric','min:0','max:999999.99'],
            'included_vehicles' => ['required','integer','min:0','max:100000'],
            'price_per_vehicle' => ['required','numeric','min:0','max:999999.99'],

            'active' => ['nullable'],
            'effective_from' => ['nullable','date'],
        ], [
            'code.unique' => 'El código ya existe.',
        ]) + [
            'active' => (bool)$request->boolean('active'),
        ];
    }
}
