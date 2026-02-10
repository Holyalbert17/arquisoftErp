<?php

namespace App\Models;

use CodeIgniter\Model;

class SucursalModuloModel extends Model
{
    protected $table            = 'sucursal_modulos';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'sucursal_id', 'modulo_id', 'empresa_modulo_id', 'activo', 'fecha_activacion',
        'configuracion', 'limite_usuarios', 'limite_registros_diarios',
        'permite_crear', 'permite_editar', 'permite_eliminar', 'permite_anular', 'permite_reportes',
        'observaciones', 'usuario_asigna', 'fecha_creacion', 'fecha_modificacion'
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'fecha_creacion';
    protected $updatedField  = 'fecha_modificacion';
}
