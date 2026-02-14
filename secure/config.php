<?php
/**
 * Configuración de credenciales de base de datos.
 * Usa variables de entorno si existen; si no, aplica estos valores por defecto.
 */
return [
    'DB_HOST' => getenv('DB_HOST') ?: 'sql312.infinityfree.com',
    'DB_USER' => getenv('DB_USER') ?: 'if0_41154266',
    'DB_PASS' => getenv('DB_PASS') ?: 'Jjsh102030',
    'DB_NAME' => getenv('DB_NAME') ?: 'if0_41154266_sofhann',
];
