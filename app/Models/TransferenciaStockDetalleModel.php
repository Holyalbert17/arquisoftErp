<?php

namespace App\Models;

use CodeIgniter\Model;

class TransferenciaStockDetalleModel extends Model
{
    protected $table            = 'transferencias_stock_detalle';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'transferencia_id', 'producto_id', 'cantidad_solicitada', 'cantidad_enviada',
        'cantidad_recibida', 'cantidad_pendiente', 'lote', 'observaciones'
    ];
}
