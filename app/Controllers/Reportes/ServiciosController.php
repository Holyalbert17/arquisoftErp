<?php

namespace App\Controllers\Reportes;

use App\Controllers\BaseController;
use App\Models\OrdenServicioModel;

class ServiciosController extends BaseController
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

        $ordenModel = new OrdenServicioModel();
        $builder = $ordenModel->where('empresa_id', $empresaId);
        
        if ($sucursalId) {
            $builder->where('sucursal_id', $sucursalId);
        }

        $fechaDesde = $this->request->getGet('fecha_desde') ?? date('Y-m-01');
        $fechaHasta = $this->request->getGet('fecha_hasta') ?? date('Y-m-d');

        $builder->where('fecha_creacion >=', $fechaDesde)
                ->where('fecha_creacion <=', $fechaHasta . ' 23:59:59');

        $ordenes = $builder->orderBy('fecha_creacion', 'DESC')->findAll();

        // Calcular estadísticas
        $totalOrdenes = count($ordenes);
        $ordenesPendientes = count(array_filter($ordenes, fn($o) => $o['estado'] == 'PENDIENTE'));
        $ordenesCompletadas = count(array_filter($ordenes, fn($o) => $o['estado'] == 'COMPLETADA'));
        $totalIngresos = array_sum(array_filter(array_column($ordenes, 'total'), fn($v) => $v !== null));

        $data = [
            'title' => 'Reporte de Servicios',
            'ordenes' => $ordenes,
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
            'total_ordenes' => $totalOrdenes,
            'ordenes_pendientes' => $ordenesPendientes,
            'ordenes_completadas' => $ordenesCompletadas,
            'total_ingresos' => $totalIngresos
        ];

        return $this->render('reportes/servicios/index', $data);
    }
}
