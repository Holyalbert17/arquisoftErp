<?php

namespace App\Models;

use CodeIgniter\Model;

class TipoCambioModel extends Model
{
    protected $table            = 'tipos_cambio';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'empresa_id', 'fecha', 'moneda_origen', 'moneda_destino', 'compra', 'venta',
        'promedio', 'fuente', 'observaciones', 'fecha_creacion', 'usuario_creacion'
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'fecha_creacion';

    /**
     * Obtener tipo de cambio del día
     */
    public function getTipoCambioHoy($empresaId, $monedaDestino = 'USD')
    {
        $fechaHoy = date('Y-m-d');
        
        $tipoCambio = $this->where('empresa_id', $empresaId)
                          ->where('fecha', $fechaHoy)
                          ->where('moneda_destino', $monedaDestino)
                          ->first();
        
        return $tipoCambio ? $tipoCambio['promedio'] : 1;
    }
}
