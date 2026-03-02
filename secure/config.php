<?php
/**
 * Configuración de credenciales de base de datos.
 * Usa variables de entorno si existen; si no, aplica estos valores por defecto.
 */
return [
    // Producción (InfinityFree)
    'DB_HOST' => getenv('DB_HOST') ?: 'sql313.byetcluster.com',
    'DB_USER' => getenv('DB_USER') ?: 'if0_41156536',
    'DB_PASS' => getenv('DB_PASS') ?: 'Jspaz3456789',
    'DB_NAME' => getenv('DB_NAME') ?: 'if0_41156536_universidad',

    // Opcional: credenciales para entorno local
    'DB_HOST_LOCAL' => getenv('DB_HOST_LOCAL') ?: '127.0.0.1',
    'DB_USER_LOCAL' => getenv('DB_USER_LOCAL') ?: 'root',
    'DB_PASS_LOCAL' => getenv('DB_PASS_LOCAL') ?: '',
    'DB_NAME_LOCAL' => getenv('DB_NAME_LOCAL') ?: 'mi_bd_local',
];
