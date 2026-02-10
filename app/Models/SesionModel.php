<?php

namespace App\Models;

use CodeIgniter\Model;

class SesionModel extends Model
{
    protected $table            = 'sesiones';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'usuario_id', 'session_id', 'ip_address', 'user_agent', 
        'fecha_inicio', 'fecha_fin', 'activa', 'ultima_actividad'
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'fecha_inicio';
    protected $updatedField  = 'ultima_actividad';
}
