<?php

namespace App\Models;

use CodeIgniter\Model;

class ClienteModel extends Model
{
    protected $table            = 'clientes';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    // IMPORTANTE: fecha_creacion y fecha_modificacion se manejan automáticamente con useTimestamps
    protected $allowedFields    = [
        'empresa_id', 'codigo', 'tipo_cliente', 'ruc', 'ci', 'nombre', 'apellido',
        'razon_social', 'nombre_fantasia', 'direccion', 'ciudad', 'departamento',
        'barrio', 'referencia_direccion', 'telefono', 'celular', 'email',
        'fecha_nacimiento', 'sexo', 'tipo_contribuyente', 'categoria_cliente',
        'lista_precio_id', 'limite_credito', 'credito_utilizado', 'credito_disponible',
        'dias_credito', 'descuento_porcentaje', 'vendedor_asignado_id', 'foto',
        'notas_internas', 'observaciones', 'etiquetas', 'estado',
        'fecha_ultima_compra', 'total_compras', 'cantidad_compras', 'usuario_creacion'
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'fecha_creacion';
    protected $updatedField  = 'fecha_modificacion';

    // Callbacks
    protected $allowCallbacks = true;
    protected $beforeInsert   = ['normalizeData'];
    protected $beforeUpdate   = ['normalizeData'];

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
            'empresa_id', 'lista_precio_id', 'vendedor_asignado_id', 'usuario_creacion',
            'dias_credito', 'cantidad_compras'
        ];
        
        // Campos que son DECIMAL (no integer)
        $decimalFields = [
            'limite_credito', 'credito_utilizado', 'credito_disponible',
            'descuento_porcentaje', 'total_compras'
        ];
        
        // Campos que pueden ser null o integer
        $nullableIntegerFields = [
            'lista_precio_id', 'vendedor_asignado_id', 'usuario_creacion'
        ];
        
        foreach ($data['data'] as $field => $value) {
            if (in_array($field, $integerFields)) {
                if (in_array($field, $nullableIntegerFields)) {
                    // Campos opcionales, pueden ser null
                    if ($value === null || $value === '' || $value === '0') {
                        $data['data'][$field] = null;
                    } else {
                        if (!is_numeric($value)) {
                            log_message('error', "ClienteModel::normalizeData - Campo {$field} tiene valor no numérico: " . var_export($value, true));
                            throw new \RuntimeException("El campo {$field} debe ser numérico o null. Valor recibido: " . var_export($value, true));
                        }
                        $data['data'][$field] = (int)$value;
                    }
                } else {
                    // Campos requeridos, siempre deben ser integer
                    if ($value === null || $value === '') {
                        $data['data'][$field] = 0;
                    } elseif (!is_numeric($value)) {
                        log_message('error', "ClienteModel::normalizeData - Campo {$field} tiene valor no numérico: " . var_export($value, true));
                        throw new \RuntimeException("El campo {$field} debe ser numérico. Valor recibido: " . var_export($value, true));
                    } else {
                        $data['data'][$field] = (int)$value;
                    }
                }
            } elseif (in_array($field, $decimalFields)) {
                // Campos DECIMAL, convertir a float
                if ($value === null || $value === '') {
                    $data['data'][$field] = 0.0;
                } elseif (!is_numeric($value)) {
                    log_message('error', "ClienteModel::normalizeData - Campo {$field} tiene valor no numérico: " . var_export($value, true));
                    throw new \RuntimeException("El campo {$field} debe ser numérico. Valor recibido: " . var_export($value, true));
                } else {
                    $numero = (float)$value;
                    // Validar máximo para campos de crédito (DECIMAL(15,2) = máximo 9,999,999,999,999.99)
                    if (in_array($field, ['limite_credito', 'credito_utilizado', 'credito_disponible'])) {
                        $maxValue = 9999999999999.99;
                        if ($numero > $maxValue) {
                            log_message('warning', "ClienteModel::normalizeData - Campo {$field} excede el máximo permitido: {$numero} > {$maxValue}");
                            $numero = $maxValue;
                        }
                    }
                    $data['data'][$field] = $numero;
                }
            } elseif ($field === 'fecha_nacimiento' || $field === 'fecha_ultima_compra') {
                // Normalizar fecha: convertir cadena vacía a null
                if ($value === '' || $value === null) {
                    $data['data'][$field] = null;
                }
            } elseif (is_string($value)) {
                // Limpiar espacios en campos de texto
                $data['data'][$field] = trim($value);
            }
        }
        
        // Log para debug (solo en desarrollo)
        if (ENVIRONMENT === 'development') {
            log_message('debug', 'ClienteModel::normalizeData - Datos normalizados: ' . json_encode($data['data']));
        }
        
        return $data;
    }

    /**
     * Obtener clientes nuevos este mes
     */
    public function getNuevosEsteMes($empresaId)
    {
        $fechaInicio = date('Y-m-01');
        $fechaFin = date('Y-m-t');
        
        return $this->where('empresa_id', $empresaId)
                    ->where('fecha_creacion >=', $fechaInicio)
                    ->where('fecha_creacion <=', $fechaFin . ' 23:59:59')
                    ->where('estado', true)
                    ->orderBy('fecha_creacion', 'DESC')
                    ->limit(10)
                    ->findAll();
    }

    /**
     * Obtener nombre completo del cliente
     */
    public function getNombreCompleto($cliente)
    {
        if ($cliente['tipo_cliente'] == 'PERSONA') {
            return trim($cliente['nombre'] . ' ' . ($cliente['apellido'] ?? ''));
        } else {
            return $cliente['nombre_fantasia'] ?? $cliente['razon_social'] ?? $cliente['nombre'];
        }
    }
}
