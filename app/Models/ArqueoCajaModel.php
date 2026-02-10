<?php

namespace App\Models;

use CodeIgniter\Model;

class ArqueoCajaModel extends Model
{
    protected $table            = 'arqueos_caja';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'caja_id', 'fecha_arqueo', 'hora_arqueo', 'saldo_sistema',
        'saldo_fisico', 'diferencia', 'observaciones', 'usuario_id', 'fecha_creacion'
    ];

    protected $useTimestamps = false;

    /**
     * Obtener arqueos de una caja
     */
    public function getArqueosCaja($cajaId, $fechaInicio = null, $fechaFin = null)
    {
        $builder = $this->where('caja_id', $cajaId);
        
        if ($fechaInicio) {
            $builder->where('fecha_arqueo >=', $fechaInicio);
        }
        
        if ($fechaFin) {
            $builder->where('fecha_arqueo <=', $fechaFin);
        }
        
        return $builder->orderBy('fecha_arqueo', 'DESC')
                      ->orderBy('hora_arqueo', 'DESC')
                      ->findAll();
    }
}
