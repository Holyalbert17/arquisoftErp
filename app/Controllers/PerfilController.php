<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\UsuarioModel;
use App\Models\SesionModel;

class PerfilController extends BaseController
{
    protected $usuarioModel;
    protected $sesionModel;
    protected $auditoriaModel;

    public function __construct()
    {
        $this->usuarioModel = new UsuarioModel();
        
        // Intentar cargar SesionModel solo si la tabla existe
        try {
            $db = \Config\Database::connect();
            if ($db->tableExists('sesiones')) {
                $this->sesionModel = new SesionModel();
            }
        } catch (\Exception $e) {
            // Si hay error, no cargar el modelo
            $this->sesionModel = null;
        }
        
        // Intentar cargar AuditoriaModel si existe
        if (class_exists('\App\Models\AuditoriaModel')) {
            try {
                $this->auditoriaModel = new \App\Models\AuditoriaModel();
            } catch (\Exception $e) {
                $this->auditoriaModel = null;
            }
        }
    }
    
    protected function checkAuth()
    {
        if (!$this->isLoggedIn()) {
            return redirect()->to('/auth/login');
        }
        $this->loadUserData();
        if (!$this->user) {
            return redirect()->to('/auth/login');
        }
    }

    public function index()
    {
        $this->checkAuth();
        
        if (!$this->user || !isset($this->user['id'])) {
            return redirect()->to('/auth/login');
        }
        
        $usuarioId = $this->user['id'];
        
        // Obtener datos completos del usuario
        $usuario = $this->usuarioModel->find($usuarioId);
        
        if (!$usuario) {
            return redirect()->to('/auth/login');
        }
        
        // Obtener sesiones activas
        $sesiones = [];
        if ($this->sesionModel) {
            try {
                $sesiones = $this->sesionModel->where('usuario_id', $usuarioId)
                                              ->where('activa', true)
                                              ->orderBy('fecha_inicio', 'DESC')
                                              ->findAll();
            } catch (\Exception $e) {
                log_message('error', 'Error al obtener sesiones: ' . $e->getMessage());
                // Si la tabla no existe, simplemente no mostrar sesiones
            }
        }
        
        // Obtener historial de actividad reciente
        $historial = [];
        if ($this->auditoriaModel) {
            try {
                $historial = $this->auditoriaModel->where('usuario_id', $usuarioId)
                                                 ->orderBy('fecha_evento', 'DESC')
                                                 ->limit(20)
                                                 ->findAll();
            } catch (\Exception $e) {
                log_message('error', 'Error al obtener historial: ' . $e->getMessage());
            }
        }
        
        // Obtener estadísticas del usuario
        $estadisticas = $this->obtenerEstadisticas($usuarioId);
        
        $data = [
            'title' => 'Mi Perfil',
            'usuario' => $usuario,
            'sesiones' => $sesiones,
            'historial' => $historial,
            'estadisticas' => $estadisticas
        ];

        return $this->render('perfil/index', $data);
    }

    public function actualizarDatos()
    {
        $this->checkAuth();
        
        $usuarioId = $this->user['id'];
        
        $validation = \Config\Services::validation();
        $validation->setRules([
            'nombre' => 'required|min_length[2]|max_length[100]',
            'apellido' => 'required|min_length[2]|max_length[100]',
            'email' => "required|valid_email|is_unique[usuarios.email,id,{$usuarioId}]",
            'telefono' => 'permit_empty|max_length[50]',
            'direccion' => 'permit_empty|max_length[500]',
            'ci' => "required|is_unique[usuarios.ci,id,{$usuarioId}]"
        ]);

        if (!$validation->run($this->request->getPost())) {
            return $this->errorResponse('Error de validación: ' . implode(', ', $validation->getErrors()));
        }

        $data = [
            'nombre' => $this->request->getPost('nombre'),
            'apellido' => $this->request->getPost('apellido'),
            'email' => $this->request->getPost('email'),
            'telefono' => $this->request->getPost('telefono'),
            'direccion' => $this->request->getPost('direccion'),
            'ci' => $this->request->getPost('ci'),
            'fecha_modificacion' => date('Y-m-d H:i:s')
        ];

        try {
            $this->usuarioModel->update($usuarioId, $data);
            
            // Actualizar datos en sesión
            $this->session->set([
                'user_nombre' => $data['nombre'],
                'user_apellido' => $data['apellido'],
                'user_email' => $data['email']
            ]);
            
            // Registrar auditoría
            $this->registrarAuditoria('UPDATE', 'usuarios', $usuarioId, null, $data);
            
            return $this->successResponse('Datos actualizados exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al actualizar datos: ' . $e->getMessage());
        }
    }

    public function cambiarPassword()
    {
        $this->checkAuth();
        
        $usuarioId = $this->user['id'];
        
        $validation = \Config\Services::validation();
        $validation->setRules([
            'password_actual' => 'required',
            'password_nuevo' => 'required|min_length[8]|matches[password_confirmar]',
            'password_confirmar' => 'required|min_length[8]'
        ]);

        if (!$validation->run($this->request->getPost())) {
            return $this->errorResponse('Error de validación: ' . implode(', ', $validation->getErrors()));
        }

        $usuario = $this->usuarioModel->find($usuarioId);
        
        // Verificar contraseña actual
        if (!password_verify($this->request->getPost('password_actual'), $usuario['password'])) {
            return $this->errorResponse('La contraseña actual es incorrecta');
        }

        // Validar fortaleza de la nueva contraseña
        $passwordNuevo = $this->request->getPost('password_nuevo');
        if (!$this->validarFortalezaPassword($passwordNuevo)) {
            return $this->errorResponse('La contraseña debe contener al menos: 8 caracteres, una mayúscula, una minúscula y un número');
        }

        // Actualizar contraseña
        $passwordHash = password_hash($passwordNuevo, PASSWORD_BCRYPT);
        
        try {
            // Actualizar directamente en la base de datos para evitar el callback de hash
            $db = \Config\Database::connect();
            $db->table('usuarios')
               ->where('id', $usuarioId)
               ->update([
                   'password' => $passwordHash,
                   'fecha_modificacion' => date('Y-m-d H:i:s')
               ]);
            
            // Registrar auditoría
            $this->registrarAuditoria('UPDATE', 'usuarios', $usuarioId, null, ['password' => '***']);
            
            return $this->successResponse('Contraseña actualizada exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al actualizar contraseña: ' . $e->getMessage());
        }
    }

    public function cambiarFoto()
    {
        $this->checkAuth();
        
        $usuarioId = $this->user['id'];
        
        $foto = $this->request->getFile('foto');
        
        if (!$foto || !$foto->isValid() || $foto->hasMoved()) {
            return $this->errorResponse('No se ha seleccionado una imagen válida');
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

        $uploadPath = ROOTPATH . 'public/uploads/usuarios';
        
        // Crear directorio si no existe
        if (!is_dir($uploadPath)) {
            mkdir($uploadPath, 0755, true);
        }

        // Eliminar foto anterior si existe
        $usuario = $this->usuarioModel->find($usuarioId);
        if ($usuario && !empty($usuario['foto_perfil'])) {
            $fotoAnterior = $uploadPath . '/' . $usuario['foto_perfil'];
            if (file_exists($fotoAnterior)) {
                unlink($fotoAnterior);
            }
        }

        // Generar nombre único
        $newName = $foto->getRandomName();
        
        if ($foto->move($uploadPath, $newName)) {
            try {
                $this->usuarioModel->update($usuarioId, [
                    'foto_perfil' => $newName,
                    'fecha_modificacion' => date('Y-m-d H:i:s')
                ]);
                
                // Actualizar en sesión
                $this->session->set('user_foto', $newName);
                
                // Registrar auditoría
                $this->registrarAuditoria('UPDATE', 'usuarios', $usuarioId, null, ['foto_perfil' => $newName]);
                
                return $this->successResponse('Foto de perfil actualizada exitosamente', [
                    'foto' => $newName,
                    'url' => base_url('uploads/usuarios/' . $newName)
                ]);
            } catch (\Exception $e) {
                // Eliminar archivo si falla la actualización
                unlink($uploadPath . '/' . $newName);
                return $this->errorResponse('Error al actualizar foto: ' . $e->getMessage());
            }
        } else {
            return $this->errorResponse('Error al subir la imagen: ' . $foto->getErrorString());
        }
    }

    public function cerrarSesion()
    {
        $this->checkAuth();
        
        $sesionId = $this->request->getPost('sesion_id');
        
        if (!$sesionId) {
            return $this->errorResponse('ID de sesión no proporcionado');
        }

        try {
            if (!$this->sesionModel) {
                return $this->errorResponse('La funcionalidad de sesiones no está disponible');
            }
            
            // Buscar por session_id o id
            $this->sesionModel->groupStart()
                         ->where('session_id', $sesionId)
                         ->orWhere('id', $sesionId)
                         ->groupEnd()
                         ->set([
                             'activa' => false,
                             'fecha_fin' => date('Y-m-d H:i:s')
                         ])
                         ->update();
        
            return $this->successResponse('Sesión cerrada exitosamente');
        } catch (\Exception $e) {
            log_message('error', 'Error al cerrar sesión: ' . $e->getMessage());
            return $this->errorResponse('Error al cerrar sesión: ' . $e->getMessage());
        }
    }

    public function cerrarTodasSesiones()
    {
        $this->checkAuth();
        
        $usuarioId = $this->user['id'];
        $sesionActual = session_id();
        
        try {
            if (!$this->sesionModel) {
                return $this->errorResponse('La funcionalidad de sesiones no está disponible');
            }
            
            $this->sesionModel->where('usuario_id', $usuarioId)
                             ->where('session_id !=', $sesionActual)
                             ->set([
                                 'activa' => false,
                                 'fecha_fin' => date('Y-m-d H:i:s')
                             ])
                             ->update();
            
            return $this->successResponse('Todas las sesiones han sido cerradas exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al cerrar sesiones: ' . $e->getMessage());
        }
    }

    public function actualizarPreferencias()
    {
        $this->checkAuth();
        
        if (!$this->user || !isset($this->user['id'])) {
            return $this->errorResponse('Usuario no autenticado', 401);
        }
        
        $usuarioId = $this->user['id'];
        
        // Obtener preferencias actuales si existen
        $usuario = $this->usuarioModel->find($usuarioId);
        $preferenciasActuales = [];
        if (!empty($usuario['preferencias'])) {
            try {
                $preferenciasActuales = json_decode($usuario['preferencias'], true) ?? [];
            } catch (\Exception $e) {
                $preferenciasActuales = [];
            }
        }
        
        // Obtener datos del POST (checkboxes pueden no enviarse si están desmarcados)
        $postData = $this->request->getPost();
        
        $preferencias = [
            'notificaciones_email' => isset($postData['notificaciones_email']) && $postData['notificaciones_email'] == 'on' ? true : false,
            'notificaciones_push' => isset($postData['notificaciones_push']) && $postData['notificaciones_push'] == 'on' ? true : false,
            'tema_oscuro' => isset($postData['tema_oscuro']) && $postData['tema_oscuro'] == 'on' ? true : false,
            'idioma' => $postData['idioma'] ?? ($preferenciasActuales['idioma'] ?? 'es'),
            'zona_horaria' => $postData['zona_horaria'] ?? ($preferenciasActuales['zona_horaria'] ?? 'America/Asuncion'),
            'formato_fecha' => $postData['formato_fecha'] ?? ($preferenciasActuales['formato_fecha'] ?? 'd/m/Y'),
            'formato_hora' => $postData['formato_hora'] ?? ($preferenciasActuales['formato_hora'] ?? 'H:i:s')
        ];

        try {
            // Guardar preferencias en JSON
            $this->usuarioModel->update($usuarioId, [
                'preferencias' => json_encode($preferencias),
                'fecha_modificacion' => date('Y-m-d H:i:s')
            ]);
            
            // Actualizar en sesión
            $this->session->set('preferencias', $preferencias);
            
            // Registrar auditoría
            $this->registrarAuditoria('UPDATE', 'usuarios', $usuarioId, $preferenciasActuales, $preferencias);
            
            return $this->successResponse('Preferencias actualizadas exitosamente', ['preferencias' => $preferencias]);
        } catch (\Exception $e) {
            log_message('error', 'Error al actualizar preferencias: ' . $e->getMessage());
            return $this->errorResponse('Error al actualizar preferencias: ' . $e->getMessage());
        }
    }

    public function guardarTema()
    {
        $this->checkAuth();
        
        if (!$this->user || !isset($this->user['id'])) {
            return $this->errorResponse('Usuario no autenticado', 401);
        }
        
        $usuarioId = $this->user['id'];
        $temaOscuro = $this->request->getPost('tema_oscuro');
        
        // Convertir a boolean
        $temaOscuro = ($temaOscuro == '1' || $temaOscuro === 1 || $temaOscuro === true || $temaOscuro === 'true');
        
        try {
            // Actualizar campo tema_oscuro directamente
            $this->usuarioModel->update($usuarioId, [
                'tema_oscuro' => $temaOscuro ? true : false,
                'fecha_modificacion' => date('Y-m-d H:i:s')
            ]);
            
            // También actualizar en preferencias JSON si existe
            $usuario = $this->usuarioModel->find($usuarioId);
            $preferencias = [];
            if (!empty($usuario['preferencias'])) {
                try {
                    $preferencias = json_decode($usuario['preferencias'], true) ?? [];
                } catch (\Exception $e) {
                    $preferencias = [];
                }
            }
            $preferencias['tema_oscuro'] = $temaOscuro;
            
            $this->usuarioModel->update($usuarioId, [
                'preferencias' => json_encode($preferencias)
            ]);
            
            return $this->successResponse('Tema actualizado exitosamente', ['tema_oscuro' => $temaOscuro]);
        } catch (\Exception $e) {
            log_message('error', 'Error al guardar tema: ' . $e->getMessage());
            return $this->errorResponse('Error al guardar tema: ' . $e->getMessage());
        }
    }

    public function obtenerHistorial()
    {
        $this->checkAuth();
        
        $usuarioId = $this->user['id'];
        $pagina = $this->request->getGet('pagina') ?? 1;
        $limite = $this->request->getGet('limite') ?? 20;
        
        $historial = [];
        $pager = null;
        
        if ($this->auditoriaModel) {
            try {
                $historial = $this->auditoriaModel->where('usuario_id', $usuarioId)
                                                 ->orderBy('fecha_evento', 'DESC')
                                                 ->paginate($limite, 'default', $pagina);
                $pager = $this->auditoriaModel->pager->getDetails();
            } catch (\Exception $e) {
                log_message('error', 'Error al obtener historial: ' . $e->getMessage());
            }
        }
        
        return $this->response->setJSON([
            'success' => true,
            'data' => $historial,
            'pager' => $pager
        ]);
    }

    private function obtenerEstadisticas($usuarioId)
    {
        $estadisticas = [
            'total_ventas' => 0,
            'total_compras' => 0,
            'total_ordenes' => 0,
            'ultimo_acceso' => null,
            'dias_activo' => 0
        ];

        try {
            // Total de ventas realizadas
            $ventaModel = new \App\Models\VentaModel();
            $estadisticas['total_ventas'] = $ventaModel->where('usuario_id', $usuarioId)
                                                       ->where('estado !=', 'ANULADA')
                                                       ->countAllResults(false);
            
            // Total de compras realizadas
            $compraModel = new \App\Models\CompraModel();
            $estadisticas['total_compras'] = $compraModel->where('usuario_id', $usuarioId)
                                                         ->countAllResults(false);
            
            // Total de órdenes de servicio
            $ordenModel = new \App\Models\OrdenServicioModel();
            $estadisticas['total_ordenes'] = $ordenModel->where('usuario_creacion', $usuarioId)
                                                        ->countAllResults(false);
            
            // Último acceso
            $usuario = $this->usuarioModel->find($usuarioId);
            $estadisticas['ultimo_acceso'] = $usuario['ultimo_acceso'] ?? null;
            
            // Días activo (desde fecha de creación)
            if ($usuario && $usuario['fecha_creacion']) {
                $fechaCreacion = new \DateTime($usuario['fecha_creacion']);
                $fechaActual = new \DateTime();
                $estadisticas['dias_activo'] = $fechaCreacion->diff($fechaActual)->days;
            }
        } catch (\Exception $e) {
            log_message('error', 'Error al obtener estadísticas: ' . $e->getMessage());
        }

        return $estadisticas;
    }

    private function validarFortalezaPassword($password)
    {
        // Mínimo 8 caracteres, al menos una mayúscula, una minúscula y un número
        return strlen($password) >= 8 &&
               preg_match('/[A-Z]/', $password) &&
               preg_match('/[a-z]/', $password) &&
               preg_match('/[0-9]/', $password);
    }

    private function registrarAuditoria($accion, $tabla, $registroId, $datosAnteriores, $datosNuevos)
    {
        if (!$this->auditoriaModel) {
            return;
        }
        
        try {
            $this->auditoriaModel->insert([
                'usuario_id' => $this->user['id'],
                'empresa_id' => $this->user['empresa_id'] ?? null,
                'tabla_afectada' => $tabla,
                'accion' => $accion,
                'registro_id' => $registroId,
                'datos_anteriores' => $datosAnteriores ? json_encode($datosAnteriores) : null,
                'datos_nuevos' => $datosNuevos ? json_encode($datosNuevos) : null,
                'ip_address' => $this->request->getIPAddress(),
                'user_agent' => $this->request->getUserAgent()->getAgentString(),
                'fecha_evento' => date('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            log_message('error', 'Error al registrar auditoría: ' . $e->getMessage());
        }
    }
}
