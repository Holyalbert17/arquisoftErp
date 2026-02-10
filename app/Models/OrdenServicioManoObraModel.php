<?php

namespace App\Models;

use CodeIgniter\Model;

class OrdenServicioManoObraModel extends Model
{
    protected $table            = 'ordenes_servicio_mano_obra';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'orden_id', 'descripcion', 'tipo_servicio', 'horas_estimadas', 'horas_reales',
        'precio_hora', 'precio_fijo', 'descuento_porcentaje', 'descuento_monto',
        'tipo_iva', 'porcentaje_iva', 'subtotal', 'monto_iva', 'total',
        'tecnico_id', 'es_presupuesto', 'fue_realizado', 'observaciones',
        'fecha_agregado', 'usuario_agrega'
    ];
}
