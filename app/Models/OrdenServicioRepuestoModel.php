<?php

namespace App\Models;

use CodeIgniter\Model;

class OrdenServicioRepuestoModel extends Model
{
    protected $table = 'ordenes_servicio_repuestos';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $protectFields = true;
    protected $allowedFields = [
        'orden_id',
        'producto_id',
        'cantidad',
        'precio_unitario',
        'descuento_porcentaje',
        'descuento_monto',
        'tipo_iva',
        'porcentaje_iva',
        'subtotal',
        'monto_iva',
        'total',
        'es_presupuesto',
        'fue_utilizado',
        'costo_unitario',
        'observaciones',
        'fecha_agregado',
        'usuario_agrega'
    ];
    /**
     * Obtiene los repuestos asociados a una orden
     */
    public function getPorOrden($ordenId)
    {
        return $this->where('orden_id', $ordenId)->findAll();
    }
}
