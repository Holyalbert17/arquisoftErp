<?php

namespace App\Controllers\Reportes;

use App\Controllers\BaseController;
use App\Models\VentaModel;
use App\Models\VentaDetalleModel;

class VentasController extends BaseController
{
    protected function checkAuth()
    {
        if (!$this->isLoggedIn()) {
            return redirect()->to('/auth/login');
        }
        $this->loadUserData();
        $this->requirePermission('REPORTES_VENTAS', 'ver');
    }

    public function index()
    {
        $this->checkAuth();
        $empresaId = $this->user['empresa_id'] ?? null;
        $sucursalId = $this->sucursal['id'] ?? null;
        
        if (!$empresaId) {
            return redirect()->to('/auth/login');
        }

        $ventaModel = new VentaModel();
        $builder = $ventaModel->where('empresa_id', $empresaId);
        
        if ($sucursalId) {
            $builder->where('sucursal_id', $sucursalId);
        }

        $fechaDesde = $this->request->getGet('fecha_desde') ?? date('Y-m-01');
        $fechaHasta = $this->request->getGet('fecha_hasta') ?? date('Y-m-d');

        $builder->where('fecha_venta >=', $fechaDesde)
                ->where('fecha_venta <=', $fechaHasta . ' 23:59:59');

        $ventas = $builder->orderBy('fecha_venta', 'DESC')->findAll();

        // Calcular estadísticas
        $totalVentas = count($ventas);
        $totalMonto = array_sum(array_column($ventas, 'total'));
        $totalPagado = array_sum(array_column($ventas, 'monto_pagado'));

        $data = [
            'title' => 'Reporte de Ventas',
            'ventas' => $ventas,
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
            'total_ventas' => $totalVentas,
            'total_monto' => $totalMonto,
            'total_pagado' => $totalPagado
        ];

        return $this->render('reportes/ventas/index', $data);
    }
}
