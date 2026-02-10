<?php

namespace App\Models;

use CodeIgniter\Model;

class ProveedorModel extends Model
{
    protected $table = 'proveedores';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $protectFields = true;
    protected $allowedFields = [
        'empresa_id',
        'codigo',
        'ruc',
        'razon_social',
        'nombre_comercial',
        'direccion',
        'ciudad',
        'pais',
        'telefono',
        'celular',
        'email',
        'sitio_web',
        'contacto_nombre',
        'contacto_cargo',
        'contacto_telefono',
        'contacto_email',
        'dias_pago',
        'condiciones_pago',
        'moneda_preferida',
        'cuenta_bancaria',
        'banco',
        'observaciones',
        'calificacion',
        'estado',
        'fecha_creacion',
        'fecha_modificacion',
        'usuario_creacion'
    ];

    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'fecha_creacion';
    protected $updatedField = 'fecha_modificacion';

    // Callbacks
    protected $allowCallbacks = true;
    protected $beforeInsert = ['normalizeData'];
    protected $beforeUpdate = ['normalizeData'];

    /**
     * Normalizar datos antes de insert/update
     */
    protected function normalizeData(array $data)
    {
        if (!isset($data['data'])) {
            return $data;
        }

        // Campos que deben ser integer
        $integerFields = [
            'empresa_id',
            'dias_pago',
            'calificacion',
            'usuario_creacion'
        ];

        // Campos que pueden ser null
        $nullableIntegerFields = [
            'dias_pago',
            'calificacion',
            'usuario_creacion'
        ];

        foreach ($data['data'] as $field => $value) {
            if (in_array($field, $integerFields)) {
                if (in_array($field, $nullableIntegerFields)) {
                    // Campos opcionales, pueden ser null
                    if ($value === null || $value === '') {
                        $data['data'][$field] = null;
                    } elseif (is_numeric($value)) {
                        $data['data'][$field] = (int) $value;
                    }
                } else {
                    // Campos requeridos
                    if ($value === null || $value === '') {
                        $data['data'][$field] = 0;
                    } elseif (is_numeric($value)) {
                        $data['data'][$field] = (int) $value;
                    }
                }
            } elseif (is_string($value)) {
                $data['data'][$field] = trim($value);
            }
        }

        return $data;
    }
}
