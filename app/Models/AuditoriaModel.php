<?php

namespace App\Models;

use CodeIgniter\Model;

class AuditoriaModel extends Model
{
    protected $table            = 'auditoria';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'empresa_id', 'usuario_id', 'tabla_afectada', 'accion',
        'registro_id', 'datos_anteriores', 'datos_nuevos',
        'ip_address', 'user_agent', 'observaciones', 'fecha_evento'
    ];

    // Dates
    protected $useTimestamps = false;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'fecha_evento';

    /**
     * Registrar evento de auditoría
     */
    public function registrar($data)
    {
        $insertData = [
            'empresa_id' => $data['empresa_id'] ?? null,
            'usuario_id' => $data['usuario_id'] ?? null,
            'tabla_afectada' => $data['tabla_afectada'],
            'accion' => $data['accion'],
            'registro_id' => $data['registro_id'] ?? null,
            'datos_anteriores' => isset($data['datos_anteriores']) ? json_encode($data['datos_anteriores']) : null,
            'datos_nuevos' => isset($data['datos_nuevos']) ? json_encode($data['datos_nuevos']) : null,
            'ip_address' => $data['ip_address'] ?? null,
            'user_agent' => $data['user_agent'] ?? null,
            'observaciones' => $data['observaciones'] ?? null,
            'fecha_evento' => date('Y-m-d H:i:s')
        ];

        return $this->insert($insertData);
    }
}
