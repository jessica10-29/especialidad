# 🖨️ Guía del Sistema de Impresión y Reportes

## Overview
El sistema de impresión de **Unicali Segura** permite generar certificados académicos, reportes consolidados y documentos profesionales con códigos QR de verificación.

---

## 📄 Archivos Principales

### 1. **pdf.php** - Certificado Académico Oficial
**Acceso:** Estudiantes | Profesores (para sus estudiantes)
**Función:** Genera certificado con calificaciones finales

#### Características:
- Diseño certificado oficial con bordes dorados
- Tabla con notas de materias y estado (aprobado/reprobado)
- Código QR de verificación única (folio UC-ID)
- Código de barras por estudiante
- Impresión directa optimizada para A4

#### Uso:
```
/pdf.php                           # Mi certificado (estudiante)
/pdf.php?materia=Matemáticas      # Certificado de una materia específica
/pdf.php?estudiante_id=5          # Ver certificado de estudiante (solo profesor)
```

#### Botones:
- **← Regresar**: Vuelve al dashboard
- **Imprimir Certificado Profesional**: Abre el diálogo de impresión del navegador

---

### 2. **reporte_notas_pdf.php** - Reporte Consolidado de Notas
**Acceso:** Solo Profesores
**Función:** Genera tabla con todas las notas de una materia

#### Características:
- Tabla con todos los estudiantes del curso
- Columnas: Corte 1-3, Examen Final, Seguimiento, Definitiva
- Cálculo automático de promedios ponderados (20%-20%-20%-30%-10%)
- Firma del docente y sello institucional
- Optimizado para impresión en A4

#### Uso:
```
/reporte_notas_pdf.php?materia=ID_MATERIA
```

#### Requisitos:
- Tener materias asignadas en el período actual
- Permisos de profesor

---

### 3. **generar_documento.php** - Certificados Académicos Personalizados
**Acceso:** Estudiantes | Profesores (para sus estudiantes) | Administradores
**Función:** Genera certificaciones de estudio o cartas de recomendación

#### Tipos de Documentos:
- `?tipo=estudio` → Certificación académica (vinculación institucional)
- `?tipo=recomendacion` → Carta de recomendación

#### Características:
- Diseño elegante con bordes decorativos
- Datos personalizados del usuario
- QR + Código de barras integrados
- Sello digital
- Compatible con descarga como PDF

#### Links:
```
/generar_documento.php?tipo=estudio
/generar_documento.php?tipo=recomendacion
/generar_documento.php?tipo=estudio&usuario_id=5  # Para otros (profesor)
```

---

## 🔧 Herramientas de Diagnóstico

### **validar_impresion.php** - Validador del Sistema
Utility para verificar que todo funcione correctamente.

**Valida:**
- ✓ Conexión a base de datos
- ✓ Tablas requeridas
- ✓ Carpetas y permisos
- ✓ Funciones PHP
- ✓ APIs externas
- ✓ Período actual

**Acceso:**
```
/validar_impresion.php
```

---

## 📊 Sistema de Calificación

### Escala de Notas:
- **5.0**: Excelente (A)
- **4.0-4.9**: Muy Bueno (B)
- **3.0-3.9**: Bueno (C)
- **Menor a 3.0**: Reprobado (F)

### Ponderación de Cortes (5 evaluaciones):
```
Calificación Final = (Corte1 × 0.2) + (Corte2 × 0.2) + (Corte3 × 0.2)
                     + (ExamenFinal × 0.3) + (Seguimiento × 0.1)
```

---

## 🔐 Códigos de Verificación

Cada documento incluye:

### QR (Código QR)
- Escaneable desde cualquier dispositivo
- Enlace a página de verificación: `/verificar.php?folio=UC-ID`
- Verifica autenticidad del documento
- Información: nombre, ID, programa, calificaciones

### Código de Barras
- Formato: Code128
- Contiene: ID usuario, nombre, cédula, código estudiantil, programa
- Para sistemas de lectura automatizada

### Folio
- Formato: `UC-USUARIO_ID`
- Único y no duplicable
- Generado automáticamente al crear documento

---

## 🖨️ Recomendaciones de Impresión

### Calidad:
- **Mejor:** Impresora láser B/N (nítido y rápido)
- **Bueno:** Inyección de tinta a color
- **Evitar:** Impresoras de baja resolución

### Configuración:
| Aspecto | Recomendación |
|---------|---------------|
| Tamaño de papel | A4 (210×297mm) |
| Márgenes | Automáticos |
| Orientación | Vertical (Retrato) |
| Escala | 100% (Sin ajustar) |
| Color | Completo |
| Calidad | Alta/Mejor |

### Guardado como PDF:
1. Abre el documento en el navegador
2. Presiona `Ctrl+P` o "Imprimir"
3. Selecciona "Guardar como PDF"
4. Elige ubicación y nombre
5. **Listo:** Archivo generado

---

## ⚠️ Solución de Problemas

### ❌ QR no aparece
**Causa:** Problemas de conexión a internet o carpeta /uploads/qr sin permisos
**Solución:**
1. Verifica conexión a internet
2. Ejecuta `/validar_impresion.php`
3. Si dice "FALLO - carpeta /uploads/qr", crea la carpeta manualmente:
   ```bash
   mkdir -p /var/www/html/especialidad/uploads/qr
   chmod 755 /var/www/html/especialidad/uploads/qr
   ```

### ❌ Notas no aparecen
**Causa:** Período sin notas registradas
**Solución:**
1. Verifica que estés en el período correcto
2. El profesor debe registrar notas en: `/agregar_nota.php`
3. Las notas deben estar en periodo_actual

### ❌ Errores de impresión
**Causa:** Estilos CSS incompatibles con navegador o impresora
**Solución:**
1. Usa Chrome, Firefox o Edge (no IE)
2. Desactiva tema oscuro del navegador
3. Permite "Gráficos de fondo" en opciones de impresión

### ❌ Página sale cortada al imprimir
**Causa:** Márgenes o escala incorrectos
**Solución:**
1. Abre Print Preview (Ctrl+P)
2. Ajusta márgenes a "Mínimo"
3. Escala a 100% (no automático)
4. Deselecciona "Encabezados y pié de página"

---

## 📚 Códigos de Referencia Rápida

### URLs de Impresión:
```
# Estudiante
/pdf.php                                    # Mi certificado
/generar_documento.php?tipo=estudio        # Mi certificación
/generar_documento.php?tipo=recomendacion  # Carta de recomendación

# Profesor
/reporte_notas_pdf.php?materia=1           # Reportes de notas
/pdf.php?estudiante_id=5                   # Certificado de estudiante
/generar_documento.php?tipo=estudio&usuario_id=5

# Admin
/validar_impresion.php                     # Diagnóstico del sistema
```

### Funciones en conexion.php:
```php
obtener_periodo_actual()      // ID del período actual
descargar_con_timeout($url)   // Descarga con timeout corto
url_verificacion($folio)       // URL de verificación del folio
limpiar_dato($valor)          // Sanitiza entrada
```

---

## 📞 Soporte

Si encuentras problemas:
1. Ejecuta `/validar_impresion.php`
2. Recopila el resultado
3. Contacta al administrador con la información

---

**Última actualización:** 28/03/2026
**Versión:** 2.0 (Sistema completo de impresión)
