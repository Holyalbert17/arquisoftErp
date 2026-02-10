<?php

namespace App\Controllers\Inventario;

use App\Controllers\BaseController;
use App\Models\AjusteInventarioModel;
use App\Models\AjusteInventarioDetalleModel;
use App\Models\ProductoModel;
use App\Models\StockModel;
use App\Models\MovimientoStockModel;

class AjustesController extends BaseController
{
    protected $ajusteModel;
    protected $ajusteDetalleModel;
    protected $productoModel;
    protected $stockModel;
    protected $movimientoStockModel;

    public function __construct()
    {
        $this->ajusteModel = new AjusteInventarioModel();
        $this->ajusteDetalleModel = new AjusteInventarioDetalleModel();
        $this->productoModel = new ProductoModel();
        $this->stockModel = new StockModel();
        $this->movimientoStockModel = new MovimientoStockModel();
    }

    protected function checkAuth()
    {
        if (!$this->isLoggedIn()) {
            return redirect()->to('/auth/login');
        }
        $this->requirePermission('INVENTARIO', 'editar');
    }

    public function index()
    {
        $this->checkAuth();
        $empresaId = $this->user['empresa_id'] ?? null;

        if (!$empresaId) {
            return redirect()->to('/auth/login');
        }

        $sucursalId = $this->sucursal['id'] ?? null;

        if (!$sucursalId && $this->session->has('sucursal_id')) {
            $sucursalId = $this->session->get('sucursal_id');
        }

        $builder = $this->ajusteModel->where('empresa_id', $empresaId);
        if ($sucursalId) {
            $builder->where('sucursal_id', $sucursalId);
        }

        $ajustes = $builder->orderBy('fecha_solicitud', 'DESC')
            ->limit(100)
            ->findAll();

        $data = [
            'title' => 'Ajustes de Inventario',
            'ajustes' => $ajustes
        ];

        return $this->render('inventario/ajustes/index', $data);
    }

    public function crear()
    {
        $this->checkAuth();
        $empresaId = $this->user['empresa_id'] ?? null;

        if (!$empresaId) {
            return redirect()->to('/auth/login');
        }

        $sucursalId = $this->sucursal['id'] ?? null;

        if (!$sucursalId && $this->session->has('sucursal_id')) {
            $sucursalId = $this->session->get('sucursal_id');
        }

        if (!$sucursalId && isset($this->user['sucursal_id'])) {
            $sucursalId = $this->user['sucursal_id'];
        }

        $data = [
            'title' => 'Nuevo Ajuste de Inventario',
            'sucursal_id' => $sucursalId,
            'productos' => []
        ];

        return $this->render('inventario/ajustes/form', $data);
    }

    public function guardar()
    {
        $this->checkAuth();
        $empresaId = $this->user['empresa_id'] ?? null;

        if (!$empresaId) {
            return $this->errorResponse('Usuario no tiene empresa asignada', 403);
        }

        $rules = [
            'tipo_ajuste' => 'required|in_list[ENTRADA,SALIDA,FISICO,MERMA]',
            'motivo' => 'required|min_length[5]',
            'productos' => 'required'
        ];

        if (!$this->validate($rules)) {
            return $this->errorResponse('Error de validación: ' . implode(', ', $this->validator->getErrors()));
        }

        $empresaId = $this->user['empresa_id'];
        $sucursalId = $this->sucursal ? $this->sucursal['id'] : $this->user['sucursal_id'];
        $productos = json_decode($this->request->getPost('productos'), true);

        if (empty($productos)) {
            return $this->errorResponse('Debe agregar al menos un producto');
        }

        // Generar número de ajuste
        $numeroAjuste = $this->generarNumeroAjuste($empresaId);

        $db = \Config\Database::connect();
        $db->transStart();

        try {
            // Crear ajuste
            $ajusteData = [
                'empresa_id' => $empresaId,
                'sucursal_id' => $sucursalId,
                'numero_ajuste' => $numeroAjuste,
                'tipo_ajuste' => $this->request->getPost('tipo_ajuste'),
                'motivo' => $this->request->getPost('motivo'),
                'observaciones' => $this->request->getPost('observaciones'),
                'estado' => 'PENDIENTE',
                'usuario_solicita' => $this->user['id']
            ];

            $this->ajusteModel->insert($ajusteData);
            $ajusteId = $this->ajusteModel->getInsertID();

            // Crear detalles
            foreach ($productos as $producto) {
                // Validar que el producto existe
                $productoData = $this->productoModel->find($producto['id']);
                if (!$productoData || $productoData['empresa_id'] != $empresaId) {
                    throw new \Exception("Producto con ID {$producto['id']} no encontrado o no pertenece a la empresa");
                }

                $stock = $this->stockModel->where('producto_id', $producto['id'])
                    ->where('sucursal_id', $sucursalId)
                    ->first();

                $cantidadExistente = $stock ? floatval($stock['cantidad']) : 0;
                $cantidadNueva = $this->normalizarNumero($producto['cantidad_nueva'] ?? 0);

                if ($cantidadNueva < 0) {
                    throw new \Exception("La cantidad nueva no puede ser negativa para el producto: {$productoData['nombre']}");
                }

                $diferencia = $cantidadNueva - $cantidadExistente;

                $this->ajusteDetalleModel->insert([
                    'ajuste_id' => $ajusteId,
                    'producto_id' => $producto['id'],
                    'cantidad_existente' => $cantidadExistente,
                    'cantidad_nueva' => $cantidadNueva,
                    'diferencia' => $diferencia,
                    'costo_unitario' => $stock ? floatval($stock['costo_promedio'] ?? 0) : 0,
                    'observaciones' => $producto['observaciones'] ?? null
                ]);
            }

            $db->transComplete();

            if ($db->transStatus() === false) {
                return $this->errorResponse('Error al crear el ajuste');
            }

            return $this->successResponse('Ajuste creado exitosamente', [
                'redirect' => base_url('inventario/ajustes/ver/' . $ajusteId)
            ]);

        } catch (\Exception $e) {
            $db->transRollback();
            return $this->errorResponse('Error: ' . $e->getMessage());
        }
    }

    public function aprobar($id)
    {
        $this->checkAuth();
        $empresaId = $this->user['empresa_id'] ?? null;

        if (!$empresaId) {
            return $this->errorResponse('Usuario sin empresa asignada', 403);
        }

        $inventarioService = new \App\Services\InventarioService();

        $resultado = $inventarioService->aprobarAjuste($id, $this->user['id'], $empresaId);

        if ($resultado['success']) {
            return $this->successResponse($resultado['message']);
        } else {
            return $this->errorResponse($resultado['message']);
        }
    }

    public function ver($id)
    {
        $this->checkAuth();
        $empresaId = $this->user['empresa_id'] ?? null;

        if (!$empresaId) {
            return redirect()->to('/auth/login');
        }

        $ajuste = $this->ajusteModel->find($id);

        if (!$ajuste || $ajuste['empresa_id'] != $empresaId) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Ajuste no encontrado');
        }

        // Obtener detalles
        $detalles = $this->ajusteDetalleModel->where('ajuste_id', $id)->findAll();

        // Obtener información de productos y sucursal
        $sucursalModel = new \App\Models\SucursalModel();
        $sucursal = $sucursalModel->find($ajuste['sucursal_id']);

        foreach ($detalles as &$detalle) {
            $detalle['producto'] = $this->productoModel->find($detalle['producto_id']);
        }

        $data = [
            'title' => 'Ver Ajuste de Inventario',
            'ajuste' => $ajuste,
            'detalles' => $detalles,
            'sucursal' => $sucursal
        ];

        return $this->render('inventario/ajustes/ver', $data);
    }

    private function generarNumeroAjuste($empresaId)
    {
        $ultimo = $this->ajusteModel->where('empresa_id', $empresaId)
            ->orderBy('id', 'DESC')
            ->first();

        $numero = $ultimo ? ((int) str_replace('AJUS-', '', $ultimo['numero_ajuste'] ?? '0') + 1) : 1;
        return 'AJUS-' . str_pad($numero, 8, '0', STR_PAD_LEFT);
    }
}
