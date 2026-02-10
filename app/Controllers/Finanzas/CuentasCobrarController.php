<?php

namespace App\Controllers\Finanzas;

use App\Controllers\BaseController;
use App\Models\CuentaCobrarModel;
use App\Models\PagoCuentaModel;
use App\Models\ClienteModel;
use App\Models\VentaModel;
use App\Models\CajaModel;
use App\Models\MovimientoCajaModel;

class CuentasCobrarController extends BaseController
{
    protected $cuentaCobrarModel;
    protected $pagoCuentaModel;
    protected $clienteModel;
    protected $ventaModel;
    protected $cajaModel;
    protected $movimientoCajaModel;

    public function __construct()
    {
        $this->cuentaCobrarModel = new CuentaCobrarModel();
        $this->pagoCuentaModel = new PagoCuentaModel();
        $this->clienteModel = new ClienteModel();
        $this->ventaModel = new VentaModel();
        $this->cajaModel = new CajaModel();
        $this->movimientoCajaModel = new MovimientoCajaModel();
    }

    protected function checkAuth()
    {
        if (!$this->isLoggedIn()) {
            return redirect()->to('/auth/login');
        }
        $this->requirePermission('CUENTAS_COBRAR', 'ver');
    }

    public function index()
    {
        $this->checkAuth();
        $empresaId = $this->user['empresa_id'];
        $sucursalId = $this->sucursal ? $this->sucursal['id'] : null;

        $builder = $this->cuentaCobrarModel->where('empresa_id', $empresaId);
        if ($sucursalId) {
            $builder->where('sucursal_id', $sucursalId);
        }

        // Filtrar solo cuentas con deuda pendiente (mayor a 0)
        // Usamos 0.9 para evitar problemas con decimales pequeños que visualmente son 0
        $builder->where('monto_pendiente >=', 1);

        $cuentas = $builder->orderBy('fecha_vencimiento', 'ASC')
            ->findAll();

        // Verificar si hay ventas pendientes sin cuenta por cobrar y sincronizar automáticamente
        $ventasBuilder = $this->ventaModel->where('empresa_id', $empresaId)
            ->where('monto_pendiente >', 0);
        if ($sucursalId) {
            $ventasBuilder->where('sucursal_id', $sucursalId);
        }
        $ventasPendientes = $ventasBuilder->findAll();

        $ventasSinCuenta = 0;
        foreach ($ventasPendientes as $venta) {
            $cuentaExistente = $this->cuentaCobrarModel->where('venta_id', $venta['id'])->first();
            if (!$cuentaExistente) {
                $ventasSinCuenta++;
            }
        }

        // Si hay ventas sin cuenta, sincronizar automáticamente
        if ($ventasSinCuenta > 0) {
            $this->sincronizarVentas($empresaId, $sucursalId);
            // Recargar cuentas después de sincronizar (query limpio)
            $reloadBuilder = $this->cuentaCobrarModel->where('empresa_id', $empresaId);
            if ($sucursalId) {
                $reloadBuilder->where('sucursal_id', $sucursalId);
            }
            $reloadBuilder->where('monto_pendiente >=', 1);
            $cuentas = $reloadBuilder->orderBy('fecha_vencimiento', 'ASC')->findAll();
        }

        // Agregar información de cliente, pagos y calcular días vencidos
        $fechaActual = time();
        foreach ($cuentas as &$cuenta) {
            $cuenta['cliente'] = $this->clienteModel->find($cuenta['cliente_id']);

            // Obtener historial de pagos
            $cuenta['pagos'] = $this->pagoCuentaModel
                ->where('cuenta_cobrar_id', $cuenta['id'])
                ->orderBy('fecha_pago', 'DESC')
                ->orderBy('fecha_creacion', 'DESC')
                ->findAll();

            // Calcular días vencido
            if (!empty($cuenta['fecha_vencimiento'])) {
                $fechaVencimientoTimestamp = strtotime($cuenta['fecha_vencimiento']);
                // Se vence al día siguiente de la fecha de vencimiento
                if ($fechaVencimientoTimestamp < strtotime('today')) {
                    $cuenta['dias_vencido'] = floor((time() - $fechaVencimientoTimestamp) / (60 * 60 * 24));
                } else {
                    $cuenta['dias_vencido'] = 0;
                }
            } else {
                $cuenta['dias_vencido'] = 0;
            }

            // Actualizar estado según monto pendiente y días vencido
            // Importante: Redondear el monto pendiente para coincidir con lo visual (Guaraníes)
            $montoPendiente = round((float) ($cuenta['monto_pendiente'] ?? 0));

            if ($montoPendiente <= 0) {
                $nuevoEstado = 'PAGADO';
            } elseif ($cuenta['dias_vencido'] > 0) {
                $nuevoEstado = 'VENCIDO';
            } elseif ($montoPendiente < round((float) ($cuenta['monto_total'] ?? 0))) {
                $nuevoEstado = 'PARCIAL';
            } else {
                $nuevoEstado = 'PENDIENTE';
            }

            // Actualizar en base de datos si cambió
            if (($cuenta['estado'] ?? 'PENDIENTE') !== $nuevoEstado) {
                $this->cuentaCobrarModel->update($cuenta['id'], [
                    'dias_vencido' => $cuenta['dias_vencido'],
                    'estado' => $nuevoEstado
                ]);
                $cuenta['estado'] = $nuevoEstado;
            }
        }

        // Obtener cajas abiertas para el selector en el modal de pago
        $cajaModel = new \App\Models\CajaModel();
        $cajasBuilder = $cajaModel->where('empresa_id', $empresaId)
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

        $data = [
            'title' => 'Cuentas por Cobrar',
            'cuentas' => $cuentas,
            'cajasAbiertas' => $cajasAbiertas
        ];

        return $this->render('finanzas/cuentas_cobrar/index', $data);
    }

    public function registrarPago()
    {
        $this->checkAuth();
        $cuentaId = $this->request->getPost('cuenta_id');
        $monto = $this->normalizarNumero($this->request->getPost('monto'), 0);
        $formaPago = $this->request->getPost('forma_pago');
        $referencia = $this->request->getPost('referencia');
        $cajaId = $this->request->getPost('caja_id');

        $finanzasService = new \App\Services\FinanzasService();
        $resultado = $finanzasService->registrarCobro($cuentaId, $monto, $formaPago, $cajaId, $this->user['id'], $referencia);

        if ($resultado['success']) {
            return $this->successResponse($resultado['message']);
        } else {
            return $this->errorResponse($resultado['message']);
        }
    }

    /**
     * Sincronizar ventas pendientes que no tienen cuenta por cobrar
     */
    private function sincronizarVentas($empresaId, $sucursalId = null)
    {
        $builder = $this->ventaModel->where('empresa_id', $empresaId)
            ->where('monto_pendiente >', 0);

        if ($sucursalId) {
            $builder->where('sucursal_id', $sucursalId);
        }

        $ventas = $builder->findAll();
        $cuentasCreadas = 0;

        foreach ($ventas as $venta) {
            // Verificar si ya existe una cuenta por cobrar para esta venta
            $cuentaExistente = $this->cuentaCobrarModel->where('venta_id', $venta['id'])->first();

            if (!$cuentaExistente) {
                // Obtener cliente para calcular días de crédito
                $cliente = $this->clienteModel->find($venta['cliente_id']);
                $diasCredito = (int) ($cliente['dias_credito'] ?? 30);

                $fechaVencimiento = !empty($venta['fecha_vencimiento'])
                    ? date('Y-m-d', strtotime($venta['fecha_vencimiento']))
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

                $cuentaCobrarData = [
                    'empresa_id' => $venta['empresa_id'],
                    'sucursal_id' => $venta['sucursal_id'],
                    'cliente_id' => $venta['cliente_id'],
                    'venta_id' => $venta['id'],
                    'numero_documento' => $venta['numero_venta'] ?? $venta['numero_factura'] ?? 'N/A',
                    'tipo_documento' => $venta['tipo_comprobante'] ?? 'TICKET',
                    'fecha_emision' => date('Y-m-d', strtotime($venta['fecha_venta'])),
                    'fecha_vencimiento' => $fechaVencimiento,
                    // Guaraníes no tienen decimales, redondear a enteros
                    'monto_total' => round((float) ($venta['total'] ?? 0)),
                    'monto_pagado' => round((float) ($venta['monto_pagado'] ?? 0)),
                    'monto_pendiente' => round((float) ($venta['monto_pendiente'] ?? 0)),
                    'dias_vencido' => $diasVencido,
                    'estado' => $estado
                ];

                if ($this->cuentaCobrarModel->insert($cuentaCobrarData)) {
                    $cuentasCreadas++;
                }
            }
        }

        return $cuentasCreadas;
    }

    /**
     * Sincronizar ventas pendientes que no tienen cuenta por cobrar
     * Este método crea cuentas por cobrar para ventas existentes que tienen monto pendiente
     */
    public function sincronizar()
    {
        $this->checkAuth();
        $empresaId = $this->user['empresa_id'];
        $sucursalId = $this->sucursal ? $this->sucursal['id'] : null;

        $db = \Config\Database::connect();
        $db->transStart();

        try {
            $cuentasCreadas = $this->sincronizarVentas($empresaId, $sucursalId);

            $db->transComplete();

            if ($db->transStatus() === false) {
                return $this->errorResponse('Error al sincronizar cuentas por cobrar');
            }

            return $this->successResponse("Se crearon {$cuentasCreadas} cuenta(s) por cobrar para ventas pendientes");

        } catch (\Exception $e) {
            $db->transRollback();
            return $this->errorResponse('Error: ' . $e->getMessage());
        }
    }

    /**
     * Obtener historial de pagos de una cuenta por cobrar
     */
    public function obtenerHistorialPagos($cuentaId)
    {
        $this->checkAuth();

        $cuenta = $this->cuentaCobrarModel->find($cuentaId);

        if (!$cuenta || $cuenta['empresa_id'] != $this->user['empresa_id']) {
            return $this->errorResponse('Cuenta no encontrada', 404);
        }

        $pagos = $this->pagoCuentaModel
            ->where('cuenta_cobrar_id', $cuentaId)
            ->orderBy('fecha_pago', 'DESC')
            ->orderBy('fecha_creacion', 'DESC')
            ->findAll();

        return $this->successResponse('Historial obtenido', ['pagos' => $pagos, 'cuenta' => $cuenta]);
    }
}
