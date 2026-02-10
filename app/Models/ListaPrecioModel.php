<?php

namespace App\Models;

use CodeIgniter\Model;

class ListaPrecioModel extends Model
{
    protected $table            = 'listas_precios';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'empresa_id', 'nombre', 'descripcion', 'tipo_lista', 'tipo_descuento',
        'descuento_general', 'margen_porcentaje', 'es_predeterminada', 'moneda',
        'fecha_vigencia_desde', 'fecha_vigencia_hasta', 'aplica_lunes', 'aplica_martes',
        'aplica_miercoles', 'aplica_jueves', 'aplica_viernes', 'aplica_sabado', 'aplica_domingo',
        'hora_desde', 'hora_hasta', 'sucursales_aplicables', 'categorias_aplicables',
        'clientes_aplicables', 'cantidad_minima', 'estado', 'fecha_creacion',
        'fecha_modificacion', 'usuario_creacion'
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'fecha_creacion';
    protected $updatedField  = 'fecha_modificacion';
}
