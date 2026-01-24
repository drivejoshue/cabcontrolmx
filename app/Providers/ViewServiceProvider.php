<?php

namespace App\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use App\Models\PartnerNotification;

class ViewServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        view()->composer('layouts.partner', function ($view) {

            $user = auth()->user();
            if (!$user) return;

            $tenantId  = (int) ($user->tenant_id ?? 0);
            $partnerId = (int) (session('partner_id') ?: ($user->default_partner_id ?? 0));

            if ($tenantId <= 0 || $partnerId <= 0) {
                $view->with('topbarBadges', [
                    'inbox_unread'   => 0,
                    'support_unread' => 0,
                ]);
                return;
            }

            // 1) Inbox: notificaciones sin leer
            $inboxUnread = (int) PartnerNotification::query()
                ->where('tenant_id', $tenantId)
                ->where('partner_id', $partnerId)
                ->whereNull('read_at')
                ->count();

            // 2) Support: threads con respuesta nueva para partner
            // Regla simple: hay mensajes nuevos (last_message_at) y partner no ha “leído” esa última actividad.
            // (Opcional: validar que el último mensaje sea del tenant para no contarte tus propios mensajes)
            $supportUnread = (int) DB::table('partner_threads as t')
                ->leftJoin('partner_thread_messages as m', 'm.id', '=', 't.last_message_id')
                ->where('t.tenant_id', $tenantId)
                ->where('t.partner_id', $partnerId)
                ->whereNotNull('t.last_message_at')
                ->where(function($w){
                    $w->whereNull('t.last_partner_read_at')
                      ->orWhereColumn('t.last_message_at', '>', 't.last_partner_read_at');
                })
                ->where(function($w){
                    // ✅ Solo cuenta si el último mensaje NO es del partner (o sea, respondió el tenant)
                    $w->whereNull('m.author_role')
                      ->orWhere('m.author_role', '!=', 'partner');
                })
                ->count();

            $view->with('topbarBadges', [
                'inbox_unread'   => $inboxUnread,
                'support_unread' => $supportUnread,
            ]);
        });

        view()->composer('layouts.admin', function ($view) {
            $user = auth()->user();
            if (!$user) return;

            $tenantId = (int) ($user->tenant_id ?? 0);
            if ($tenantId <= 0) {
                $view->with('adminTopbarBadges', ['partner_support_unread' => 0]);
                return;
            }

            // Tickets con respuesta nueva para tenant (escribió el partner)
            $partnerSupportUnread = (int) DB::table('partner_threads as t')
                ->leftJoin('partner_thread_messages as m', 'm.id', '=', 't.last_message_id')
                ->where('t.tenant_id', $tenantId)
                ->whereNotNull('t.last_message_at')
                ->where(function ($w) {
                    $w->whereNull('t.last_tenant_read_at')
                      ->orWhereColumn('t.last_message_at', '>', 't.last_tenant_read_at');
                })
                ->where(function ($w) {
                    // ✅ Solo cuenta si el último mensaje NO es del tenant (o sea, respondió el partner)
                    $w->whereNull('m.author_role')
                      ->orWhere('m.author_role', '!=', 'tenant');
                })
                ->count();

            $view->with('adminTopbarBadges', [
                'partner_support_unread' => $partnerSupportUnread,
            ]);
        });
        
    }
}
