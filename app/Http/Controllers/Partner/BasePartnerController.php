<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

abstract class BasePartnerController extends Controller
{
    protected function tenantId(): int
    {
        $tid = Auth::user()->tenant_id ?? null;
        if (!$tid) abort(403, 'Usuario sin tenant asignado');
        return (int)$tid;
    }

    protected function partnerId(): int
    {
        $pid = session('partner_id') ?? null;
        if (!$pid) abort(403, 'Sin contexto de partner');
        return (int)$pid;
    }
}
