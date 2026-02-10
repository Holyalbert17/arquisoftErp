<?php

namespace App\Models;

use CodeIgniter\Model;

class CompraDetalleModel extends Model
{
    protected $table            = 'compras_detalle';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'compra_id', 'producto_id', 'descripcion', 'cantidad_solicitada', 'cantidad_recibida',
        'cantidad_pendiente', 'precio_unitario', 'descuento_porcentaje', 'descuento_monto',
        'porcentaje_iva', 'subtotal', 'monto_iva', 'total', 'lote', 'fecha_vencimiento',
        'numero_serie', 'observaciones', 'linea_orden'
    ];
}
