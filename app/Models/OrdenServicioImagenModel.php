<?php

namespace App\Models;

use CodeIgniter\Model;

class OrdenServicioImagenModel extends Model
{
    protected $table            = 'ordenes_servicio_imagenes';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'orden_id', 'url', 'url_thumbnail', 'titulo', 'descripcion', 'tipo',
        'categoria', 'orden', 'tamaño_bytes', 'ancho_px', 'alto_px', 'formato',
        'es_publica', 'visible_cliente', 'fecha_creacion', 'usuario_creacion'
    ];
}
