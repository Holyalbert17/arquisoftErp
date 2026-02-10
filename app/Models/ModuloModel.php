<?php

namespace App\Models;

use CodeIgniter\Model;

class ModuloModel extends Model
{
    protected $table            = 'modulos';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'nombre', 'codigo', 'descripcion', 'icono', 'color', 'categoria',
        'orden', 'requiere_licencia', 'precio_mensual', 'es_modulo_base',
        'url_documentacion', 'version', 'estado', 'fecha_creacion', 'fecha_modificacion'
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'fecha_creacion';
    protected $updatedField  = 'fecha_modificacion';
}
