<?php

namespace App\Models;

use CodeIgniter\Model;

class CuentaCobrarModel extends Model
{
    protected $table            = 'cuentas_cobrar';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'empresa_id', 'sucursal_id', 'cliente_id', 'venta_id', 'numero_documento',
        'tipo_documento', 'fecha_emision', 'fecha_vencimiento', 'monto_total',
        'monto_pagado', 'monto_pendiente', 'dias_vencido', 'estado', 'observaciones',
        'fecha_creacion', 'fecha_modificacion'
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'fecha_creacion';
    protected $updatedField  = 'fecha_modificacion';
}
