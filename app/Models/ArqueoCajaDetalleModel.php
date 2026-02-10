<?php

namespace App\Models;

use CodeIgniter\Model;

class ArqueoCajaDetalleModel extends Model
{
    protected $table = 'arqueos_caja_detalle';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $protectFields = true;
    protected $allowedFields = [
        'arqueo_id',
        'denominacion',
        'cantidad',
        'subtotal',
        'orden',
        'tipo_medio_pago',
        'descripcion'
    ];

    protected $useTimestamps = false;
}
