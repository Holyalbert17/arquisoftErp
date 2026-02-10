<?php

namespace App\Services;

use App\Models\CuentaCobrarModel;
use App\Models\CuentaPagarModel;
use App\Models\PagoCuentaModel;
use App\Models\PagoVentaModel; // Si aplica separación, o unificar en PagoCuenta
use App\Models\CajaModel;
use App\Models\MovimientoCajaModel;
use App\Models\VentaModel;
use App\Models\ClienteModel;
use Config\Database;

class FinanzasService
{
    protected $cuentaCobrarModel;
    protected $cuentaPagarModel; // Agregado
    protected $pagoCuentaModel;
    protected $cajaModel;
    protected $movimientoCajaModel;
    protected $ventaModel;
    protected $compraModel; // Agregado
    protected $clienteModel;
    protected $proveedorModel; // Agregado por si acaso
    protected $db;

    public function __construct()
    {
        $this->db = Database::connect();
        $this->cuentaCobrarModel = new CuentaCobrarModel($this->db);
        $this->cuentaPagarModel = new CuentaPagarModel($this->db); // Inicializar
        $this->pagoCuentaModel = new PagoCuentaModel($this->db);
        $this->cajaModel = new CajaModel($this->db);
        $this->movimientoCajaModel = new MovimientoCajaModel($this->db);
        $this->ventaModel = new VentaModel($this->db);
        $this->compraModel = new \App\Models\CompraModel($this->db); // Inicializar
        $this->clienteModel = new ClienteModel($this->db);
        $this->proveedorModel = new \App\Models\ProveedorModel($this->db);
    }

    /**
     * Registrar cobro de una Cuenta por Cobrar
     */
    public function registrarCobro(int $cuentaId, float $monto, string $formaPago, int $cajaId, int $usuarioId, string $referencia = '')
    {
        $cuenta = $this->cuentaCobrarModel->find($cuentaId);

        if (!$cuenta) {
            return ['success' => false, 'message' => 'Cuenta no encontrada'];
        }

        // Validaciones básicas
        $montoPendiente = (float) ($cuenta['monto_pendiente'] ?? 0);
        if ($monto <= 0) {
            return ['success' => false, 'message' => 'El monto debe ser mayor a cero'];
        }
        if ($monto > $montoPendiente) { // Tolerancia por redondeo? Mejor estricto por ahora
            // Podríamos usar bccomp o round para evitar problemas de float
            if (round($monto) > round($montoPendiente)) {
                return ['success' => false, 'message' => 'El monto excede el saldo pendiente'];
            }
        }

        $caja = $this->cajaModel->find($cajaId);
        if (!$caja || $caja['estado'] != 'ABIERTA') {
            return ['success' => false, 'message' => 'Caja cerrada o no encontrada'];
        }

        $this->db->transStart();

        try {
            $formaPagoNorm = $this->normalizarFormaPago((string) $formaPago);
            $esEfectivo = ($formaPagoNorm === 'EFECTIVO');

            // 1. Registrar Pago
            $pagoData = [
                'empresa_id' => $cuenta['empresa_id'],
                'sucursal_id' => $cuenta['sucursal_id'],
                'cuenta_cobrar_id' => $cuentaId,
                'forma_pago' => $formaPagoNorm,
                'monto' => $monto,
                'moneda' => 'PYG',
                'referencia' => $referencia,
                'fecha_pago' => date('Y-m-d'),
                'usuario_id' => $usuarioId,
                'caja_id' => $cajaId
            ];
            $this->pagoCuentaModel->insert($pagoData);

            // 2. Movimiento Caja
            $this->movimientoCajaModel->insert([
                'caja_id' => $cajaId,
                'tipo_movimiento' => 'COBRO',
                'monto' => $monto,
                'moneda' => 'PYG',
                'tipo_cambio' => 1,
                'monto_moneda_principal' => $monto,
                'concepto' => 'Cobro Doc: ' . $cuenta['numero_documento'] . ' ' . $referencia,
                'referencia' => $referencia,
                'usuario_id' => $usuarioId
            ]);

            // 3. saldo_actual se actualiza automáticamente por el trigger trg_actualizar_saldo_caja

            // 4. Actualizar Cuenta
            $montoPagadoActual = (float) ($cuenta['monto_pagado'] ?? 0);
            $nuevoMontoPagado = $montoPagadoActual + $monto;
            $nuevoMontoPendiente = max(0, (float) ($cuenta['monto_total'] ?? 0) - $nuevoMontoPagado);

            $nuevoEstado = 'PENDIENTE';
            if ($nuevoMontoPendiente <= 1) { // Tolerancia 1 Gs
                $nuevoEstado = 'PAGADO';
                $nuevoMontoPendiente = 0;
            } elseif ($nuevoMontoPendiente < (float) ($cuenta['monto_total'] ?? 0)) {
                $nuevoEstado = 'PARCIAL';
            }

            // Verificar vencimiento si sigue pendiente
            if ($nuevoEstado != 'PAGADO' && !empty($cuenta['fecha_vencimiento'])) {
                if (strtotime($cuenta['fecha_vencimiento']) < time()) {
                    $nuevoEstado = 'VENCIDO';
                }
            }

            $this->cuentaCobrarModel->update($cuentaId, [
                'monto_pagado' => $nuevoMontoPagado,
                'monto_pendiente' => $nuevoMontoPendiente,
                'estado' => $nuevoEstado
            ]);

            // 5. Sincronizar Venta (si existe)
            if (!empty($cuenta['venta_id'])) {
                $this->actualizarEstadoVenta($cuenta['venta_id'], $monto, $nuevoMontoPendiente);
            }

            // 6. Actualizar Crédito Cliente
            $cliente = $this->clienteModel->find($cuenta['cliente_id']);
            if ($cliente) {
                $nuevoCreditoUtilizado = max(0, (float) ($cliente['credito_utilizado'] ?? 0) - $monto);
                $this->clienteModel->update($cliente['id'], [
                    'credito_utilizado' => $nuevoCreditoUtilizado
                ]);
            }

            $this->db->transComplete();

            if ($this->db->transStatus() === false) {
                throw new \Exception('Error al registrar cobro');
            }

            return ['success' => true, 'message' => 'Cobro registrado exitosamente'];

        } catch (\Exception $e) {
            $this->db->transRollback();
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }

    private function normalizarFormaPago(string $formaPago): string
    {
        $fp = strtoupper(trim($formaPago));

        if ($fp === '' || $fp === 'EFECTIVO' || $fp === 'CONTADO' || $fp === 'CASH') {
            return 'EFECTIVO';
        }
        if (in_array($fp, ['TARJETA_CREDITO', 'CREDITO', 'TC'], true)) {
            return 'TARJETA_CREDITO';
        }
        if (in_array($fp, ['TARJETA_DEBITO', 'DEBITO', 'TD'], true)) {
            return 'TARJETA_DEBITO';
        }
        if (in_array($fp, ['TRANSFERENCIA', 'TRANSFER', 'BANCARIA'], true)) {
            return 'TRANSFERENCIA';
        }
        if (in_array($fp, ['CHEQUE'], true)) {
            return 'CHEQUE';
        }

        return 'OTRO';
    }

    private function actualizarEstadoVenta($ventaId, $montoPagadoAhora, $montoPendienteCuenta)
    {
        $venta = $this->ventaModel->find($ventaId);
        if ($venta) {
            $montoPagadoVenta = (float) ($venta['monto_pagado'] ?? 0) + $montoPagadoAhora;

            // Si la cuenta quedó en 0, la venta también debería (para sincronizar)
            // Si no, recálculo normal
            $montoPendienteVenta = (float) ($venta['total'] ?? 0) - $montoPagadoVenta;

            if ($montoPendienteCuenta == 0) {
                $montoPendienteVenta = 0;
                $montoPagadoVenta = $venta['total']; // Ajustar por redondeos
            }

            $this->ventaModel->update($ventaId, [
                'monto_pagado' => $montoPagadoVenta,
                'monto_pendiente' => max(0, $montoPendienteVenta)
            ]);
        }
    }

    /**
     * Registrar pago a proveedor de una Cuenta por Pagar
     */
    public function registrarPagoProveedor(int $cuentaId, float $monto, string $formaPago, int $cajaId, int $usuarioId, string $referencia = '')
    {
        $cuenta = $this->cuentaPagarModel->find($cuentaId);

        if (!$cuenta) {
            return ['success' => false, 'message' => 'Cuenta por pagar no encontrada'];
        }

        $montoPendiente = (float) ($cuenta['monto_pendiente'] ?? 0);
        if ($monto <= 0) {
            return ['success' => false, 'message' => 'El monto debe ser mayor a cero'];
        }
        if (round($monto) > round($montoPendiente)) {
            return ['success' => false, 'message' => 'El monto excede el saldo pendiente'];
        }

        $caja = $this->cajaModel->find($cajaId);
        if (!$caja || $caja['estado'] != 'ABIERTA') {
            return ['success' => false, 'message' => 'Caja cerrada o no encontrada'];
        }

        // Verificar saldo en caja para el egreso
        if ($caja['saldo_actual'] < $monto) {
            return ['success' => false, 'message' => 'Saldo insuficiente en caja'];
        }

        $this->db->transStart();

        try {
            $formaPagoNorm = $this->normalizarFormaPago((string) $formaPago);
            $esEfectivo = ($formaPagoNorm === 'EFECTIVO');

            // 1) Registrar pago en pagos_cuentas
            $pagoData = [
                'empresa_id' => $cuenta['empresa_id'],
                'sucursal_id' => $cuenta['sucursal_id'],
                'cuenta_pagar_id' => $cuentaId,
                'forma_pago' => $formaPagoNorm,
                'monto' => $monto,
                'moneda' => 'PYG',
                'tipo_cambio' => 1,
                'referencia' => $referencia,
                'fecha_pago' => date('Y-m-d'),
                'usuario_id' => $usuarioId,
                'caja_id' => $cajaId
            ];
            $this->pagoCuentaModel->insert($pagoData);

            // 2) Registrar movimiento de caja
            $this->movimientoCajaModel->insert([
                'caja_id' => $cajaId,
                'tipo_movimiento' => 'EGRESO',
                'monto' => $monto,
                'moneda' => 'PYG',
                'tipo_cambio' => 1,
                'monto_moneda_principal' => $monto,
                'concepto' => 'Pago a Proveedor Doc: ' . ($cuenta['numero_documento'] ?? 'N/A') . ' ' . $referencia,
                'referencia' => $referencia,
                'usuario_id' => $usuarioId,
                'fecha_movimiento' => date('Y-m-d H:i:s')
            ]);

            // 3) saldo_actual se actualiza automáticamente por el trigger trg_actualizar_saldo_caja

            // 4) Actualizar cuenta por pagar
            $montoPagadoActual = (float) ($cuenta['monto_pagado'] ?? 0);
            $nuevoMontoPagado = $montoPagadoActual + $monto;
            $montoTotal = (float) ($cuenta['monto_total'] ?? 0);
            $nuevoMontoPendiente = max(0, $montoTotal - $nuevoMontoPagado);

            $nuevoEstado = 'PENDIENTE';
            if ($nuevoMontoPendiente <= 1) {
                $nuevoEstado = 'PAGADO';
                $nuevoMontoPendiente = 0;
            } elseif ($nuevoMontoPendiente < $montoTotal) {
                $nuevoEstado = 'PARCIAL';
            }

            $this->cuentaPagarModel->update($cuentaId, [
                'monto_pagado' => $nuevoMontoPagado,
                'monto_pendiente' => $nuevoMontoPendiente,
                'estado' => $nuevoEstado
            ]);

            // 5) Sincronizar compra relacionada
            if (!empty($cuenta['compra_id'])) {
                $compra = $this->compraModel->find($cuenta['compra_id']);
                if ($compra) {
                    $this->compraModel->update($cuenta['compra_id'], [
                        'monto_pagado' => $nuevoMontoPagado,
                        'monto_pendiente' => $nuevoMontoPendiente
                    ]);
                }
            }

            $this->db->transComplete();

            if ($this->db->transStatus() === false) {
                throw new \Exception('Error al registrar pago a proveedor');
            }

            return ['success' => true, 'message' => 'Pago registrado exitosamente'];

        } catch (\Exception $e) {
            $this->db->transRollback();
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }
}
