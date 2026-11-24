<?php
declare(strict_types=1);
namespace RaceControl\Application;

use PDO;
use PDOException;
use RaceControl\Domain\PoliticaContrasena;
use RaceControl\Domain\SistemaPuntos;
use RaceControl\Infrastructure\Database;

/** CRUD genérico guiado por las definiciones de Recursos: listar, validar, guardar y borrar. */
final class CatalogoService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::pdo();
    }

    private function recurso(string $nombre): array
    {
        $r = Recursos::get($nombre);
        if (!$r) {
            throw new NoEncontradoError('Recurso desconocido.');
        }
        return $r;
    }

    /** @return array{datos:array,total:int,pagina:int,por_pagina:int} */
    public function listar(string $nombre, array $q): array
    {
        $r = $this->recurso($nombre);
        [$where, $params] = $this->condiciones($r, $q);

        $por = (int) ($q['por'] ?? 10);
        $por = in_array($por, [10, 25, 50], true) ? $por : 10;
        $pagina = max(1, (int) ($q['pagina'] ?? 1));

        $total = $this->db->prepare("SELECT COUNT(*) FROM ({$r['select']}{$where}) AS conteo");
        $total->execute($params);
        $total = (int) $total->fetchColumn();
        $pagina = min($pagina, max(1, (int) ceil($total / $por)));

        $orden = $r['orden'][$q['orden'] ?? ''] ?? $r['orden'][$r['orden_defecto']];
        $dir = strtolower((string) ($q['dir'] ?? ($r['orden_dir_defecto'] ?? 'asc'))) === 'desc' ? 'DESC' : 'ASC';
        $sql = "{$r['select']}{$where} ORDER BY $orden $dir, t.id ASC LIMIT $por OFFSET " . (($pagina - 1) * $por);
        $st = $this->db->prepare($sql);
        $st->execute($params);

        return ['datos' => $st->fetchAll(), 'total' => $total, 'pagina' => $pagina, 'por_pagina' => $por];
    }

    /** Lista corta {id, etiqueta} para listas desplegables. */
    public function opciones(string $nombre, array $q = []): array
    {
        $r = $this->recurso($nombre);
        $where = $r['borrado_logico'] ? ' WHERE t.deleted_at IS NULL' : '';
        $params = [];
        if ($nombre === 'vehiculos' && isset($q['tipo_vehiculo']) && $q['tipo_vehiculo'] !== '') {
            if (!in_array($q['tipo_vehiculo'], ['auto', 'moto'], true)) {
                throw new ValidacionError(['tipo_vehiculo' => 'Elige un tipo de vehículo válido.']);
            }
            $where .= ($where ? ' AND' : ' WHERE') . ' t.tipo_vehiculo=?';
            $params[] = $q['tipo_vehiculo'];
        }
        if ($nombre === 'pilotos' && isset($q['equipo_id']) && $q['equipo_id'] !== '') {
            $equipoId = filter_var($q['equipo_id'], FILTER_VALIDATE_INT);
            if ($equipoId === false || $equipoId < 1) {
                throw new ValidacionError(['equipo_id' => 'Elige una escudería válida.']);
            }
            $where .= ($where ? ' AND' : ' WHERE') . ' t.equipo_id=?';
            $params[] = $equipoId;
        }
        if ($nombre === 'pilotos' && isset($q['tipo_vehiculo']) && $q['tipo_vehiculo'] !== '') {
            if (!in_array($q['tipo_vehiculo'], ['auto', 'moto', 'mixto'], true)) {
                throw new ValidacionError(['tipo_vehiculo' => 'Elige un tipo de vehículo válido.']);
            }
            $where .= ($where ? ' AND' : ' WHERE') . ' EXISTS (
                SELECT 1 FROM vehiculos v
                WHERE v.piloto_id=t.id AND v.deleted_at IS NULL';
            if ($q['tipo_vehiculo'] !== 'mixto') {
                $where .= ' AND v.tipo_vehiculo=?';
                $params[] = $q['tipo_vehiculo'];
            }
            $where .= ')';
        }
        if ($nombre === 'vehiculos' && isset($q['piloto_id']) && $q['piloto_id'] !== '') {
            $pilotoId = filter_var($q['piloto_id'], FILTER_VALIDATE_INT);
            if ($pilotoId === false || $pilotoId < 1) {
                throw new ValidacionError(['piloto_id' => 'Elige un piloto válido.']);
            }
            $where .= ($where ? ' AND' : ' WHERE') . ' t.piloto_id=?';
            $params[] = $pilotoId;
        }
        $tipoVehiculo = $nombre === 'vehiculos' ? ', t.tipo_vehiculo' : '';
        $st = $this->db->prepare("SELECT t.id, {$r['etiqueta_sql']} AS etiqueta$tipoVehiculo FROM {$r['tabla']} t{$where} ORDER BY etiqueta");
        $st->execute($params);
        return $st->fetchAll();
    }

    public function obtener(string $nombre, int $id): array
    {
        $r = $this->recurso($nombre);
        $borrado = $r['borrado_logico'] ? ' AND t.deleted_at IS NULL' : '';
        $st = $this->db->prepare("{$r['select']} WHERE t.id=?$borrado");
        $st->execute([$id]);
        return $st->fetch() ?: throw new NoEncontradoError();
    }

    public function crear(string $nombre, array $datos, array $ctx = []): array
    {
        $r = $this->recurso($nombre);
        [$limpios, $errores] = $this->validar($r, $datos, null);
        $limpios = Hooks::antes($nombre, $limpios, null, $errores, null, $this->db);
        if ($errores) {
            throw new ValidacionError($errores);
        }
        $cols = array_keys($limpios);
        $sql = sprintf('INSERT INTO %s (%s) VALUES (%s)', $r['tabla'], implode(',', $cols), implode(',', array_fill(0, count($cols), '?')));
        $this->ejecutar($sql, array_values($limpios));
        $id = (int) $this->db->lastInsertId();
        Hooks::despuesGuardar($nombre, $id, $limpios, null, $this->db);
        return $this->obtener($nombre, $id);
    }

    public function actualizar(string $nombre, int $id, array $datos, array $ctx = []): array
    {
        $r = $this->recurso($nombre);
        $existente = $this->obtener($nombre, $id);
        [$limpios, $errores] = $this->validar($r, $datos, $id);
        $limpios = Hooks::antes($nombre, $limpios, $id, $errores, $existente, $this->db);
        if ($errores) {
            throw new ValidacionError($errores);
        }
        if ($limpios) {
            $set = implode(',', array_map(fn ($c) => "$c=?", array_keys($limpios)));
            $this->ejecutar("UPDATE {$r['tabla']} SET $set WHERE id=?", [...array_values($limpios), $id]);
        }
        Hooks::despuesGuardar($nombre, $id, $limpios, $existente, $this->db);
        return $this->obtener($nombre, $id);
    }

    public function eliminar(string $nombre, int $id, array $ctx = []): void
    {
        $r = $this->recurso($nombre);
        $existente = $this->obtener($nombre, $id);
        Hooks::antesBorrar($nombre, $existente, $ctx, $this->db);
        if ($r['borrado_logico']) {
            $this->ejecutar("UPDATE {$r['tabla']} SET deleted_at=? WHERE id=?", [Database::ahora(), $id]);
        } else {
            $this->ejecutar("DELETE FROM {$r['tabla']} WHERE id=?", [$id]);
        }
        Hooks::despuesBorrar($nombre, $existente, $this->db);
    }

    // ---------- Internos ----------

    private function condiciones(array $r, array $q): array
    {
        $w = [];
        $p = [];
        if ($r['borrado_logico']) {
            $w[] = 't.deleted_at IS NULL';
        }
        $busqueda = trim((string) ($q['q'] ?? ''));
        if ($busqueda !== '' && $r['buscar']) {
            $like = '%' . addcslashes($busqueda, '%_\\') . '%';
            $w[] = '(' . implode(' OR ', array_map(fn ($c) => "$c LIKE ?", $r['buscar'])) . ')';
            array_push($p, ...array_fill(0, count($r['buscar']), $like));
        }
        foreach ($r['filtros'] as $clave => $columna) {
            if (isset($q[$clave]) && $q[$clave] !== '') {
                $w[] = "$columna = ?";
                $p[] = $q[$clave];
            }
        }
        return [$w ? ' WHERE ' . implode(' AND ', $w) : '', $p];
    }

    /** @return array{0:array,1:array} [datos limpios por columna, errores por campo] */
    private function validar(array $r, array $datos, ?int $id): array
    {
        $limpios = [];
        $errores = [];
        foreach ($r['campos'] as $campo => $c) {
            $valor = $datos[$campo] ?? null;
            if (is_string($valor)) {
                $valor = trim(preg_replace('/\s+/u', ' ', $valor));
            }
            $vacio = $valor === null || $valor === '';
            $tipo = $c['tipo'];
            $columna = $c['columna'] ?? $campo;

            if ($tipo === 'booleano') {
                $limpios[$columna] = $vacio || $valor === '0' || $valor === 0 || $valor === false ? 0 : 1;
                continue;
            }
            if ($tipo === 'password') {
                if ($vacio) {
                    if ($id === null) {
                        $errores[$campo] = 'Escribe una contraseña.';
                    }
                    continue;
                }
                $problemas = PoliticaContrasena::validar((string) $valor, (string) ($datos['usuario'] ?? ''));
                if ($problemas) {
                    $errores[$campo] = implode(' ', $problemas);
                    continue;
                }
                $limpios[$columna] = password_hash((string) $valor, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT);
                continue;
            }
            if ($vacio) {
                if (!empty($c['requerido'])) {
                    $errores[$campo] = 'Este campo es obligatorio.';
                } else {
                    $limpios[$columna] = null;
                }
                continue;
            }
            $error = null;
            $limpios[$columna] = $this->validarTipo($c, $tipo, $valor, $error);
            if ($error) {
                $errores[$campo] = $error;
            }
        }
        $this->verificarUnicos($r, $limpios, $id, $errores);
        return [$limpios, $errores];
    }

    private function validarTipo(array $c, string $tipo, mixed $v, ?string &$error): mixed
    {
        switch ($tipo) {
            case 'texto':
            case 'email':
                $v = (string) $v;
                if (($c['normalizar'] ?? '') === 'titulo' && ($v === mb_strtolower($v) || $v === mb_strtoupper($v))) {
                    $v = mb_convert_case($v, MB_CASE_TITLE);
                } elseif (($c['normalizar'] ?? '') === 'mayus') {
                    $v = mb_strtoupper($v);
                }
                if (isset($c['max']) && mb_strlen($v) > $c['max']) {
                    $error = "Máximo {$c['max']} caracteres.";
                } elseif ($tipo === 'email' && !filter_var($v, FILTER_VALIDATE_EMAIL)) {
                    $error = 'Escribe un correo válido.';
                } elseif (isset($c['patron']) && !preg_match($c['patron'], $v)) {
                    $error = $c['patron_msg'] ?? 'Formato no válido.';
                }
                return $v;
            case 'entero':
                if (filter_var($v, FILTER_VALIDATE_INT) === false) {
                    $error = 'Escribe un número entero.';
                    return null;
                }
                $v = (int) $v;
                if (isset($c['min']) && $v < $c['min'] || isset($c['max']) && $v > $c['max']) {
                    $error = "Debe estar entre {$c['min']} y {$c['max']}.";
                }
                return $v;
            case 'decimal':
                if (!is_numeric($v)) {
                    $error = 'Escribe un número.';
                    return null;
                }
                $v = round((float) $v, 3);
                if (isset($c['min']) && $v < $c['min'] || isset($c['max']) && $v > $c['max']) {
                    $error = "Debe estar entre {$c['min']} y {$c['max']}.";
                }
                return $v;
            case 'fecha':
                $d = \DateTime::createFromFormat('Y-m-d', (string) $v);
                if (!$d || $d->format('Y-m-d') !== $v) {
                    $error = 'Escribe una fecha válida (AAAA-MM-DD).';
                } elseif (!empty($c['no_futura']) && $d > new \DateTime('today')) {
                    $error = 'La fecha no puede ser futura.';
                }
                return $v;
            case 'enum':
                if (!array_key_exists((string) $v, $c['opciones'])) {
                    $error = 'Elige una opción válida.';
                }
                return $v;
            case 'ref':
                $ref = Recursos::get($c['ref']);
                $borrado = $ref['borrado_logico'] ? ' AND deleted_at IS NULL' : '';
                $st = $this->db->prepare("SELECT 1 FROM {$ref['tabla']} WHERE id=?$borrado");
                $st->execute([(int) $v]);
                if (!$st->fetchColumn()) {
                    $error = 'El registro elegido no existe o fue eliminado.';
                }
                return (int) $v;
            case 'puntos':
                if (SistemaPuntos::parsear((string) $v) === null) {
                    $error = 'Usa números enteros separados por coma. Ejemplo: 25,18,15.';
                }
                return str_replace(' ', '', (string) $v);
        }
        return $v;
    }

    private function verificarUnicos(array $r, array $limpios, ?int $id, array &$errores): void
    {
        foreach ($r['unicos'] as $grupo) {
            $cols = array_keys($grupo);
            if (array_diff($cols, array_keys($limpios)) || array_filter($cols, fn ($c) => ($limpios[$c] ?? null) === null)) {
                continue;
            }
            $mensaje = current(array_filter($grupo));
            $campoError = array_key_last(array_filter($grupo));
            $sql = "SELECT id FROM {$r['tabla']} WHERE " . implode(' AND ', array_map(fn ($c) => "$c=?", $cols));
            $p = array_map(fn ($c) => $limpios[$c], $cols);
            if ($id !== null) {
                $sql .= ' AND id<>?';
                $p[] = $id;
            }
            $st = $this->db->prepare($sql);
            $st->execute($p);
            if ($st->fetchColumn() && !isset($errores[$campoError])) {
                $errores[$campoError] = $mensaje;
            }
        }
    }

    private function ejecutar(string $sql, array $params): void
    {
        try {
            $this->db->prepare($sql)->execute($params);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                throw new ErrorApp('No se puede completar la acción: el registro está relacionado con otros datos o ya existe.', 409);
            }
            throw $e;
        }
    }
}
