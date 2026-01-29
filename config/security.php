<?php

return [
  'sysadmin_totp_enabled' => env('SYSADMIN_TOTP_ENABLED', true),
  'sysadmin_stepup_route' => env('SYSADMIN_STEPUP_ROUTE', 'sysadmin.stepup.show'),
  'sysadmin_stepup_ttl'   => env('SYSADMIN_STEPUP_TTL', 900),
];
