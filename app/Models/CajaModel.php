<?php

namespace App\Models;

use CodeIgniter\Model;

class CajaModel extends Model
{
    protected $table            = 'cajas';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'empresa_id', 'sucursal_id', 'usuario_id', 'nombre', 'descripcion',
        'estado', 'saldo_inicial', 'saldo_actual', 'saldo_esperado', 'diferencia',
        'fecha_apertura', 'fecha_cierre', 'usuario_apertura', 'usuario_cierre',
        'observaciones_apertura', 'observaciones_cierre', 'fecha_creacion', 'fecha_modificacion'
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'fecha_creacion';
    protected $updatedField  = 'fecha_modificacion';

    /**
     * Obtener cajas abiertas de un usuario
     */
    public function getCajasAbiertas($usuarioId, $sucursalId = null)
    {
        $builder = $this->where('usuario_id', $usuarioId)
                        ->where('estado', 'ABIERTA');
        
        if ($sucursalId) {
            $builder->where('sucursal_id', $sucursalId);
        }
        
        return $builder->findAll();
    }

    /**
     * Obtener cajas de una sucursal
     */
    public function getCajasSucursal($sucursalId)
    {
        return $this->where('sucursal_id', $sucursalId)
                   ->orderBy('nombre', 'ASC')
                   ->findAll();
    }

    /**
     * Verificar si hay cajas abiertas
     */
    public function tieneCajasAbiertas($usuarioId, $sucursalId = null)
    {
        $builder = $this->where('usuario_id', $usuarioId)
                        ->where('estado', 'ABIERTA');
        
        if ($sucursalId) {
            $builder->where('sucursal_id', $sucursalId);
        }
        
        return $builder->countAllResults() > 0;
    }
}
