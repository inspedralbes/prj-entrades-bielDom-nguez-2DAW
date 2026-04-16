<?php

//================================ NAMESPACES / IMPORTS ============
// Configuració Laravel: retorn d’array (sense imports).

//================================ PROPIETATS / ATRIBUTS ==========

//================================ MÈTODES / FUNCIONS ===========

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Nuxt (p. ex. :3000) crida l’API Laravel (:8000): cal CORS per al navegador.
    | Sense aquest fitxer, config('cors') és buit i no s’afegeixen capçaleres CORS.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://localhost:3000',
        'http://127.0.0.1:3000',
        'http://localhost:3001',
        'http://127.0.0.1:3001',
        /* Nuxt públic al servidor d’aula (API a :8000, front a :3000). */
        'http://pulse.daw.inspedralbes.cat:3000',
    ],

    /*
     * Altres projectes al mateix domini d’aula (subdomini + port Nuxt opcional).
     * Ex.: http://altre.daw.inspedralbes.cat:3000
     */
    'allowed_origins_patterns' => [
        '#^https?://[a-z0-9.-]+\.daw\.inspedralbes\.cat(:\d+)?$#i',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
