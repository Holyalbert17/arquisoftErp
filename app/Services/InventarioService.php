<?php

namespace App\Services;

use App\Models\AjusteInventarioModel;
use App\Models\AjusteInventarioDetalleModel;
use App\Models\StockModel;
use App\Models\ProductoModel;
use App\Models\MovimientoStockModel;
use Config\Database;

class InventarioService
{
    protected $ajusteModel;
    protected $ajusteDetalleModel;
    protected $stockModel;
    protected $productoModel;
    protected $movimientoStockModel;
    protected $db;

    public function __construct()
    {
        $this->db = Database::connect();
        $this->ajusteModel = new AjusteInventarioModel();
        $this->ajusteDetalleModel = new AjusteInventarioDetalleModel();
        $this->stockModel = new StockModel();
        $this->productoModel = new ProductoModel();
        $this->movimientoStockModel = new MovimientoStockModel();
    }

    /**
     * Aprobar un ajuste de inventario de manera transaccional y segura
     */
    public function aprobarAjuste(int $ajusteId, int $usuarioId, int $empresaId)
    {
        $this->db->transStart();

        try {
            // Verificar ajuste (LOCK FOR UPDATE para evitar que otro proceso lo apruebe simultáneamente)
            // CI4 no tiene lockForUpdate directo en el Query Builder estándar, usamos raw o un simple find
            // Dado que el estado es lo importante, verificamos estado.

            $ajuste = $this->ajusteModel->find($ajusteId);

            if (!$ajuste) {
                throw new \Exception('Ajuste no encontrado');
            }

            if ($ajuste['empresa_id'] != $empresaId) {
                throw new \Exception('No tiene permisos para este ajuste');
            }

            if ($ajuste['estado'] != 'PENDIENTE') {
                throw new \Exception('Solo se pueden aprobar ajustes pendientes');
            }

            $detalles = $this->ajusteDetalleModel->where('ajuste_id', $ajusteId)->findAll();

            foreach ($detalles as $detalle) {
                // Validación estricta usando SQL directo para evitar Race Conditions con los datos en memoria PHP
                // En lugar de: $nuevo = $actual + $diferencia
                // Haremos: UPDATE stock SET cantidad = cantidad + $diferencia
                // Pero primero debemos asegurarnos que el stock existe y manejar la creación si es nuevo.

                $stock = $this->stockModel->where('producto_id', $detalle['producto_id'])
                    ->where('sucursal_id', $ajuste['sucursal_id'])
                    ->first();

                $cantidadAnterior = $stock ? $stock['cantidad'] : 0;
                $diferencia = $detalle['diferencia']; // Puede ser positiva o negativa
                $cantidadNuevaCalculada = $cantidadAnterior + $diferencia; // Solo para referencia en movimientos

                if ($stock) {
                    // Validar constraint lógica en PHP antes de intentar SQL para mejor mensaje de error
                    // (Aunque la DB tiene el check final)
                    $cantidadReservada = (float) ($stock['cantidad_reservada'] ?? 0);

                    // Si reducimos stock, verificar que no rompemos la reserva
                    if ($diferencia < 0) {
                        // Obtenemos el valor más reciente posible
                        $stockFresh = $this->db->table('stock')->select('cantidad, cantidad_reservada')
                            ->where('id', $stock['id'])
                            ->get()->getRowArray();

                        if (($stockFresh['cantidad'] + $diferencia) < $stockFresh['cantidad_reservada']) {
                            $prod = $this->productoModel->find($detalle['producto_id']);
                            $nombre = $prod['nombre'] ?? 'ID:' . $detalle['producto_id'];
                            throw new \Exception("Stock insuficiente para '$nombre'. Reservado: {$stockFresh['cantidad_reservada']}, Nuevo Total: " . ($stockFresh['cantidad'] + $diferencia));
                        }
                    }

                    // Actualización Atómica
                    // UPDATE stock SET cantidad = cantidad + ? WHERE id = ?
                    // Esto maneja la concurrencia correctamente porque la DB serializa las escrituras en la fila
                    $this->db->table('stock')
                        ->where('id', $stock['id'])
                        ->set('cantidad', "cantidad + ($diferencia)", false) // false evita escape
                        ->update();

                    // Recalcular cantidad disponible
                    $this->db->table('stock')
                        ->where('id', $stock['id'])
                        ->set('cantidad_disponible', 'cantidad - cantidad_reservada', false)
                        ->update();

                } else {
                    // Si no existe stock y se intenta restar, error lógica (no debería pasar si viene de form, pero por seguridad)
                    if ($diferencia < 0) {
                        throw new \Exception("No existe registro de stock para reducir en el producto ID: {$detalle['producto_id']}");
                    }

                    // Insertar nuevo
                    $this->stockModel->insert([
                        'producto_id' => $detalle['producto_id'],
                        'sucursal_id' => $ajuste['sucursal_id'],
                        'cantidad' => $diferencia,
                        'cantidad_reservada' => 0,
                        'cantidad_disponible' => $diferencia,
                        'costo_promedio' => $detalle['costo_unitario']
                    ]);
                }

                // Registrar movimiento
                $tipoMovimiento = $diferencia > 0 ? 'AJUSTE_ENTRADA' : 'AJUSTE_SALIDA';

                $this->movimientoStockModel->insert([
                    'empresa_id' => $empresaId,
                    'sucursal_id' => $ajuste['sucursal_id'],
                    'producto_id' => $detalle['producto_id'],
                    'tipo_movimiento' => $tipoMovimiento,
                    'cantidad' => abs($diferencia),
                    'cantidad_anterior' => $cantidadAnterior, // Referencial
                    'cantidad_nueva' => $cantidadNuevaCalculada, // Referencial
                    'costo_unitario' => $detalle['costo_unitario'],
                    'documento_referencia' => $ajuste['numero_ajuste'],
                    'tipo_documento' => 'AJUSTE',
                    'motivo' => $ajuste['motivo'],
                    'usuario_id' => $usuarioId,
                    'fecha_movimiento' => date('Y-m-d H:i:s')
                ]);
            }

            // Actualizar estado del ajuste
            $this->ajusteModel->update($ajusteId, [
                'estado' => 'PROCESADO',
                'fecha_aprobacion' => date('Y-m-d H:i:s'),
                'fecha_procesamiento' => date('Y-m-d H:i:s'),
                'usuario_aprueba' => $usuarioId
            ]);

            $this->db->transComplete();

            if ($this->db->transStatus() === false) {
                // Obtener el error de DB si es posible
                $error = $this->db->error();
                throw new \Exception('Error de base de datos al aprobar ajuste: ' . ($error['message'] ?? 'Desconocido'));
            }

            return ['success' => true, 'message' => 'Ajuste procesado exitosamente'];

        } catch (\Exception $e) {
            $this->db->transRollback();
            // Verificar si es error de restricción específico de Postgres
            if (strpos($e->getMessage(), 'chk_reservada_menor') !== false) {
                return ['success' => false, 'message' => 'Error Crítico: La operación dejaría el stock por debajo de la cantidad reservada.'];
            }
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }
}
