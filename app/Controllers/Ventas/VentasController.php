<?php

namespace App\Controllers\Ventas;

use App\Controllers\BaseController;
use App\Models\VentaModel;
use App\Models\VentaDetalleModel;
use App\Models\PagoVentaModel;
use App\Libraries\TicketTermico;

class VentasController extends BaseController
{
    protected $ventaModel;
    protected $ventaDetalleModel;
    protected $pagoVentaModel;
    protected $ventaService;

    public function __construct()
    {
        $this->ventaModel = new VentaModel();
        $this->ventaDetalleModel = new VentaDetalleModel();
        $this->pagoVentaModel = new PagoVentaModel();
        $this->ventaService = new \App\Services\VentaService();
    }

    protected function checkAuth()
    {
        if (!$this->isLoggedIn()) {
            return redirect()->to('/auth/login');
        }
        $this->requirePermission('POS', 'ver');
    }

    public function index()
    {
        $this->checkAuth();
        $empresaId = $this->user['empresa_id'];
        $sucursalId = $this->sucursal ? $this->sucursal['id'] : null;

        // Determinar nivel del rol
        $rolModel = new \App\Models\RolModel();
        $rol = $rolModel->find($this->user['rol_id'] ?? 0);
        $esAdmin = $rol && (int)($rol['nivel'] ?? 99) <= 3;

        $builder = $this->ventaModel->where('empresa_id', $empresaId);
        if ($sucursalId) {
            $builder->where('sucursal_id', $sucursalId);
        }
        // Empleados (nivel 4+) solo ven sus propias ventas
        if (!$esAdmin) {
            $builder->groupStart()
                    ->where('usuario_id', $this->user['id'])
                    ->orWhere('vendedor_id', $this->user['id'])
                    ->groupEnd();
        }

        $ventas = $builder->orderBy('fecha_venta', 'DESC')
            ->limit(100)
            ->findAll();

        $data = [
            'title' => 'Lista de Ventas',
            'ventas' => $ventas
        ];

        return $this->render('ventas/index', $data);
    }

    public function ver($id)
    {
        $this->checkAuth();
        $venta = $this->ventaModel->find($id);

        if (!$venta || $venta['empresa_id'] != $this->user['empresa_id']) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Venta no encontrada');
        }

        // Empleados (nivel 4+) solo pueden ver sus propias ventas
        $rolModel = new \App\Models\RolModel();
        $rol = $rolModel->find($this->user['rol_id'] ?? 0);
        $esAdmin = $rol && (int)($rol['nivel'] ?? 99) <= 3;
        if (!$esAdmin) {
            $userId = $this->user['id'];
            if (($venta['usuario_id'] ?? null) != $userId && ($venta['vendedor_id'] ?? null) != $userId) {
                throw new \CodeIgniter\Exceptions\PageNotFoundException('No tiene acceso a esta venta');
            }
        }

        // Función helper para convertir valores numéricos correctamente
        $normalizarNumero = function ($valor) {
            if ($valor === null || $valor === '' || $valor === false) {
                return 0.0;
            }
            // Si ya es numérico (int o float), devolverlo directamente
            if (is_numeric($valor)) {
                return (float) $valor;
            }
            // Si es string, intentar convertir
            if (is_string($valor)) {
                $valor = trim($valor);
                // Solo limpiar caracteres no numéricos excepto punto, coma y guión
                $valor = preg_replace('/[^0-9.,-]/', '', $valor);
                // Si tiene tanto punto como coma, asumir formato europeo (1.234,56)
                if (strpos($valor, '.') !== false && strpos($valor, ',') !== false) {
                    $valor = str_replace('.', '', $valor); // Remover separador de miles
                    $valor = str_replace(',', '.', $valor); // Convertir coma decimal a punto
                }
                // Si solo tiene coma, asumir que es decimal europeo
                elseif (strpos($valor, ',') !== false && strpos($valor, '.') === false) {
                    $valor = str_replace(',', '.', $valor);
                }
                // Si solo tiene punto, dejarlo como está (formato SQL estándar)
            }
            return (float) $valor;
        };

        // Obtener detalles
        $venta['detalles'] = $this->ventaDetalleModel->where('venta_id', $id)->findAll();

        // DEBUG: Log valores originales
        log_message('debug', 'VENTA ID: ' . $id);
        log_message('debug', 'Subtotal original: ' . var_export($venta['subtotal'] ?? 'NULL', true));

        // Asegurar que los valores numéricos se conviertan correctamente
        foreach ($venta['detalles'] as &$detalle) {
            // DEBUG: Log valores originales del detalle
            log_message('debug', 'Detalle - Precio original: ' . var_export($detalle['precio_unitario'] ?? 'NULL', true));
            log_message('debug', 'Detalle - Total original: ' . var_export($detalle['total'] ?? 'NULL', true));

            $detalle['precio_unitario'] = $normalizarNumero($detalle['precio_unitario'] ?? 0);
            $detalle['descuento_total_monto'] = $normalizarNumero($detalle['descuento_total_monto'] ?? 0);
            $detalle['total'] = $normalizarNumero($detalle['total'] ?? 0);
            $detalle['cantidad'] = $normalizarNumero($detalle['cantidad'] ?? 0);

            // DEBUG: Log valores después de normalizar
            log_message('debug', 'Detalle - Precio normalizado: ' . $detalle['precio_unitario']);
            log_message('debug', 'Detalle - Total normalizado: ' . $detalle['total']);
        }
        unset($detalle);

        // Asegurar que los valores de la venta se conviertan correctamente
        $venta['subtotal'] = $normalizarNumero($venta['subtotal'] ?? 0);
        $venta['descuento_monto'] = $normalizarNumero($venta['descuento_monto'] ?? 0);
        $venta['iva_10'] = $normalizarNumero($venta['iva_10'] ?? 0);
        $venta['iva_5'] = $normalizarNumero($venta['iva_5'] ?? 0);
        $venta['total_iva'] = $normalizarNumero($venta['total_iva'] ?? 0);
        $venta['total'] = $normalizarNumero($venta['total'] ?? 0);
        $venta['monto_pagado'] = $normalizarNumero($venta['monto_pagado'] ?? 0);
        $venta['vuelto'] = $normalizarNumero($venta['vuelto'] ?? 0);

        // DEBUG: Log valores después de normalizar
        log_message('debug', 'Subtotal normalizado: ' . $venta['subtotal']);
        log_message('debug', 'Total normalizado: ' . $venta['total']);

        // Obtener pagos
        $venta['pagos'] = $this->pagoVentaModel->where('venta_id', $id)->findAll();

        // Asegurar que los valores de pagos se conviertan correctamente
        foreach ($venta['pagos'] as &$pago) {
            $pago['monto'] = $normalizarNumero($pago['monto'] ?? 0);
        }
        unset($pago);

        // Obtener cliente (inicializar como null si no existe)
        $venta['cliente'] = null;
        if (!empty($venta['cliente_id'])) {
            $clienteModel = new \App\Models\ClienteModel();
            $venta['cliente'] = $clienteModel->find($venta['cliente_id']);
        }

        // Obtener usuario que realizó la venta
        $venta['usuario'] = null;
        if (!empty($venta['usuario_id'])) {
            $usuarioModel = new \App\Models\UsuarioModel();
            $venta['usuario'] = $usuarioModel->find($venta['usuario_id']);
        }

        // Obtener vendedor si es diferente del usuario
        $venta['vendedor'] = null;
        if (!empty($venta['vendedor_id']) && $venta['vendedor_id'] != $venta['usuario_id']) {
            $usuarioModel = new \App\Models\UsuarioModel();
            $venta['vendedor'] = $usuarioModel->find($venta['vendedor_id']);
        }

        $data = [
            'title' => 'Ver Venta',
            'venta' => $venta
        ];

        return $this->render('ventas/ver', $data);
    }

    public function imprimirTicket($id)
    {
        $this->checkAuth();
        $venta = $this->ventaModel->find($id);

        if (!$venta || $venta['empresa_id'] != $this->user['empresa_id']) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Venta no encontrada');
        }

        if ($venta['tipo_comprobante'] === 'FACTURA' || $venta['tipo_comprobante'] === 'FACTURA_ELECTRONICA') {
            return $this->imprimirFactura($id);
        }

        $detalles = $this->ventaDetalleModel->where('venta_id', $id)->findAll();

        try {
            // Calcular altura necesaria para el ticket
            $alturaNecesaria = \App\Libraries\TicketTermico::calcularAlturaNecesaria($venta, $detalles, $this->empresa);
            $ticket = new TicketTermico($this->empresa, $alturaNecesaria);
            $pdfContent = $ticket->generarTicketVenta($venta, $detalles);

            if ($pdfContent) {
                // Configurar headers para PDF
                $this->response->setHeader('Content-Type', 'application/pdf');
                $this->response->setHeader('Content-Disposition', 'inline; filename="ticket_' . $venta['numero_venta'] . '.pdf"');
                $this->response->setHeader('Content-Length', strlen($pdfContent));
                $this->response->setBody($pdfContent);
                return $this->response;
            }
        } catch (\Exception $e) {
            log_message('error', 'Error al generar PDF: ' . $e->getMessage());
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Error al generar el PDF: ' . $e->getMessage());
        }

        throw new \CodeIgniter\Exceptions\PageNotFoundException('Error al generar el PDF');
    }

    public function imprimirFactura($id)
    {
        $this->checkAuth();
        $venta = $this->ventaModel->find($id);

        if (!$venta || $venta['empresa_id'] != $this->user['empresa_id']) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Venta no encontrada');
        }

        // Obtener detalles del venta con nomnbre de producto
        $db = \Config\Database::connect();
        $detalles = $db->table('ventas_detalle vd')
            ->select('vd.*, p.codigo, p.nombre as nombre_producto, p.porcentaje_iva as iva')
            ->join('productos p', 'p.id = vd.producto_id', 'left')
            ->where('vd.venta_id', $id)
            ->get()->getResultArray();

        // Obtener cliente
        $cliente = null;
        if (!empty($venta['cliente_id'])) {
            $clienteModel = new \App\Models\ClienteModel();
            $cliente = $clienteModel->find($venta['cliente_id']);
        }

        // Obtener Talonario (Si no está en venta, buscar el activo para FACTURA)
        $talonario = null;
        $talonarioModel = new \App\Models\TalonarioModel();

        // Primero intentamos buscar histórico si la venta guardara referencia (no parece tener ID, pero tiene timbrado)
        // Buscaremos por timbrado si existe
        if (!empty($venta['timbrado'])) {
            $talonario = $talonarioModel->where('empresa_id', $this->user['empresa_id'])
                ->where('timbrado', $venta['timbrado'])
                ->first();
        }

        // Si no encontramos, buscamos el activo actual (fallback)
        if (!$talonario) {
            $talonario = $talonarioModel->where('empresa_id', $this->user['empresa_id'])
                ->where('tipo_comprobante', 'FACTURA')
                ->where('estado', 'ACTIVO')
                ->where('sucursal_id', $venta['sucursal_id'])
                ->first();
        }

        // Obtener Sucursales para membrete
        $sucursalModel = new \App\Models\SucursalModel();
        $sucursales = $sucursalModel->getPorEmpresa($this->user['empresa_id']);

        try {
            $facturaPDF = new \App\Libraries\FacturaPDF($this->empresa, $talonario, $sucursales);
            $pdfContent = $facturaPDF->generarFactura($venta, $detalles, $cliente);

            if ($pdfContent) {
                $this->response->setHeader('Content-Type', 'application/pdf');
                $this->response->setHeader('Content-Disposition', 'inline; filename="factura_' . ($venta['numero_factura'] ?? $venta['numero_venta']) . '.pdf"');
                $this->response->setHeader('Content-Length', strlen($pdfContent));
                $this->response->setBody($pdfContent);
                return $this->response;
            }
        } catch (\Exception $e) {
            log_message('error', 'Error al generar Factura PDF: ' . $e->getMessage());
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Error al generar la Factura: ' . $e->getMessage());
        }
    }

    public function anular($id)
    {
        // Verificar autenticación sin redirección para AJAX
        if (!$this->isLoggedIn()) {
            return $this->errorResponse('No está autenticado', 401);
        }

        $this->loadUserData();

        if (!$this->user) {
            return $this->errorResponse('Usuario no encontrado', 401);
        }

        // Obtener motivo de POST o JSON
        $motivo = $this->request->getPost('motivo');
        if (empty($motivo)) {
            // Intentar obtener de JSON
            $jsonData = $this->request->getJSON(true);
            if ($jsonData && isset($jsonData['motivo'])) {
                $motivo = $jsonData['motivo'];
            }
        }

        if (empty($motivo)) {
            return $this->errorResponse('Debe proporcionar un motivo de anulación');
        }

        // Empleados (nivel 4+) solo pueden anular sus propias ventas
        $venta = $this->ventaModel->find($id);
        if (!$venta || $venta['empresa_id'] != $this->user['empresa_id']) {
            return $this->errorResponse('Venta no encontrada', 404);
        }
        $rolModel = new \App\Models\RolModel();
        $rol = $rolModel->find($this->user['rol_id'] ?? 0);
        $esAdmin = $rol && (int)($rol['nivel'] ?? 99) <= 3;
        if (!$esAdmin) {
            $userId = $this->user['id'];
            if (($venta['usuario_id'] ?? null) != $userId && ($venta['vendedor_id'] ?? null) != $userId) {
                return $this->errorResponse('No tiene permiso para anular esta venta', 403);
            }
        }

        // Delegar al servicio
        $resultado = $this->ventaService->anularVenta($id, $this->user, $motivo);

        if ($resultado['success']) {
            return $this->successResponse($resultado['message']);
        } else {
            $httpCode = $resultado['http_code'] ?? 400;
            return $this->errorResponse($resultado['message'], $httpCode);
        }
    }
}
