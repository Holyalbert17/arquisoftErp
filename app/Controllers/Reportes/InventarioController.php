<?php

namespace App\Controllers\Reportes;

use App\Controllers\BaseController;
use App\Models\ProductoModel;
use App\Models\StockModel;
use App\Models\MovimientoStockModel;
use App\Models\SucursalModel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class InventarioController extends BaseController
{
    protected $productoModel;
    protected $stockModel;
    protected $movimientoModel;
    protected $sucursalModel;

    public function __construct()
    {
        $this->productoModel = new ProductoModel();
        $this->stockModel = new StockModel();
        $this->movimientoModel = new MovimientoStockModel();
        $this->sucursalModel = new SucursalModel();
    }

    protected function checkAuth()
    {
        if (!$this->isLoggedIn()) {
            return redirect()->to('/auth/login');
        }
        $this->loadUserData();
        $this->requirePermission('REPORTES_INVENTARIO', 'ver');
    }

    public function index()
    {
        $this->checkAuth();
        $empresaId = $this->user['empresa_id'] ?? null;
        $sucursalId = $this->sucursal['id'] ?? null;

        if (!$empresaId) {
            return redirect()->to('/auth/login');
        }

        $db = \Config\Database::connect();

        // Obtener stock con valorización
        $builder = $db->table('stock s')
            ->select('s.*, p.codigo, p.nombre as producto_nombre, p.precio_compra, p.precio_venta,
                              p.stock_minimo, p.punto_reorden, p.controla_stock,
                              c.nombre as categoria_nombre, m.nombre as marca_nombre,
                              suc.nombre as sucursal_nombre,
                              (s.cantidad * COALESCE(s.costo_promedio, p.precio_compra, 0)) as valor_costo,
                              (s.cantidad * p.precio_venta) as valor_venta')
            ->join('productos p', 'p.id = s.producto_id', 'inner')
            ->join('sucursales suc', 'suc.id = s.sucursal_id', 'inner')
            ->join('categorias c', 'c.id = p.categoria_id', 'left')
            ->join('marcas m', 'm.id = p.marca_id', 'left')
            ->where('p.empresa_id', $empresaId)
            ->where('p.estado', true);

        if ($sucursalId) {
            $builder->where('s.sucursal_id', $sucursalId);
        }

        $productosConStock = $builder->orderBy('p.nombre', 'ASC')
            ->get()
            ->getResultArray();

        // Calcular estadísticas
        $totalValorizacionCosto = 0;
        $totalValorizacionVenta = 0;
        $productosStockBajo = 0;
        $productosStockCritico = 0;

        foreach ($productosConStock as $item) {
            $totalValorizacionCosto += floatval($item['valor_costo'] ?? 0);
            $totalValorizacionVenta += floatval($item['valor_venta'] ?? 0);

            if ($item['controla_stock']) {
                if ($item['cantidad_disponible'] <= $item['stock_minimo']) {
                    $productosStockCritico++;
                } elseif ($item['cantidad_disponible'] <= $item['punto_reorden']) {
                    $productosStockBajo++;
                }
            }
        }

        // Obtener sucursales para filtro
        $sucursales = $this->sucursalModel->where('empresa_id', $empresaId)
            ->where('estado', true)
            ->findAll();

        $data = [
            'title' => 'Reporte de Inventario',
            'productos' => $productosConStock,
            'total_productos' => count($productosConStock),
            'total_valorizacion_costo' => $totalValorizacionCosto,
            'total_valorizacion_venta' => $totalValorizacionVenta,
            'utilidad_potencial' => $totalValorizacionVenta - $totalValorizacionCosto,
            'productos_stock_bajo' => $productosStockBajo,
            'productos_stock_critico' => $productosStockCritico,
            'sucursales' => $sucursales,
            'sucursal_actual' => $sucursalId
        ];

        return $this->render('reportes/inventario/index', $data);
    }

    /**
     * Reporte de stock bajo
     */
    public function stockBajo()
    {
        $this->checkAuth();
        $empresaId = $this->user['empresa_id'] ?? null;
        $sucursalId = $this->sucursal['id'] ?? null;

        if (!$empresaId) {
            return redirect()->to('/auth/login');
        }

        $db = \Config\Database::connect();

        $builder = $db->table('stock s')
            ->select('s.*, p.codigo, p.nombre as producto_nombre, p.precio_compra, p.precio_venta,
                              p.stock_minimo, p.punto_reorden, p.controla_stock,
                              c.nombre as categoria_nombre, m.nombre as marca_nombre,
                              suc.nombre as sucursal_nombre')
            ->join('productos p', 'p.id = s.producto_id', 'inner')
            ->join('sucursales suc', 'suc.id = s.sucursal_id', 'inner')
            ->join('categorias c', 'c.id = p.categoria_id', 'left')
            ->join('marcas m', 'm.id = p.marca_id', 'left')
            ->where('p.empresa_id', $empresaId)
            ->where('p.estado', true)
            ->where('p.controla_stock', true)
            ->where('s.cantidad_disponible <=', 'p.punto_reorden', false);

        if ($sucursalId) {
            $builder->where('s.sucursal_id', $sucursalId);
        }

        $productosStockBajo = $builder->orderBy('s.cantidad_disponible', 'ASC')
            ->get()
            ->getResultArray();

        $data = [
            'title' => 'Productos con Stock Bajo',
            'productos' => $productosStockBajo,
            'total' => count($productosStockBajo),
            'sucursal_actual' => $sucursalId
        ];

        return $this->render('reportes/inventario/stock_bajo', $data);
    }

    /**
     * Reporte de productos próximos a vencer
     */
    public function proximosVencer()
    {
        $this->checkAuth();
        $empresaId = $this->user['empresa_id'] ?? null;
        $sucursalId = $this->sucursal['id'] ?? null;
        $dias = $this->request->getGet('dias') ?? 30;

        if (!$empresaId) {
            return redirect()->to('/auth/login');
        }

        $db = \Config\Database::connect();
        $fechaLimite = date('Y-m-d', strtotime("+{$dias} days"));

        $builder = $db->table('stock s')
            ->select('s.*, p.codigo, p.nombre as producto_nombre,
                              c.nombre as categoria_nombre, m.nombre as marca_nombre,
                              suc.nombre as sucursal_nombre,
                              (s.fecha_vencimiento - CURRENT_DATE) as dias_restantes')
            ->join('productos p', 'p.id = s.producto_id', 'inner')
            ->join('sucursales suc', 'suc.id = s.sucursal_id', 'inner')
            ->join('categorias c', 'c.id = p.categoria_id', 'left')
            ->join('marcas m', 'm.id = p.marca_id', 'left')
            ->where('p.empresa_id', $empresaId)
            ->where('p.estado', true)
            ->where('s.fecha_vencimiento IS NOT NULL')
            ->where('s.fecha_vencimiento <=', $fechaLimite)
            ->where('s.fecha_vencimiento >=', date('Y-m-d'));

        if ($sucursalId) {
            $builder->where('s.sucursal_id', $sucursalId);
        }

        $productos = $builder->orderBy('s.fecha_vencimiento', 'ASC')
            ->get()
            ->getResultArray();

        $data = [
            'title' => 'Productos Próximos a Vencer',
            'productos' => $productos,
            'total' => count($productos),
            'dias' => $dias,
            'sucursal_actual' => $sucursalId
        ];

        return $this->render('reportes/inventario/proximos_vencer', $data);
    }

    /**
     * Reporte de movimientos de stock
     */
    public function movimientos()
    {
        $this->checkAuth();
        $empresaId = $this->user['empresa_id'] ?? null;
        $sucursalId = $this->sucursal['id'] ?? null;

        if (!$empresaId) {
            return redirect()->to('/auth/login');
        }

        $fechaDesde = $this->request->getGet('fecha_desde') ?? date('Y-m-01');
        $fechaHasta = $this->request->getGet('fecha_hasta') ?? date('Y-m-d');
        $tipoMovimiento = $this->request->getGet('tipo_movimiento');
        $productoId = $this->request->getGet('producto_id');

        $db = \Config\Database::connect();

        $builder = $db->table('movimientos_stock m')
            ->select('m.*, p.codigo, p.nombre as producto_nombre,
                              s.nombre as sucursal_nombre,
                              u.nombre as usuario_nombre, u.apellido as usuario_apellido')
            ->join('productos p', 'p.id = m.producto_id', 'left')
            ->join('sucursales s', 's.id = m.sucursal_id', 'left')
            ->join('usuarios u', 'u.id = m.usuario_id', 'left')
            ->where('m.empresa_id', $empresaId)
            ->where('m.fecha_movimiento >=', $fechaDesde . ' 00:00:00')
            ->where('m.fecha_movimiento <=', $fechaHasta . ' 23:59:59');

        if ($sucursalId) {
            $builder->where('m.sucursal_id', $sucursalId);
        }

        if ($tipoMovimiento) {
            $builder->where('m.tipo_movimiento', $tipoMovimiento);
        }

        if ($productoId) {
            $builder->where('m.producto_id', $productoId);
        }

        $movimientos = $builder->orderBy('m.fecha_movimiento', 'DESC')
            ->limit(1000)
            ->get()
            ->getResultArray();

        // Obtener productos para filtro
        $productos = $this->productoModel->where('empresa_id', $empresaId)
            ->where('estado', true)
            ->orderBy('nombre', 'ASC')
            ->findAll();

        // Obtener sucursales para filtro
        $sucursales = $this->sucursalModel->where('empresa_id', $empresaId)
            ->where('estado', true)
            ->findAll();

        $data = [
            'title' => 'Movimientos de Stock',
            'movimientos' => $movimientos,
            'productos' => $productos,
            'sucursales' => $sucursales,
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
            'tipo_movimiento' => $tipoMovimiento,
            'producto_id' => $productoId,
            'sucursal_actual' => $sucursalId
        ];

        return $this->render('reportes/inventario/movimientos', $data);
    }

    /**
     * Exportar reporte a Excel
     */
    public function exportarExcel($tipo = 'inventario')
    {
        $this->checkAuth();
        $empresaId = $this->user['empresa_id'] ?? null;
        $sucursalId = $this->sucursal['id'] ?? null;

        if (!$empresaId) {
            return $this->errorResponse('Usuario no tiene empresa asignada', 403);
        }

        // Verificar si PhpSpreadsheet está instalado
        if (!class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')) {
            // Intentar cargar el autoloader de Composer si existe
            $autoloadPath = ROOTPATH . 'vendor/autoload.php';
            if (file_exists($autoloadPath)) {
                require_once $autoloadPath;
            }

            // Verificar nuevamente
            if (!class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')) {
                return $this->errorResponse('La librería PhpSpreadsheet no está instalada. Por favor ejecute: composer install', 500);
            }
        }

        try {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            switch ($tipo) {
                case 'inventario':
                    $this->exportarInventarioExcel($spreadsheet, $empresaId, $sucursalId);
                    $filename = 'inventario_' . date('Ymd_His') . '.xlsx';
                    break;
                case 'stock_bajo':
                    $this->exportarStockBajoExcel($spreadsheet, $empresaId, $sucursalId);
                    $filename = 'stock_bajo_' . date('Ymd_His') . '.xlsx';
                    break;
                case 'proximos_vencer':
                    $dias = $this->request->getGet('dias') ?? 30;
                    $this->exportarProximosVencerExcel($spreadsheet, $empresaId, $sucursalId, $dias);
                    $filename = 'productos_proximos_vencer_' . date('Ymd_His') . '.xlsx';
                    break;
                case 'movimientos':
                    $fechaDesde = $this->request->getGet('fecha_desde') ?? date('Y-m-01');
                    $fechaHasta = $this->request->getGet('fecha_hasta') ?? date('Y-m-d');
                    $this->exportarMovimientosExcel($spreadsheet, $empresaId, $sucursalId, $fechaDesde, $fechaHasta);
                    $filename = 'movimientos_stock_' . date('Ymd_His') . '.xlsx';
                    break;
                default:
                    return $this->errorResponse('Tipo de reporte no válido', 400);
            }

            // Configurar headers para descarga
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . $filename . '"');
            header('Cache-Control: max-age=0');

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            exit;

        } catch (\Exception $e) {
            log_message('error', 'Error al exportar Excel: ' . $e->getMessage());
            return $this->errorResponse('Error al generar el archivo Excel: ' . $e->getMessage());
        }
    }

    private function exportarInventarioExcel($spreadsheet, $empresaId, $sucursalId)
    {
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Inventario');

        // Encabezado
        $sheet->setCellValue('A1', 'REPORTE DE INVENTARIO');
        $sheet->mergeCells('A1:J1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Encabezados de columna
        $headers = ['Código', 'Producto', 'Categoría', 'Marca', 'Sucursal', 'Cantidad', 'Disponible', 'Stock Mínimo', 'Valor Costo', 'Valor Venta'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '3', $header);
            $col++;
        }

        // Estilo de encabezados
        $sheet->getStyle('A3:J3')->getFont()->setBold(true);
        $sheet->getStyle('A3:J3')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('4472C4');
        $sheet->getStyle('A3:J3')->getFont()->getColor()->setRGB('FFFFFF');

        // Datos
        $db = \Config\Database::connect();
        $builder = $db->table('stock s')
            ->select('p.codigo, p.nombre as producto_nombre, c.nombre as categoria_nombre,
                              m.nombre as marca_nombre, suc.nombre as sucursal_nombre,
                              s.cantidad, s.cantidad_disponible, p.stock_minimo,
                              (s.cantidad * COALESCE(s.costo_promedio, p.precio_compra, 0)) as valor_costo,
                              (s.cantidad * p.precio_venta) as valor_venta')
            ->join('productos p', 'p.id = s.producto_id', 'inner')
            ->join('sucursales suc', 'suc.id = s.sucursal_id', 'inner')
            ->join('categorias c', 'c.id = p.categoria_id', 'left')
            ->join('marcas m', 'm.id = p.marca_id', 'left')
            ->where('p.empresa_id', $empresaId)
            ->where('p.estado', true);

        if ($sucursalId) {
            $builder->where('s.sucursal_id', $sucursalId);
        }

        $productos = $builder->orderBy('p.nombre', 'ASC')->get()->getResultArray();

        $row = 4;
        $totalCosto = 0;
        $totalVenta = 0;

        foreach ($productos as $item) {
            $sheet->setCellValue('A' . $row, $item['codigo']);
            $sheet->setCellValue('B' . $row, $item['producto_nombre']);
            $sheet->setCellValue('C' . $row, $item['categoria_nombre'] ?? '-');
            $sheet->setCellValue('D' . $row, $item['marca_nombre'] ?? '-');
            $sheet->setCellValue('E' . $row, $item['sucursal_nombre']);
            $sheet->setCellValue('F' . $row, number_format($item['cantidad'], 2, ',', '.'));
            $sheet->setCellValue('G' . $row, number_format($item['cantidad_disponible'], 2, ',', '.'));
            $sheet->setCellValue('H' . $row, number_format($item['stock_minimo'], 0, ',', '.'));
            $sheet->setCellValue('I' . $row, number_format($item['valor_costo'], 0, ',', '.'));
            $sheet->setCellValue('J' . $row, number_format($item['valor_venta'], 0, ',', '.'));

            $totalCosto += floatval($item['valor_costo']);
            $totalVenta += floatval($item['valor_venta']);
            $row++;
        }

        // Totales
        $sheet->setCellValue('H' . $row, 'TOTALES:');
        $sheet->getStyle('H' . $row)->getFont()->setBold(true);
        $sheet->setCellValue('I' . $row, number_format($totalCosto, 0, ',', '.'));
        $sheet->setCellValue('J' . $row, number_format($totalVenta, 0, ',', '.'));
        $sheet->getStyle('I' . $row . ':J' . $row)->getFont()->setBold(true);

        // Ajustar ancho de columnas
        foreach (range('A', 'J') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    private function exportarStockBajoExcel($spreadsheet, $empresaId, $sucursalId)
    {
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Stock Bajo');
        $sheet->setCellValue('A1', 'PRODUCTOS CON STOCK BAJO');
        $sheet->mergeCells('A1:J1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Encabezados
        $headers = ['Código', 'Producto', 'Categoría', 'Marca', 'Sucursal', 'Cantidad', 'Disponible', 'Stock Mínimo', 'Punto Reorden', 'Estado'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '3', $header);
            $col++;
        }

        // Estilo encabezados
        $sheet->getStyle('A3:J3')->getFont()->setBold(true);
        $sheet->getStyle('A3:J3')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('C00000'); // Rojo
        $sheet->getStyle('A3:J3')->getFont()->getColor()->setRGB('FFFFFF');

        // Datos
        $db = \Config\Database::connect();
        $builder = $db->table('stock s')
            ->select('s.*, p.codigo, p.nombre as producto_nombre,
                              c.nombre as categoria_nombre, m.nombre as marca_nombre,
                              suc.nombre as sucursal_nombre, p.stock_minimo, p.punto_reorden')
            ->join('productos p', 'p.id = s.producto_id', 'inner')
            ->join('sucursales suc', 'suc.id = s.sucursal_id', 'inner')
            ->join('categorias c', 'c.id = p.categoria_id', 'left')
            ->join('marcas m', 'm.id = p.marca_id', 'left')
            ->where('p.empresa_id', $empresaId)
            ->where('p.estado', true)
            ->where('p.controla_stock', true)
            ->where('s.cantidad_disponible <=', 'p.punto_reorden', false);

        if ($sucursalId) {
            $builder->where('s.sucursal_id', $sucursalId);
        }

        $productos = $builder->orderBy('s.cantidad_disponible', 'ASC')->get()->getResultArray();

        $row = 4;
        foreach ($productos as $item) {
            $sheet->setCellValue('A' . $row, $item['codigo']);
            $sheet->setCellValue('B' . $row, $item['producto_nombre']);
            $sheet->setCellValue('C' . $row, $item['categoria_nombre'] ?? '-');
            $sheet->setCellValue('D' . $row, $item['marca_nombre'] ?? '-');
            $sheet->setCellValue('E' . $row, $item['sucursal_nombre']);
            $sheet->setCellValue('F' . $row, $item['cantidad']);
            $sheet->setCellValue('G' . $row, $item['cantidad_disponible']);
            $sheet->setCellValue('H' . $row, $item['stock_minimo']);
            $sheet->setCellValue('I' . $row, $item['punto_reorden']);

            $estado = ($item['cantidad_disponible'] <= $item['stock_minimo']) ? 'CRÍTICO' : 'BAJO';
            $sheet->setCellValue('J' . $row, $estado);

            if ($estado == 'CRÍTICO') {
                $sheet->getStyle('J' . $row)->getFont()->getColor()->setRGB('FF0000');
                $sheet->getStyle('J' . $row)->getFont()->setBold(true);
            }

            $row++;
        }

        foreach (range('A', 'J') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    private function exportarProximosVencerExcel($spreadsheet, $empresaId, $sucursalId, $dias)
    {
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Próximos a Vencer');
        $sheet->setCellValue('A1', 'PRODUCTOS PRÓXIMOS A VENCER (Próximos ' . $dias . ' días)');
        $sheet->mergeCells('A1:G1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Encabezados
        $headers = ['Código', 'Producto', 'Sucursal', 'Lote', 'Fecha Vencimiento', 'Días Restantes', 'Cantidad'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '3', $header);
            $col++;
        }

        // Estilo encabezados
        $sheet->getStyle('A3:G3')->getFont()->setBold(true);
        $sheet->getStyle('A3:G3')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFC000'); // Naranja

        // Datos
        $db = \Config\Database::connect();
        $fechaLimite = date('Y-m-d', strtotime("+{$dias} days"));

        $builder = $db->table('stock s')
            ->select('s.*, p.codigo, p.nombre as producto_nombre,
                              suc.nombre as sucursal_nombre,
                              (s.fecha_vencimiento - CURRENT_DATE) as dias_restantes')
            ->join('productos p', 'p.id = s.producto_id', 'inner')
            ->join('sucursales suc', 'suc.id = s.sucursal_id', 'inner')
            ->where('p.empresa_id', $empresaId)
            ->where('p.estado', true)
            ->where('s.fecha_vencimiento IS NOT NULL')
            ->where('s.fecha_vencimiento <=', $fechaLimite)
            ->where('s.fecha_vencimiento >=', date('Y-m-d'));

        if ($sucursalId) {
            $builder->where('s.sucursal_id', $sucursalId);
        }

        $productos = $builder->orderBy('s.fecha_vencimiento', 'ASC')->get()->getResultArray();

        $row = 4;
        foreach ($productos as $item) {
            $sheet->setCellValue('A' . $row, $item['codigo']);
            $sheet->setCellValue('B' . $row, $item['producto_nombre']);
            $sheet->setCellValue('C' . $row, $item['sucursal_nombre']);
            $sheet->setCellValue('D' . $row, $item['lote'] ?? '-');
            $sheet->setCellValue('E' . $row, date('d/m/Y', strtotime($item['fecha_vencimiento'])));
            $sheet->setCellValue('F' . $row, $item['dias_restantes']);
            $sheet->setCellValue('G' . $row, $item['cantidad_disponible']);

            if ($item['dias_restantes'] <= 7) {
                $sheet->getStyle('F' . $row)->getFont()->getColor()->setRGB('FF0000');
                $sheet->getStyle('F' . $row)->getFont()->setBold(true);
            }

            $row++;
        }

        foreach (range('A', 'G') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    private function exportarMovimientosExcel($spreadsheet, $empresaId, $sucursalId, $fechaDesde, $fechaHasta)
    {
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Movimientos');
        $sheet->setCellValue('A1', 'MOVIMIENTOS DE STOCK (' . date('d/m/Y', strtotime($fechaDesde)) . ' - ' . date('d/m/Y', strtotime($fechaHasta)) . ')');
        $sheet->mergeCells('A1:I1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Encabezados
        $headers = ['Fecha', 'Tipo', 'Producto', 'Sucursal', 'Cantidad', 'Stock Anterior', 'Stock Nuevo', 'Usuario', 'Detalle'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '3', $header);
            $col++;
        }

        // Estilo encabezados
        $sheet->getStyle('A3:I3')->getFont()->setBold(true);
        $sheet->getStyle('A3:I3')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('5B9BD5'); // Azul claro
        $sheet->getStyle('A3:I3')->getFont()->getColor()->setRGB('FFFFFF');

        // Datos
        $db = \Config\Database::connect();
        $tipoMovimiento = $this->request->getGet('tipo_movimiento');
        $productoId = $this->request->getGet('producto_id');

        $builder = $db->table('movimientos_stock m')
            ->select('m.*, p.codigo, p.nombre as producto_nombre,
                              s.nombre as sucursal_nombre,
                              u.nombre as usuario_nombre, u.apellido as usuario_apellido')
            ->join('productos p', 'p.id = m.producto_id', 'left')
            ->join('sucursales s', 's.id = m.sucursal_id', 'left')
            ->join('usuarios u', 'u.id = m.usuario_id', 'left')
            ->where('m.empresa_id', $empresaId)
            ->where('m.fecha_movimiento >=', $fechaDesde . ' 00:00:00')
            ->where('m.fecha_movimiento <=', $fechaHasta . ' 23:59:59');

        if ($sucursalId) {
            $builder->where('m.sucursal_id', $sucursalId);
        }
        if ($tipoMovimiento) {
            $builder->where('m.tipo_movimiento', $tipoMovimiento);
        }
        if ($productoId) {
            $builder->where('m.producto_id', $productoId);
        }

        $movimientos = $builder->orderBy('m.fecha_movimiento', 'DESC')->get()->getResultArray();

        $row = 4;
        foreach ($movimientos as $mov) {
            $sheet->setCellValue('A' . $row, date('d/m/Y H:i', strtotime($mov['fecha_movimiento'])));
            $sheet->setCellValue('B' . $row, $mov['tipo_movimiento']);
            $sheet->setCellValue('C' . $row, $mov['producto_nombre']);
            $sheet->setCellValue('D' . $row, $mov['sucursal_nombre']);
            $sheet->setCellValue('E' . $row, $mov['cantidad']);
            $sheet->setCellValue('F' . $row, $mov['stock_anterior']);
            $sheet->setCellValue('G' . $row, $mov['stock_actual']);
            $sheet->setCellValue('H' . $row, $mov['usuario_nombre'] . ' ' . $mov['usuario_apellido']);
            $sheet->setCellValue('I' . $row, $mov['referencia'] ?? $mov['concepto']);

            $row++;
        }

        foreach (range('A', 'I') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }
}
