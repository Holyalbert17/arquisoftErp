<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\UsuarioModel;
use App\Models\RolModel;
use App\Models\EmpresaModel;
use App\Models\SucursalModel;

class UsuariosController extends BaseController
{
    protected $usuarioModel;
    protected $rolModel;
    protected $empresaModel;
    protected $sucursalModel;

    public function __construct()
    {
        // No verificar permisos en constructor para peticiones AJAX
        // La verificación se hará en cada método individualmente
        $this->usuarioModel = new UsuarioModel();
        $this->rolModel = new RolModel();
        $this->empresaModel = new EmpresaModel();
        $this->sucursalModel = new SucursalModel();
    }

    protected function checkAuth()
    {
        if (!$this->isLoggedIn()) {
            return redirect()->to('/auth/login');
        }
        // Asegurar que user data esté cargado
        if (!$this->user) {
            $this->loadUserData();
        }
        $this->requirePermission('USUARIOS', 'ver');
    }

    public function index()
    {
        $this->checkAuth();
        $empresaId = $this->user['rol_id'] == \App\Models\RolModel::SUPER_ADMIN ? null : $this->user['empresa_id'];

        $builder = $this->usuarioModel;
        if ($empresaId) {
            $builder->where('empresa_id', $empresaId);
        }

        $usuarios = $builder->findAll();

        // Agregar información de rol y empresa
        foreach ($usuarios as &$usuario) {
            $usuario['rol'] = $this->rolModel->find($usuario['rol_id']);
            $usuario['empresa'] = $this->empresaModel->find($usuario['empresa_id']);
        }

        $data = [
            'title' => 'Gestión de Usuarios',
            'usuarios' => $usuarios
        ];

        return $this->render('admin/usuarios/index', $data);
    }

    public function crear()
    {
        $this->requireLogin();
        $this->requirePermission('USUARIOS', 'crear');
        $this->checkAuth();
        $empresaId = $this->user['rol_id'] == 1 ? null : $this->user['empresa_id'];

        $data = [
            'title' => 'Nuevo Usuario',
            'usuario' => null,
            'roles' => $this->rolModel->getActivos(),
            'empresas' => $this->empresaModel->getActivas(),
            'sucursales' => $empresaId ? $this->sucursalModel->getPorEmpresa($empresaId) : [],
            'empresa_id' => $empresaId
        ];

        return $this->render('admin/usuarios/form', $data);
    }

    public function guardar()
    {
        // Verificar autenticación sin redirigir (para AJAX)
        if (!$this->isLoggedIn()) {
            return $this->errorResponse('No autenticado', 401);
        }

        // Cargar datos del usuario (necesario antes de verificar permisos)
        $this->loadUserData();

        // Verificar que el usuario esté cargado
        if (!$this->user) {
            return $this->errorResponse('No autenticado', 401);
        }

        // Verificar permisos
        $this->requirePermission('USUARIOS', 'crear');

        // Obtener datos del POST
        $empresaIdPost = $this->request->getPost('empresa_id');
        $rolIdPost = $this->request->getPost('rol_id');
        $nombrePost = $this->request->getPost('nombre');
        $apellidoPost = $this->request->getPost('apellido');
        $ciPost = $this->request->getPost('ci');
        $emailPost = $this->request->getPost('email');
        $usernamePost = $this->request->getPost('username');
        $passwordPost = $this->request->getPost('password');

        // Validar que los datos requeridos estén presentes
        if (empty($empresaIdPost) || empty($rolIdPost) || empty($nombrePost) || empty($apellidoPost) || empty($ciPost) || empty($emailPost) || empty($usernamePost) || empty($passwordPost)) {
            return $this->errorResponse('Todos los campos requeridos deben estar completos');
        }

        // Enforce multiempresa: admin/gerente solo pueden operar dentro de su empresa
        if (($this->user['rol_id'] ?? null) != \App\Models\RolModel::SUPER_ADMIN) {
            $empresaIdUser = $this->user['empresa_id'] ?? null;
            if (!$empresaIdUser) {
                return $this->errorResponse('Usuario no tiene empresa asignada', 403);
            }
            if ((int) $empresaIdPost !== (int) $empresaIdUser) {
                return $this->errorResponse('No tiene permisos para operar sobre otra empresa', 403);
            }
            $empresaIdPost = $empresaIdUser;
        }

        // Validar formato de CI
        if (!preg_match('/^[0-9]{1,8}$/', $ciPost)) {
            return $this->errorResponse('El CI debe contener solo números (máximo 8 dígitos)');
        }

        // Validar formato de email
        if (!filter_var($emailPost, FILTER_VALIDATE_EMAIL)) {
            return $this->errorResponse('El email no tiene un formato válido');
        }

        // Validar longitud de password
        if (strlen($passwordPost) < 8) {
            return $this->errorResponse('La contraseña debe tener al menos 8 caracteres');
        }

        // Verificar que CI, email y username no estén duplicados
        $ciExiste = $this->usuarioModel->where('ci', $ciPost)->countAllResults();
        if ($ciExiste > 0) {
            return $this->errorResponse('El número de CI ya está registrado por otro usuario');
        }

        $emailExiste = $this->usuarioModel->where('email', $emailPost)->countAllResults();
        if ($emailExiste > 0) {
            return $this->errorResponse('El email ya está registrado por otro usuario');
        }

        $usernameExiste = $this->usuarioModel->where('username', $usernamePost)->countAllResults();
        if ($usernameExiste > 0) {
            return $this->errorResponse('El nombre de usuario ya está registrado por otro usuario');
        }

        // Validaciones básicas sin is_unique (ya validamos manualmente arriba)
        $rules = [
            'empresa_id' => 'required|is_natural_no_zero',
            'rol_id' => 'required|is_natural_no_zero',
            'nombre' => 'required|min_length[2]|max_length[100]',
            'apellido' => 'required|min_length[2]|max_length[100]',
            'username' => 'required|min_length[3]|max_length[50]'
        ];

        if (!$this->validate($rules)) {
            return $this->errorResponse('Error de validación: ' . implode(', ', $this->validator->getErrors()));
        }

        $sucursalId = $this->request->getPost('sucursal_id');
        $sucursalId = !empty($sucursalId) ? intval($sucursalId) : null;

        $usuarioCreacion = $this->session->get('user_id');
        $usuarioCreacion = !empty($usuarioCreacion) ? intval($usuarioCreacion) : null;

        // Construir array de datos en el orden correcto según allowedFields
        // IMPORTANTE: El orden debe coincidir con el orden de allowedFields en UsuarioModel
        $data = [];

        // Campos integer (requeridos)
        $data['empresa_id'] = (int) $empresaIdPost;
        $data['sucursal_id'] = $sucursalId;
        $data['rol_id'] = intval($this->request->getPost('rol_id'));

        // Campos de texto
        $data['nombre'] = trim($this->request->getPost('nombre'));
        $data['apellido'] = trim($this->request->getPost('apellido'));
        $data['ci'] = trim($this->request->getPost('ci'));
        $data['email'] = trim($this->request->getPost('email'));
        $data['telefono'] = $this->request->getPost('telefono') ? trim($this->request->getPost('telefono')) : null;
        $data['direccion'] = $this->request->getPost('direccion') ? trim($this->request->getPost('direccion')) : null;
        $data['username'] = trim($this->request->getPost('username'));
        $data['password'] = $this->request->getPost('password');

        // Campos opcionales
        $data['foto_perfil'] = null; // Se asignará después si hay archivo
        $data['firma_digital'] = null;
        $data['estado'] = true;
        $data['ultimo_acceso'] = null;
        $data['preferencias'] = null;
        $data['usuario_creacion'] = $usuarioCreacion;

        // Subir foto si existe
        try {
            $foto = $this->request->getFile('foto_perfil');
            if ($foto && $foto->isValid() && !$foto->hasMoved()) {
                $uploadPath = ROOTPATH . 'public/uploads/usuarios';

                // Crear directorio si no existe
                if (!is_dir($uploadPath)) {
                    mkdir($uploadPath, 0755, true);
                }

                // Validar tipo de archivo
                $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                if (!in_array($foto->getMimeType(), $allowedTypes)) {
                    return redirect()->back()
                        ->withInput()
                        ->with('error', 'Tipo de archivo no permitido. Solo se permiten imágenes (JPG, PNG, GIF, WEBP)');
                }

                // Validar tamaño (max 2MB)
                if ($foto->getSize() > 2097152) {
                    return redirect()->back()
                        ->withInput()
                        ->with('error', 'La imagen es demasiado grande. Tamaño máximo: 2MB');
                }

                $newName = $foto->getRandomName();
                if ($foto->move($uploadPath, $newName)) {
                    $data['foto_perfil'] = $newName;
                } else {
                    return redirect()->back()
                        ->withInput()
                        ->with('error', 'Error al subir la imagen: ' . $foto->getErrorString());
                }
            }
        } catch (\Exception $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Error al procesar la imagen: ' . $e->getMessage());
        }

        try {
            // Validar sucursal pertenece a la empresa (si aplica)
            if (!empty($data['sucursal_id'])) {
                $sucursal = $this->sucursalModel->find((int) $data['sucursal_id']);
                if (!$sucursal || (int) ($sucursal['empresa_id'] ?? 0) !== (int) $data['empresa_id']) {
                    return $this->errorResponse('Sucursal inválida para la empresa seleccionada', 400);
                }
            }

            // Validar que los campos integer sean realmente enteros
            if (!is_numeric($data['empresa_id']) || !is_numeric($data['rol_id'])) {
                return $this->errorResponse('Los campos empresa_id y rol_id deben ser numéricos');
            }

            // Asegurar que los campos integer estén correctamente tipados ANTES de pasar al modelo
            $data['empresa_id'] = (int) $data['empresa_id'];
            $data['rol_id'] = (int) $data['rol_id'];
            if (isset($data['sucursal_id']) && $data['sucursal_id'] !== null && $data['sucursal_id'] !== '') {
                $data['sucursal_id'] = (int) $data['sucursal_id'];
            } else {
                $data['sucursal_id'] = null;
            }
            if (isset($data['usuario_creacion']) && $data['usuario_creacion'] !== null && $data['usuario_creacion'] !== '') {
                $data['usuario_creacion'] = (int) $data['usuario_creacion'];
            } else {
                $data['usuario_creacion'] = null;
            }

            // Remover campos vacíos que puedan causar problemas
            foreach ($data as $key => $value) {
                if ($value === '' && !in_array($key, ['telefono', 'direccion', 'foto_perfil', 'firma_digital', 'preferencias'])) {
                    unset($data[$key]);
                }
            }

            // Asegurar que no se pasen campos que CodeIgniter maneja automáticamente
            unset($data['fecha_creacion']);
            unset($data['fecha_modificacion']);

            // Validación final: verificar que no haya valores de texto en campos integer
            $integerFields = ['empresa_id', 'sucursal_id', 'rol_id', 'usuario_creacion'];
            foreach ($integerFields as $field) {
                if (isset($data[$field]) && $data[$field] !== null && !is_int($data[$field]) && !is_numeric($data[$field])) {
                    log_message('error', "UsuariosController::guardar - Campo {$field} tiene valor no numérico: " . var_export($data[$field], true));
                    return $this->errorResponse("El campo {$field} debe ser numérico. Valor recibido: " . var_export($data[$field], true));
                }
            }

            // Log para debug en desarrollo
            if (ENVIRONMENT === 'development') {
                log_message('debug', 'UsuariosController::guardar - Datos antes de insert: ' . json_encode($data));
                log_message('debug', 'UsuariosController::guardar - Tipos de datos: ' . json_encode(array_map('gettype', $data)));
            }

            // Desactivar validación del modelo para inserciones
            // Ya validamos manualmente en el controlador (incluyendo unicidad)
            $this->usuarioModel->skipValidation(true);

            // Intentar insertar con manejo de errores mejorado
            try {
                $result = $this->usuarioModel->insert($data);
            } catch (\Exception $e) {
                log_message('error', 'UsuariosController::guardar - Excepción en insert: ' . $e->getMessage());
                log_message('error', 'UsuariosController::guardar - Stack trace: ' . $e->getTraceAsString());
                log_message('error', 'UsuariosController::guardar - Datos enviados: ' . json_encode($data));
                // Reactivar validación
                $this->usuarioModel->skipValidation(false);
                return $this->errorResponse('Error al crear el usuario: ' . $e->getMessage());
            }

            if (!$result) {
                $errors = $this->usuarioModel->errors();
                $errorMessage = 'Error al crear el usuario';
                if (!empty($errors)) {
                    $errorMessage .= ': ' . implode(', ', $errors);
                }
                log_message('error', 'UsuariosController::guardar - Error en insert: ' . $errorMessage);
                log_message('error', 'UsuariosController::guardar - Datos enviados: ' . json_encode($data));
                log_message('error', 'UsuariosController::guardar - Errores del modelo: ' . json_encode($errors));
                // Reactivar validación
                $this->usuarioModel->skipValidation(false);
                return $this->errorResponse($errorMessage);
            }

            // Reactivar validación para futuras operaciones
            $this->usuarioModel->skipValidation(false);

            if ($result) {
                $auditoriaModel = new \App\Models\AuditoriaModel();
                $auditoriaModel->registrar([
                    'usuario_id' => $this->session->get('user_id'),
                    'empresa_id' => $data['empresa_id'],
                    'tabla_afectada' => 'usuarios',
                    'accion' => 'INSERT',
                    'datos_nuevos' => $data,
                    'ip_address' => $this->request->getIPAddress()
                ]);

                return $this->successResponse('Usuario creado exitosamente', [
                    'redirect' => base_url('admin/usuarios')
                ]);
            }

            return $this->errorResponse('Error al crear el usuario');
        } catch (\Exception $e) {
            // Asegurar que la validación se reactive incluso si hay excepción
            $this->usuarioModel->skipValidation(false);
            log_message('error', 'UsuariosController::guardar - Excepción general: ' . $e->getMessage());
            log_message('error', 'UsuariosController::guardar - Stack trace: ' . $e->getTraceAsString());
            log_message('error', 'UsuariosController::guardar - Datos: ' . json_encode($data ?? []));
            return $this->errorResponse('Error al crear el usuario: ' . $e->getMessage());
        }
    }

    public function editar($id)
    {
        $this->requireLogin();
        $this->requirePermission('USUARIOS', 'editar');
        $this->checkAuth();

        $usuario = $this->usuarioModel->find($id);

        if (!$usuario) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Usuario no encontrado');
        }

        $empresaId = $this->user['rol_id'] == 1 ? null : $this->user['empresa_id'];

        $data = [
            'title' => 'Editar Usuario',
            'usuario' => $usuario,
            'roles' => $this->rolModel->getActivos(),
            'empresas' => $this->empresaModel->getActivas(),
            'sucursales' => $this->sucursalModel->getPorEmpresa($usuario['empresa_id']),
            'empresa_id' => $empresaId
        ];

        return $this->render('admin/usuarios/form', $data);
    }

    public function actualizar($id)
    {
        // Log inicial para verificar que el método se está ejecutando
        if (ENVIRONMENT === 'development') {
            log_message('debug', 'UsuariosController::actualizar - Método llamado con ID: ' . $id);
            log_message('debug', 'UsuariosController::actualizar - Método HTTP: ' . $this->request->getMethod());
            log_message('debug', 'UsuariosController::actualizar - POST data: ' . json_encode($this->request->getPost()));
        }

        try {
            // Verificar autenticación - para peticiones AJAX, devolver JSON en lugar de redirect
            if (!$this->isLoggedIn()) {
                return $this->errorResponse('No autenticado', 401);
            }

            // Cargar datos de usuario
            $this->checkAuth();

            // Verificar permisos
            if (!$this->hasPermission('USUARIOS', 'editar')) {
                // Si es administrador, permitir
                if (isset($this->user['rol_id'])) {
                    $rolModel = new \App\Models\RolModel();
                    $rol = $rolModel->find($this->user['rol_id']);
                    if (!$rol || $rol['nivel'] > 2) {
                        return $this->errorResponse('No tiene permisos para editar usuarios', 403);
                    }
                } else {
                    return $this->errorResponse('No tiene permisos para editar usuarios', 403);
                }
            }

            // Verificar que el método sea POST
            if (!$this->request->is('post')) {
                return $this->errorResponse('Método no permitido', 405);
            }

            // Cargar datos de usuario si no están cargados
            if (!$this->user) {
                $this->loadUserData();
            }

            $usuario = $this->usuarioModel->find($id);

            if (!$usuario) {
                log_message('error', "UsuariosController::actualizar - Usuario con ID {$id} no encontrado");
                return $this->errorResponse('Usuario no encontrado', 404);
            }

            // Validar que los datos requeridos estén presentes
            $empresaIdPost = $this->request->getPost('empresa_id');
            $rolIdPost = $this->request->getPost('rol_id');
            $nombrePost = $this->request->getPost('nombre');
            $apellidoPost = $this->request->getPost('apellido');
            $ciPost = $this->request->getPost('ci');
            $emailPost = $this->request->getPost('email');
            $usernamePost = $this->request->getPost('username');

            if (empty($empresaIdPost) || empty($rolIdPost) || empty($nombrePost) || empty($apellidoPost) || empty($ciPost) || empty($emailPost) || empty($usernamePost)) {
                return $this->errorResponse('Todos los campos requeridos deben estar completos');
            }

            // Verificar que CI, email y username no estén duplicados (excepto el usuario actual)
            $ciExiste = $this->usuarioModel->where('ci', $ciPost)->where('id !=', $id)->countAllResults();
            if ($ciExiste > 0) {
                return $this->errorResponse('El número de CI ya está registrado por otro usuario');
            }

            $emailExiste = $this->usuarioModel->where('email', $emailPost)->where('id !=', $id)->countAllResults();
            if ($emailExiste > 0) {
                return $this->errorResponse('El email ya está registrado por otro usuario');
            }

            $usernameExiste = $this->usuarioModel->where('username', $usernamePost)->where('id !=', $id)->countAllResults();
            if ($usernameExiste > 0) {
                return $this->errorResponse('El nombre de usuario ya está registrado por otro usuario');
            }

            $rules = [
                'empresa_id' => 'required|is_natural_no_zero',
                'rol_id' => 'required|is_natural_no_zero',
                'nombre' => 'required|min_length[2]|max_length[100]',
                'apellido' => 'required|min_length[2]|max_length[100]',
                'ci' => 'required|regex_match[/^[0-9]{1,8}$/]',
                'email' => 'required|valid_email',
                'username' => 'required|min_length[3]|max_length[50]'
            ];

            // Password es opcional en actualización
            if ($this->request->getPost('password')) {
                $rules['password'] = 'min_length[8]';
            }

            if (!$this->validate($rules)) {
                $errors = $this->validator->getErrors();
                $errorMessage = 'Error de validación: ' . implode(', ', $errors);
                log_message('error', 'UsuariosController::actualizar - Errores de validación: ' . json_encode($errors));
                log_message('error', 'UsuariosController::actualizar - Datos POST: ' . json_encode($this->request->getPost()));
                return $this->errorResponse($errorMessage);
            }
        } catch (\Exception $e) {
            log_message('error', 'UsuariosController::actualizar - Error inicial: ' . $e->getMessage());
            log_message('error', 'Stack trace: ' . $e->getTraceAsString());
            log_message('error', 'POST data: ' . json_encode($this->request->getPost()));
            return $this->errorResponse('Error al procesar la solicitud: ' . $e->getMessage());
        } catch (\Error $e) {
            log_message('error', 'UsuariosController::actualizar - Error fatal: ' . $e->getMessage());
            log_message('error', 'Stack trace: ' . $e->getTraceAsString());
            return $this->errorResponse('Error fatal al procesar la solicitud: ' . $e->getMessage());
        }

        $sucursalId = $this->request->getPost('sucursal_id');
        $sucursalId = !empty($sucursalId) ? intval($sucursalId) : null;

        // Validar y convertir campos integer
        $empresaId = $this->request->getPost('empresa_id');
        if (!is_numeric($empresaId)) {
            return $this->errorResponse('El campo empresa_id debe ser numérico');
        }
        $empresaId = (int) $empresaId;

        // Enforce multiempresa: admin/gerente solo pueden operar dentro de su empresa
        if (($this->user['rol_id'] ?? null) != \App\Models\RolModel::SUPER_ADMIN) {
            $empresaIdUser = $this->user['empresa_id'] ?? null;
            if (!$empresaIdUser) {
                return $this->errorResponse('Usuario no tiene empresa asignada', 403);
            }
            if ((int) $empresaId !== (int) $empresaIdUser) {
                return $this->errorResponse('No tiene permisos para operar sobre otra empresa', 403);
            }
            $empresaId = (int) $empresaIdUser;
        }

        $rolId = $this->request->getPost('rol_id');
        if (!is_numeric($rolId)) {
            return $this->errorResponse('El campo rol_id debe ser numérico');
        }
        $rolId = (int) $rolId;

        $data = [
            'empresa_id' => $empresaId,
            'sucursal_id' => $sucursalId,
            'rol_id' => $rolId,
            'nombre' => trim($this->request->getPost('nombre')),
            'apellido' => trim($this->request->getPost('apellido')),
            'ci' => trim($this->request->getPost('ci')),
            'email' => trim($this->request->getPost('email')),
            'telefono' => $this->request->getPost('telefono') ? trim($this->request->getPost('telefono')) : null,
            'direccion' => $this->request->getPost('direccion') ? trim($this->request->getPost('direccion')) : null,
            'username' => trim($this->request->getPost('username'))
        ];

        // Actualizar password solo si se proporciona
        if ($this->request->getPost('password')) {
            $data['password'] = $this->request->getPost('password');
        }

        // Subir foto si existe
        try {
            $foto = $this->request->getFile('foto_perfil');
            if ($foto && $foto->isValid() && !$foto->hasMoved()) {
                $uploadPath = ROOTPATH . 'public/uploads/usuarios';

                // Crear directorio si no existe
                if (!is_dir($uploadPath)) {
                    mkdir($uploadPath, 0755, true);
                }

                // Validar tipo de archivo
                $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                if (!in_array($foto->getMimeType(), $allowedTypes)) {
                    return $this->errorResponse('Tipo de archivo no permitido. Solo se permiten imágenes (JPG, PNG, GIF, WEBP)');
                }

                // Validar tamaño (max 2MB)
                if ($foto->getSize() > 2097152) {
                    return $this->errorResponse('La imagen es demasiado grande. Tamaño máximo: 2MB');
                }

                // Eliminar foto anterior si existe
                if ($usuario['foto_perfil'] && file_exists($uploadPath . '/' . $usuario['foto_perfil'])) {
                    @unlink($uploadPath . '/' . $usuario['foto_perfil']);
                }

                $newName = $foto->getRandomName();
                if ($foto->move($uploadPath, $newName)) {
                    $data['foto_perfil'] = $newName;
                } else {
                    return $this->errorResponse('Error al subir la foto: ' . $foto->getErrorString());
                }
            }
        } catch (\Exception $e) {
            return $this->errorResponse('Error al procesar la foto: ' . $e->getMessage());
        }

        try {
            // Validar sucursal pertenece a la empresa (si aplica)
            if (!empty($data['sucursal_id'])) {
                $sucursal = $this->sucursalModel->find((int) $data['sucursal_id']);
                if (!$sucursal || (int) ($sucursal['empresa_id'] ?? 0) !== (int) $data['empresa_id']) {
                    return $this->errorResponse('Sucursal inválida para la empresa seleccionada', 400);
                }
            }

            // Asegurar que los campos integer estén correctamente tipados ANTES de pasar al modelo
            $data['empresa_id'] = (int) $data['empresa_id'];
            $data['rol_id'] = (int) $data['rol_id'];
            if (isset($data['sucursal_id']) && $data['sucursal_id'] !== null && $data['sucursal_id'] !== '') {
                $data['sucursal_id'] = (int) $data['sucursal_id'];
            } else {
                $data['sucursal_id'] = null;
            }

            // Remover campos vacíos excepto los que pueden ser cadenas vacías
            foreach ($data as $key => $value) {
                if ($value === '' && !in_array($key, ['telefono', 'direccion', 'foto_perfil', 'firma_digital', 'preferencias', 'password'])) {
                    unset($data[$key]);
                }
            }

            // Asegurar que no se pasen campos que CodeIgniter maneja automáticamente
            unset($data['fecha_creacion']);
            unset($data['fecha_modificacion']);

            // Validación final: verificar que no haya valores de texto en campos integer
            $integerFields = ['empresa_id', 'sucursal_id', 'rol_id'];
            foreach ($integerFields as $field) {
                if (isset($data[$field]) && $data[$field] !== null && !is_int($data[$field]) && !is_numeric($data[$field])) {
                    log_message('error', "UsuariosController::actualizar - Campo {$field} tiene valor no numérico: " . var_export($data[$field], true));
                    return $this->errorResponse("El campo {$field} debe ser numérico. Valor recibido: " . var_export($data[$field], true));
                }
            }

            // Log para debug en desarrollo
            if (ENVIRONMENT === 'development') {
                log_message('debug', 'UsuariosController::actualizar - Datos antes de update: ' . json_encode($data));
                log_message('debug', 'UsuariosController::actualizar - Tipos de datos: ' . json_encode(array_map('gettype', $data)));
            }

            // Desactivar validación del modelo para actualizaciones
            // Ya validamos manualmente en el controlador (incluyendo unicidad)
            $this->usuarioModel->skipValidation(true);

            // Intentar actualizar con manejo de errores mejorado
            // CodeIgniter filtra automáticamente los campos según allowedFields
            try {
                $result = $this->usuarioModel->update($id, $data);
            } catch (\Exception $e) {
                log_message('error', 'UsuariosController::actualizar - Excepción en update: ' . $e->getMessage());
                log_message('error', 'UsuariosController::actualizar - Stack trace: ' . $e->getTraceAsString());
                log_message('error', 'UsuariosController::actualizar - Datos enviados: ' . json_encode($data));
                // Reactivar validación
                $this->usuarioModel->skipValidation(false);
                return $this->errorResponse('Error al actualizar el usuario: ' . $e->getMessage());
            }

            // Reactivar validación para futuras operaciones
            $this->usuarioModel->skipValidation(false);

            if (!$result) {
                $errors = $this->usuarioModel->errors();
                $errorMessage = 'Error al actualizar el usuario';
                if (!empty($errors)) {
                    $errorMessage .= ': ' . implode(', ', $errors);
                }
                log_message('error', 'UsuariosController::actualizar - Error en update: ' . $errorMessage);
                log_message('error', 'UsuariosController::actualizar - Datos enviados: ' . json_encode($data));
                log_message('error', 'UsuariosController::actualizar - ID: ' . $id);
                log_message('error', 'UsuariosController::actualizar - Errores del modelo: ' . json_encode($errors));
                return $this->errorResponse($errorMessage);
            }

            // Si llegamos aquí, la actualización fue exitosa
            try {
                $auditoriaModel = new \App\Models\AuditoriaModel();
                $auditoriaModel->registrar([
                    'usuario_id' => $this->session->get('user_id'),
                    'empresa_id' => $data['empresa_id'],
                    'tabla_afectada' => 'usuarios',
                    'accion' => 'UPDATE',
                    'registro_id' => $id,
                    'datos_anteriores' => $usuario,
                    'datos_nuevos' => $data,
                    'ip_address' => $this->request->getIPAddress()
                ]);
            } catch (\Exception $e) {
                log_message('error', 'Error al registrar auditoría: ' . $e->getMessage());
            }

            return $this->successResponse('Usuario actualizado exitosamente', [
                'redirect' => base_url('admin/usuarios')
            ]);
        } catch (\Exception $e) {
            log_message('error', 'Error al actualizar usuario: ' . $e->getMessage());
            log_message('error', 'Stack trace: ' . $e->getTraceAsString());
            log_message('error', 'Datos: ' . json_encode($data ?? []));
            return $this->errorResponse('Error al actualizar el usuario: ' . $e->getMessage());
        }
    }

    public function ver($id)
    {
        $this->checkAuth();
        $usuario = $this->usuarioModel->find($id);

        if (!$usuario) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Usuario no encontrado');
        }

        // Obtener información adicional
        $rol = $this->rolModel->find($usuario['rol_id']);
        $empresa = $this->empresaModel->find($usuario['empresa_id']);
        $sucursal = $usuario['sucursal_id'] ? $this->sucursalModel->find($usuario['sucursal_id']) : null;

        // Obtener estadísticas del usuario
        $db = \Config\Database::connect();
        $ventasHoy = $db->table('ventas')
            ->where('usuario_id', $id)
            ->where('DATE(fecha_venta)', date('Y-m-d'))
            ->countAllResults();

        $data = [
            'title' => 'Ver Usuario',
            'usuario' => $usuario,
            'rol' => $rol,
            'empresa' => $empresa,
            'sucursal' => $sucursal,
            'ventas_hoy' => $ventasHoy
        ];

        return $this->render('admin/usuarios/ver', $data);
    }

    public function eliminar($id)
    {
        $this->checkAuth();
        $usuario = $this->usuarioModel->find($id);

        if (!$usuario) {
            return $this->errorResponse('Usuario no encontrado', 404);
        }

        // No permitir eliminar al usuario actual
        if ($usuario['id'] == $this->session->get('user_id')) {
            return $this->errorResponse('No se puede eliminar su propio usuario');
        }

        // Verificar si tiene ventas asociadas
        $ventaModel = new \App\Models\VentaModel();
        $tieneVentas = $ventaModel->where('usuario_id', $id)->countAllResults();

        if ($tieneVentas > 0) {
            return $this->errorResponse('No se puede eliminar el usuario porque tiene ventas asociadas');
        }

        // Solo desactivar
        if ($this->usuarioModel->update($id, ['estado' => false])) {
            $auditoriaModel = new \App\Models\AuditoriaModel();
            $auditoriaModel->registrar([
                'usuario_id' => $this->session->get('user_id'),
                'empresa_id' => $usuario['empresa_id'],
                'tabla_afectada' => 'usuarios',
                'accion' => 'DELETE',
                'registro_id' => $id,
                'datos_anteriores' => $usuario,
                'ip_address' => $this->request->getIPAddress()
            ]);

            return $this->successResponse('Usuario eliminado exitosamente');
        }

        return $this->errorResponse('Error al eliminar el usuario');
    }
}
