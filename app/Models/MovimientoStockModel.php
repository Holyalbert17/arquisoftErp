<?php

namespace App\Models;

use CodeIgniter\Model;

class MovimientoStockModel extends Model
{
    protected $table            = 'movimientos_stock';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'empresa_id', 'sucursal_id', 'producto_id', 'tipo_movimiento', 'cantidad',
        'cantidad_anterior', 'cantidad_nueva', 'costo_unitario', 'precio_venta_unitario',
        'moneda', 'documento_referencia', 'tipo_documento', 'lote', 'motivo',
        'fecha_movimiento', 'usuario_id'
    ];
}
