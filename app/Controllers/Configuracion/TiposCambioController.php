<?php

namespace App\Controllers\Configuracion;

use App\Controllers\BaseController;
use App\Models\TipoCambioModel;

class TiposCambioController extends BaseController
{
    protected $tipoCambioModel;

    public function __construct()
    {
        $this->tipoCambioModel = new TipoCambioModel();
    }

    protected function checkAuth()
    {
        if (!$this->isLoggedIn()) {
            return redirect()->to('/auth/login');
        }
        $this->loadUserData();
        $rolModel = new \App\Models\RolModel();
        $rol = isset($this->user['rol_id']) ? $rolModel->find($this->user['rol_id']) : null;
        if (!$rol || (int) ($rol['nivel'] ?? 99) > 3) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('No tiene permisos');
        }
    }

    public function index()
    {
        $this->checkAuth();
        $empresaId = $this->user['empresa_id'] ?? null;

        if (!$empresaId) {
            return redirect()->to('/auth/login');
        }

        $tiposCambio = $this->tipoCambioModel->where('empresa_id', $empresaId)
            ->orderBy('fecha', 'DESC')
            ->limit(100)
            ->findAll();

        $data = [
            'title' => 'Tipos de Cambio',
            'tipos_cambio' => $tiposCambio
        ];

        return $this->render('configuracion/tipos_cambio/index', $data);
    }

    public function crear()
    {
        $this->checkAuth();
        $empresaId = $this->user['empresa_id'] ?? null;

        if (!$empresaId) {
            return redirect()->to('/auth/login');
        }

        $data = [
            'title' => 'Nuevo Tipo de Cambio',
            'tipo_cambio' => null
        ];

        return $this->render('configuracion/tipos_cambio/form', $data);
    }

    public function guardar()
    {
        $this->checkAuth();
        $empresaId = $this->user['empresa_id'] ?? null;

        if (!$empresaId) {
            return $this->errorResponse('Usuario no tiene empresa asignada', 403);
        }

        $rules = [
            'fecha' => 'required|valid_date',
            'moneda_destino' => 'required|in_list[USD,BRL]',
            'compra' => 'required|numeric|greater_than[0]',
            'venta' => 'required|numeric|greater_than[0]',
            'promedio' => 'required|numeric|greater_than[0]'
        ];

        if (!$this->validate($rules)) {
            return $this->errorResponse('Error de validación: ' . implode(', ', $this->validator->getErrors()));
        }

        try {
            // Verificar si ya existe para esta fecha y moneda
            $existe = $this->tipoCambioModel->where('empresa_id', $empresaId)
                ->where('fecha', $this->request->getPost('fecha'))
                ->where('moneda_destino', $this->request->getPost('moneda_destino'))
                ->first();

            if ($existe) {
                return $this->errorResponse('Ya existe un tipo de cambio para esta fecha y moneda');
            }

            $tipoCambioData = [
                'empresa_id' => $empresaId,
                'fecha' => $this->request->getPost('fecha'),
                'moneda_origen' => 'PYG',
                'moneda_destino' => $this->request->getPost('moneda_destino'),
                'compra' => (float) $this->request->getPost('compra'),
                'venta' => (float) $this->request->getPost('venta'),
                'promedio' => (float) $this->request->getPost('promedio'),
                'fuente' => $this->request->getPost('fuente') ?? 'MANUAL',
                'observaciones' => $this->request->getPost('observaciones'),
                'usuario_creacion' => $this->user['id']
            ];

            $this->tipoCambioModel->insert($tipoCambioData);

            return $this->successResponse('Tipo de cambio registrado exitosamente', [
                'redirect' => base_url('configuracion/tipos-cambio')
            ]);

        } catch (\Exception $e) {
            return $this->errorResponse('Error: ' . $e->getMessage());
        }
    }

    public function obtenerTipoCambio()
    {
        $this->checkAuth();
        $empresaId = $this->user['empresa_id'] ?? null;
        $moneda = $this->request->getPost('moneda') ?? 'USD';
        $fecha = $this->request->getPost('fecha') ?? date('Y-m-d');

        if (!$empresaId) {
            return $this->errorResponse('Usuario no tiene empresa asignada', 403);
        }

        $tipoCambio = $this->tipoCambioModel->where('empresa_id', $empresaId)
            ->where('moneda_destino', $moneda)
            ->where('fecha', $fecha)
            ->first();

        if (!$tipoCambio) {
            // Buscar el más reciente
            $tipoCambio = $this->tipoCambioModel->where('empresa_id', $empresaId)
                ->where('moneda_destino', $moneda)
                ->where('fecha <=', $fecha)
                ->orderBy('fecha', 'DESC')
                ->first();
        }

        if (!$tipoCambio) {
            return $this->errorResponse('No se encontró tipo de cambio para esta moneda');
        }

        return $this->successResponse('Tipo de cambio obtenido', [
            'compra' => $tipoCambio['compra'],
            'venta' => $tipoCambio['venta'],
            'promedio' => $tipoCambio['promedio'],
            'fecha' => $tipoCambio['fecha']
        ]);
    }

    public function editar($id)
    {
        $this->checkAuth();
        $empresaId = $this->user['empresa_id'] ?? null;

        if (!$empresaId) {
            return redirect()->to('/auth/login');
        }

        $tipoCambio = $this->tipoCambioModel->find($id);

        if (!$tipoCambio || $tipoCambio['empresa_id'] != $empresaId) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Tipo de cambio no encontrado');
        }

        $data = [
            'title' => 'Editar Tipo de Cambio',
            'tipo_cambio' => $tipoCambio
        ];

        return $this->render('configuracion/tipos_cambio/form', $data);
    }

    public function actualizar($id)
    {
        $this->checkAuth();
        $empresaId = $this->user['empresa_id'] ?? null;

        if (!$empresaId) {
            return $this->errorResponse('Usuario no tiene empresa asignada', 403);
        }

        $tipoCambio = $this->tipoCambioModel->find($id);

        if (!$tipoCambio || $tipoCambio['empresa_id'] != $empresaId) {
            return $this->errorResponse('Tipo de cambio no encontrado', 404);
        }

        $rules = [
            'fecha' => 'required|valid_date',
            'moneda_destino' => 'required|in_list[USD,BRL]',
            'compra' => 'required|numeric|greater_than[0]',
            'venta' => 'required|numeric|greater_than[0]',
            'promedio' => 'required|numeric|greater_than[0]'
        ];

        if (!$this->validate($rules)) {
            return $this->errorResponse('Error de validación: ' . implode(', ', $this->validator->getErrors()));
        }

        try {
            // Verificar si ya existe para esta fecha y moneda (excepto el actual)
            $fecha = $this->request->getPost('fecha');
            $monedaDestino = $this->request->getPost('moneda_destino');

            $existe = $this->tipoCambioModel->where('empresa_id', $empresaId)
                ->where('fecha', $fecha)
                ->where('moneda_destino', $monedaDestino)
                ->where('id !=', $id)
                ->first();

            if ($existe) {
                return $this->errorResponse('Ya existe un tipo de cambio para esta fecha y moneda');
            }

            $tipoCambioData = [
                'fecha' => $fecha,
                'moneda_destino' => $monedaDestino,
                'compra' => (float) $this->request->getPost('compra'),
                'venta' => (float) $this->request->getPost('venta'),
                'promedio' => (float) $this->request->getPost('promedio'),
                'fuente' => $this->request->getPost('fuente') ?? 'MANUAL',
                'observaciones' => $this->request->getPost('observaciones')
            ];

            $this->tipoCambioModel->update($id, $tipoCambioData);

            return $this->successResponse('Tipo de cambio actualizado exitosamente', [
                'redirect' => base_url('configuracion/tipos-cambio')
            ]);

        } catch (\Exception $e) {
            return $this->errorResponse('Error: ' . $e->getMessage());
        }
    }

    public function eliminar($id)
    {
        $this->checkAuth();
        $empresaId = $this->user['empresa_id'] ?? null;

        if (!$empresaId) {
            return $this->errorResponse('Usuario no tiene empresa asignada', 403);
        }

        $tipoCambio = $this->tipoCambioModel->find($id);

        if (!$tipoCambio || $tipoCambio['empresa_id'] != $empresaId) {
            return $this->errorResponse('Tipo de cambio no encontrado', 404);
        }

        if ($this->tipoCambioModel->delete($id)) {
            return $this->successResponse('Tipo de cambio eliminado exitosamente');
        }

        return $this->errorResponse('Error al eliminar el tipo de cambio');
    }
}
