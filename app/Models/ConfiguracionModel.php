<?php

namespace App\Models;

use CodeIgniter\Model;

class ConfiguracionModel extends Model
{
    protected $table            = 'configuraciones';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'empresa_id', 'modulo', 'clave', 'valor', 'tipo', 'descripcion', 'editable'
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'fecha_creacion';
    protected $updatedField  = 'fecha_modificacion';

    /**
     * Obtener o establecer una configuración
     */
    public function setConfiguracion($empresaId, $modulo, $clave, $valor)
    {
        $existe = $this->where('empresa_id', $empresaId)
                      ->where('modulo', $modulo)
                      ->where('clave', $clave)
                      ->first();
        
        if ($existe) {
            return $this->update($existe['id'], [
                'valor' => $valor,
                'fecha_modificacion' => date('Y-m-d H:i:s')
            ]);
        } else {
            return $this->insert([
                'empresa_id' => $empresaId,
                'modulo' => $modulo,
                'clave' => $clave,
                'valor' => $valor,
                'tipo' => 'STRING',
                'editable' => true
            ]);
        }
    }

    /**
     * Obtener una configuración
     */
    public function getConfiguracion($empresaId, $modulo, $clave, $default = null)
    {
        $config = $this->where('empresa_id', $empresaId)
                      ->where('modulo', $modulo)
                      ->where('clave', $clave)
                      ->first();
        
        return $config ? $config['valor'] : $default;
    }
}
