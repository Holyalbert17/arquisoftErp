<?php

namespace App\Models;

use CodeIgniter\Model;

class ListaPrecioDetalleModel extends Model
{
    protected $table            = 'listas_precios_detalle';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'lista_id', 'producto_id', 'tipo_aplicacion', 'descuento_porcentaje',
        'descuento_monto', 'precio_especial', 'precio_final', 'precio_oferta',
        'es_oferta', 'fecha_inicio_oferta', 'fecha_fin_oferta', 'cantidad_minima',
        'cantidad_maxima', 'observaciones', 'fecha_creacion'
    ];
}
