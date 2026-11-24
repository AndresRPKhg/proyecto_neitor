<?php
declare(strict_types=1);
namespace RaceControl\Application;

/**
 * Definición declarativa de los recursos CRUD (DRY): un solo servicio y un solo componente JS
 * leen estas definiciones para listar, validar, guardar y dibujar tablas y formularios.
 */
final class Recursos
{
    private const TIPOS = ['auto' => 'Auto', 'moto' => 'Moto'];
    private const TIPOS_CAMPEONATO = ['auto' => 'Auto', 'moto' => 'Moto', 'mixto' => 'Mixto'];
    private const TIPOS_CARRERA = ['auto' => 'Autos', 'moto' => 'Motos', 'mixto' => 'Mixto (autos y motos)'];

    public static function get(string $nombre): ?array
    {
        return self::todos()[$nombre] ?? null;
    }

    public static function todos(): array
    {
        static $r = null;
        return $r ??= [
            'pilotos' => [
                'tabla' => 'pilotos', 'etiqueta' => 'Pilotos', 'singular' => 'piloto', 'modulo' => 'catalogos',
                'permisos' => ['ver' => null, 'crear' => 'catalogo.crear', 'editar' => 'catalogo.editar', 'borrar' => 'catalogo.borrar'],
                'borrado_logico' => true,
                'select' => 'SELECT t.*, CONCAT(t.nombre," ",t.apellido) AS nombre_completo, e.nombre AS equipo FROM pilotos t LEFT JOIN equipos e ON e.id=t.equipo_id',
                'etiqueta_sql' => 'CONCAT(t.nombre," ",t.apellido)',
                'buscar' => ['t.nombre', 't.apellido', 't.documento', 't.nacionalidad'],
                'orden' => ['nombre_completo' => 'nombre_completo', 'documento' => 't.documento', 'nacionalidad' => 't.nacionalidad', 'equipo' => 'equipo'],
                'orden_defecto' => 'nombre_completo',
                'filtros' => ['equipo_id' => 't.equipo_id'],
                'columnas' => [['nombre_completo', 'Piloto'], ['documento', 'Documento'], ['nacionalidad', 'Nacionalidad'], ['equipo', 'Escudería']],
                'unicos' => [['documento' => 'Ya existe un piloto con este documento.']],
                'campos' => [
                    'nombre' => ['tipo' => 'texto', 'etiqueta' => 'Nombre', 'requerido' => true, 'max' => 80, 'normalizar' => 'titulo'],
                    'apellido' => ['tipo' => 'texto', 'etiqueta' => 'Apellido', 'requerido' => true, 'max' => 80, 'normalizar' => 'titulo'],
                    'documento' => ['tipo' => 'texto', 'etiqueta' => 'Documento de identidad', 'requerido' => true, 'max' => 30],
                    'fecha_nacimiento' => ['tipo' => 'fecha', 'etiqueta' => 'Fecha de nacimiento', 'requerido' => true, 'no_futura' => true],
                    'nacionalidad' => ['tipo' => 'texto', 'etiqueta' => 'Nacionalidad', 'requerido' => true, 'max' => 60, 'normalizar' => 'titulo'],
                    'equipo_id' => ['tipo' => 'ref', 'etiqueta' => 'Escudería', 'ref' => 'equipos'],
                ],
            ],
            'equipos' => [
                'tabla' => 'equipos', 'etiqueta' => 'Escuderías', 'singular' => 'escudería', 'modulo' => 'catalogos',
                'permisos' => ['ver' => null, 'crear' => 'catalogo.crear', 'editar' => 'catalogo.editar', 'borrar' => 'catalogo.borrar'],
                'borrado_logico' => true,
                'select' => 'SELECT t.* FROM equipos t',
                'etiqueta_sql' => 't.nombre',
                'buscar' => ['t.nombre', 't.pais'],
                'orden' => ['nombre' => 't.nombre', 'pais' => 't.pais', 'anio_fundacion' => 't.anio_fundacion'],
                'orden_defecto' => 'nombre', 'filtros' => [],
                'columnas' => [['nombre', 'Escudería'], ['pais', 'País'], ['anio_fundacion', 'Fundación']],
                'enlace_detalle' => '/escuderias/',
                'unicos' => [['nombre' => 'Ya existe una escudería con este nombre.']],
                'campos' => [
                    'nombre' => ['tipo' => 'texto', 'etiqueta' => 'Nombre de la escudería', 'requerido' => true, 'max' => 100],
                    'pais' => ['tipo' => 'texto', 'etiqueta' => 'País', 'requerido' => true, 'max' => 60, 'normalizar' => 'titulo'],
                    'anio_fundacion' => ['tipo' => 'entero', 'etiqueta' => 'Año de fundación', 'min' => 1900, 'max' => (int) date('Y')],
                ],
            ],
            'vehiculos' => [
                'tabla' => 'vehiculos', 'etiqueta' => 'Vehículos', 'singular' => 'vehículo', 'modulo' => 'catalogos',
                'permisos' => ['ver' => null, 'crear' => 'catalogo.crear', 'editar' => 'catalogo.editar', 'borrar' => 'catalogo.borrar'],
                'borrado_logico' => true,
                'select' => 'SELECT t.*, e.nombre AS equipo, CONCAT(p.nombre," ",p.apellido) AS piloto
                    FROM vehiculos t LEFT JOIN equipos e ON e.id=t.equipo_id
                    LEFT JOIN pilotos p ON p.id=t.piloto_id AND p.equipo_id=t.equipo_id',
                'etiqueta_sql' => 'CONCAT(t.marca," ",t.modelo," (",t.placa,")")',
                'buscar' => ['t.marca', 't.modelo', 't.placa'],
                'orden' => ['tipo_vehiculo' => 't.tipo_vehiculo', 'marca' => 't.marca', 'modelo' => 't.modelo', 'placa' => 't.placa', 'anio' => 't.anio', 'equipo' => 'equipo', 'piloto' => 'piloto'],
                'orden_defecto' => 'marca',
                'filtros' => ['tipo_vehiculo' => 't.tipo_vehiculo', 'equipo_id' => 't.equipo_id'],
                'columnas' => [['tipo_vehiculo', 'Tipo'], ['marca', 'Marca'], ['modelo', 'Modelo'], ['placa', 'Placa'], ['anio', 'Año'], ['equipo', 'Escudería'], ['piloto', 'Piloto']],
                'unicos' => [['placa' => 'Ya existe un vehículo con esta placa.']],
                'campos' => [
                    'tipo_vehiculo' => ['tipo' => 'enum', 'etiqueta' => 'Tipo de vehículo', 'requerido' => true, 'opciones' => self::TIPOS],
                    'marca' => ['tipo' => 'texto', 'etiqueta' => 'Marca', 'requerido' => true, 'max' => 60, 'normalizar' => 'titulo'],
                    'modelo' => ['tipo' => 'texto', 'etiqueta' => 'Modelo', 'requerido' => true, 'max' => 60],
                    'placa' => ['tipo' => 'texto', 'etiqueta' => 'Placa', 'requerido' => true, 'max' => 20, 'normalizar' => 'mayus'],
                    'anio' => ['tipo' => 'entero', 'etiqueta' => 'Año', 'requerido' => true, 'min' => 1950, 'max' => (int) date('Y') + 1],
                    'equipo_id' => ['tipo' => 'ref', 'etiqueta' => 'Escudería', 'ref' => 'equipos'],
                    'piloto_id' => ['tipo' => 'ref', 'etiqueta' => 'Piloto asignado', 'ref' => 'pilotos', 'filtro_por' => 'equipo_id',
                        'ayuda' => 'Solo puedes asignar pilotos de la misma escudería del vehículo.'],
                ],
            ],
            'circuitos' => [
                'tabla' => 'circuitos', 'etiqueta' => 'Circuitos', 'singular' => 'circuito', 'modulo' => 'catalogos',
                'permisos' => ['ver' => null, 'crear' => 'catalogo.crear', 'editar' => 'catalogo.editar', 'borrar' => 'catalogo.borrar'],
                'borrado_logico' => false,
                'select' => 'SELECT t.* FROM circuitos t',
                'etiqueta_sql' => 't.nombre',
                'buscar' => ['t.nombre', 't.pais', 't.ciudad'],
                'orden' => ['nombre' => 't.nombre', 'pais' => 't.pais', 'ciudad' => 't.ciudad', 'longitud_km' => 't.longitud_km'],
                'orden_defecto' => 'nombre', 'filtros' => [],
                'columnas' => [['nombre', 'Circuito'], ['ciudad', 'Ciudad'], ['pais', 'País'], ['longitud_km', 'Longitud (km)']],
                'unicos' => [['nombre' => 'Ya existe un circuito con este nombre.']],
                'campos' => [
                    'nombre' => ['tipo' => 'texto', 'etiqueta' => 'Nombre del circuito', 'requerido' => true, 'max' => 100],
                    'pais' => ['tipo' => 'texto', 'etiqueta' => 'País', 'requerido' => true, 'max' => 60, 'normalizar' => 'titulo'],
                    'ciudad' => ['tipo' => 'texto', 'etiqueta' => 'Ciudad', 'requerido' => true, 'max' => 80, 'normalizar' => 'titulo'],
                    'longitud_km' => ['tipo' => 'decimal', 'etiqueta' => 'Longitud (km)', 'requerido' => true, 'min' => 0.1, 'max' => 50],
                ],
            ],
            'campeonatos' => [
                'tabla' => 'campeonatos', 'etiqueta' => 'Campeonatos', 'singular' => 'campeonato', 'modulo' => 'competencias',
                'permisos' => ['ver' => null, 'crear' => 'competencia.crear', 'editar' => 'competencia.editar', 'borrar' => 'competencia.borrar'],
                'borrado_logico' => false,
                'select' => 'SELECT t.*, (SELECT COUNT(*) FROM carreras c WHERE c.campeonato_id=t.id) AS total_carreras FROM campeonatos t',
                'etiqueta_sql' => 'CONCAT(t.nombre," ",t.temporada)',
                'buscar' => ['t.nombre'],
                'orden' => ['nombre' => 't.nombre', 'temporada' => 't.temporada', 'tipo_vehiculo' => 't.tipo_vehiculo', 'total_carreras' => 'total_carreras'],
                'orden_defecto' => 'temporada', 'orden_dir_defecto' => 'desc',
                'filtros' => ['tipo_vehiculo' => 't.tipo_vehiculo', 'temporada' => 't.temporada'],
                'columnas' => [['nombre', 'Campeonato'], ['temporada', 'Temporada'], ['tipo_vehiculo', 'Tipo'], ['total_carreras', 'Carreras']],
                'enlace_detalle' => '/campeonatos/',
                'unicos' => [['nombre' => null, 'temporada' => 'Ya existe este campeonato en esa temporada.']],
                'campos' => [
                    'nombre' => ['tipo' => 'texto', 'etiqueta' => 'Nombre del campeonato', 'requerido' => true, 'max' => 120],
                    'temporada' => ['tipo' => 'entero', 'etiqueta' => 'Temporada (año)', 'requerido' => true, 'min' => 2000, 'max' => (int) date('Y') + 5],
                    'tipo_vehiculo' => ['tipo' => 'enum', 'etiqueta' => 'Tipo de vehículo', 'requerido' => true, 'opciones' => self::TIPOS_CAMPEONATO],
                    'puntos' => ['tipo' => 'puntos', 'etiqueta' => 'Puntos por posición (separados por coma)', 'requerido' => true, 'max' => 100,
                        'ayuda' => 'Primer lugar, segundo lugar, etc. Ejemplo: 25,18,15,12,10,8,6,4,2,1'],
                ],
            ],
            'carreras' => [
                'tabla' => 'carreras', 'etiqueta' => 'Carreras', 'singular' => 'carrera', 'modulo' => 'competencias',
                'permisos' => ['ver' => null, 'crear' => 'competencia.crear', 'editar' => 'competencia.editar', 'borrar' => 'competencia.borrar'],
                'borrado_logico' => false,
                'select' => 'SELECT t.*, c.nombre AS campeonato, ci.nombre AS circuito,
                    (SELECT COUNT(*) FROM inscripciones i WHERE i.carrera_id=t.id) AS inscritos
                    FROM carreras t JOIN campeonatos c ON c.id=t.campeonato_id JOIN circuitos ci ON ci.id=t.circuito_id',
                'etiqueta_sql' => 't.nombre',
                'buscar' => ['t.nombre', 'ci.nombre'],
                'orden' => ['nombre' => 't.nombre', 'fecha' => 't.fecha', 'tipo_vehiculo' => 't.tipo_vehiculo', 'circuito' => 'circuito', 'estado' => 't.estado', 'inscritos' => 'inscritos'],
                'orden_defecto' => 'fecha',
                'filtros' => ['campeonato_id' => 't.campeonato_id', 'tipo_vehiculo' => 't.tipo_vehiculo', 'estado' => 't.estado'],
                'columnas' => [['nombre', 'Carrera'], ['fecha', 'Fecha'], ['tipo_vehiculo', 'Tipo'], ['circuito', 'Circuito'], ['inscritos', 'Inscritos'], ['estado', 'Estado']],
                'unicos' => [],
                'campos' => [
                    'campeonato_id' => ['tipo' => 'ref', 'etiqueta' => 'Campeonato', 'requerido' => true, 'ref' => 'campeonatos'],
                    'tipo_vehiculo' => ['tipo' => 'enum', 'etiqueta' => 'Tipo de vehículo', 'requerido' => true, 'opciones' => self::TIPOS_CARRERA],
                    'circuito_id' => ['tipo' => 'ref', 'etiqueta' => 'Circuito', 'requerido' => true, 'ref' => 'circuitos'],
                    'nombre' => ['tipo' => 'texto', 'etiqueta' => 'Nombre de la carrera', 'requerido' => true, 'max' => 120],
                    'fecha' => ['tipo' => 'fecha', 'etiqueta' => 'Fecha', 'requerido' => true],
                    'cupo_maximo' => ['tipo' => 'entero', 'etiqueta' => 'Cupo máximo de pilotos', 'requerido' => true, 'min' => 2, 'max' => 100, 'defecto' => 20],
                    'vueltas' => ['tipo' => 'entero', 'etiqueta' => 'Número de vueltas', 'requerido' => true, 'min' => 1, 'max' => 500, 'defecto' => 10],
                ],
            ],
            'usuarios' => [
                'tabla' => 'usuarios', 'etiqueta' => 'Usuarios', 'singular' => 'usuario', 'modulo' => 'usuarios',
                'permisos' => ['ver' => 'usuarios.gestionar', 'crear' => 'usuarios.gestionar', 'editar' => 'usuarios.gestionar', 'borrar' => 'usuarios.gestionar'],
                'borrado_logico' => false,
                'select' => 'SELECT t.id,t.nombre,t.usuario,t.email,t.rol_id,t.activo,t.created_at,t.updated_at,r.nombre AS rol
                    FROM usuarios t JOIN roles r ON r.id=t.rol_id',
                'etiqueta_sql' => 't.usuario',
                'buscar' => ['t.nombre', 't.usuario', 't.email'],
                'orden' => ['nombre' => 't.nombre', 'usuario' => 't.usuario', 'email' => 't.email', 'rol' => 'rol', 'activo' => 't.activo'],
                'orden_defecto' => 'nombre', 'filtros' => ['rol_id' => 't.rol_id'],
                'columnas' => [['nombre', 'Nombre'], ['usuario', 'Usuario'], ['email', 'Correo'], ['rol', 'Rol'], ['activo', 'Activo']],
                'unicos' => [['usuario' => 'Ese nombre de usuario ya está en uso.'], ['email' => 'Ese correo ya está registrado.']],
                'campos' => [
                    'nombre' => ['tipo' => 'texto', 'etiqueta' => 'Nombre completo', 'requerido' => true, 'max' => 120, 'normalizar' => 'titulo'],
                    'usuario' => ['tipo' => 'texto', 'etiqueta' => 'Usuario', 'requerido' => true, 'max' => 40, 'patron' => '/^[A-Za-z0-9_.-]{3,40}$/',
                        'patron_msg' => 'Usa de 3 a 40 letras, números, punto, guion o guion bajo.'],
                    'email' => ['tipo' => 'email', 'etiqueta' => 'Correo electrónico', 'requerido' => true, 'max' => 160],
                    'rol_id' => ['tipo' => 'ref', 'etiqueta' => 'Rol', 'requerido' => true, 'ref' => 'roles'],
                    'contrasena' => ['tipo' => 'password', 'etiqueta' => 'Contraseña', 'columna' => 'password_hash',
                        'ayuda' => 'Mínimo 12 caracteres con mayúscula, minúscula, número y símbolo. Al editar, déjala vacía para no cambiarla.'],
                    'activo' => ['tipo' => 'booleano', 'etiqueta' => 'Cuenta activa', 'defecto' => 1],
                ],
            ],
            // Solo para llenar listas desplegables
            'roles' => [
                'tabla' => 'roles', 'etiqueta' => 'Roles', 'solo_opciones' => true, 'borrado_logico' => false,
                'permisos' => ['ver' => null], 'select' => 'SELECT t.* FROM roles t', 'etiqueta_sql' => 't.nombre',
                'campos' => [], 'orden' => [], 'filtros' => [], 'buscar' => [],
            ],
        ];
    }

    /** Metadatos seguros para el frontend (sin SQL). */
    public static function meta(string $nombre): array
    {
        $r = self::get($nombre);
        return [
            'nombre' => $nombre, 'etiqueta' => $r['etiqueta'], 'singular' => $r['singular'] ?? '',
            'columnas' => $r['columnas'] ?? [], 'campos' => $r['campos'], 'filtros' => array_keys($r['filtros'] ?? []),
            'orden_defecto' => $r['orden_defecto'] ?? null, 'orden_dir_defecto' => $r['orden_dir_defecto'] ?? 'asc',
            'enlace_detalle' => $r['enlace_detalle'] ?? null, 'permisos' => $r['permisos'],
        ];
    }
}
