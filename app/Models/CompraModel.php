<?php

namespace App\Models;

use CodeIgniter\Model;

class CompraModel extends Model
{
    protected $table            = 'compras';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'empresa_id', 'sucursal_id', 'proveedor_id', 'usuario_id', 'numero_compra',
        'numero_factura_proveedor', 'timbrado_proveedor', 'tipo_compra', 'condicion_pago',
        'estado', 'moneda', 'tipo_cambio', 'subtotal', 'descuento_porcentaje',
        'descuento_monto', 'total_iva', 'otros_gastos', 'total', 'total_moneda_principal',
        'monto_pagado', 'monto_pendiente', 'fecha_compra', 'fecha_vencimiento',
        'fecha_recepcion', 'fecha_factura_proveedor', 'forma_pago', 'dias_credito',
        'observaciones', 'notas_internas', 'numero_despacho', 'gastos_importacion',
        'fecha_creacion', 'fecha_modificacion'
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'fecha_creacion';
    protected $updatedField  = 'fecha_modificacion';
}
