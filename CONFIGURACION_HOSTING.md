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

### Opción 1: Usar las mismas credenciales locales (RECOMENDADO - Más simple)
Si tu hosting está alojando también la BD `universidad`:

1. No modificar nada en `secure/config.php`
2. El sistema automáticamente detecta que NO eres local
3. Intenta conectar con credenciales remotas
4. Si están vacías (como ahora), **automáticamente fallback a locales**

✅ **Ventaja:** Funciona igual en local que en hosting sin cambios

---

### Opción 2: Usar credenciales remota personalizadas
Si tienes otra base de datos en hosting:

1. Abre `secure/config.php`
2. Completa las credenciales remotas:

```php
'DB_HOST' => getenv('DB_HOST') ?: 'db.mihosting.com',
'DB_USER' => getenv('DB_USER') ?: 'usuario_remoto',
'DB_PASS' => getenv('DB_PASS') ?: 'contraseña_remota',
'DB_NAME' => getenv('DB_NAME') ?: 'base_datos_remota',
```

---

## 🔍 Verificar Conexión

Abre en el navegador:
```
http://localhost/especialidad/test_login.php  (Local)
http://midominio.com/especialidad/test_login.php  (Hosting)
```

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
- [ ] Credenciales remotas en `secure/config.php` (O dejar vacías para fallback)
- [ ] Base de datos creada en hosting
- [ ] SSL/HTTPS configurado (opcional pero recomendado)
- [ ] Acceder a `https://midominio.com/especialidad/login.php`

---

## 🆘 Si sigue sin funcionar

1. Ejecuta `test_login.php` para diagnosticar
2. Verifica que la tabla `usuarios` existe: `SELECT * FROM usuarios;`
3. Confirma credenciales en `secure/config.php`
4. Revisa logs en `/logs/php-error.log`
