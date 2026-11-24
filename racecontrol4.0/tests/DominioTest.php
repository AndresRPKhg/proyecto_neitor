<?php
declare(strict_types=1);
namespace RaceControl\Tests;

use PHPUnit\Framework\TestCase;
use RaceControl\Domain\Estadistica;
use RaceControl\Domain\PoliticaContrasena;
use RaceControl\Domain\ReglasInscripcion;
use RaceControl\Domain\Rol;
use RaceControl\Domain\SistemaPuntos;
use RaceControl\Domain\Tiempo;

final class DominioTest extends TestCase
{
    public function testContrasenaFuerteEsValida(): void
    {
        $this->assertSame([], PoliticaContrasena::validar('Catedral#Noche2026', 'herin'));
    }

    public function testContrasenaDebilTieneVariosErrores(): void
    {
        $errores = PoliticaContrasena::validar('corta');
        $this->assertGreaterThanOrEqual(4, count($errores));
    }

    public function testContrasenaComunOConUsuarioSeRechaza(): void
    {
        $this->assertNotEmpty(PoliticaContrasena::validar('Password#12345678'));
        $this->assertNotEmpty(PoliticaContrasena::validar('Herin#Segura2026', 'herin'));
    }

    public function testTiempoConvierteYFormatea(): void
    {
        $this->assertSame(2712345, Tiempo::aMs('45:12.345'));
        $this->assertSame('45:12.345', Tiempo::formato(2712345));
        $this->assertNull(Tiempo::aMs('  '));
        $this->assertFalse(Tiempo::aMs('45:72.345'));
        $this->assertFalse(Tiempo::aMs('abc'));
    }

    public function testPuntosPorDefecto(): void
    {
        $p = SistemaPuntos::parsear(SistemaPuntos::DEFECTO);
        $this->assertSame(25, SistemaPuntos::paraPosicion($p, 1));
        $this->assertSame(1, SistemaPuntos::paraPosicion($p, 10));
        $this->assertSame(0, SistemaPuntos::paraPosicion($p, 11));
        $this->assertNull(SistemaPuntos::parsear('25,x,15'));
    }

    public function testEstadisticaDescriptiva(): void
    {
        $v = [1, 2, 2, 3, 100];
        $this->assertSame(21.6, Estadistica::media($v));
        $this->assertSame(2.0, Estadistica::mediana($v));
        $this->assertSame(2.0, Estadistica::moda($v));
        $this->assertSame(2.5, Estadistica::mediana([1, 2, 3, 4]));
        $this->assertNull(Estadistica::media([]));
    }

    public function testOutliersPorIqr(): void
    {
        $fuera = Estadistica::outliers([90, 91, 92, 93, 94, 200]);
        $this->assertSame([5 => 200], $fuera);
        $this->assertSame([], Estadistica::outliers([1, 2, 3]));
    }

    public function testReglasDeInscripcion(): void
    {
        $carrera = ['estado' => 'programada', 'tipo_vehiculo' => 'auto', 'cupo_maximo' => 2];
        $this->assertSame([], ReglasInscripcion::validar($carrera, ['tipo_vehiculo' => 'auto'], 1, false, false));
        $this->assertSame([], ReglasInscripcion::validar(['estado' => 'programada', 'tipo_vehiculo' => 'mixto', 'cupo_maximo' => 2], ['tipo_vehiculo' => 'moto'], 1, false, false));
        $this->assertArrayHasKey('piloto_id', ReglasInscripcion::validar($carrera, ['tipo_vehiculo' => 'auto'], 1, true, false));
        $this->assertArrayHasKey('vehiculo_id', ReglasInscripcion::validar($carrera, ['tipo_vehiculo' => 'moto'], 1, false, false));
        $this->assertArrayHasKey('carrera_id', ReglasInscripcion::validar($carrera, ['tipo_vehiculo' => 'auto'], 2, false, false));
        $this->assertArrayHasKey('carrera_id', ReglasInscripcion::validar(['estado' => 'finalizada'] + $carrera, ['tipo_vehiculo' => 'auto'], 0, false, false));
    }

    public function testMatrizDePermisos(): void
    {
        $this->assertTrue(Rol::puede('admin', 'usuarios.gestionar'));
        $this->assertFalse(Rol::puede('editor', 'usuarios.gestionar'));
        $this->assertTrue(Rol::puede('editor', 'catalogo.editar'));
        $this->assertFalse(Rol::puede('editor', 'catalogo.borrar'));
        $this->assertFalse(Rol::puede('lector', 'resultados.gestionar'));
        $this->assertTrue(Rol::puede('lector', 'reportes.exportar'));
    }
}
