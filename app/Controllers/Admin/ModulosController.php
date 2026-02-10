<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\ModuloModel;
use App\Models\EmpresaModuloModel;
use App\Models\SucursalModuloModel;

class ModulosController extends BaseController
{
    protected $moduloModel;
    protected $empresaModuloModel;
    protected $sucursalModuloModel;

    public function __construct()
    {
        $this->moduloModel = new ModuloModel();
        $this->empresaModuloModel = new EmpresaModuloModel();
        $this->sucursalModuloModel = new SucursalModuloModel();
    }

    protected $esSuperAdmin = false;

    protected function checkAuth()
    {
        if (!$this->isLoggedIn()) {
            return redirect()->to('/auth/login');
        }
        $this->loadUserData();
        $rolModel = new \App\Models\RolModel();
        $rol = isset($this->user['rol_id']) ? $rolModel->find($this->user['rol_id']) : null;
        if (!$rol || (int) ($rol['nivel'] ?? 99) > 3) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('No tiene permisos para acceder');
        }
        $this->esSuperAdmin = ((int)($rol['nivel'] ?? 99) === 1);
    }

    public function index()
    {
        $this->checkAuth();

        $empresaModel = new \App\Models\EmpresaModel();
        $sucursalModel = new \App\Models\SucursalModel();

        // Super Admin: ver todas las empresas; otros: solo la suya
        if ($this->esSuperAdmin) {
            $empresas = $empresaModel->where('estado', true)->orderBy('nombre', 'ASC')->findAll();
        } else {
            $empresas = [$empresaModel->find($this->user['empresa_id'])];
        }

        // Empresa seleccionada (por GET param o la primera)
        $empresaIdSeleccionada = $this->request->getGet('empresa_id');
        if (!$empresaIdSeleccionada || (!$this->esSuperAdmin && $empresaIdSeleccionada != $this->user['empresa_id'])) {
            $empresaIdSeleccionada = $this->esSuperAdmin ? ($empresas[0]['id'] ?? $this->user['empresa_id']) : $this->user['empresa_id'];
        }

        $empresaActual = $empresaModel->find($empresaIdSeleccionada);

        // Obtener módulos disponibles
        $modulos = $this->moduloModel->where('estado', true)
            ->orderBy('categoria', 'ASC')
            ->orderBy('orden', 'ASC')
            ->findAll();

        // Obtener módulos asignados a la empresa seleccionada
        $modulosEmpresa = $this->empresaModuloModel
            ->select('empresa_modulos.*, modulos.nombre, modulos.codigo, modulos.categoria, modulos.descripcion as modulo_descripcion, modulos.estado as modulo_estado')
            ->join('modulos', 'modulos.id = empresa_modulos.modulo_id')
            ->where('empresa_modulos.empresa_id', $empresaIdSeleccionada)
            ->findAll();

        // Obtener sucursales de la empresa seleccionada
        $sucursales = $sucursalModel->where('empresa_id', $empresaIdSeleccionada)
            ->where('estado', true)
            ->findAll();

        // Obtener módulos por sucursal
        $modulosPorSucursal = [];
        foreach ($sucursales as $suc) {
            $modulosPorSucursal[$suc['id']] = $this->sucursalModuloModel
                ->select('sucursal_modulos.*, modulos.nombre, modulos.codigo')
                ->join('modulos', 'modulos.id = sucursal_modulos.modulo_id')
                ->where('sucursal_modulos.sucursal_id', $suc['id'])
                ->where('sucursal_modulos.activo', true)
                ->findAll();
        }

        $data = [
            'title' => 'Gestión de Módulos y Licencias',
            'empresa' => $empresaActual,
            'empresas' => $empresas,
            'empresaIdSeleccionada' => $empresaIdSeleccionada,
            'esSuperAdmin' => $this->esSuperAdmin,
            'modulos' => $modulos,
            'modulosEmpresa' => $modulosEmpresa,
            'sucursales' => $sucursales,
            'modulosPorSucursal' => $modulosPorSucursal
        ];

        return $this->render('admin/modulos/index', $data);
    }

    /**
     * Extraer mensaje limpio de errores PostgreSQL
     */
    private function limpiarErrorPG(string $mensaje): string
    {
        // Buscar el mensaje ERROR: ... antes de CONTEXT:
        if (preg_match('/ERROR:\s*(.+?)(?:\nCONTEXT:|$)/s', $mensaje, $matches)) {
            return trim($matches[1]);
        }
        // Fallback: quitar prefijo "pg_query()..."
        if (preg_match('/ERROR:\s*(.+)/s', $mensaje, $matches)) {
            return trim(explode("\n", $matches[1])[0]);
        }
        return $mensaje;
    }

    public function asignarEmpresa()
    {
        $this->checkAuth();
        // Super Admin puede asignar a cualquier empresa
        $empresaId = $this->request->getPost('empresa_id');
        if (!$empresaId || (!$this->esSuperAdmin && $empresaId != $this->user['empresa_id'])) {
            $empresaId = $this->user['empresa_id'];
        }
        $moduloId = $this->request->getPost('modulo_id');
        $tipoLicencia = $this->request->getPost('tipo_licencia') ?? 'MENSUAL';
        $diasVigencia = $this->request->getPost('dias_vigencia') ?? 30;

        if (!$moduloId) {
            return $this->errorResponse('Módulo no especificado');
        }

        $db = \Config\Database::connect();

        try {
            $result = $db->query("SELECT asignar_modulo_empresa(?, ?, ?, ?, ?) as id", [
                $empresaId,
                $moduloId,
                $tipoLicencia,
                $diasVigencia,
                $this->user['id']
            ]);

            return $this->successResponse('Módulo asignado exitosamente');

        } catch (\Exception $e) {
            return $this->errorResponse($this->limpiarErrorPG($e->getMessage()));
        }
    }

    public function asignarSucursal()
    {
        $this->checkAuth();
        $sucursalId = $this->request->getPost('sucursal_id');
        $moduloId = $this->request->getPost('modulo_id');

        if (!$sucursalId || !$moduloId) {
            return $this->errorResponse('Parámetros incompletos');
        }

        $db = \Config\Database::connect();

        try {
            // Usar función de base de datos
            $result = $db->query("SELECT asignar_modulo_sucursal(?, ?, ?) as id", [
                $sucursalId,
                $moduloId,
                $this->user['id']
            ]);

            return $this->successResponse('Módulo asignado a sucursal exitosamente');

        } catch (\Exception $e) {
            return $this->errorResponse($this->limpiarErrorPG($e->getMessage()));
        }
    }

    public function desactivar($id)
    {
        $this->checkAuth();

        $empresaModulo = $this->empresaModuloModel->find($id);

        if (!$empresaModulo) {
            return $this->errorResponse('Módulo no encontrado', 404);
        }
        // Solo Super Admin puede desactivar de otra empresa
        if (!$this->esSuperAdmin && $empresaModulo['empresa_id'] != $this->user['empresa_id']) {
            return $this->errorResponse('No tiene permisos', 403);
        }

        $this->empresaModuloModel->update($id, [
            'activo' => false,
            'fecha_modificacion' => date('Y-m-d H:i:s')
        ]);

        return $this->successResponse('Módulo desactivado exitosamente');
    }

    public function renovar($id)
    {
        $this->checkAuth();
        $tipoLicencia = $this->request->getPost('tipo_licencia') ?? 'MENSUAL';
        $diasVigencia = $this->request->getPost('dias_vigencia') ?? 30;

        $db = \Config\Database::connect();

        try {
            $result = $db->query("SELECT renovar_licencia_modulo(?, ?, ?) as renovado", [
                $id,
                $tipoLicencia,
                $diasVigencia
            ]);

            return $this->successResponse('Licencia renovada exitosamente');

        } catch (\Exception $e) {
            return $this->errorResponse($this->limpiarErrorPG($e->getMessage()));
        }
    }
}
