<?php

namespace App\Libraries;

use TCPDF;

class ReportePDF extends TCPDF
{
    protected $empresa;

    public function __construct($empresa = null)
    {
        parent::__construct('P', 'mm', 'A4', true, 'UTF-8', false);
        $this->empresa = $empresa;
        $this->setMargins(15, 20, 15);
        $this->setAutoPageBreak(true, 20);
        $this->setFont('helvetica', '', 10);
    }

    public function generarReporteVentas($empresaId, $sucursalId = null, $fechaDesde = null, $fechaHasta = null)
    {
        $this->AddPage();

        // Encabezado
        $this->SetFont('helvetica', 'B', 16);
        $this->Cell(0, 10, 'REPORTE DE VENTAS', 0, 1, 'C');

        if ($this->empresa) {
            $this->SetFont('helvetica', '', 12);
            $this->Cell(0, 6, $this->empresa['nombre'], 0, 1, 'C');
        }

        $this->Ln(5);

        // Período
        if ($fechaDesde && $fechaHasta) {
            $this->SetFont('helvetica', '', 10);
            $this->Cell(0, 6, 'Período: ' . date('d/m/Y', strtotime($fechaDesde)) . ' - ' . date('d/m/Y', strtotime($fechaHasta)), 0, 1);
        }

        $this->Ln(5);

        // Obtener datos
        $ventaModel = new \App\Models\VentaModel();
        $builder = $ventaModel->where('empresa_id', $empresaId)
            ->where('estado', 'COMPLETADA');

        if ($sucursalId) {
            $builder->where('sucursal_id', $sucursalId);
        }

        if ($fechaDesde) {
            $builder->where('fecha_venta >=', $fechaDesde . ' 00:00:00');
        }

        if ($fechaHasta) {
            $builder->where('fecha_venta <=', $fechaHasta . ' 23:59:59');
        }

        $ventas = $builder->orderBy('fecha_venta', 'DESC')->findAll();

        // Tabla
        $this->SetFont('helvetica', 'B', 9);
        $this->Cell(30, 7, 'Fecha', 1, 0, 'C');
        $this->Cell(40, 7, 'N° Venta', 1, 0, 'C');
        $this->Cell(60, 7, 'Cliente', 1, 0, 'C');
        $this->Cell(50, 7, 'Total', 1, 1, 'R');

        $this->SetFont('helvetica', '', 8);
        $totalGeneral = 0;

        foreach ($ventas as $venta) {
            $clienteModel = new \App\Models\ClienteModel();
            $cliente = $venta['cliente_id'] ? $clienteModel->find($venta['cliente_id']) : null;
            $nombreCliente = $cliente ? $clienteModel->getNombreCompleto($cliente) : 'Walk-in';

            $this->Cell(30, 6, date('d/m/Y', strtotime($venta['fecha_venta'])), 1, 0);
            $this->Cell(40, 6, $venta['numero_venta'], 1, 0);
            $this->Cell(60, 6, substr($nombreCliente, 0, 30), 1, 0);
            $this->Cell(50, 6, number_format($venta['total'], 0, ',', '.'), 1, 1, 'R');

            $totalGeneral += $venta['total'];
        }

        // Total
        $this->SetFont('helvetica', 'B', 10);
        $this->Cell(130, 7, 'TOTAL GENERAL', 1, 0, 'R');
        $this->Cell(50, 7, number_format($totalGeneral, 0, ',', '.'), 1, 1, 'R');

        return $this->Output('reporte_ventas.pdf', 'I');
    }

    public function generarReporteInventario($empresaId, $sucursalId = null)
    {
        $this->AddPage();

        // Encabezado
        $this->SetFont('helvetica', 'B', 16);
        $this->Cell(0, 10, 'REPORTE DE INVENTARIO', 0, 1, 'C');

        if ($this->empresa) {
            $this->SetFont('helvetica', '', 12);
            $this->Cell(0, 6, $this->empresa['nombre'], 0, 1, 'C');
        }

        $this->Ln(5);

        // Obtener datos
        $stockModel = new \App\Models\StockModel();
        $builder = $stockModel->select('stock.*, productos.codigo, productos.nombre, productos.precio_venta')
            ->join('productos', 'productos.id = stock.producto_id')
            ->join('sucursales', 'sucursales.id = stock.sucursal_id')
            ->where('sucursales.empresa_id', $empresaId)
            ->where('productos.controla_stock', true);

        if ($sucursalId) {
            $builder->where('stock.sucursal_id', $sucursalId);
        }

        $stocks = $builder->orderBy('productos.nombre', 'ASC')->findAll();

        // Tabla
        $this->SetFont('helvetica', 'B', 9);
        $this->Cell(30, 7, 'Código', 1, 0, 'C');
        $this->Cell(80, 7, 'Producto', 1, 0, 'C');
        $this->Cell(30, 7, 'Cantidad', 1, 0, 'C');
        $this->Cell(50, 7, 'Valor Total', 1, 1, 'R');

        $this->SetFont('helvetica', '', 8);
        $valorTotal = 0;

        foreach ($stocks as $stock) {
            $valor = $stock['cantidad'] * ($stock['costo_promedio'] ?? 0);
            $valorTotal += $valor;

            $this->Cell(30, 6, $stock['codigo'], 1, 0);
            $this->Cell(80, 6, substr($stock['nombre'], 0, 40), 1, 0);
            $this->Cell(30, 6, number_format($stock['cantidad'], 2), 1, 0, 'R');
            $this->Cell(50, 6, number_format($valor, 0, ',', '.'), 1, 1, 'R');
        }

        // Total
        $this->SetFont('helvetica', 'B', 10);
        $this->Cell(140, 7, 'VALOR TOTAL INVENTARIO', 1, 0, 'R');
        $this->Cell(50, 7, number_format($valorTotal, 0, ',', '.'), 1, 1, 'R');

        return $this->Output('reporte_inventario.pdf', 'I');
    }

    public function generarReporteServicios($empresaId, $sucursalId = null, $fechaDesde = null, $fechaHasta = null)
    {
        $this->AddPage();

        // Encabezado
        $this->SetFont('helvetica', 'B', 16);
        $this->Cell(0, 10, 'REPORTE DE ÓRDENES DE SERVICIO', 0, 1, 'C');

        if ($this->empresa) {
            $this->SetFont('helvetica', '', 12);
            $this->Cell(0, 6, $this->empresa['nombre'], 0, 1, 'C');
        }

        $this->Ln(5);

        // Obtener datos
        $ordenModel = new \App\Models\OrdenServicioModel();
        $builder = $ordenModel->where('empresa_id', $empresaId)
            ->where('estado_activo', true);

        if ($sucursalId) {
            $builder->where('sucursal_id', $sucursalId);
        }

        if ($fechaDesde) {
            $builder->where('fecha_recepcion >=', $fechaDesde . ' 00:00:00');
        }

        if ($fechaHasta) {
            $builder->where('fecha_recepcion <=', $fechaHasta . ' 23:59:59');
        }

        $ordenes = $builder->orderBy('fecha_recepcion', 'DESC')->findAll();

        // Tabla
        $this->SetFont('helvetica', 'B', 9);
        $this->Cell(40, 7, 'N° Orden', 1, 0, 'C');
        $this->Cell(30, 7, 'Fecha', 1, 0, 'C');
        $this->Cell(60, 7, 'Cliente', 1, 0, 'C');
        $this->Cell(40, 7, 'Equipo', 1, 0, 'C');
        $this->Cell(20, 7, 'Total', 1, 1, 'R');

        $this->SetFont('helvetica', '', 8);
        $totalGeneral = 0;

        foreach ($ordenes as $orden) {
            $clienteModel = new \App\Models\ClienteModel();
            $cliente = $clienteModel->find($orden['cliente_id']);
            $nombreCliente = $cliente ? $clienteModel->getNombreCompleto($cliente) : 'N/A';

            $this->Cell(40, 6, $orden['numero_orden'], 1, 0);
            $this->Cell(30, 6, date('d/m/Y', strtotime($orden['fecha_recepcion'])), 1, 0);
            $this->Cell(60, 6, substr($nombreCliente, 0, 25), 1, 0);
            $this->Cell(40, 6, substr($orden['tipo_equipo'], 0, 20), 1, 0);
            $this->Cell(20, 6, number_format($orden['presupuesto_total'] ?? 0, 0, ',', '.'), 1, 1, 'R');

            $totalGeneral += $orden['presupuesto_total'] ?? 0;
        }

        // Total
        $this->SetFont('helvetica', 'B', 10);
        $this->Cell(170, 7, 'TOTAL GENERAL', 1, 0, 'R');
        $this->Cell(20, 7, number_format($totalGeneral, 0, ',', '.'), 1, 1, 'R');

        return $this->Output('reporte_servicios.pdf', 'I');
    }
    public function generarReporteProductosMasVendidos($empresaId, $sucursalId = null, $fechaDesde = null, $fechaHasta = null)
    {
        $this->AddPage();

        $this->SetFont('helvetica', 'B', 16);
        $this->Cell(0, 10, 'PRODUCTOS MÁS VENDIDOS', 0, 1, 'C');

        if ($this->empresa) {
            $this->SetFont('helvetica', '', 12);
            $this->Cell(0, 6, $this->empresa['nombre'], 0, 1, 'C');
        }

        if ($fechaDesde && $fechaHasta) {
            $this->SetFont('helvetica', '', 10);
            $this->Cell(0, 6, 'Período: ' . date('d/m/Y', strtotime($fechaDesde)) . ' - ' . date('d/m/Y', strtotime($fechaHasta)), 0, 1, 'C');
        }
        $this->Ln(5);

        // Obtener datos
        $db = \Config\Database::connect();
        $builder = $db->table('venta_detalles vd')
            ->select('p.codigo, p.nombre, SUM(vd.cantidad) as total_cantidad, SUM(vd.total) as total_monto')
            ->join('ventas v', 'v.id = vd.venta_id')
            ->join('productos p', 'p.id = vd.producto_id')
            ->where('v.empresa_id', $empresaId)
            ->where('v.estado', 'COMPLETADA');

        if ($sucursalId)
            $builder->where('v.sucursal_id', $sucursalId);
        if ($fechaDesde)
            $builder->where('v.fecha_venta >=', $fechaDesde . ' 00:00:00');
        if ($fechaHasta)
            $builder->where('v.fecha_venta <=', $fechaHasta . ' 23:59:59');

        $productos = $builder->groupBy('p.id, p.codigo, p.nombre')
            ->orderBy('total_cantidad', 'DESC')
            ->limit(20)
            ->get()->getResultArray();

        // Tabla
        $this->SetFont('helvetica', 'B', 9);
        $this->Cell(30, 7, 'Código', 1, 0, 'C');
        $this->Cell(80, 7, 'Producto', 1, 0, 'C');
        $this->Cell(30, 7, 'Cantidad', 1, 0, 'C');
        $this->Cell(50, 7, 'Total Vendido', 1, 1, 'R');

        $this->SetFont('helvetica', '', 8);
        foreach ($productos as $prod) {
            $this->Cell(30, 6, $prod['codigo'], 1, 0);
            $this->Cell(80, 6, substr($prod['nombre'], 0, 45), 1, 0);
            $this->Cell(30, 6, number_format($prod['total_cantidad'], 2), 1, 0, 'C');
            $this->Cell(50, 6, number_format($prod['total_monto'], 0, ',', '.'), 1, 1, 'R');
        }

        return $this->Output('productos_mas_vendidos.pdf', 'I');
    }

    public function generarReporteStockBajo($empresaId, $sucursalId = null)
    {
        $this->AddPage();

        $this->SetFont('helvetica', 'B', 16);
        $this->Cell(0, 10, 'REPORTE DE STOCK BAJO', 0, 1, 'C');
        $this->Ln(5);

        // Obtener datos
        $db = \Config\Database::connect();
        $builder = $db->table('stock s')
            ->select('s.cantidad, s.cantidad_disponible, p.codigo, p.nombre, p.stock_minimo, suc.nombre as sucursal')
            ->join('productos p', 'p.id = s.producto_id')
            ->join('sucursales suc', 'suc.id = s.sucursal_id')
            ->where('p.empresa_id', $empresaId)
            ->where('p.controla_stock', true)
            ->where('s.cantidad_disponible <=', 'p.punto_reorden', false); // Uso de false para evitar escape de columna

        if ($sucursalId)
            $builder->where('s.sucursal_id', $sucursalId);

        $items = $builder->orderBy('s.cantidad_disponible', 'ASC')->get()->getResultArray();

        // Tabla
        $this->SetFont('helvetica', 'B', 9);
        $this->Cell(30, 7, 'Código', 1, 0, 'C');
        $this->Cell(60, 7, 'Producto', 1, 0, 'C');
        $this->Cell(40, 7, 'Sucursal', 1, 0, 'C');
        $this->Cell(30, 7, 'Disponible', 1, 0, 'C');
        $this->Cell(30, 7, 'Mínimo', 1, 1, 'C');

        $this->SetFont('helvetica', '', 8);
        foreach ($items as $item) {
            // Resaltar críticos
            if ($item['cantidad_disponible'] <= $item['stock_minimo']) {
                $this->SetTextColor(255, 0, 0);
            } else {
                $this->SetTextColor(0, 0, 0);
            }

            $this->Cell(30, 6, $item['codigo'], 1, 0);
            $this->Cell(60, 6, substr($item['nombre'], 0, 35), 1, 0);
            $this->Cell(40, 6, substr($item['sucursal'], 0, 20), 1, 0);
            $this->Cell(30, 6, number_format($item['cantidad_disponible'], 2), 1, 0, 'C');
            $this->Cell(30, 6, number_format($item['stock_minimo'], 0), 1, 1, 'C');
        }
        // Restaurar color
        $this->SetTextColor(0, 0, 0);

        return $this->Output('stock_bajo.pdf', 'I');
    }

    public function generarReporteMovimientosStock($empresaId, $sucursalId = null, $fechaDesde = null, $fechaHasta = null)
    {
        $this->AddPage();

        $this->SetFont('helvetica', 'B', 16);
        $this->Cell(0, 10, 'MOVIMIENTOS DE STOCK', 0, 1, 'C');
        if ($fechaDesde && $fechaHasta) {
            $this->SetFont('helvetica', '', 10);
            $this->Cell(0, 6, 'Período: ' . date('d/m/Y', strtotime($fechaDesde)) . ' - ' . date('d/m/Y', strtotime($fechaHasta)), 0, 1, 'C');
        }
        $this->Ln(5);

        // Obtener datos
        $db = \Config\Database::connect();
        $builder = $db->table('movimientos_stock m')
            ->select('m.*, p.codigo, p.nombre, u.nombre as usuario')
            ->join('productos p', 'p.id = m.producto_id')
            ->join('usuarios u', 'u.id = m.usuario_id', 'left')
            ->where('m.empresa_id', $empresaId);

        if ($sucursalId)
            $builder->where('m.sucursal_id', $sucursalId);
        if ($fechaDesde)
            $builder->where('m.fecha_movimiento >=', $fechaDesde . ' 00:00:00');
        if ($fechaHasta)
            $builder->where('m.fecha_movimiento <=', $fechaHasta . ' 23:59:59');

        $movimientos = $builder->orderBy('m.fecha_movimiento', 'DESC')->limit(500)->get()->getResultArray();

        // Tabla
        $this->SetFont('helvetica', 'B', 8);
        $this->Cell(30, 7, 'Fecha', 1, 0, 'C');
        $this->Cell(20, 7, 'Tipo', 1, 0, 'C');
        $this->Cell(50, 7, 'Producto', 1, 0, 'C');
        $this->Cell(20, 7, 'Cant.', 1, 0, 'C');
        $this->Cell(20, 7, 'Saldo', 1, 0, 'C');
        $this->Cell(50, 7, 'Detalle', 1, 1, 'C');

        $this->SetFont('helvetica', '', 7);
        foreach ($movimientos as $mov) {
            $this->Cell(30, 6, date('d/m/Y H:i', strtotime($mov['fecha_movimiento'])), 1, 0);
            $this->Cell(20, 6, $mov['tipo_movimiento'], 1, 0);
            $this->Cell(50, 6, substr($mov['nombre'], 0, 30), 1, 0);

            $cantidad = ($mov['tipo_movimiento'] == 'ENTRADA' ? '+' : ($mov['tipo_movimiento'] == 'SALIDA' ? '-' : '')) . number_format($mov['cantidad'], 2);
            $this->Cell(20, 6, $cantidad, 1, 0, 'R');

            $this->Cell(20, 6, number_format($mov['stock_actual'], 2), 1, 0, 'R');
            $this->Cell(50, 6, substr($mov['referencia'] ?? $mov['concepto'], 0, 35), 1, 1);
        }

        return $this->Output('movimientos_stock.pdf', 'I');
    }

    public function generarReporteOrdenesPendientes($empresaId, $sucursalId = null)
    {
        $this->AddPage();

        $this->SetFont('helvetica', 'B', 16);
        $this->Cell(0, 10, 'ÓRDENES DE SERVICIO PENDIENTES', 0, 1, 'C');
        $this->Ln(5);

        // Obtener datos
        $ordenModel = new \App\Models\OrdenServicioModel();
        $builder = $ordenModel->where('empresa_id', $empresaId)
            ->where('estado !=', 'COMPLETADA')
            ->where('estado !=', 'ENTREGADA')
            ->where('estado !=', 'CANCELADA');

        if ($sucursalId)
            $builder->where('sucursal_id', $sucursalId);

        $ordenes = $builder->orderBy('fecha_recepcion', 'ASC')->findAll();

        // Tabla
        $this->SetFont('helvetica', 'B', 9);
        $this->Cell(30, 7, 'N° Orden', 1, 0, 'C');
        $this->Cell(25, 7, 'Fecha', 1, 0, 'C');
        $this->Cell(50, 7, 'Cliente', 1, 0, 'C');
        $this->Cell(40, 7, 'Equipo', 1, 0, 'C');
        $this->Cell(25, 7, 'Estado', 1, 0, 'C');
        $this->Cell(20, 7, 'Total', 1, 1, 'R');

        $this->SetFont('helvetica', '', 8);
        foreach ($ordenes as $orden) {
            $clienteModel = new \App\Models\ClienteModel();
            $cliente = $clienteModel->find($orden['cliente_id']);
            $nombreCliente = $cliente ? $clienteModel->getNombreCompleto($cliente) : 'N/A';

            $this->Cell(30, 6, $orden['numero_orden'], 1, 0);
            $this->Cell(25, 6, date('d/m/Y', strtotime($orden['fecha_recepcion'])), 1, 0);
            $this->Cell(50, 6, substr($nombreCliente, 0, 25), 1, 0);
            $this->Cell(40, 6, substr($orden['tipo_equipo'], 0, 20), 1, 0);
            $this->Cell(25, 6, $orden['estado'], 1, 0);
            $this->Cell(20, 6, number_format($orden['presupuesto_total'] ?? 0, 0, ',', '.'), 1, 1, 'R');
        }

        return $this->Output('ordenes_pendientes.pdf', 'I');
    }
}
