<?php

namespace App\Models;

use CodeIgniter\Model;

class TransferenciaStockModel extends Model
{
    protected $table            = 'transferencias_stock';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'empresa_id', 'numero_transferencia', 'sucursal_origen_id', 'sucursal_destino_id',
        'estado', 'fecha_solicitud', 'fecha_envio', 'fecha_recepcion',
        'usuario_solicita', 'usuario_autoriza', 'usuario_recibe', 'observaciones', 'motivo_rechazo'
    ];
}
