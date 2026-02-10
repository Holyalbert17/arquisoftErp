<?php

namespace App\Models;

use CodeIgniter\Model;

class StockModel extends Model
{
    protected $table            = 'stock';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'producto_id', 'sucursal_id', 'cantidad', 'cantidad_reservada',
        'cantidad_disponible', 'ubicacion', 'lote', 'fecha_vencimiento',
        'costo_promedio', 'fecha_actualizacion'
    ];

    protected $useTimestamps = false;
    protected $dateFormat    = 'datetime';
    protected $updatedField  = 'fecha_actualizacion';
}
