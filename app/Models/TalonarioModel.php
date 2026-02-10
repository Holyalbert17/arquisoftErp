<?php

namespace App\Models;

use CodeIgniter\Model;

class TalonarioModel extends Model
{
    protected $table = 'talonarios';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $protectFields = true;
    protected $allowedFields = [
        'empresa_id',
        'sucursal_id',
        'tipo_comprobante',
        'timbrado',
        'establecimiento',
        'punto_expedicion',
        'numero_desde',
        'numero_hasta',
        'numero_actual',
        'fecha_inicio_vigencia',
        'fecha_fin_vigencia',
        'estado',
        'observaciones',
        'fecha_creacion',
        'usuario_creacion'
    ];

    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'fecha_creacion';
    protected $updatedField = '';
}
