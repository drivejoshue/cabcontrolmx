<?php

namespace App\Http\Controllers\SysAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use PragmaRX\Google2FA\Google2FA;

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Encoding\Encoding;


class SysAdminStepUpController extends Controller
{
    public function show(Request $request)
    {
        $u = $request->user();
        abort_unless($u && (int)$u->is_sysadmin === 1, 403);

        $g2fa = new Google2FA();

        $issuer = config('app.name', 'Orbana');
        $label  = $u->email ?: ('sysadmin-'.$u->id);

        // Secret: si no existe, créalo
        $secret = (string)($u->sysadmin_totp_secret ?? '');
        if ($secret === '') {
            $secret = $g2fa->generateSecretKey(32);
            $u->sysadmin_totp_secret = $secret;
            $u->save();
        }

        // ✅ Confirmado = ya pasó al menos una verificación correcta
        $isConfirmed = !empty($u->sysadmin_totp_confirmed_at);

        // Si NO está confirmado, mostramos QR (enroll/repair)
        $mode = $isConfirmed ? 'verify' : 'enroll';
        $qrDataUri = null;
        $otpauth = null;

        if (!$isConfirmed) {
            $otpauth = $g2fa->getQRCodeUrl($issuer, $label, $secret);

          $qr = new QrCode(
    data: $otpauth,
    encoding: new Encoding('UTF-8'),
    size: 260,
    margin: 12
);

           $writer = new PngWriter();
$result = $writer->write($qr);
$qrDataUri = $result->getDataUri();
        }

        return view('sysadmin.stepup', [
            'mode'      => $mode,      // enroll|verify
            'issuer'    => $issuer,
            'label'     => $label,
            'qrDataUri' => $qrDataUri, // string|null
            'otpauth'   => $otpauth,   // string|null
        ]);
    }

    public function verify(Request $request)
    {
        $u = $request->user();
        abort_unless($u && (int)$u->is_sysadmin === 1, 403);

        $request->validate([
            'code' => ['required', 'string', 'min:6', 'max:10'],
        ]);

        $code = preg_replace('/\s+/', '', (string)$request->input('code'));

        $secret = (string)($u->sysadmin_totp_secret ?? '');
        if ($secret === '') {
            return redirect()->route('sysadmin.stepup.show');
        }

        $g2fa = new Google2FA();

        // window=1 tolera ±30s
        $valid = $g2fa->verifyKey($secret, $code, 1);

        if (!$valid) {
            return back()->withErrors(['code' => 'Código inválido.'])->withInput();
        }

        if (empty($u->sysadmin_totp_enabled_at)) {
            $u->sysadmin_totp_enabled_at = now();
        }
        $u->sysadmin_totp_confirmed_at = now();
        $u->save();

        $request->session()->put('sysadmin_mfa_ok_at', now()->toIso8601String());
        $request->session()->put('sysadmin_mfa_ok_level', 'totp');

        return redirect()->intended('/sysadmin');
    }
}
