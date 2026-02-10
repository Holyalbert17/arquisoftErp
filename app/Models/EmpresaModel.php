<?php

namespace App\Models;

use CodeIgniter\Model;

class EmpresaModel extends Model
{
    protected $table            = 'empresas';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'nombre', 'ruc', 'direccion', 'ciudad', 'telefono', 'email',
        'logo', 'protector_pantalla', 'aplica_iva', 'porcentaje_iva',
        'usa_facturacion_electronica', 'timbrado', 'establecimiento',
        'punto_expedicion', 'dias_plazo_retiro', 'moneda_principal',
        'terminos_condiciones', 'politica_garantia', 'estado',
        'tema_oscuro', 'usuario_creacion'
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'fecha_creacion';
    protected $updatedField  = 'fecha_modificacion';
    protected $deletedField  = 'deleted_at';

    // Validation
    protected $validationRules      = [
        'nombre' => 'required|min_length[3]|max_length[200]',
        'ruc' => 'required|regex_match[/^[0-9]{8}-[0-9]{1}$/]|is_unique[empresas.ruc,id,{id}]',
        'email' => 'valid_email|max_length[150]',
        'porcentaje_iva' => 'in_list[5.00,10.00]',
        'moneda_principal' => 'in_list[PYG,USD,BRL]'
    ];
    protected $validationMessages   = [
        'ruc' => [
            'regex_match' => 'El RUC debe tener el formato XXXXXXXX-X',
            'is_unique' => 'Este RUC ya está registrado'
        ]
    ];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    // Callbacks
    protected $allowCallbacks = true;
    protected $beforeInsert   = ['normalizeData'];
    protected $afterInsert    = [];
    protected $beforeUpdate   = ['normalizeData'];
    protected $afterUpdate    = [];
    protected $beforeFind     = [];
    protected $afterFind      = [];
    protected $beforeDelete   = [];
    protected $afterDelete    = [];

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
            'dias_plazo_retiro', 'timbrado', 'establecimiento', 'punto_expedicion', 'usuario_creacion'
        ];
        
        // Campos que pueden ser null o integer
        $nullableIntegerFields = [
            'timbrado', 'establecimiento', 'punto_expedicion', 'usuario_creacion'
        ];
        
        // Campos que son DECIMAL
        $decimalFields = [
            'porcentaje_iva'
        ];
        
        foreach ($data['data'] as $field => $value) {
            if (in_array($field, $integerFields)) {
                if (in_array($field, $nullableIntegerFields)) {
                    // Campos opcionales, pueden ser null
                    if ($value === null || $value === '' || $value === '0') {
                        $data['data'][$field] = null;
                    } else {
                        if (!is_numeric($value)) {
                            log_message('error', "EmpresaModel::normalizeData - Campo {$field} tiene valor no numérico: " . var_export($value, true));
                            throw new \RuntimeException("El campo {$field} debe ser numérico o null. Valor recibido: " . var_export($value, true));
                        }
                        $data['data'][$field] = (int)$value;
                    }
                } else {
                    // Campos requeridos, siempre deben ser integer
                    if ($value === null || $value === '') {
                        $data['data'][$field] = 0;
                    } elseif (!is_numeric($value)) {
                        log_message('error', "EmpresaModel::normalizeData - Campo {$field} tiene valor no numérico: " . var_export($value, true));
                        throw new \RuntimeException("El campo {$field} debe ser numérico. Valor recibido: " . var_export($value, true));
                    } else {
                        $data['data'][$field] = (int)$value;
                    }
                }
            } elseif (in_array($field, $decimalFields)) {
                // Campos DECIMAL, convertir a float
                if ($value === null || $value === '') {
                    $data['data'][$field] = null;
                } elseif (!is_numeric($value)) {
                    log_message('error', "EmpresaModel::normalizeData - Campo {$field} tiene valor no numérico: " . var_export($value, true));
                    throw new \RuntimeException("El campo {$field} debe ser numérico. Valor recibido: " . var_export($value, true));
                } else {
                    $data['data'][$field] = (float)$value;
                }
            } elseif ($field === 'aplica_iva' || $field === 'usa_facturacion_electronica' || $field === 'estado' || $field === 'tema_oscuro') {
                // Campos booleanos
                $data['data'][$field] = (bool)$value;
            } elseif (is_string($value)) {
                // Limpiar espacios en campos de texto
                $data['data'][$field] = trim($value);
            }
        }
        
        // Asegurar que fecha_creacion y fecha_modificacion no se pasen (se manejan automáticamente)
        unset($data['data']['fecha_creacion']);
        unset($data['data']['fecha_modificacion']);
        
        // Log para debug (solo en desarrollo)
        if (ENVIRONMENT === 'development') {
            log_message('debug', 'EmpresaModel::normalizeData - Datos normalizados: ' . json_encode($data['data']));
        }
        
        return $data;
    }

    /**
     * Validar RUC paraguayo
     * 
     * @param string $ruc RUC en formato XXXXXXXX-X
     * @return bool True si el RUC es válido, false en caso contrario
     */
    public function validarRUC($ruc)
    {
        // Si el RUC está vacío, retornar false
        if (empty($ruc)) {
            return false;
        }
        
        // Limpiar y verificar formato (remover espacios y caracteres especiales, excepto el guión)
        $ruc = preg_replace('/[^0-9-]/', '', trim($ruc));
        
        // Verificar formato básico: 8 dígitos, guión, 1 dígito
        if (!preg_match('/^[0-9]{8}-[0-9]{1}$/', $ruc)) {
            return false;
        }
        
        // Validar dígito verificador usando el algoritmo paraguayo
        $digitos = str_replace('-', '', $ruc);
        
        // Verificar que tenga exactamente 9 dígitos
        if (strlen($digitos) !== 9) {
            return false;
        }
        
        $verificador = (int)substr($digitos, -1);
        $base = substr($digitos, 0, 8);
        
        // Factores para el cálculo del dígito verificador (algoritmo paraguayo)
        $factores = [9, 8, 7, 6, 5, 4, 3, 2];
        $suma = 0;
        
        // Calcular suma ponderada
        for ($i = 0; $i < 8; $i++) {
            if (!is_numeric($base[$i])) {
                return false; // Si algún carácter no es numérico, retornar false
            }
            $suma += (int)$base[$i] * $factores[$i];
        }
        
        // Calcular resto
        $resto = 11 - ($suma % 11);
        
        // Ajustar resto según reglas paraguayas
        if ($resto == 10) {
            $resto = 0;
        } elseif ($resto == 11) {
            $resto = 1;
        }
        
        // Comparar con el dígito verificador
        return $resto == $verificador;
    }

    /**
     * Obtener empresas activas
     */
    public function getActivas()
    {
        return $this->where('estado', true)->findAll();
    }

    /**
     * Obtener empresa con sus sucursales
     */
    public function getConSucursales($id)
    {
        $empresa = $this->find($id);
        if ($empresa) {
            $sucursalModel = new SucursalModel();
            $empresa['sucursales'] = $sucursalModel->where('empresa_id', $id)->findAll();
        }
        return $empresa;
    }
}
