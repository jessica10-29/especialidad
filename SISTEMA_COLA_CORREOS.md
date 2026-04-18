# 🔧 Solución Completa: Sistema de Cola de Correos

## 🎯 Problema Solucionado

**Error Original:**
```
Fatal error: Maximum execution time of 15 seconds exceeded 
in C:\xampp\htdocs\especialidad\PHPMailer\SMTP.php on line 423
```

**Causa:**
- `set_time_limit(15)` en recover_password.php era muy corto
- PHPMailer intentaba conectarse a SMTP y tardaba más de 15 segundos
- No había manejo asincrónico de envío de correos

---

## ✅ Solución Implementada

### 1. **Sistema de Cola (Queue)**
- Los correos se guardan en base de datos en lugar de enviarlos directamente
- Un servicio independiente procesa la cola sin límite de tiempo
- Funcionan bien en **localhost** y **servidor público**

### 2. **Archivos Creados**

#### 📄 `enviar_cola_correos.php`
- Procesa correos pendientes de la cola
- Timeout ampliado a 120 segundos
- Se puede ejecutar desde cron o manualmente
- URL: `http://localhost/especialidad/enviar_cola_correos.php?token=DESARROLLO_LOCAL_2025`

#### 📄 `funciones_mail.php`
- Función `agregar_correo_a_cola()` - Agregar correos a la cola
- Función `enviar_correo_recuperacion()` - Enviar correo de recuperación
- En localhost: guarda correos en log en lugar de enviar

#### 📄 `procesar_cola_auto.php`
- Se ejecuta automáticamente en background en localhost
- Procesaría cada 5 minutos
- Garantiza que los correos se envíen sin interferir

#### 📄 `admin_cola_correos.php`
- Panel para administrar la cola
- Ver correos pendientes, enviados y con error
- Procesar cola manualmente
- Reintentar o eliminar correos
- **Acceder a:** `http://localhost/especialidad/admin_cola_correos.php?token=DESARROLLO_LOCAL_2025`

---

## 🚀 Cambios Realizados

### `recover_password.php`
```php
// ANTES: set_time_limit(15);
// AHORA: set_time_limit(60);

require_once 'funciones_mail.php';  // Nuevo import

// ANTES: Envío directo con PHPMailer
// AHORA: 
if (enviar_correo_recuperacion($email, $nombre, $link)) {
    header('Location: recover_password.php?ok=1');
    exit;
}
```

### `conexion.php`
Agregado al final:
```php
// Auto-procesar cola en localhost
if ($isLocal && php_sapi_name() !== 'cli' && !headers_sent()) {
    register_shutdown_function(function() {
        @include_once __DIR__ . '/procesar_cola_auto.php';
    });
}
```

---

## 📊 Flujo de Funcionamiento

```
Usuario solicita recuperación de contraseña
        ↓
recover_password.php ejecuta
        ↓
Crea token y lo guarda en DB
        ↓
Llama a enviar_correo_recuperacion()
        ↓
Correo se agrega a mail_queue (tabla)
        ↓
Responde inmediatamente al usuario (SIN TIMEOUT)
        ↓
En background:
  - procesar_cola_auto.php se ejecuta
  - Llama a enviar_cola_correos.php
  - Procesa correos pendientes
  - Los envía uno por uno
        ↓
✅ Correo enviado exitosamente
```

---

## 🖥️ Uso en Localhost

### 1. **Automático (Recomendado)**
Los correos se procesan automáticamente cada 5 minutos cuando un usuario hace clic:
- En login
- En recuperar contraseña
- En cualquier página que requiera conexión.php

**Verificar:** `http://localhost/especialidad/logs/mail-local.log`

### 2. **Manual**
Procesar la cola manualmente:
```bash
curl "http://localhost/especialidad/enviar_cola_correos.php?token=DESARROLLO_LOCAL_2025"
```

Respuesta:
```json
{
  "status": "ok",
  "procesados": 2,
  "errores": 0,
  "timestamp": "2026-04-17 15:30:45"
}
```

### 3. **Admin Panel**
Acceder a: `http://localhost/especialidad/admin_cola_correos.php?token=DESARROLLO_LOCAL_2025`

---

## 🌐 Uso en Servidor Público

### Opción A: **Cron Job** (Recomendado)
Agregar a crontab cada 5 minutos:
```bash
*/5 * * * * curl -s "https://tudominio.com/especialidad/enviar_cola_correos.php?token=DESARROLLO_LOCAL_2025" > /dev/null 2>&1
```

### Opción B: **Webhook**
Tu proveedor de hosting puede ejecutar el script via HTTP.

### Opción C: **Servicio de Cola Externo**
Usar servicios como:
- AWS SQS + Lambda
- Google Cloud Tasks
- SendGrid API

---

## 📋 Tabla de Base de Datos

Se crea automáticamente:

```sql
CREATE TABLE mail_queue (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) NOT NULL,
    nombre VARCHAR(255),
    asunto VARCHAR(255) NOT NULL,
    contenido LONGTEXT NOT NULL,
    tipo ENUM('reset_password', 'cambio_contraseña', 'notificacion', 'otro'),
    datos_json JSON,
    enviado TINYINT DEFAULT 0,
    intentos INT DEFAULT 0,
    ultimo_intento DATETIME,
    error_mensaje TEXT,
    creado DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX(enviado, creado)
);
```

---

## 🔍 Solución de Problemas

### Problema: Los correos no se envían en localhost

**Solución:**
1. Abre: `http://localhost/especialidad/admin_cola_correos.php?token=DESARROLLO_LOCAL_2025`
2. Busca correos en estado "PENDIENTE"
3. Haz clic en "Procesar Cola Ahora"
4. Revisa: `logs/mail-local.log`

### Problema: Correos con estado ERROR

**Solución:**
1. Revisa el mensaje de error en el admin panel
2. Corrige la configuración SMTP en `secure/mail.php`
3. Haz clic en "Reintentar"

### Problema: Cambiar SMTP o credenciales

- En `secure/mail.php` - para producción
- El sistema usa `SMTP_HOST`, `SMTP_PORT`, `SMTP_USER`, `SMTP_PASS`
- Puede usar variables de entorno

---

## 🔐 Seguridad

- ✅ URLs protegidas con token
- ✅ Correos con HTML seguro
- ✅ Tokens de recuperación con expiración
- ✅ Contraseñas hasheadas con bcrypt
- ✅ Logs separados por tipo

---

## 📝 Logs

Ubicaciones:
- **Errores PHP:** `logs/php-error.log`
- **Envío de correos:** `logs/mail-send.log`
- **Cola de correos:** `logs/mail-queue.log`
- **Correos en localhost:** `logs/mail-local.log`

---

## 🧪 Testing

### Enviar correo de prueba
```php
<?php
require_once 'funciones_mail.php';

// Agregar correo a cola
$resultado = agregar_correo_a_cola(
    'test@ejemplo.com',
    'Juan Pérez',
    'Correo de prueba',
    '<h1>Hola</h1><p>Este es un correo de prueba</p>',
    'otro'
);

echo $resultado ? 'Agregado a cola' : 'Error al agregar';
?>
```

### Ver cola de correos
```php
<?php
require_once 'conexion.php';

$pendientes = $conn->query("
    SELECT email, asunto, creado 
    FROM mail_queue 
    WHERE enviado = 0
");

while ($row = $pendientes->fetch_assoc()) {
    echo "{$row['email']} - {$row['asunto']}\n";
}
?>
```

---

## 📞 Soporte

Si tienes problemas:
1. Revisa `logs/mail-queue.log`
2. Accede al admin panel de cola
3. Revisa que MySQL esté corriendo
4. Verifica token SMTP en `secure/mail.php`

---

**¡Sistema completo de cola de correos implementado!** ✅
