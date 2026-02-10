<?php

namespace App\Libraries;

use App\Models\NotificacionModel;
use App\Models\StockModel;
use App\Models\ProductoModel;
use App\Models\CuentaCobrarModel;
use App\Models\OrdenServicioModel;

class NotificacionesHelper
{
    protected $notificacionModel;

    public function __construct()
    {
        $this->notificacionModel = new NotificacionModel();
    }

    /**
     * Crear notificación
     */
    public function crear($empresaId, $usuarioId, $tipo, $titulo, $mensaje, $accionUrl = null, $icono = null)
    {
        return $this->notificacionModel->insert([
            'empresa_id' => $empresaId,
            'usuario_id' => $usuarioId,
            'tipo' => $tipo,
            'titulo' => $titulo,
            'mensaje' => $mensaje,
            'accion_url' => $accionUrl,
            'icono' => $icono,
            'leida' => false,
            'estado' => true
        ]);
    }

    /**
     * Notificar a todos los usuarios de una empresa
     */
    public function notificarEmpresa($empresaId, $tipo, $titulo, $mensaje, $accionUrl = null)
    {
        $usuarioModel = new \App\Models\UsuarioModel();
        $usuarios = $usuarioModel->where('empresa_id', $empresaId)
                                 ->where('estado', true)
                                 ->findAll();

        foreach ($usuarios as $usuario) {
            $this->crear($empresaId, $usuario['id'], $tipo, $titulo, $mensaje, $accionUrl);
        }
    }

    /**
     * Verificar y notificar stock bajo
     */
    public function verificarStockBajo($empresaId, $sucursalId = null)
    {
        $stockModel = new StockModel();
        $productoModel = new ProductoModel();
        
        $builder = $stockModel->select('stock.*, productos.nombre, productos.stock_minimo, productos.punto_reorden')
                             ->join('productos', 'productos.id = stock.producto_id')
                             ->where('productos.empresa_id', $empresaId)
                             ->where('productos.controla_stock', true)
                             ->where('productos.estado', true);
        
        if ($sucursalId) {
            $builder->where('stock.sucursal_id', $sucursalId);
        }
        
        $stocks = $builder->findAll();
        
        foreach ($stocks as $stock) {
            if ($stock['cantidad_disponible'] <= $stock['stock_minimo']) {
                $this->notificarEmpresa(
                    $empresaId,
                    'WARNING',
                    'Stock Crítico',
                    "El producto {$stock['nombre']} tiene stock crítico. Stock actual: {$stock['cantidad_disponible']}",
                    base_url('inventario/stock')
                );
            } elseif ($stock['cantidad_disponible'] <= $stock['punto_reorden']) {
                $this->notificarEmpresa(
                    $empresaId,
                    'INFO',
                    'Stock Bajo',
                    "El producto {$stock['nombre']} ha alcanzado el punto de reorden. Stock actual: {$stock['cantidad_disponible']}",
                    base_url('inventario/stock')
                );
            }
        }
    }

    /**
     * Verificar y notificar cuentas vencidas
     */
    public function verificarCuentasVencidas($empresaId)
    {
        $cuentaCobrarModel = new CuentaCobrarModel();
        $cuentas = $cuentaCobrarModel->where('empresa_id', $empresaId)
                                    ->where('estado', 'VENCIDO')
                                    ->where('monto_pendiente >', 0)
                                    ->findAll();
        
        if (count($cuentas) > 0) {
            $this->notificarEmpresa(
                $empresaId,
                'ERROR',
                'Cuentas Vencidas',
                "Hay " . count($cuentas) . " cuenta(s) vencida(s) pendiente(s) de cobro",
                base_url('finanzas/cuentas-cobrar')
            );
        }
    }

    /**
     * Verificar órdenes de servicio próximas a vencer
     */
    public function verificarOrdenesPorVencer($empresaId)
    {
        $ordenModel = new OrdenServicioModel();
        $fechaLimite = date('Y-m-d', strtotime('+3 days'));
        
        $ordenes = $ordenModel->where('empresa_id', $empresaId)
                             ->where('estado_activo', true)
                             ->where('fecha_limite_retiro <=', $fechaLimite)
                             ->where('fecha_limite_retiro >=', date('Y-m-d'))
                             ->findAll();
        
        foreach ($ordenes as $orden) {
            $diasRestantes = (strtotime($orden['fecha_limite_retiro']) - time()) / 86400;
            
            $this->notificarEmpresa(
                $empresaId,
                'WARNING',
                'Orden Próxima a Vencer',
                "La orden {$orden['numero_orden']} vence en {$diasRestantes} día(s)",
                base_url('servicios/ordenes/ver/' . $orden['id'])
            );
        }
    }

    /**
     * Notificar orden lista para retiro
     */
    public function notificarOrdenListaRetiro($ordenId)
    {
        $ordenModel = new OrdenServicioModel();
        $orden = $ordenModel->find($ordenId);
        
        if (!$orden) return;
        
        $clienteModel = new \App\Models\ClienteModel();
        $cliente = $clienteModel->find($orden['cliente_id']);
        
        $this->crear(
            $orden['empresa_id'],
            null, // Notificar a todos los usuarios
            'SUCCESS',
            'Orden Lista para Retiro',
            "La orden {$orden['numero_orden']} del cliente {$cliente['nombre']} está lista para retiro",
            base_url('servicios/ordenes/ver/' . $ordenId),
            'bell'
        );
    }
}
