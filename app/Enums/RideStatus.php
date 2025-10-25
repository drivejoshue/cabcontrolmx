<?php

final class RideStatus {
    public const REQUESTED = 'requested';
    public const OFFERED   = 'offered';
    public const ACCEPTED  = 'accepted';
    public const EN_ROUTE  = 'en_route';  // si lo usas
    public const ARRIVED   = 'arrived';
    public const ONBOARD   = 'on_board';   // <- estandarizado
    public const FINISHED  = 'finished';
    public const CANCELED  = 'canceled';
}

