<?php

namespace App\Models;

use CodeIgniter\Model;

class SucursalModel extends Model
{
    protected $table            = 'sucursales';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'empresa_id', 'nombre', 'codigo', 'direccion', 'ciudad', 'departamento',
        'telefono', 'email', 'latitud', 'longitud', 'horario_apertura',
        'horario_cierre', 'es_matriz', 'codigo_establecimiento', 'responsable',
        'estado', 'tema_oscuro', 'fecha_creacion', 'fecha_modificacion'
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'fecha_creacion';
    protected $updatedField  = 'fecha_modificacion';

    // Validation
    protected $validationRules      = [
        'empresa_id' => 'required|is_natural_no_zero',
        'nombre' => 'required|min_length[3]|max_length[200]',
        'departamento' => 'in_list[CAPITAL,CONCEPCIÓN,SAN PEDRO,CORDILLERA,GUAIRÁ,CAAAGUAZÚ,CAAZAPÁ,ITAPÚA,MISIONES,PARAGUARÍ,ALTO PARANÁ,CENTRAL,ÑEEMBUCÚ,AMAMBAY,CANINDEYÚ,PRESIDENTE HAYES,BOQUERÓN,ALTO PARAGUAY]'
    ];
    protected $validationMessages   = [];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    /**
     * Obtener sucursales de una empresa
     */
    public function getPorEmpresa($empresaId, $soloActivas = true)
    {
        $builder = $this->where('empresa_id', $empresaId);
        if ($soloActivas) {
            $builder->where('estado', true);
        }
        return $builder->findAll();
    }

    /**
     * Obtener sucursal matriz de una empresa
     */
    public function getMatriz($empresaId)
    {
        return $this->where('empresa_id', $empresaId)
                    ->where('es_matriz', true)
                    ->where('estado', true)
                    ->first();
    }

    /**
     * Verificar si solo hay una matriz
     */
    public function validarUnicaMatriz($empresaId, $sucursalId = null)
    {
        $matriz = $this->getMatriz($empresaId);
        if ($matriz && $matriz['id'] != $sucursalId) {
            return false;
        }
        return true;
    }
}
