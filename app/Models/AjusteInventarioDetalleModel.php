<?php

namespace App\Models;

use CodeIgniter\Model;

class AjusteInventarioDetalleModel extends Model
{
    protected $table            = 'ajustes_inventario_detalle';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'ajuste_id', 'producto_id', 'cantidad_existente', 'cantidad_nueva',
        'diferencia', 'costo_unitario', 'observaciones'
    ];
}
