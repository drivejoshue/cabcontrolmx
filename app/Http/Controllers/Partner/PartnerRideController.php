<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;

class PartnerRideController extends Controller
{
    public function index()
    {
        // manda al reporte real
        return redirect()->route('partner.reports.rides.index');
    }

    public function show(int $ride)
    {
        // manda al show real de reportes
        return redirect()->route('partner.reports.rides.show', $ride);
    }
}
