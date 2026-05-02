# Configuración para Local y Hosting

## ✅ EN LOCAL (XAMPP)

El sistema detecta automáticamente que estás en local y usa:
- Host: `localhost`
- Usuario: `root`
- Contraseña: *(vacía)*
- Base de datos: `universidad`

**No necesitas hacer nada especial.** Solo asegúrate de:
1. MySQL activo en XAMPP
2. Base de datos `universidad` importada

---

## 🌐 EN HOSTING REMOTO (InfinityFree, Hostinger, etc.)

En un hosting compartido debes configurar las credenciales reales del panel. El fallback a `localhost`/`root` solo sirve en tu equipo local.

### Credenciales remotas obligatorias

1. Abre `secure/config.php`
2. Completa las credenciales remotas con los datos del panel:

```php
'DB_HOST' => getenv('DB_HOST') ?: 'sql123.epizy.com',
'DB_USER' => getenv('DB_USER') ?: 'if0_12345678',
'DB_PASS' => getenv('DB_PASS') ?: 'tu_password_del_hosting',
'DB_NAME' => getenv('DB_NAME') ?: 'if0_12345678_universidad',
```

Notas para InfinityFree:
- No uses `localhost` como host de MySQL.
- El host suele verse como `sql123.epizy.com` o `sql123.infinityfree.com`.
- Si tu panel muestra un alias distinto, usa exactamente el que te entrega el hosting.

---

## 🔍 Verificar Conexión

Abre en el navegador:
``` 
http://localhost/especialidad/test_login.php  (Local)
```

En producción `test_login.php` puede estar bloqueado por seguridad. En ese caso revisa `logs/php-error.log` o habilita temporalmente un entorno de prueba.

Te mostrará:
- ✅ Entorno (Local o Remoto)
- ✅ Host/Puerto/BD que está usando
- ✅ Estado de conexión
- ✅ Usuarios disponibles

---

## 📋 Checklist

### Local
- [ ] XAMPP MySQL activado
- [ ] Base de datos `universidad` importada
- [ ] Usuarios creados (o usar bd.sql)
- [ ] Acceder a `http://localhost/especialidad/login.php`

### Hosting
- [ ] Credenciales remotas correctas en `secure/config.php` o en secretos del deploy
- [ ] Base de datos creada en hosting
- [ ] SSL/HTTPS configurado (opcional pero recomendado)
- [ ] Acceder a `https://midominio.com/especialidad/login.php`

---

## 🆘 Si sigue sin funcionar

1. Ejecuta `test_login.php` para diagnosticar
2. Verifica que la tabla `usuarios` existe: `SELECT * FROM usuarios;`
3. Confirma credenciales en `secure/config.php`
4. Revisa logs en `/logs/php-error.log`
