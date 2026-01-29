<?php

namespace App\Http\Controllers\SysAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Contracts\Encryption\DecryptException;
use PragmaRX\Google2FA\Google2FA;

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Encoding\Encoding;

class SysAdminStepUpController extends Controller
{
    private function readTotpSecretBase32($u): ?string
    {
        $stored = (string)($u->sysadmin_totp_secret ?? '');
        if ($stored === '') return null;

        // 1) Intentar decrypt (nuevo/estándar)
        $plain = null;
        try {
            $plain = Crypt::decryptString($stored);
        } catch (DecryptException $e) {
            // 2) Compat: quizá estaba guardado en texto plano base32
            $plain = $stored;
        }

        $plain = strtoupper(trim($plain));

        // Validación base32 simple (A-Z2-7) y longitud mínima
        if (!preg_match('/^[A-Z2-7]{16,}$/', $plain)) {
            return null; // corrupto / formato inválido
        }

        return $plain;
    }

    private function writeTotpSecretBase32($u, string $secretBase32): void
    {
        $secretBase32 = strtoupper(trim($secretBase32));
        $u->sysadmin_totp_secret = Crypt::encryptString($secretBase32);
    }

    public function show(Request $request)
    {
        $u = $request->user();
        abort_unless($u && (int)$u->is_sysadmin === 1, 403);

        $g2fa = new Google2FA();

        $issuer = config('app.name', 'Orbana');
        $label  = $u->email ?: ('sysadmin-'.$u->id);

        // Leer secret (desencriptando si aplica)
        $secret = $this->readTotpSecretBase32($u);

        // Si no existe o está corrupto → regenerar y guardar ENCRIPTADO
        if (!$secret) {
            $secret = $g2fa->generateSecretKey(32);
            $this->writeTotpSecretBase32($u, $secret);

            // Al regenerar, fuerza re-enroll (por seguridad)
            $u->sysadmin_totp_enabled_at = null;
            $u->sysadmin_totp_confirmed_at = null;
            $u->save();
        }

        $isConfirmed = !empty($u->sysadmin_totp_confirmed_at);

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
            'mode'      => $mode,
            'issuer'    => $issuer,
            'label'     => $label,
            'qrDataUri' => $qrDataUri,
            'otpauth'   => $otpauth,
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

        $secret = $this->readTotpSecretBase32($u);

        // Si no hay secret (o corrupto), re-enroll
        if (!$secret) {
            // Limpia flags para que show() enseñe QR nuevo
            $u->sysadmin_totp_secret = null;
            $u->sysadmin_totp_enabled_at = null;
            $u->sysadmin_totp_confirmed_at = null;
            $u->save();

            return redirect()->route('sysadmin.stepup.show')
                ->withErrors(['code' => 'Se reinició la configuración de 2FA. Vuelve a escanear el QR.']);
        }

        $g2fa = new Google2FA();

        // window=2 tolera ±60s (más robusto)
        $valid = $g2fa->verifyKey($secret, $code, 2);

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
