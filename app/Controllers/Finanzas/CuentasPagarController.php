<?php

namespace App\Controllers\Finanzas;

use App\Controllers\BaseController;
use App\Models\CuentaPagarModel;
use App\Models\PagoCuentaModel;
use App\Models\ProveedorModel;
use App\Models\CompraModel;
use App\Models\CajaModel;
use App\Models\MovimientoCajaModel;

class CuentasPagarController extends BaseController
{
    protected $cuentaPagarModel;
    protected $pagoCuentaModel;
    protected $proveedorModel;
    protected $compraModel;
    protected $cajaModel;
    protected $movimientoCajaModel;

    public function __construct()
    {
        $this->cuentaPagarModel = new CuentaPagarModel();
        $this->pagoCuentaModel = new PagoCuentaModel();
        $this->proveedorModel = new ProveedorModel();
        $this->compraModel = new CompraModel();
        $this->cajaModel = new CajaModel();
        $this->movimientoCajaModel = new MovimientoCajaModel();
    }

    protected function checkAuth()
    {
        if (!$this->isLoggedIn()) {
            return redirect()->to('/auth/login');
        }
        $this->requirePermission('CUENTAS_PAGAR', 'ver');
    }

    public function index()
    {
        $this->checkAuth();
        $empresaId = $this->user['empresa_id'];
        $sucursalId = $this->sucursal ? $this->sucursal['id'] : null;

        $builder = $this->cuentaPagarModel->where('empresa_id', $empresaId);
        if ($sucursalId) {
            $builder->where('sucursal_id', $sucursalId);
        }

        $cuentas = $builder->orderBy('fecha_vencimiento', 'ASC')
            ->findAll();

        // Obtener cajas abiertas para el selector en el modal de pago
        $cajasBuilder = $this->cajaModel->where('empresa_id', $empresaId)
            ->where('estado', 'ABIERTA');
        if ($sucursalId) {
            $cajasBuilder->where('sucursal_id', $sucursalId);
        }
        // Empleados solo ven sus propias cajas
        $rolModel = new \App\Models\RolModel();
        $rol = $rolModel->find($this->user['rol_id'] ?? 0);
        $esAdmin = $rol && (int)($rol['nivel'] ?? 99) <= 3;
        if (!$esAdmin) {
            $cajasBuilder->groupStart()
                ->where('usuario_id', $this->user['id'])
                ->orWhere('usuario_apertura', $this->user['id'])
                ->groupEnd();
        }
        $cajasAbiertas = $cajasBuilder->findAll();

        // Verificar si hay compras pendientes sin cuenta por pagar y sincronizar automáticamente
        $comprasBuilder = $this->compraModel->where('empresa_id', $empresaId)
            ->where('monto_pendiente >', 0);
        if ($sucursalId) {
            $comprasBuilder->where('sucursal_id', $sucursalId);
        }
        $comprasPendientes = $comprasBuilder->findAll();

        $comprasSinCuenta = 0;
        foreach ($comprasPendientes as $compra) {
            $cuentaExistente = $this->cuentaPagarModel->where('compra_id', $compra['id'])->first();
            if (!$cuentaExistente) {
                $comprasSinCuenta++;
            }
        }

        // Si hay compras sin cuenta, sincronizar automáticamente
        if ($comprasSinCuenta > 0) {
            $this->sincronizarCompras($empresaId, $sucursalId);
            // Recargar cuentas después de sincronizar (query limpio)
            $reloadBuilder = $this->cuentaPagarModel->where('empresa_id', $empresaId);
            if ($sucursalId) {
                $reloadBuilder->where('sucursal_id', $sucursalId);
            }
            $cuentas = $reloadBuilder->orderBy('fecha_vencimiento', 'ASC')->findAll();
        }

        // Agregar información de proveedor y calcular días vencidos
        $fechaActual = time();
        foreach ($cuentas as &$cuenta) {
            $cuenta['proveedor'] = $this->proveedorModel->find($cuenta['proveedor_id']);

            // Calcular días vencidos si hay fecha de vencimiento
            if (!empty($cuenta['fecha_vencimiento'])) {
                $fechaVencimiento = strtotime($cuenta['fecha_vencimiento']);
                if ($fechaVencimiento < $fechaActual && ($cuenta['monto_pendiente'] ?? 0) > 0) {
                    $diasVencido = floor(($fechaActual - $fechaVencimiento) / (60 * 60 * 24));
                    $cuenta['dias_vencido'] = $diasVencido;

                    // Actualizar estado si está vencida y pendiente
                    if ($cuenta['estado'] != 'PAGADO' && $diasVencido > 0) {
                        $cuenta['estado'] = 'VENCIDO';
                        // Actualizar en la base de datos
                        $this->cuentaPagarModel->update($cuenta['id'], [
                            'dias_vencido' => $diasVencido,
                            'estado' => 'VENCIDO'
                        ]);
                    }
                } else {
                    $cuenta['dias_vencido'] = $cuenta['dias_vencido'] ?? 0;
                }
            } else {
                $cuenta['dias_vencido'] = $cuenta['dias_vencido'] ?? 0;
            }

            // Actualizar estado según monto pendiente
            if (($cuenta['monto_pendiente'] ?? 0) <= 0 && $cuenta['estado'] != 'PAGADO') {
                $this->cuentaPagarModel->update($cuenta['id'], ['estado' => 'PAGADO']);
                $cuenta['estado'] = 'PAGADO';
            } elseif (($cuenta['monto_pagado'] ?? 0) > 0 && ($cuenta['monto_pendiente'] ?? 0) > 0 && $cuenta['estado'] == 'PENDIENTE') {
                $this->cuentaPagarModel->update($cuenta['id'], ['estado' => 'PARCIAL']);
                $cuenta['estado'] = 'PARCIAL';
            }
        }

        $data = [
            'title' => 'Cuentas por Pagar',
            'cuentas' => $cuentas,
            'cajasAbiertas' => $cajasAbiertas
        ];

        return $this->render('finanzas/cuentas_pagar/index', $data);
    }

    public function registrarPago()
    {
        $this->checkAuth();
        $cuentaId = $this->request->getPost('cuenta_id');
        $monto = $this->normalizarNumero($this->request->getPost('monto'));
        $formaPago = $this->request->getPost('forma_pago');
        $referencia = $this->request->getPost('referencia');
        $cajaId = $this->request->getPost('caja_id');

        // Validaciones pre-servicio básicas (Input validation)
        if ($monto <= 0) {
            return $this->errorResponse('El monto debe ser mayor a cero');
        }

        $finanzasService = new \App\Services\FinanzasService();
        $resultado = $finanzasService->registrarPagoProveedor($cuentaId, $monto, $formaPago, $cajaId, $this->user['id'], $referencia);

        if ($resultado['success']) {
            return $this->successResponse($resultado['message']);
        } else {
            return $this->errorResponse($resultado['message']);
        }
    }

    /**
     * Método privado para sincronizar compras (usado internamente)
     */
    private function sincronizarCompras($empresaId, $sucursalId = null)
    {
        $builder = $this->compraModel->where('empresa_id', $empresaId)
            ->where('monto_pendiente >', 0);

        if ($sucursalId) {
            $builder->where('sucursal_id', $sucursalId);
        }

        $compras = $builder->findAll();
        $cuentasCreadas = 0;

        foreach ($compras as $compra) {
            // Verificar si ya existe una cuenta por pagar para esta compra
            $cuentaExistente = $this->cuentaPagarModel->where('compra_id', $compra['id'])->first();

            if (!$cuentaExistente) {
                // Crear cuenta por pagar
                $diasCredito = (int) ($compra['dias_credito'] ?? 30);
                $fechaVencimiento = !empty($compra['fecha_vencimiento'])
                    ? date('Y-m-d', strtotime($compra['fecha_vencimiento']))
                    : date('Y-m-d', strtotime("+{$diasCredito} days"));

                // Calcular días vencido
                $diasVencido = 0;
                $fechaVencimientoTimestamp = strtotime($fechaVencimiento);
                $fechaActualTimestamp = time();
                if ($fechaVencimientoTimestamp < $fechaActualTimestamp) {
                    $diasVencido = floor(($fechaActualTimestamp - $fechaVencimientoTimestamp) / (60 * 60 * 24));
                }

                // Determinar estado
                $estado = 'PENDIENTE';
                if ($diasVencido > 0) {
                    $estado = 'VENCIDO';
                }

                $cuentaPagarData = [
                    'empresa_id' => $compra['empresa_id'],
                    'sucursal_id' => $compra['sucursal_id'],
                    'proveedor_id' => $compra['proveedor_id'],
                    'compra_id' => $compra['id'],
                    'numero_documento' => $compra['numero_factura_proveedor'] ?? $compra['numero_compra'],
                    'tipo_documento' => $compra['tipo_compra'] ?? 'COMPRA',
                    'fecha_emision' => date('Y-m-d', strtotime($compra['fecha_compra'])),
                    'fecha_vencimiento' => $fechaVencimiento,
                    'monto_total' => round($compra['total'], 2),
                    'monto_pagado' => round($compra['monto_pagado'] ?? 0, 2),
                    'monto_pendiente' => round($compra['monto_pendiente'], 2),
                    'dias_vencido' => $diasVencido,
                    'estado' => $estado
                ];

                if ($this->cuentaPagarModel->insert($cuentaPagarData)) {
                    $cuentasCreadas++;
                }
            }
        }

        return $cuentasCreadas;
    }

    /**
     * Sincronizar compras pendientes que no tienen cuenta por pagar
     * Este método crea cuentas por pagar para compras existentes que tienen monto pendiente
     */
    public function sincronizar()
    {
        $this->checkAuth();
        $empresaId = $this->user['empresa_id'];
        $sucursalId = $this->sucursal ? $this->sucursal['id'] : null;

        $db = \Config\Database::connect();
        $db->transStart();

        try {
            $cuentasCreadas = $this->sincronizarCompras($empresaId, $sucursalId);

            $db->transComplete();

            if ($db->transStatus() === false) {
                return $this->errorResponse('Error al sincronizar cuentas por pagar');
            }

            return $this->successResponse("Se crearon {$cuentasCreadas} cuenta(s) por pagar para compras pendientes");

        } catch (\Exception $e) {
            $db->transRollback();
            return $this->errorResponse('Error: ' . $e->getMessage());
        }
    }
}
