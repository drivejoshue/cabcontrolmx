<?php

namespace App\Enums;

final class RideStatus
{
    public const REQUESTED = 'requested';
    public const OFFERED   = 'offered';
    public const ACCEPTED  = 'accepted';
    public const EN_ROUTE  = 'en_route';
    public const ARRIVED   = 'arrived';
    public const ONBOARD   = 'on_board';
    public const FINISHED  = 'finished';
    public const CANCELED  = 'canceled';
}
