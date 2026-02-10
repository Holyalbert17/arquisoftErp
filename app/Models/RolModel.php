<?php

namespace App\Models;

use CodeIgniter\Model;

class RolModel extends Model
{
    protected $table = 'roles';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $protectFields = true;
    protected $allowedFields = [
        'nombre',
        'descripcion',
        'nivel',
        'es_rol_sistema',
        'estado',
        'fecha_creacion'
    ];

    const SUPER_ADMIN = 1;
    const ADMIN_EMPRESA = 2; // Gerente / Dueño

    // Dates - tabla roles solo tiene fecha_creacion, no tiene updated_at/fecha_modificacion
    protected $useTimestamps = false;

    /**
     * Obtener roles activos
     */
    public function getActivos()
    {
        return $this->where('estado', true)->orderBy('nivel', 'ASC')->findAll();
    }

    /**
     * Obtener rol con permisos
     */
    public function getConPermisos($id)
    {
        $rol = $this->find($id);
        if ($rol) {
            $permisoModel = new PermisoModel();
            $rol['permisos'] = $permisoModel->where('rol_id', $id)->findAll();
        }
        return $rol;
    }
}
