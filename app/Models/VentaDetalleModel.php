<?php

namespace App\Models;

use CodeIgniter\Model;

class VentaDetalleModel extends Model
{
    protected $table            = 'ventas_detalle';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'venta_id', 'producto_id', 'orden_servicio_id', 'descripcion', 'cantidad',
        'precio_unitario', 'precio_lista', 'descuento_lista_porcentaje', 'descuento_lista_monto',
        'descuento_adicional_porcentaje', 'descuento_adicional_monto', 'descuento_total_porcentaje',
        'descuento_total_monto', 'tipo_iva', 'porcentaje_iva', 'subtotal', 'monto_iva',
        'total', 'costo_unitario', 'costo_total', 'utilidad', 'margen_porcentaje',
        'lote', 'numero_serie', 'fecha_vencimiento', 'es_servicio', 'es_repuesto_orden',
        'observaciones', 'linea_orden'
    ];
    
    // Casts para asegurar conversión correcta de tipos numéricos
    protected array $casts = [
        'venta_id' => 'int',
        'producto_id' => '?int',
        'orden_servicio_id' => '?int',
        'cantidad' => 'float',
        'precio_unitario' => 'float',
        'precio_lista' => '?float',
        'descuento_lista_porcentaje' => '?float',
        'descuento_lista_monto' => '?float',
        'descuento_adicional_porcentaje' => '?float',
        'descuento_adicional_monto' => '?float',
        'descuento_total_porcentaje' => '?float',
        'descuento_total_monto' => '?float',
        'porcentaje_iva' => '?float',
        'subtotal' => 'float',
        'monto_iva' => '?float',
        'total' => 'float',
        'costo_unitario' => '?float',
        'costo_total' => '?float',
        'utilidad' => '?float',
        'margen_porcentaje' => '?float',
        'es_servicio' => 'bool',
        'es_repuesto_orden' => 'bool',
        'linea_orden' => '?int'
    ];
}
