<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class EmailVerificationResendController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        $sentTo = $user->routeNotificationFor('mail') ?? $user->email;

        if (!$sentTo) {
            Log::warning('Verification resend blocked: no email', ['user_id' => $user->id]);
            return back()->withErrors([
                'email' => 'No hay un correo configurado para enviar la verificación.',
            ]);
        }

        if ($user->hasVerifiedEmail()) {
            return back()->with([
                'status' => 'already-verified',
                'sent_to' => $sentTo,
            ]);
        }

        try {
            $user->sendEmailVerificationNotification();

            Log::info('Verification link sent', [
                'user_id' => $user->id,
                'email' => $user->email,
                'sent_to' => $sentTo,
                'mailer' => config('mail.default'),
                'smtp_host' => config('mail.mailers.smtp.host'),
                'app_url' => config('app.url'),
            ]);

            return back()->with([
                'status' => 'verification-link-sent',
                'sent_to' => $sentTo,
            ]);

        } catch (Throwable $e) {
            Log::error('Verification link send failed', [
                'user_id' => $user->id,
                'email' => $user->email,
                'sent_to' => $sentTo,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors([
                'email' => 'No se pudo enviar el correo de verificación. Intenta de nuevo o contacta soporte.',
            ]);
        }
    }
}
