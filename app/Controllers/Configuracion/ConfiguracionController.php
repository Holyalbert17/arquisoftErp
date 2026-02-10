<?php

namespace App\Controllers\Configuracion;

use App\Controllers\BaseController;
use App\Models\ConfiguracionModel;
use App\Models\TalonarioModel;
use App\Models\TipoCambioModel;

class ConfiguracionController extends BaseController
{
    protected $configuracionModel;
    protected $talonarioModel;
    protected $tipoCambioModel;

    public function __construct()
    {
        $this->configuracionModel = new ConfiguracionModel();
        $this->talonarioModel = new TalonarioModel();
        $this->tipoCambioModel = new TipoCambioModel();
    }

    protected function checkAuth()
    {
        if (!$this->isLoggedIn()) {
            return redirect()->to('/auth/login');
        }
        $this->loadUserData();
        // Administrador empresa (Dueño) y Gerente: nivel <= 3
        $rolModel = new \App\Models\RolModel();
        $rol = isset($this->user['rol_id']) ? $rolModel->find($this->user['rol_id']) : null;
        if (!$rol || (int) ($rol['nivel'] ?? 99) > 3) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('No tiene permisos para acceder a esta sección');
        }
    }

    public function index()
    {
        $this->checkAuth();
        $empresaId = $this->user['empresa_id'] ?? null;

        if (!$empresaId) {
            return redirect()->to('/auth/login');
        }

        $data = [
            'title' => 'Configuración del Sistema',
            'configuraciones' => $this->obtenerConfiguraciones($empresaId),
            'talonarios' => $this->talonarioModel->where('empresa_id', $empresaId)->findAll(),
            'tipos_cambio' => $this->tipoCambioModel->where('empresa_id', $empresaId)
                ->orderBy('fecha', 'DESC')
                ->limit(10)
                ->findAll()
        ];

        return $this->render('configuracion/index', $data);
    }

    public function guardar()
    {
        $this->checkAuth();
        $empresaId = $this->user['empresa_id'] ?? null;

        if (!$empresaId) {
            return $this->errorResponse('Usuario no tiene empresa asignada', 403);
        }

        $modulo = $this->request->getPost('modulo');
        $configuraciones = $this->request->getPost('configuraciones');

        if (!$modulo || !$configuraciones) {
            return $this->errorResponse('Datos incompletos');
        }

        foreach ($configuraciones as $clave => $valor) {
            $this->configuracionModel->setConfiguracion($empresaId, $modulo, $clave, $valor);
        }

        return $this->successResponse('Configuración guardada exitosamente');
    }

    private function obtenerConfiguraciones($empresaId)
    {
        $configs = $this->configuracionModel->where('empresa_id', $empresaId)->findAll();
        $resultado = [];

        foreach ($configs as $config) {
            $resultado[$config['modulo']][$config['clave']] = $config['valor'];
        }

        return $resultado;
    }
}
