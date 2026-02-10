<?php

namespace App\Models;

use CodeIgniter\Model;

class OrdenServicioHistorialModel extends Model
{
    protected $table            = 'ordenes_servicio_historial';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'orden_id', 'estado_anterior_id', 'estado_nuevo_id', 'observaciones',
        'duracion_minutos', 'fecha_cambio', 'usuario_id', 'ip_address'
    ];
}
