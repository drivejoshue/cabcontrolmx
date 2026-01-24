<?php

namespace App\Services\Partner;

use App\Models\PartnerNotification;

class PartnerInboxService
{
    public static function notify(
        int $tenantId,
        int $partnerId,
        string $type,
        string $level,
        string $title,
        ?string $body = null,
        ?string $entityType = null,
        ?int $entityId = null,
        array $data = []
    ): PartnerNotification {
        // Normaliza level permitido
        $level = in_array($level, ['info','success','warning','danger'], true) ? $level : 'info';

        return PartnerNotification::create([
            'tenant_id'    => $tenantId,
            'partner_id'   => $partnerId,
            'type'         => $type,
            'level'        => $level,
            'title'        => $title,
            'body'         => $body,
            'entity_type'  => $entityType,
            'entity_id'    => $entityId,
            'data'         => $data ?: null,
        ]);
    }
}
