<?php

namespace App\Models;

use CodeIgniter\Model;

class PermisoModel extends Model
{
    protected $table            = 'permisos';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'rol_id', 'modulo', 'ver', 'crear', 'editar', 'eliminar', 'anular', 'fecha_creacion'
    ];

    // Dates - tabla permisos solo tiene fecha_creacion, no tiene updated_at/fecha_modificacion
    protected $useTimestamps = false;

    /**
     * Verificar si un rol tiene permiso
     */
    public function hasPermission($rolId, $modulo, $accion = 'ver')
    {
        $permiso = $this->where('rol_id', $rolId)
                        ->where('modulo', $modulo)
                        ->first();

        if (!$permiso) {
            return false;
        }

        // Administrador siempre tiene todos los permisos
        $rolModel = new RolModel();
        $rol = $rolModel->find($rolId);
        if ($rol && $rol['nivel'] == 1) {
            return true;
        }

        // Verificar acción específica
        switch ($accion) {
            case 'ver':
                return (bool)$permiso['ver'];
            case 'crear':
                return (bool)$permiso['crear'];
            case 'editar':
                return (bool)$permiso['editar'];
            case 'eliminar':
                return (bool)$permiso['eliminar'];
            case 'anular':
                return (bool)$permiso['anular'];
            default:
                return false;
        }
    }

    /**
     * Obtener permisos por rol
     */
    public function getPorRol($rolId)
    {
        return $this->where('rol_id', $rolId)->findAll();
    }
}
