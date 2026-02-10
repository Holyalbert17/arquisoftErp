<?php

namespace Tests\Unit\Services;

use CodeIgniter\Test\CIUnitTestCase;
use App\Services\VentaService;

class VentaServiceTest extends CIUnitTestCase
{
    protected $ventaService;

    protected function setUp(): void
    {
        parent::setUp();
        // Mock models would be ideal here, but for calculations we don't need DB access usually
        // However, VentaService constructor instantiates models. 
        // We will test ONLY calcularTotales for now which is pure logic.

        // We can't easily instantiate VentaService without DB connection due to constructor.
        // So we will allow it to connect (integration test) or we need to refactor service to allow injection.
        // For this task, let's assume valid DB env or just suppress constructor for calculation test?
        // CI4 ReflectionHelper can help, or we just rely on the env being setup.

        $this->ventaService = new VentaService(false);
    }

    public function testCalcularTotalesSimple()
    {
        $productos = [
            [
                'precio' => 10000,
                'cantidad' => 2,
                'descuento' => 0,
                'tipo_iva' => 'GRAVADO_10'
            ]
        ];

        $resultado = $this->ventaService->calcularTotales($productos);

        $this->assertEquals(20000, $resultado['total']);
        $this->assertEquals(20000, $resultado['subtotal_gravado_10']);
        // IVA 10% is amount / 11.  20000 / 11 = 1818.18... -> 1818
        $this->assertEquals(1818, $resultado['iva_10']);
        $this->assertEquals(0, $resultado['iva_5']);
    }

    public function testCalcularTotalesMixto()
    {
        $productos = [
            [
                'precio' => 11000, // 1000 de IVA
                'cantidad' => 1,
                'tipo_iva' => 'GRAVADO_10'
            ],
            [
                'precio' => 21000, // 1000 de IVA (5% es div 21)
                'cantidad' => 1,
                'tipo_iva' => 'GRAVADO_5'
            ]
        ];

        $resultado = $this->ventaService->calcularTotales($productos);

        $this->assertEquals(32000, $resultado['total']);
        $this->assertEquals(1000, $resultado['iva_10']);
        $this->assertEquals(1000, $resultado['iva_5']);
        $this->assertEquals(2000, $resultado['total_iva']);
    }

    public function testDescuentoGeneral()
    {
        $productos = [
            [
                'precio' => 100000,
                'cantidad' => 1,
                'tipo_iva' => 'GRAVADO_10'
            ]
        ];

        // 10% descuento general
        $resultado = $this->ventaService->calcularTotales($productos, 10);

        $this->assertEquals(90000, $resultado['total']);
        $this->assertEquals(10000, $resultado['descuento_monto']);
        // IVA de 90000 -> 90000 / 11 = 8181.81 -> 8182
        $this->assertEquals(8182, $resultado['iva_10']);
    }

    public function testValidarCredito()
    {
        // Mocking ClienteModel using Reflection
        $mockClienteModel = $this->createMock(\App\Models\ClienteModel::class);
        $mockClienteModel->method('find')->willReturn([
            'id' => 1,
            'limite_credito' => 1000000,
            'credito_utilizado' => 500000
        ]);

        $reflection = new \ReflectionClass($this->ventaService);
        $property = $reflection->getProperty('clienteModel');
        $property->setAccessible(true);
        $property->setValue($this->ventaService, $mockClienteModel);

        $resultado = $this->ventaService->validarCredito(1, 400000);
        $this->assertTrue($resultado['valido']);

        $resultado = $this->ventaService->validarCredito(1, 600000);
        $this->assertFalse($resultado['valido']);
        $this->assertStringContainsString('Crédito insuficiente', $resultado['mensaje']);
    }
}
