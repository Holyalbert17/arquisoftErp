<?php

namespace App\Controllers\Reportes;

use App\Controllers\BaseController;
use App\Libraries\ReportePDF;

class ReportesController extends BaseController
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

        $data = [
            'title' => 'Reportes del Sistema'
        ];

        return $this->render('reportes/index', $data);
    }

    public function generar()
    {
        $this->checkAuth();
        $tipo = $this->request->getPost('tipo_reporte');
        $fechaDesde = $this->request->getPost('fecha_desde');
        $fechaHasta = $this->request->getPost('fecha_hasta');
        $formato = $this->request->getPost('formato') ?? 'PDF';

        $empresaId = $this->user['empresa_id'] ?? null;
        $sucursalId = $this->sucursal['id'] ?? null;

        if (!$empresaId) {
            return $this->errorResponse('Usuario no tiene empresa asignada', 403);
        }

        try {
            $reportePDF = new ReportePDF($this->empresa);

            switch ($tipo) {
                case 'VENTAS':
                case 'VENTAS_DIARIAS':
                case 'VENTAS_MENSUALES':
                    return $reportePDF->generarReporteVentas($empresaId, $sucursalId, $fechaDesde, $fechaHasta);

                case 'PRODUCTOS_MAS_VENDIDOS':
                    return $reportePDF->generarReporteProductosMasVendidos($empresaId, $sucursalId, $fechaDesde, $fechaHasta);

                case 'INVENTARIO':
                    return $reportePDF->generarReporteInventario($empresaId, $sucursalId);

                case 'STOCK_BAJO':
                    return $reportePDF->generarReporteStockBajo($empresaId, $sucursalId);

                case 'MOVIMIENTOS_STOCK':
                    return $reportePDF->generarReporteMovimientosStock($empresaId, $sucursalId, $fechaDesde, $fechaHasta);

                case 'SERVICIOS':
                    return $reportePDF->generarReporteServicios($empresaId, $sucursalId, $fechaDesde, $fechaHasta);

                case 'ORDENES_PENDIENTES':
                case 'ORDENES_COMPLETADAS':
                    return $reportePDF->generarReporteOrdenesPendientes($empresaId, $sucursalId);

                default:
                    return $this->errorResponse('Tipo de reporte no válido');
            }
        } catch (\Exception $e) {
            return $this->errorResponse('Error al generar el reporte: ' . $e->getMessage());
        }
    }
}
