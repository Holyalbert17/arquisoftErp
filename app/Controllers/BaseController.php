<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * BaseController provides a convenient place for loading components
 * and performing functions that are needed by all your controllers.
 */
abstract class BaseController extends Controller
{
    /**
     * An array of helpers to be loaded automatically upon
     * class instantiation. These helpers will be available
     * to all other controllers that extend BaseController.
     *
     * @var list<string>
     */
    protected $helpers = ['form', 'url', 'html', 'number', 'date', 'text', 'currency'];

    /**
     * Session instance
     */
    protected $session;

    /**
     * Current user data
     */
    protected $user;

    /**
     * Current empresa data
     */
    protected $empresa;

    /**
     * Current sucursal data
     */
    protected $sucursal;

    /**
     * @return void
     */
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        // Do Not Edit This Line
        parent::initController($request, $response, $logger);

        // Preload any models, libraries, etc, here.
        $this->session = \Config\Services::session();

        // Load user data if logged in
        if ($this->session->has('user_id')) {
            $this->loadUserData();
        }
    }

    /**
     * Load current user data
     */
    protected function loadUserData()
    {
        try {
            if (!$this->session->has('user_id')) {
                return;
            }

            $userModel = new \App\Models\UsuarioModel();
            $this->user = $userModel->find($this->session->get('user_id'));

            if ($this->user && isset($this->user['empresa_id'])) {
                try {
                    $empresaModel = new \App\Models\EmpresaModel();
                    $this->empresa = $empresaModel->find($this->user['empresa_id']);
                } catch (\Exception $e) {
                    $this->empresa = null;
                }

                if ($this->session->has('sucursal_id')) {
                    try {
                        $sucursalModel = new \App\Models\SucursalModel();
                        $this->sucursal = $sucursalModel->find($this->session->get('sucursal_id'));
                    } catch (\Exception $e) {
                        $this->sucursal = null;
                    }
                }
            }
        } catch (\Exception $e) {
            // Si hay error, simplemente no cargar datos
            $this->user = null;
            $this->empresa = null;
            $this->sucursal = null;
        }
    }

    /**
     * Check if user is logged in
     */
    protected function isLoggedIn()
    {
        if (!$this->session) {
            $this->session = \Config\Services::session();
        }
        // Verificar que la sesión esté inicializada antes de usar has()
        if (!$this->session) {
            return false;
        }
        return $this->session->has('user_id');
    }

    /**
     * Require login
     */
    protected function requireLogin()
    {
        if (!$this->session) {
            $this->session = \Config\Services::session();
        }
        // Verificar que la sesión esté inicializada
        if (!$this->session) {
            return redirect()->to('/auth/login');
        }
        if (!$this->isLoggedIn()) {
            return redirect()->to('/auth/login');
        }
    }

    /**
     * Check authentication and load user data
     */
    protected function checkAuth()
    {
        if (!$this->isLoggedIn()) {
            return redirect()->to('/auth/login');
        }
        $this->loadUserData();
    }

    /**
     * Check permission
     */
    protected function hasPermission($modulo, $accion = 'ver')
    {
        if (!$this->user) {
            return false;
        }

        $permisoModel = new \App\Models\PermisoModel();
        return $permisoModel->hasPermission($this->user['rol_id'], $modulo, $accion);
    }

    /**
     * Require permission
     */
    protected function requirePermission($modulo, $accion = 'ver')
    {
        // Si no hay usuario, no verificar permisos (ya se verifica en checkAuth)
        if (!$this->user) {
            return;
        }

        // Verificar que la empresa tenga el módulo activo (aplica a todos los roles)
        if (!$this->empresaTieneModulo($modulo)) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('El módulo no está habilitado para su empresa');
        }

        // Administradores siempre tienen acceso a módulos habilitados
        if (isset($this->user['rol_id'])) {
            $rolModel = new \App\Models\RolModel();
            $rol = $rolModel->find($this->user['rol_id']);
            if ($rol && $rol['nivel'] <= 3) {
                return; // Administradores tienen acceso total
            }
        }

        // Verificar permiso específico del rol
        if (!$this->hasPermission($modulo, $accion)) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('No tiene permisos para acceder a esta sección');
        }
    }

    /**
     * Verificar si la empresa del usuario tiene un módulo activo con licencia vigente.
     * Retorna true si el módulo está activo o si no hay registros de empresa_modulos (sin restricciones configuradas).
     */
    protected function empresaTieneModulo(string $modulo): bool
    {
        if (!$this->user || empty($this->user['empresa_id'])) {
            return true; // Sin empresa, no restringir
        }

        try {
            $db = \Config\Database::connect();

            // Si la tabla no existe, no restringir
            if (!$db->tableExists('empresa_modulos') || !$db->tableExists('modulos')) {
                return true;
            }

            // Verificar si la empresa tiene ALGÚN módulo asignado
            $totalAsignados = $db->table('empresa_modulos')
                ->where('empresa_id', $this->user['empresa_id'])
                ->countAllResults();

            // Si la empresa no tiene ningún módulo asignado, permitir todo (sin restricciones configuradas)
            if ($totalAsignados === 0) {
                return true;
            }

            // Buscar el módulo específico activo y con licencia vigente
            $resultado = $db->table('empresa_modulos em')
                ->join('modulos m', 'm.id = em.modulo_id')
                ->where('em.empresa_id', $this->user['empresa_id'])
                ->where('m.codigo', $modulo)
                ->where('em.activo', true)
                ->get()
                ->getRowArray();

            if (!$resultado) {
                return false; // Módulo no asignado o inactivo
            }

            // Verificar vigencia de licencia si tiene fecha de vencimiento
            if (!empty($resultado['fecha_fin_licencia'])) {
                if (strtotime($resultado['fecha_fin_licencia']) < time()) {
                    return false; // Licencia vencida
                }
            }

            return true;
        } catch (\Exception $e) {
            log_message('error', 'Error verificando módulo empresa: ' . $e->getMessage());
            return true; // En caso de error, no bloquear
        }
    }

    /**
     * Obtener permisos del usuario actual indexados por módulo
     * Retorna array: ['MODULO' => ['ver'=>bool,'crear'=>bool,'editar'=>bool,'eliminar'=>bool,'anular'=>bool], ...]
     * Para roles nivel <= 3 retorna todos los permisos en true
     */
    protected function getUserPermissions()
    {
        if (!$this->user || !isset($this->user['rol_id'])) {
            return [];
        }

        $rolModel = new \App\Models\RolModel();
        $rol = $rolModel->find($this->user['rol_id']);

        // Roles nivel <= 3 (Super Admin, Admin Empresa, Gerente) tienen acceso total
        if ($rol && (int)($rol['nivel'] ?? 99) <= 3) {
            return ['__full_access' => true, '__rol_nivel' => (int)$rol['nivel']];
        }

        $permisoModel = new \App\Models\PermisoModel();
        $permisos = $permisoModel->where('rol_id', $this->user['rol_id'])->findAll();

        $result = ['__full_access' => false, '__rol_nivel' => (int)($rol['nivel'] ?? 99)];
        foreach ($permisos as $p) {
            $result[$p['modulo']] = [
                'ver' => (bool)$p['ver'],
                'crear' => (bool)$p['crear'],
                'editar' => (bool)$p['editar'],
                'eliminar' => (bool)$p['eliminar'],
                'anular' => (bool)$p['anular'],
            ];
        }
        return $result;
    }

    /**
     * Render view with AdminLTE layout
     */
    protected function render($view, $data = [], $layout = 'adminlte')
    {
        // Solo pasar valores si no están ya definidos en $data
        // Usar array_key_exists() en lugar de isset() para permitir valores null explícitos
        // Esto permite que los controladores sobrescriban estos valores cuando sea necesario
        if (!array_key_exists('user', $data)) {
            $data['user'] = $this->user;
        }
        if (!array_key_exists('empresa', $data)) {
            $data['empresa'] = $this->empresa;
        }
        if (!array_key_exists('sucursal', $data)) {
            $data['sucursal'] = $this->sucursal;
        }
        if (!array_key_exists('userPermissions', $data)) {
            $data['userPermissions'] = $this->getUserPermissions();
        }

        return view($view, $data);
    }

    /**
     * Return JSON response
     */
    protected function jsonResponse($data, $status = 200)
    {
        return $this->response->setJSON($data)->setStatusCode($status);
    }

    /**
     * Return success response
     */
    protected function successResponse($message, $data = [])
    {
        return $this->jsonResponse([
            'success' => true,
            'message' => $message,
            'data' => $data
        ]);
    }

    /**
     * Return error response
     */
    protected function errorResponse($message, $status = 400)
    {
        return $this->jsonResponse([
            'success' => false,
            'message' => $message
        ], $status);
    }

    /**
     * Normalizar número - convierte valores vacíos o inválidos a un valor por defecto
     * Maneja formatos con coma decimal (ej: "1.234,56")
     * Valida que no exceda el máximo permitido para DECIMAL(15,2): 9,999,999,999,999.99
     */
    protected function normalizarNumero($valor, $default = 0, $maxValue = 9999999999999.99)
    {
        if ($valor === null || $valor === '' || $valor === false) {
            return $default;
        }
        $valor = trim((string) $valor);
        if ($valor === '') {
            return $default;
        }

        // Si ya es un número, retornarlo directamente (validando límites)
        if (is_numeric($valor)) {
            $numero = (float) $valor;
            if ($numero > $maxValue) {
                $numero = $maxValue;
            }
            return $numero >= 0 ? $numero : $default;
        }

        // Primero eliminar puntos (separadores de miles)
        $valor = str_replace('.', '', $valor);
        // Reemplazar coma decimal por punto
        $valor = str_replace(',', '.', $valor);
        // Remover cualquier otro caracter no numérico (excepto punto decimal)
        $valor = preg_replace('/[^\d.]/', '', $valor);
        if (is_numeric($valor)) {
            $numero = (float) $valor;
            // Validar que no exceda el máximo permitido
            if ($numero > $maxValue) {
                log_message('warning', "BaseController::normalizarNumero - Valor excede el máximo permitido: {$numero} > {$maxValue}");
                $numero = $maxValue;
            }
            return $numero >= 0 ? $numero : $default;
        }
        return $default;
    }

    /**
     * Normalizar fecha - convierte cadenas vacías a null para campos DATE
     */
    protected function normalizarFecha($valor)
    {
        if ($valor === null || $valor === '' || $valor === false) {
            return null;
        }
        $valor = trim((string) $valor);
        if ($valor === '') {
            return null;
        }
        // Validar que sea una fecha válida
        $timestamp = strtotime($valor);
        if ($timestamp === false) {
            return null;
        }
        return date('Y-m-d', $timestamp);
    }
}
