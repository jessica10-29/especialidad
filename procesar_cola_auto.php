<?php
/**
 * procesar_cola_auto.php
 * 
 * Script que se carga silenciosamente después de cualquier acción
 * para procesar la cola de correos en localhost
 * 
 * Se incluye en los archivos principales mediante:
 * register_shutdown_function(function() { include_once 'procesar_cola_auto.php'; });
 */

// Solo en localhost (en producción usar cron jobs o webhooks)
$isLocal = function_exists('es_peticion_local')
    ? es_peticion_local()
    : (isset($_SERVER['HTTP_HOST'])
        && (stripos($_SERVER['HTTP_HOST'], 'localhost') !== false
            || stripos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false));

if (!$isLocal) {
    return; // No ejecutar en producción
}

// Procesar cola solo cada 5 minutos para no sobrecargar
$cacheFile = sys_get_temp_dir() . '/mail_queue_last_process.txt';
$lastProcess = @file_get_contents($cacheFile);
$now = time();

if ($lastProcess && ($now - (int)$lastProcess) < 300) {
    return; // Hace poco que se procesó
}

// Actualizar timestamp
@file_put_contents($cacheFile, $now);

// Ejecutar procesador en background
$token = function_exists('obtener_token_mantenimiento')
    ? obtener_token_mantenimiento()
    : (getenv('MAIL_QUEUE_TOKEN') ?: 'DESARROLLO_LOCAL_2025');

$baseUrl = function_exists('construir_base_url')
    ? rtrim(construir_base_url(), '/')
    : (((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http')
        . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/especialidad');

$url = $baseUrl . '/enviar_cola_correos.php?token=' . urlencode($token);

// Ejecutar en background (sin esperar respuesta)
$context = stream_context_create([
    'http' => [
        'timeout' => 5,
        'ignore_errors' => true
    ]
]);

@file_get_contents($url, false, $context);
