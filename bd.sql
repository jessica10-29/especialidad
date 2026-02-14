CREATE DATABASE IF NOT EXISTS universidad;
USE universidad;

-- 1. Tabla de Usuarios
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    rol ENUM('profesor', 'estudiante', 'admin') NOT NULL,
    foto VARCHAR(255) DEFAULT 'default_avatar.png',
    identificacion VARCHAR(20) DEFAULT NULL,
    telefono VARCHAR(20) DEFAULT NULL,
    direccion VARCHAR(255) DEFAULT NULL,
    ciudad VARCHAR(100) DEFAULT NULL,
    departamento VARCHAR(100) DEFAULT NULL,
    -- Datos específicos de estudiante (pueden ser NULL si es admin/profe)
    correo_institucional VARCHAR(100) DEFAULT NULL,
    programa_academico VARCHAR(100) DEFAULT NULL,
    semestre VARCHAR(20) DEFAULT NULL,
    codigo_estudiantil VARCHAR(50) DEFAULT NULL,
    codigo_profesor VARCHAR(50) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Tabla de Periodos Académicos
CREATE TABLE IF NOT EXISTS periodos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL, -- Ej: '2024-1'
    fecha_inicio DATE NOT NULL,
    fecha_fin DATE NOT NULL,
    estado ENUM('activo', 'inactivo') DEFAULT 'activo',
    limite_notas DATE DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 3. Tabla de Materias
CREATE TABLE IF NOT EXISTS materias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    codigo VARCHAR(20) NOT NULL UNIQUE,
    profesor_id INT, -- Considera crear una tabla intermedia 'cursos' si hay múltiples grupos
    descripcion TEXT,
    FOREIGN KEY (profesor_id) REFERENCES usuarios(id) ON DELETE SET NULL
);

-- 4. Tabla de Matriculas
CREATE TABLE IF NOT EXISTS matriculas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    estudiante_id INT NOT NULL,
    materia_id INT NOT NULL,
    periodo_id INT NOT NULL, -- Hacemos este campo obligatorio
    promedio DECIMAL(4, 2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (estudiante_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (materia_id) REFERENCES materias(id) ON DELETE CASCADE,
    FOREIGN KEY (periodo_id) REFERENCES periodos(id) ON DELETE CASCADE
);

-- 5. Tabla de Notas (Evaluaciones)
CREATE TABLE IF NOT EXISTS notas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    matricula_id INT NOT NULL,
    corte VARCHAR(50) NOT NULL, -- Ej: 'Parcial 1'
    valor DECIMAL(4, 2) NOT NULL CHECK (valor >= 0 AND valor <= 5), -- Validación básica SQL
    observacion TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (matricula_id) REFERENCES matriculas(id) ON DELETE CASCADE
);

-- 6. Historial de Cambios (Auditoría)
CREATE TABLE IF NOT EXISTS historial_notas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nota_id INT NOT NULL,
    valor_anterior DECIMAL(4,2),
    valor_nuevo DECIMAL(4,2),
    observacion_anterior TEXT,
    observacion_nueva TEXT,
    justificacion TEXT,
    profesor_id INT, -- Quien hizo el cambio
    fecha_cambio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (nota_id) REFERENCES notas(id) ON DELETE CASCADE,
    FOREIGN KEY (profesor_id) REFERENCES usuarios(id) ON DELETE SET NULL
);

-- 7. Asistencia
CREATE TABLE IF NOT EXISTS asistencia (
    id INT AUTO_INCREMENT PRIMARY KEY,
    matricula_id INT NOT NULL,
    fecha DATE NOT NULL,
    estado ENUM('Presente', 'Ausente', 'Justificado', 'Tardanza') NOT NULL,
    -- Campos redundantes para consultas rápidas (opcional, mantener sincronizados por código)
    estudiante_id INT, 
    materia_id INT,    
    FOREIGN KEY (matricula_id) REFERENCES matriculas(id) ON DELETE CASCADE
);

-- 8. Permisos Especiales
CREATE TABLE IF NOT EXISTS permisos_especiales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    profesor_id INT NOT NULL,
    materia_id INT NOT NULL,
    fecha_vencimiento DATETIME NOT NULL,
    motivo VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (profesor_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (materia_id) REFERENCES materias(id) ON DELETE CASCADE
);

-- 9. Configuración General
CREATE TABLE IF NOT EXISTS configuracion (
    id INT AUTO_INCREMENT PRIMARY KEY,
    clave VARCHAR(100) NOT NULL UNIQUE,
    valor VARCHAR(255) NOT NULL
);

INSERT INTO configuracion (clave, valor) VALUES 
('min_nota', '0.0'),
('max_nota', '5.0'),
('edicion_notas_activa', '1')
ON DUPLICATE KEY UPDATE valor = VALUES(valor);

-- 10. Recuperación de Contraseñas (Separada)
CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    token VARCHAR(255) NOT NULL,
    expira DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    -- No hacemos FK a usuarios(email) porque a veces el email podría cambiar, 
    -- pero sí se recomienda indexar el email para búsquedas rápidas.
    , INDEX (email)
);

