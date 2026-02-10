<?php

namespace App\Models;

use CodeIgniter\Model;

class PagoCuentaModel extends Model
{
    protected $table            = 'pagos_cuentas';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'empresa_id', 'sucursal_id', 'cuenta_cobrar_id', 'cuenta_pagar_id',
        'forma_pago', 'monto', 'moneda', 'tipo_cambio', 'referencia',
        'fecha_pago', 'fecha_acreditacion', 'observaciones', 'usuario_id', 'caja_id',
        'fecha_creacion'
    ];
}
