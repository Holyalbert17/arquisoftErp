<?php

namespace App\Models;

use CodeIgniter\Model;

class AjusteInventarioModel extends Model
{
    protected $table            = 'ajustes_inventario';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'empresa_id', 'sucursal_id', 'numero_ajuste', 'tipo_ajuste', 'motivo',
        'observaciones', 'estado', 'usuario_solicita', 'usuario_aprueba',
        'fecha_solicitud', 'fecha_aprobacion', 'fecha_procesamiento'
    ];
}
