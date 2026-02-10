<?php

namespace App\Models;

use CodeIgniter\Model;

class EmpresaModuloModel extends Model
{
    protected $table            = 'empresa_modulos';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'empresa_id', 'modulo_id', 'activo', 'fecha_activacion', 'fecha_vencimiento',
        'configuracion', 'limite_usuarios', 'limite_registros', 'limite_sucursales',
        'tipo_licencia', 'clave_licencia', 'fecha_inicio_licencia', 'fecha_fin_licencia',
        'observaciones', 'usuario_asigna', 'fecha_creacion', 'fecha_modificacion'
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'fecha_creacion';
    protected $updatedField  = 'fecha_modificacion';
}
