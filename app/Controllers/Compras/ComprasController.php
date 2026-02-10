<?php

namespace App\Controllers\Compras;

use App\Controllers\BaseController;
use App\Models\CompraModel;
use App\Models\CompraDetalleModel;
use App\Models\ProveedorModel;
use App\Models\ProductoModel;
use App\Models\StockModel;
use App\Models\MovimientoStockModel;

class ComprasController extends BaseController
{
    protected $compraModel;
    protected $compraDetalleModel;
    protected $proveedorModel;
    protected $productoModel;
    protected $stockModel;
    protected $movimientoStockModel;

    public function __construct()
    {
        $this->compraModel = new CompraModel();
        $this->compraDetalleModel = new CompraDetalleModel();
        $this->proveedorModel = new ProveedorModel();
        $this->productoModel = new ProductoModel();
        $this->stockModel = new StockModel();
        $this->movimientoStockModel = new MovimientoStockModel();
    }

    protected function checkAuth()
    {
        if (!$this->isLoggedIn()) {
            return redirect()->to('/auth/login');
        }
        $this->requirePermission('COMPRAS', 'ver');
    }

    public function index()
    {
        $this->checkAuth();
        $empresaId = $this->user['empresa_id'];
        $sucursalId = $this->sucursal ? $this->sucursal['id'] : null;

        $builder = $this->compraModel->where('empresa_id', $empresaId);
        if ($sucursalId) {
            $builder->where('sucursal_id', $sucursalId);
        }

        $compras = $builder->orderBy('fecha_compra', 'DESC')
            ->limit(100)
            ->findAll();

        // Agregar información de proveedor
        foreach ($compras as &$compra) {
            $compra['proveedor'] = $this->proveedorModel->find($compra['proveedor_id']);
        }

        $data = [
            'title' => 'Compras',
            'compras' => $compras
        ];

        return $this->render('compras/index', $data);
    }

    public function crear()
    {
        $this->checkAuth();
        $empresaId = $this->user['empresa_id'];
        $sucursalId = $this->sucursal ? $this->sucursal['id'] : $this->user['sucursal_id'];

        $data = [
            'title' => 'Nueva Compra',
            'compra' => null,
            'proveedores' => $this->proveedorModel->where('empresa_id', $empresaId)
                ->where('estado', true)
                ->findAll(),
            'productos' => $this->productoModel->where('empresa_id', $empresaId)
                ->where('estado', true)
                ->findAll(),
            'sucursal_id' => $sucursalId
        ];

        return $this->render('compras/form', $data);
    }

    public function guardar()
    {
        $this->checkAuth();
        $rules = [
            'proveedor_id' => 'required|is_natural_no_zero',
            'tipo_compra' => 'required|in_list[LOCAL,IMPORTACION,CONSIGNACION]',
            'condicion_pago' => 'required|in_list[CONTADO,CREDITO,MIXTO]',
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

        // Calcular totales (preparar datos para servicio)
        $subtotal = 0;
        $totalIva = 0;
        $otrosGastos = $this->request->getPost('otros_gastos') ?? 0;

        foreach ($productos as $producto) {
            $precio = $producto['precio'];
            $cantidad = $producto['cantidad'];
            $descuento = $producto['descuento'] ?? 0;

            $subtotalLinea = $precio * $cantidad;
            $descuentoLinea = ($subtotalLinea * $descuento) / 100;
            $subtotalLinea -= $descuentoLinea;

            $subtotal += $subtotalLinea;

            // Calcular IVA (Optimista para cabecera, servicio recalcula preciso por línea)
            $tipoIva = $producto['tipo_iva'] ?? 'GRAVADO_10';
            if ($tipoIva == 'GRAVADO_10') {
                $totalIva += ($subtotalLinea * 10) / 110;
            } elseif ($tipoIva == 'GRAVADO_5') {
                $totalIva += ($subtotalLinea * 5) / 105;
            }
        }

        $total = $subtotal + $totalIva + $otrosGastos;
        $numeroCompra = $this->generarNumeroCompra($empresaId);

        $compraData = [
            'empresa_id' => $empresaId,
            'sucursal_id' => $sucursalId,
            'proveedor_id' => $this->request->getPost('proveedor_id'),
            'usuario_id' => $this->user['id'],
            'numero_compra' => $numeroCompra,
            'numero_factura_proveedor' => $this->request->getPost('numero_factura_proveedor'),
            'timbrado_proveedor' => $this->request->getPost('timbrado_proveedor'),
            'tipo_compra' => $this->request->getPost('tipo_compra'),
            'condicion_pago' => $this->request->getPost('condicion_pago'),
            'moneda' => 'PYG',
            'tipo_cambio' => 1,
            'subtotal' => $subtotal,
            'descuento_monto' => 0,
            'total_iva' => $totalIva,
            'otros_gastos' => $otrosGastos,
            'total' => $total,
            'total_moneda_principal' => $total,
            'monto_pagado' => 0,
            'monto_pendiente' => $total, // Asumimos todo pendiente si es crédito, o gestionarlo según pago. 
            // Legacy code asumía $total.
            'estado' => 'PENDIENTE',
            'dias_credito' => $this->request->getPost('dias_credito') ?? 0,
            'observaciones' => $this->request->getPost('observaciones')
        ];

        $compraService = new \App\Services\CompraService();
        $resultado = $compraService->crearCompra($compraData, $productos, $this->user['id']);

        if ($resultado['success']) {
            return $this->successResponse($resultado['message'], [
                'redirect' => base_url('compras/ver/' . $resultado['id'])
            ]);
        } else {
            return $this->errorResponse($resultado['message']);
        }
    }

    public function recibir($id)
    {
        $this->checkAuth();
        $productos = json_decode($this->request->getPost('productos'), true);

        if (empty($productos)) {
            return $this->errorResponse('Debe especificar las cantidades recibidas');
        }

        $compraService = new \App\Services\CompraService();
        $resultado = $compraService->recibirMercaderia($id, $productos, $this->user['id']);

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

        $compra = $this->compraModel->find($id);

        if (!$compra || $compra['empresa_id'] != $empresaId) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Compra no encontrada');
        }

        // Obtener detalles
        $detalles = $this->compraDetalleModel->where('compra_id', $id)->findAll();

        // Obtener información de proveedor y productos
        $proveedor = $this->proveedorModel->find($compra['proveedor_id']);
        $sucursalModel = new \App\Models\SucursalModel();
        $sucursal = $sucursalModel->find($compra['sucursal_id']);

        foreach ($detalles as &$detalle) {
            $detalle['producto'] = $this->productoModel->find($detalle['producto_id']);
        }

        $data = [
            'title' => 'Ver Compra',
            'compra' => $compra,
            'detalles' => $detalles,
            'proveedor' => $proveedor,
            'sucursal' => $sucursal
        ];

        return $this->render('compras/ver', $data);
    }

    private function generarNumeroCompra($empresaId)
    {
        $ultimo = $this->compraModel->where('empresa_id', $empresaId)
            ->orderBy('id', 'DESC')
            ->first();

        $numero = $ultimo ? ((int) str_replace('COMP-', '', $ultimo['numero_compra'] ?? '0') + 1) : 1;
        return 'COMP-' . str_pad($numero, 8, '0', STR_PAD_LEFT);
    }
}
