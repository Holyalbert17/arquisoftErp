<?php

namespace App\Controllers;

use App\Models\UsuarioModel;
use App\Models\EmpresaModel;
use App\Models\SucursalModel;

class AuthController extends BaseController
{
    protected $usuarioModel;
    protected $empresaModel;
    protected $sucursalModel;

    public function __construct()
    {
        $this->usuarioModel = new UsuarioModel();
        $this->empresaModel = new EmpresaModel();
        $this->sucursalModel = new SucursalModel();
    }

    /**
     * Mostrar formulario de login
     */
    public function login()
    {
        // Si ya está logueado, redirigir al dashboard
        if ($this->isLoggedIn()) {
            return redirect()->to('/dashboard');
        }

        $data = [
            'title' => 'Iniciar Sesión'
        ];

        return view('auth/login', $data);
    }

    /**
     * Procesar login
     */
    public function processLogin()
    {
        $validation = \Config\Services::validation();

        $rules = [
            'username' => 'required|min_length[3]',
            'password' => 'required|min_length[6]'
        ];

        if (!$this->validate($rules)) {
            return $this->errorResponse('Por favor complete todos los campos', 400);
        }

        $username = $this->request->getPost('username');
        $password = $this->request->getPost('password');

        $usuario = $this->usuarioModel->verificarLogin($username, $password);

        if (!$usuario) {
            return $this->errorResponse('Usuario o contraseña incorrectos', 401);
        }

        // Obtener empresa y sucursal
        $empresa = $this->empresaModel->find($usuario['empresa_id']);
        $sucursal = $this->sucursalModel->find($usuario['sucursal_id']);

        // Guardar en sesión
        $sessionData = [
            'user_id' => $usuario['id'],
            'username' => $usuario['username'],
            'nombre' => $usuario['nombre'],
            'apellido' => $usuario['apellido'],
            'rol_id' => $usuario['rol_id'],
            'empresa_id' => $usuario['empresa_id'],
            'sucursal_id' => $usuario['sucursal_id'],
            'empresa_nombre' => $empresa ? $empresa['nombre'] : '',
            'sucursal_nombre' => $sucursal ? $sucursal['nombre'] : '',
            'is_logged_in' => true
        ];

        $this->session->set($sessionData);

        // Registrar auditoría
        $auditoriaModel = new \App\Models\AuditoriaModel();
        $auditoriaModel->registrar([
            'usuario_id' => $usuario['id'],
            'empresa_id' => $usuario['empresa_id'],
            'tabla_afectada' => 'usuarios',
            'accion' => 'LOGIN',
            'ip_address' => $this->request->getIPAddress(),
            'user_agent' => $this->request->getUserAgent()->getAgentString()
        ]);

        return $this->successResponse('Login exitoso', [
            'redirect' => base_url('dashboard')
        ]);
    }

    /**
     * Cerrar sesión
     */
    public function logout()
    {
        if ($this->isLoggedIn()) {
            // Registrar auditoría
            $auditoriaModel = new \App\Models\AuditoriaModel();
            $auditoriaModel->registrar([
                'usuario_id' => $this->session->get('user_id'),
                'empresa_id' => $this->session->get('empresa_id'),
                'tabla_afectada' => 'usuarios',
                'accion' => 'LOGOUT',
                'ip_address' => $this->request->getIPAddress(),
                'user_agent' => $this->request->getUserAgent()->getAgentString()
            ]);
        }

        $this->session->destroy();
        return redirect()->to('/auth/login');
    }

    /**
     * Cambiar contraseña
     */
    public function cambiarPassword()
    {
        if (!$this->isLoggedIn()) {
            return redirect()->to('/auth/login');
        }

        if ($this->request->getMethod() === 'post') {
            $validation = \Config\Services::validation();

            $rules = [
                'password_actual' => 'required',
                'password_nuevo' => 'required|min_length[8]',
                'password_confirmar' => 'required|matches[password_nuevo]'
            ];

            if (!$this->validate($rules)) {
                return $this->errorResponse('Las contraseñas no coinciden o no cumplen los requisitos', 400);
            }

            $usuario = $this->usuarioModel->find($this->session->get('user_id'));

            if (!password_verify($this->request->getPost('password_actual'), $usuario['password'])) {
                return $this->errorResponse('La contraseña actual es incorrecta', 400);
            }

            $this->usuarioModel->update($usuario['id'], [
                'password' => $this->request->getPost('password_nuevo')
            ]);

            return $this->successResponse('Contraseña actualizada correctamente');
        }

        $data = [
            'title' => 'Cambiar Contraseña'
        ];

        return $this->render('auth/cambiar_password', $data);
    }
}
