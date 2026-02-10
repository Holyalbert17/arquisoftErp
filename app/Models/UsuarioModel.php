<?php

namespace App\Models;

use CodeIgniter\Model;
use CodeIgniter\I18n\Time;

class UsuarioModel extends Model
{
    protected $table            = 'usuarios';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    // IMPORTANTE: El orden debe coincidir con el orden de las columnas en la base de datos
    // Orden según CREATE TABLE: empresa_id, sucursal_id, rol_id, nombre, apellido, ci,
    // email, telefono, direccion, username, password, foto_perfil, firma_digital, estado,
    // ultimo_acceso, preferencias, usuario_creacion
    // (fecha_creacion y fecha_modificacion se manejan automáticamente con useTimestamps)
    protected $allowedFields    = [
        'empresa_id', 'sucursal_id', 'rol_id', 'nombre', 'apellido', 'ci',
        'email', 'telefono', 'direccion', 'username', 'password',
        'foto_perfil', 'firma_digital', 'estado', 'ultimo_acceso',
        'preferencias', 'tema_oscuro', 'usuario_creacion'
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'fecha_creacion';
    protected $updatedField  = 'fecha_modificacion';

    // Validation
    protected $validationRules      = [
        'empresa_id' => 'required|is_natural_no_zero',
        'rol_id' => 'required|is_natural_no_zero',
        'nombre' => 'required|min_length[2]|max_length[100]',
        'apellido' => 'required|min_length[2]|max_length[100]',
        'ci' => 'required|regex_match[/^[0-9]{1,8}$/]|is_unique[usuarios.ci,id,{id}]',
        'email' => 'required|valid_email|is_unique[usuarios.email,id,{id}]',
        'username' => 'required|min_length[3]|max_length[50]|is_unique[usuarios.username,id,{id}]',
        'password' => 'permit_empty|min_length[8]'
    ];
    protected $validationMessages   = [];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    // Callbacks
    protected $allowCallbacks = true;
    protected $beforeInsert   = ['normalizeData', 'hashPassword'];
    protected $beforeUpdate   = ['normalizeData', 'hashPassword'];

    /**
     * Normalizar datos antes de insert/update
     */
    protected function normalizeData(array $data)
    {
        if (!isset($data['data'])) {
            return $data;
        }
        
        // Crear array normalizado en el orden correcto según allowedFields
        $normalized = [];
        
        // Procesar campos en el orden de allowedFields para mantener consistencia
        foreach ($this->allowedFields as $field) {
            if (isset($data['data'][$field])) {
                $value = $data['data'][$field];
                
                // Normalizar según el tipo de campo
                if (in_array($field, ['empresa_id', 'rol_id'])) {
                    // Campos requeridos, siempre deben ser integer
                    if (!is_numeric($value) && $value !== null && $value !== '') {
                        log_message('error', "UsuarioModel::normalizeData - Campo {$field} tiene valor no numérico: " . var_export($value, true) . " | Tipo: " . gettype($value));
                        throw new \RuntimeException("El campo {$field} debe ser numérico. Valor recibido: " . var_export($value, true));
                    }
                    $normalized[$field] = (int)$value;
                } elseif ($field === 'sucursal_id' || $field === 'usuario_creacion') {
                    // Campos opcionales, pueden ser null
                    if ($value === null || $value === '' || $value === '0') {
                        $normalized[$field] = null;
                    } else {
                        if (!is_numeric($value)) {
                            log_message('error', "UsuarioModel::normalizeData - Campo {$field} tiene valor no numérico: " . var_export($value, true) . " | Tipo: " . gettype($value));
                            throw new \RuntimeException("El campo {$field} debe ser numérico o null. Valor recibido: " . var_export($value, true));
                        }
                        $normalized[$field] = (int)$value;
                    }
                } elseif ($field === 'estado' || $field === 'tema_oscuro') {
                    // Campos booleanos (tema_oscuro puede ser NULL)
                    if ($field === 'tema_oscuro' && ($value === null || $value === '')) {
                        $normalized[$field] = null;
                    } else {
                        $normalized[$field] = (bool)$value;
                    }
                } elseif ($field === 'preferencias') {
                    // Campo JSON, mantener como está o convertir a JSON si es array
                    if (is_array($value)) {
                        $normalized[$field] = json_encode($value);
                    } elseif ($value === null || $value === '') {
                        $normalized[$field] = null;
                    } else {
                        $normalized[$field] = $value;
                    }
                } elseif (is_string($value)) {
                    // Campos de texto, limpiar espacios
                    $normalized[$field] = trim($value);
                } else {
                    $normalized[$field] = $value;
                }
            }
        }
        
        // Asegurar que los campos requeridos estén presentes y sean del tipo correcto
        if (isset($normalized['empresa_id']) && !is_int($normalized['empresa_id'])) {
            $normalized['empresa_id'] = (int)$normalized['empresa_id'];
        }
        if (isset($normalized['rol_id']) && !is_int($normalized['rol_id'])) {
            $normalized['rol_id'] = (int)$normalized['rol_id'];
        }
        
        // Log para debug (solo en desarrollo)
        if (ENVIRONMENT === 'development') {
            log_message('debug', 'UsuarioModel::normalizeData - Datos normalizados: ' . json_encode($normalized));
        }
        
        $data['data'] = $normalized;
        return $data;
    }
    
    /**
     * Hash password before insert/update
     */
    protected function hashPassword(array $data)
    {
        if (isset($data['data']['password']) && !empty($data['data']['password'])) {
            $data['data']['password'] = password_hash($data['data']['password'], PASSWORD_BCRYPT);
        } else {
            unset($data['data']['password']);
        }
        
        // Normalizar campos integer para evitar errores de tipo
        if (isset($data['data']['empresa_id'])) {
            $data['data']['empresa_id'] = intval($data['data']['empresa_id']);
        }
        if (isset($data['data']['sucursal_id'])) {
            $data['data']['sucursal_id'] = !empty($data['data']['sucursal_id']) ? intval($data['data']['sucursal_id']) : null;
        }
        if (isset($data['data']['rol_id'])) {
            $data['data']['rol_id'] = intval($data['data']['rol_id']);
        }
        if (isset($data['data']['usuario_creacion'])) {
            $data['data']['usuario_creacion'] = !empty($data['data']['usuario_creacion']) ? intval($data['data']['usuario_creacion']) : null;
        }
        if (isset($data['data']['estado'])) {
            $data['data']['estado'] = (bool)$data['data']['estado'];
        }
        
        return $data;
    }

    /**
     * Verificar credenciales de login
     */
    public function verificarLogin($username, $password)
    {
        $usuario = $this->where('username', $username)
                        ->where('estado', true)
                        ->first();

        if (!$usuario) {
            return false;
        }

        if (!password_verify($password, $usuario['password'])) {
            return false;
        }

        // Actualizar último acceso
        $this->update($usuario['id'], ['ultimo_acceso' => Time::now()]);

        return $usuario;
    }

    /**
     * Obtener usuarios por empresa
     */
    public function getPorEmpresa($empresaId, $soloActivos = true)
    {
        $builder = $this->where('empresa_id', $empresaId);
        if ($soloActivos) {
            $builder->where('estado', true);
        }
        return $builder->findAll();
    }

    /**
     * Obtener usuarios por sucursal
     */
    public function getPorSucursal($sucursalId, $soloActivos = true)
    {
        $builder = $this->where('sucursal_id', $sucursalId);
        if ($soloActivos) {
            $builder->where('estado', true);
        }
        return $builder->findAll();
    }

    /**
     * Obtener usuario con rol
     */
    public function getConRol($id)
    {
        $usuario = $this->find($id);
        if ($usuario) {
            $rolModel = new RolModel();
            $usuario['rol'] = $rolModel->find($usuario['rol_id']);
        }
        return $usuario;
    }
}
