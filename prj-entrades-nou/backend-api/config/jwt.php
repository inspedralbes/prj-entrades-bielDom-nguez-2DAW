<?php

return [

    // Preferible definir JWT_SECRET al .env; si no existeix, es reutilitza APP_KEY (només dev / proves).
    // HS256 (firebase/php-jwt): el secret en string ha de tenir com a mínim 32 bytes o JWT::encode llança «Provided key is too short».
    'secret' => env('JWT_SECRET') ?: env('APP_KEY'),

    'algo' => 'HS256',

    'ttl_seconds' => (int) env('JWT_TTL', 86400),

    /** TTL del JWT emès per cada entrada (QR); per defecte 15 min (900 s). */
    'ticket_ttl_seconds' => (int) env('JWT_TICKET_TTL', 900),

];
