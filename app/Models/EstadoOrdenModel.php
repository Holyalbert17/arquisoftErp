<?php

namespace App\Models;

use CodeIgniter\Model;

class EstadoOrdenModel extends Model
{
    protected $table            = 'estados_orden';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'nombre', 'descripcion', 'color', 'icono', 'orden', 'es_final',
        'permite_edicion', 'permite_facturacion', 'notifica_cliente',
        'plantilla_email', 'plantilla_sms', 'estado'
    ];
}
