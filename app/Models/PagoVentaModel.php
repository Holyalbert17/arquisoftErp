<?php

namespace App\Models;

use CodeIgniter\Model;

class PagoVentaModel extends Model
{
    protected $table            = 'pagos_ventas';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'venta_id', 'forma_pago', 'monto', 'moneda', 'tipo_cambio', 'monto_moneda_principal',
        'referencia', 'numero_transaccion', 'numero_autorizacion', 'numero_tarjeta_ultimos',
        'tipo_tarjeta', 'banco_emisor', 'numero_cheque', 'banco_cheque', 'fecha_cheque',
        'cuenta_destino', 'comision_porcentaje', 'comision_monto', 'monto_neto',
        'fecha_pago', 'fecha_acreditacion', 'estado', 'observaciones', 'usuario_id', 'caja_id'
    ];
    
    // Casts para asegurar conversión correcta de tipos numéricos
    protected array $casts = [
        'venta_id' => 'int',
        'monto' => 'float',
        'tipo_cambio' => '?float',
        'monto_moneda_principal' => '?float',
        'comision_porcentaje' => '?float',
        'comision_monto' => '?float',
        'monto_neto' => '?float',
        'usuario_id' => '?int',
        'caja_id' => '?int'
    ];
}
