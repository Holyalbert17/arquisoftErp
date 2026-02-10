<?php

namespace App\Models;

use CodeIgniter\Model;

class VentaModel extends Model
{
    protected $table            = 'ventas';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'empresa_id', 'sucursal_id', 'cliente_id', 'usuario_id', 'vendedor_id',
        'lista_precio_id', 'orden_servicio_id', 'numero_venta', 'tipo_comprobante',
        'numero_factura', 'timbrado', 'tipo_venta', 'tipo_pago', 'estado',
        'moneda', 'tipo_cambio', 'subtotal', 'descuento_porcentaje', 'descuento_monto',
        'recargo_porcentaje', 'recargo_monto', 'subtotal_gravado_10', 'iva_10',
        'subtotal_gravado_5', 'iva_5', 'subtotal_exento', 'total_iva', 'total',
        'total_moneda_principal', 'monto_pagado', 'monto_pendiente', 'vuelto',
        'observaciones', 'notas_internas', 'origen_venta', 'fecha_venta',
        'fecha_vencimiento', 'fecha_anulacion', 'fecha_facturacion', 'motivo_anulacion',
        'usuario_anula', 'requiere_entrega', 'direccion_entrega', 'fecha_entrega_programada',
        'fecha_entrega_real', 'estado_entrega', 'es_borrador', 'factura_impresa',
        'cantidad_impresiones', 'fecha_creacion', 'fecha_modificacion'
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'fecha_creacion';
    protected $updatedField  = 'fecha_modificacion';
    
    // Casts para asegurar conversión correcta de tipos numéricos
    protected array $casts = [
        'empresa_id' => 'int',
        'sucursal_id' => '?int',
        'cliente_id' => '?int',
        'usuario_id' => '?int',
        'vendedor_id' => '?int',
        'lista_precio_id' => '?int',
        'orden_servicio_id' => '?int',
        'tipo_cambio' => '?float',
        'subtotal' => 'float',
        'descuento_porcentaje' => '?float',
        'descuento_monto' => '?float',
        'recargo_porcentaje' => '?float',
        'recargo_monto' => '?float',
        'subtotal_gravado_10' => '?float',
        'iva_10' => '?float',
        'subtotal_gravado_5' => '?float',
        'iva_5' => '?float',
        'subtotal_exento' => '?float',
        'total_iva' => '?float',
        'total' => 'float',
        'total_moneda_principal' => '?float',
        'monto_pagado' => '?float',
        'monto_pendiente' => '?float',
        'vuelto' => '?float',
        'es_borrador' => 'bool',
        'factura_impresa' => 'bool',
        'cantidad_impresiones' => '?int',
        'usuario_anula' => '?int'
    ];

    /**
     * Obtener ventas del día
     */
    public function getVentasHoy($empresaId, $sucursalId = null)
    {
        try {
            $fechaInicio = date('Y-m-d') . ' 00:00:00';
            $fechaFin = date('Y-m-d') . ' 23:59:59';
            
            $builder = $this->where('empresa_id', $empresaId)
                            ->where('fecha_venta >=', $fechaInicio)
                            ->where('fecha_venta <=', $fechaFin)
                            ->where('estado !=', 'ANULADA');
            
            if ($sucursalId) {
                $builder->where('sucursal_id', $sucursalId);
            }
            
            return $builder->findAll();
        } catch (\Exception $e) {
            return [];
        }
    }
}
