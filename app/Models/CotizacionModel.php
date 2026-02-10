<?php

namespace App\Models;

use CodeIgniter\Model;

class CotizacionModel extends Model
{
    protected $table            = 'cotizaciones';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'empresa_id', 'sucursal_id', 'cliente_id', 'usuario_id', 'lista_precio_id',
        'numero_cotizacion', 'estado', 'subtotal', 'descuento_porcentaje', 'descuento_monto',
        'iva_10', 'iva_5', 'total', 'fecha_emision', 'fecha_vencimiento',
        'venta_id', 'fecha_conversion', 'observaciones', 'notas_internas',
        'terminos_condiciones', 'fecha_creacion', 'fecha_modificacion'
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'fecha_creacion';
    protected $updatedField  = 'fecha_modificacion';
}
