<?php

namespace App\Models;

use CodeIgniter\Model;

class CotizacionDetalleModel extends Model
{
    protected $table            = 'cotizaciones_detalle';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'cotizacion_id', 'producto_id', 'descripcion', 'cantidad', 'precio_unitario',
        'descuento_porcentaje', 'descuento_monto', 'porcentaje_iva',
        'subtotal', 'monto_iva', 'total', 'observaciones', 'linea_orden'
    ];

    protected $useTimestamps = false;
}
