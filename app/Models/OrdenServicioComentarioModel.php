<?php

namespace App\Models;

use CodeIgniter\Model;

class OrdenServicioComentarioModel extends Model
{
    protected $table            = 'ordenes_servicio_comentarios';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'orden_id', 'usuario_id', 'comentario', 'es_interno', 'visible_cliente',
        'fecha_creacion', 'fecha_modificacion'
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'fecha_creacion';
    protected $updatedField  = 'fecha_modificacion';
}
