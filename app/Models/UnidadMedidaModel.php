<?php

namespace App\Models;

use CodeIgniter\Model;

class UnidadMedidaModel extends Model
{
    protected $table            = 'unidades_medida';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'nombre', 'abreviatura', 'tipo', 'estado'
    ];
}
