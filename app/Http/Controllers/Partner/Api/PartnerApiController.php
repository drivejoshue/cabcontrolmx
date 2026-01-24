<?php

namespace App\Http\Controllers\Partner\Api;

use App\Http\Controllers\Controller;
use App\Services\Partner\PartnerMetricsService;
use App\Support\PartnerScope;
use Illuminate\Http\Request;

class PartnerApiController extends Controller
{
    public function dashboard(Request $request, PartnerMetricsService $svc)
    {
        $scope = PartnerScope::current();

        $range = $request->string('range')->toString();
        if (!in_array($range, ['today','7d','30d'], true)) $range = 'today';

        $payload = $svc->dashboard(
            tenantId: $scope->tenantId,
            partnerId: $scope->partnerId,
            range: $range
        );

        return response()->json([
            'ok' => true,
            'range' => $range,
            'data' => $payload,
        ]);
    }
}
