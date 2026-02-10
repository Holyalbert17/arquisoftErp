<?php

namespace App\Models;

use CodeIgniter\Model;

class MovimientoCajaModel extends Model
{
    protected $table = 'movimientos_caja';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $protectFields = true;
    protected $allowedFields = [
        'caja_id',
        'tipo_movimiento',
        'monto',
        'moneda',
        'tipo_cambio',
        'monto_moneda_principal',
        'concepto',
        'referencia',
        'documento_id',
        'tipo_documento',
        'observaciones',
        'fecha_movimiento',
        'usuario_id'
    ];

    protected $useTimestamps = false;

    /**
     * Obtener movimientos de una caja en un rango de fechas
     */
    public function getMovimientosCaja($cajaId, $fechaInicio = null, $fechaFin = null)
    {
        $builder = $this->where('caja_id', $cajaId);

        if ($fechaInicio) {
            $builder->where('fecha_movimiento >=', $fechaInicio);
        }

        if ($fechaFin) {
            $builder->where('fecha_movimiento <=', (strlen($fechaFin) <= 10 ? $fechaFin . ' 23:59:59' : $fechaFin));
        }

        return $builder->orderBy('fecha_movimiento', 'DESC')->findAll();
    }

    /**
     * Obtener resumen de movimientos por tipo
     */
    public function getResumenMovimientos($cajaId, $fechaInicio = null, $fechaFin = null)
    {
        $builder = $this->select('tipo_movimiento, SUM(monto_moneda_principal) as total')
            ->where('caja_id', $cajaId)
            ->groupBy('tipo_movimiento');

        if ($fechaInicio) {
            $builder->where('fecha_movimiento >=', $fechaInicio);
        }

        if ($fechaFin) {
            $builder->where('fecha_movimiento <=', (strlen($fechaFin) <= 10 ? $fechaFin . ' 23:59:59' : $fechaFin));
        }

        return $builder->findAll();
    }
}
