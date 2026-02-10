<?php

namespace App\Libraries;

use TCPDF;

class TicketTermico extends TCPDF
{
    protected $empresa;
    protected $ancho = 80; // mm para impresora térmica 80mm
    protected $alturaCalculada = 200; // Altura inicial, se ajustará

    public function __construct($empresa = null, $altura = 200)
    {
        // Permitir altura personalizada para ajuste dinámico
        $this->alturaCalculada = $altura;
        parent::__construct('P', 'mm', array($this->ancho, $altura), true, 'UTF-8', false);
        $this->empresa = $empresa;
        $this->setMargins(5, 5, 5);
        $this->setAutoPageBreak(false);
        $this->setFont('helvetica', '', 9);
    }

    /**
     * Calcular altura aproximada necesaria para el ticket
     */
    public static function calcularAlturaNecesaria($venta, $detalles, $empresa = null)
    {
        $altura = 5; // Margen superior

        // Encabezado empresa (nombre + dirección)
        // $altura += 8; // Logo - REMOVIDO
        $altura += 8; // Nombre empresa
        
        // Dirección (Dinámico)
        if ($empresa && !empty($empresa['direccion'])) {
             // Ancho disponible aprox 70mm (80 - 10 margen)
             // Estimado 40 caracteres por línea para fuente tamaño 8-9
            $nbDireccion = max(1, ceil(strlen($empresa['direccion']) / 40)); 
            $altura += ($nbDireccion * 4);
        } else {
            $altura += 4;
        }

        $altura += 4; // Teléfono
        $altura += 4; // RUC
        $altura += 4; // Línea separadora + espacio

        // Información de la venta
        $altura += 6; // Tipo comprobante
        $altura += 4; // Número venta

        // Datos fiscales de Paraguay
        $talonario = null;
        if (in_array($venta['tipo_comprobante'], ['FACTURA', 'FACTURA_ELECTRONICA'])) {
            $talonarioModel = new \App\Models\TalonarioModel();
            $talonario = $talonarioModel->where('empresa_id', $venta['empresa_id'])
                ->where('sucursal_id', $venta['sucursal_id'])
                ->where('tipo_comprobante', $venta['tipo_comprobante'])
                ->where('estado', 'ACTIVO')
                ->first();
        }

        if ($talonario) {
            $altura += 4; // Timbrado
            $altura += 4; // Establecimiento
            $altura += 4; // Punto expedición
            if ($venta['numero_factura']) {
                $altura += 4; // Número factura
            }
            $altura += 4; // Rango
            $altura += 4; // Vencimiento timbrado
        } elseif ($venta['timbrado']) {
            $altura += 4; // Timbrado (fallback)
        }

        $altura += 4; // Fecha
        $altura += 4; // Línea separadora + espacio

        // Cliente
        if ($venta['cliente_id']) {
            $clienteModel = new \App\Models\ClienteModel();
            $cliente = $clienteModel->find($venta['cliente_id']);
            if ($cliente) {
                $altura += 4; // Título cliente
                $nombreCliente = $cliente['tipo_cliente'] == 'PERSONA'
                    ? ($cliente['nombre'] . ' ' . ($cliente['apellido'] ?? ''))
                    : ($cliente['nombre_fantasia'] ?? $cliente['razon_social'] ?? '');
                $lineasNombre = max(1, ceil(strlen($nombreCliente) / 25)); // ~25 caracteres por línea
                $altura += $lineasNombre * 3; // 3mm por línea
                $altura += 4; // RUC/CI
                $altura += 4; // Espacio
            }
        }

        // Detalles de productos
        $altura += 5; // Título DETALLE
        $altura += 1; // Línea
        $altura += 1; // Espacio
        foreach ($detalles as $detalle) {
            // Calcular altura del nombre del producto (puede ser multilínea)
            $productoModel = new \App\Models\ProductoModel();
            $producto = $productoModel->find($detalle['producto_id']);
            $nombre = $producto ? $producto['nombre'] : ($detalle['descripcion'] ?? '');
            $lineasNombre = max(1, ceil(strlen($nombre) / 25)); // ~25 caracteres por línea en 80mm
            $altura += $lineasNombre * 3; // 3mm por línea

            $altura += 3; // Cantidad
            $altura += 3; // Precio unitario
            if (($detalle['descuento_total_monto'] ?? 0) > 0) {
                $altura += 3; // Línea de descuento
            }
            $altura += 3; // Subtotal línea
            $altura += 1; // Espacio entre productos
        }

        $altura += 2; // Línea separadora + espacio

        // Totales
        if ($venta['subtotal_gravado_10'] > 0)
            $altura += 4;
        if ($venta['subtotal_gravado_5'] > 0)
            $altura += 4;
        if ($venta['subtotal_exento'] > 0)
            $altura += 4;
        if ($venta['descuento_monto'] > 0)
            $altura += 4;
        if ($venta['iva_10'] > 0)
            $altura += 4;
        if ($venta['iva_5'] > 0)
            $altura += 4;
        if ($venta['total_iva'] > 0)
            $altura += 4;
        $altura += 2; // Línea separadora
        $altura += 6; // TOTAL
        $altura += 3; // Espacio

        // Formas de pago
        if ($venta['monto_pagado'] > 0) {
            $pagoModel = new \App\Models\PagoVentaModel();
            $pagos = $pagoModel->where('venta_id', $venta['id'])->findAll();
            $altura += 4; // Título
            foreach ($pagos as $pago) {
                $altura += 3; // Por cada pago
            }
            if ($venta['vuelto'] > 0) {
                $altura += 3; // Vuelto
            }
        }

        $altura += 3; // Espacio
        $altura += 2; // Línea separadora
        $altura += 2; // Espacio

        // Pie de página
        $altura += 3; // "Gracias por su compra!"
        if ($empresa && $empresa['terminos_condiciones']) {
            // Calcular altura de términos (aproximadamente 3mm por línea)
            $lineasTerminos = ceil(strlen($empresa['terminos_condiciones']) / 30); // ~30 caracteres por línea en 80mm
            $altura += max($lineasTerminos * 3, 6); // Mínimo 6mm
        }

        return max($altura + 5, 80); // Mínimo 80mm, margen inferior de 5mm
    }

    public function generarTicketVenta($venta, $detalles)
    {
        $this->AddPage();

        // Obtener datos del talonario si es factura
        $talonario = null;
        if (in_array($venta['tipo_comprobante'], ['FACTURA', 'FACTURA_ELECTRONICA'])) {
            $talonarioModel = new \App\Models\TalonarioModel();
            $talonario = $talonarioModel->where('empresa_id', $venta['empresa_id'])
                ->where('sucursal_id', $venta['sucursal_id'])
                ->where('tipo_comprobante', $venta['tipo_comprobante'])
                ->where('estado', 'ACTIVO')
                ->first();
        }

        // Logo y encabezado - LOGO REMOVIDO POR SOLICITUD
        /*
        if ($this->empresa && $this->empresa['logo']) {
            $logoPath = ROOTPATH . 'public/uploads/empresas/' . $this->empresa['logo'];
            if (file_exists($logoPath)) {
                $this->Image($logoPath, 5, 5, 20, 20, '', '', '', false, 300, '', false, false, 0);
            }
        }
        */

        $this->SetFont('helvetica', 'B', 12);
        $this->Cell(0, 8, $this->empresa ? strtoupper($this->empresa['nombre']) : 'EMPRESA', 0, 1, 'C');

        $this->SetFont('helvetica', '', 8);
        if ($this->empresa) {
            if (!empty($this->empresa['direccion'])) {
                $this->MultiCell(0, 4, $this->empresa['direccion'], 0, 'C');
            }
            $this->Cell(0, 4, 'Tel: ' . $this->empresa['telefono'], 0, 1, 'C');
            if ($this->empresa['ruc']) {
                $this->Cell(0, 4, 'RUC: ' . $this->empresa['ruc'], 0, 1, 'C');
            }
        }

        $this->Ln(2);
        $this->Line(5, $this->GetY(), $this->ancho - 5, $this->GetY());
        $this->Ln(2);

        // Información de la venta
        $this->SetFont('helvetica', 'B', 10);
        $this->Cell(0, 6, strtoupper($venta['tipo_comprobante']), 0, 1, 'C');

        $this->SetFont('helvetica', '', 8);
        $this->Cell(0, 4, 'N°: ' . $venta['numero_venta'], 0, 1, 'C');

        // Datos fiscales de Paraguay (normativa SET)
        if ($talonario) {
            if ($talonario['timbrado']) {
                $this->Cell(0, 4, 'Timbrado: ' . $talonario['timbrado'], 0, 1, 'C');
            }
            if ($talonario['establecimiento']) {
                $this->Cell(0, 4, 'Establecimiento: ' . $talonario['establecimiento'], 0, 1, 'C');
            }
            if ($talonario['punto_expedicion']) {
                $this->Cell(0, 4, 'Punto de Expedición: ' . $talonario['punto_expedicion'], 0, 1, 'C');
            }
            if ($venta['numero_factura']) {
                $this->SetFont('helvetica', 'B', 8);
                $this->Cell(0, 4, 'Factura N°: ' . $venta['numero_factura'], 0, 1, 'C');
                $this->SetFont('helvetica', '', 8);
            }
            // Rango autorizado
            if ($talonario['numero_desde'] && $talonario['numero_hasta']) {
                $this->Cell(0, 4, 'Rango: ' . $talonario['numero_desde'] . ' al ' . $talonario['numero_hasta'], 0, 1, 'C');
            }
            // Fecha de vencimiento del timbrado
            if ($talonario['fecha_fin_vigencia']) {
                $this->Cell(0, 4, 'Venc. Timbrado: ' . date('d/m/Y', strtotime($talonario['fecha_fin_vigencia'])), 0, 1, 'C');
            }
        } elseif ($venta['timbrado']) {
            // Fallback si no hay talonario pero hay timbrado en venta
            $this->Cell(0, 4, 'Timbrado: ' . $venta['timbrado'], 0, 1, 'C');
        }

        $this->Cell(0, 4, 'Fecha: ' . date('d/m/Y H:i', strtotime($venta['fecha_venta'])), 0, 1, 'C');

        $this->Ln(2);
        $this->Line(5, $this->GetY(), $this->ancho - 5, $this->GetY());
        $this->Ln(2);

        // Cliente
        if ($venta['cliente_id']) {
            $clienteModel = new \App\Models\ClienteModel();
            $cliente = $clienteModel->find($venta['cliente_id']);
            if ($cliente) {
                $nombreCliente = $cliente['tipo_cliente'] == 'PERSONA'
                    ? ($cliente['nombre'] . ' ' . ($cliente['apellido'] ?? ''))
                    : ($cliente['nombre_fantasia'] ?? $cliente['razon_social'] ?? '');

                $this->SetFont('helvetica', 'B', 8);
                $this->Cell(0, 4, 'Cliente:', 0, 1);
                $this->SetFont('helvetica', '', 8);
                $this->MultiCell(0, 4, $nombreCliente, 0, 'L');
                if ($cliente['ruc'] || $cliente['ci']) {
                    $this->Cell(0, 4, 'RUC/CI: ' . ($cliente['ruc'] ?? $cliente['ci'] ?? ''), 0, 1);
                }
                $this->Ln(2);
            }
        }

        // Detalles
        $this->SetFont('helvetica', 'B', 8);
        $this->Cell(0, 5, 'DETALLE', 0, 1);
        $this->Line(5, $this->GetY(), $this->ancho - 5, $this->GetY());
        $this->Ln(1);

        $this->SetFont('helvetica', '', 7);
        foreach ($detalles as $detalle) {
            $productoModel = new \App\Models\ProductoModel();
            $producto = $productoModel->find($detalle['producto_id']);
            $nombre = $producto ? $producto['nombre'] : ($detalle['descripcion'] ?? 'Producto');

            // Nombre del producto
            $this->SetFont('helvetica', 'B', 7);
            $this->MultiCell(0, 3, $nombre, 0, 'L');

            // Cantidad - Mostrar claramente (sin decimales si es entero)
            $this->SetFont('helvetica', '', 7);
            $cantidadFloat = (float) $detalle['cantidad'];
            if ($cantidadFloat == floor($cantidadFloat)) {
                // Es un número entero, mostrar sin decimales
                $cantidad = number_format($cantidadFloat, 0, ',', '.');
            } else {
                // Tiene decimales, mostrar con 2 decimales
                $cantidad = number_format($cantidadFloat, 2, ',', '.');
            }
            $this->Cell(0, 3, 'Cantidad: ' . $cantidad, 0, 1, 'L');

            // Precio unitario - Mostrar claramente
            $precioUnitario = number_format((float) $detalle['precio_unitario'], 0, ',', '.');
            $this->Cell(0, 3, 'Precio Unit.: ' . $precioUnitario . ' Gs.', 0, 1, 'L');

            // Descuento si aplica
            $descuentoMonto = (float) ($detalle['descuento_total_monto'] ?? 0);
            if ($descuentoMonto > 0) {
                $descuento = number_format($descuentoMonto, 0, ',', '.');
                $this->Cell(0, 3, 'Descuento: ' . $descuento . ' Gs.', 0, 1, 'L');
            }

            // Total de la línea
            $totalLinea = number_format((float) $detalle['total'], 0, ',', '.');
            $this->SetFont('helvetica', 'B', 7);
            $this->Cell(0, 3, 'Subtotal: ' . $totalLinea . ' Gs.', 0, 1, 'R');
            $this->SetFont('helvetica', '', 7);

            $this->Ln(1);
        }

        $this->Line(5, $this->GetY(), $this->ancho - 5, $this->GetY());
        $this->Ln(2);

        // Totales - Mostrar desglose correcto según normativa paraguaya
        $this->SetFont('helvetica', '', 8);

        // Subtotal (suma de todos los productos)
        $this->Cell(50, 4, 'Subtotal:', 0, 0, 'L');
        $this->Cell(0, 4, number_format((float) $venta['subtotal'], 0, ',', '.') . ' Gs.', 0, 1, 'R');

        // Descuento general (solo si hay descuento adicional al de línea)
        $descuentoMonto = (float) ($venta['descuento_monto'] ?? 0);
        if ($descuentoMonto > 0) {
            $descuentoLineas = 0;
            foreach ($detalles as $det) {
                $descuentoLineas += (float) ($det['descuento_total_monto'] ?? 0);
            }
            if ($descuentoMonto > $descuentoLineas) {
                $this->Cell(50, 4, 'Descuento General:', 0, 0, 'L');
                $this->Cell(0, 4, number_format($descuentoMonto - $descuentoLineas, 0, ',', '.') . ' Gs.', 0, 1, 'R');
            }
        }

        // Subtotal gravado 10%
        $subtotalGrav10 = (float) ($venta['subtotal_gravado_10'] ?? 0);
        if ($subtotalGrav10 > 0) {
            $this->Cell(50, 4, 'Subtotal Grav. 10%:', 0, 0, 'L');
            $this->Cell(0, 4, number_format($subtotalGrav10, 0, ',', '.') . ' Gs.', 0, 1, 'R');
        }

        // Subtotal gravado 5%
        $subtotalGrav5 = (float) ($venta['subtotal_gravado_5'] ?? 0);
        if ($subtotalGrav5 > 0) {
            $this->Cell(50, 4, 'Subtotal Grav. 5%:', 0, 0, 'L');
            $this->Cell(0, 4, number_format($subtotalGrav5, 0, ',', '.') . ' Gs.', 0, 1, 'R');
        }

        // Subtotal exento
        $subtotalExento = (float) ($venta['subtotal_exento'] ?? 0);
        if ($subtotalExento > 0) {
            $this->Cell(50, 4, 'Subtotal Exento:', 0, 0, 'L');
            $this->Cell(0, 4, number_format($subtotalExento, 0, ',', '.') . ' Gs.', 0, 1, 'R');
        }

        // IVA 10%
        $iva10 = (float) ($venta['iva_10'] ?? 0);
        if ($iva10 > 0) {
            $this->Cell(50, 4, 'IVA 10%:', 0, 0, 'L');
            $this->Cell(0, 4, number_format($iva10, 0, ',', '.') . ' Gs.', 0, 1, 'R');
        }

        // IVA 5%
        $iva5 = (float) ($venta['iva_5'] ?? 0);
        if ($iva5 > 0) {
            $this->Cell(50, 4, 'IVA 5%:', 0, 0, 'L');
            $this->Cell(0, 4, number_format($iva5, 0, ',', '.') . ' Gs.', 0, 1, 'R');
        }

        // Total IVA
        $totalIva = (float) ($venta['total_iva'] ?? 0);
        if ($totalIva > 0) {
            $this->Cell(50, 4, 'Total IVA:', 0, 0, 'L');
            $this->Cell(0, 4, number_format($totalIva, 0, ',', '.') . ' Gs.', 0, 1, 'R');
        }

        $this->Line(5, $this->GetY(), $this->ancho - 5, $this->GetY());
        $this->Ln(2);

        // Calcular el total correcto: Usar el total almacenado en la venta
        $totalFinal = (float) ($venta['total'] ?? 0);

        $this->SetFont('helvetica', 'B', 10);
        $this->Cell(50, 6, 'TOTAL:', 0, 0, 'L');
        $this->Cell(0, 6, number_format($totalFinal, 0, ',', '.') . ' Gs.', 0, 1, 'R');

        $this->Ln(3);

        // Formas de pago
        if ($venta['monto_pagado'] > 0) {
            $pagoModel = new \App\Models\PagoVentaModel();
            $pagos = $pagoModel->where('venta_id', $venta['id'])->findAll();

            if (!empty($pagos)) {
                $this->SetFont('helvetica', 'B', 8);
                $this->Cell(0, 4, 'FORMAS DE PAGO:', 0, 1);
                $this->SetFont('helvetica', '', 7);

                foreach ($pagos as $pago) {
                    $forma = str_replace('_', ' ', $pago['forma_pago']);
                    $montoPago = number_format((float) $pago['monto'], 0, ',', '.');
                    $this->Cell(0, 3, ucwords(strtolower($forma)) . ': ' . $montoPago . ' Gs.', 0, 1);
                }

                $vuelto = (float) ($venta['vuelto'] ?? 0);
                if ($vuelto > 0) {
                    $vueltoFormatted = number_format($vuelto, 0, ',', '.');
                    $this->Cell(0, 3, 'Vuelto: ' . $vueltoFormatted . ' Gs.', 0, 1);
                }
            }
        }

        $this->Ln(3);
        $this->Line(5, $this->GetY(), $this->ancho - 5, $this->GetY());
        $this->Ln(2);

        // Pie de página
        $this->SetFont('helvetica', '', 7);
        $this->Cell(0, 3, 'Gracias por su compra!', 0, 1, 'C');

        if ($this->empresa && $this->empresa['terminos_condiciones']) {
            $this->Ln(1);
            $this->MultiCell(0, 3, $this->empresa['terminos_condiciones'], 0, 'C');
        }

        // Calcular altura final real y ajustar
        $alturaFinal = $this->GetY() + 10; // 10mm de margen inferior
        $alturaFinal = max($alturaFinal, 80); // Mínimo 80mm

        // Si la altura calculada es diferente, necesitamos recrear la página
        // TCPDF no permite cambiar el tamaño después, así que usamos la altura del constructor
        // que ya fue calculada previamente

        // Retornar como string para que CodeIgniter maneje los headers
        return $this->Output('ticket_' . $venta['numero_venta'] . '.pdf', 'S');
    }

    public function generarTicketOrden($orden)
    {
        $this->AddPage();

        // Logo y encabezado - LOGO REMOVIDO
        /*
        if ($this->empresa && $this->empresa['logo']) {
            $logoPath = ROOTPATH . 'public/uploads/empresas/' . $this->empresa['logo'];
            if (file_exists($logoPath)) {
                $this->Image($logoPath, 5, 5, 20, 20, '', '', '', false, 300, '', false, false, 0);
            }
        }
        */

        $this->SetFont('helvetica', 'B', 12);
        $this->Cell(0, 8, $this->empresa ? strtoupper($this->empresa['nombre']) : 'EMPRESA', 0, 1, 'C');

        $this->SetFont('helvetica', '', 8);
        if ($this->empresa) {
            $this->Cell(0, 4, $this->empresa['direccion'], 0, 1, 'C');
            $this->Cell(0, 4, 'Tel: ' . $this->empresa['telefono'], 0, 1, 'C');
        }

        $this->Ln(2);
        $this->Line(5, $this->GetY(), $this->ancho - 5, $this->GetY());
        $this->Ln(2);

        // Título
        $this->SetFont('helvetica', 'B', 10);
        $this->Cell(0, 6, 'ORDEN DE SERVICIO', 0, 1, 'C');

        $this->SetFont('helvetica', '', 8);
        $this->Cell(0, 4, 'N°: ' . $orden['numero_orden'], 0, 1, 'C');
        $this->Cell(0, 4, 'Fecha: ' . date('d/m/Y H:i', strtotime($orden['fecha_recepcion'])), 0, 1, 'C');

        $this->Ln(2);
        $this->Line(5, $this->GetY(), $this->ancho - 5, $this->GetY());
        $this->Ln(2);

        // Cliente
        $clienteModel = new \App\Models\ClienteModel();
        $cliente = $clienteModel->find($orden['cliente_id']);
        if ($cliente) {
            $nombreCliente = $cliente['tipo_cliente'] == 'PERSONA'
                ? ($cliente['nombre'] . ' ' . ($cliente['apellido'] ?? ''))
                : ($cliente['nombre_fantasia'] ?? $cliente['razon_social']);

            $this->SetFont('helvetica', 'B', 8);
            $this->Cell(0, 4, 'Cliente:', 0, 1);
            $this->SetFont('helvetica', '', 8);
            $this->MultiCell(0, 4, $nombreCliente, 0, 'L');
            if ($cliente['celular']) {
                $this->Cell(0, 4, 'Tel: ' . $cliente['celular'], 0, 1);
            }
            $this->Ln(2);
        }

        // Equipo
        $this->SetFont('helvetica', 'B', 8);
        $this->Cell(0, 4, 'Equipo:', 0, 1);
        $this->SetFont('helvetica', '', 8);
        $this->Cell(0, 4, 'Tipo: ' . $orden['tipo_equipo'], 0, 1);
        if ($orden['marca']) {
            $this->Cell(0, 4, 'Marca: ' . $orden['marca'], 0, 1);
        }
        if ($orden['modelo']) {
            $this->Cell(0, 4, 'Modelo: ' . $orden['modelo'], 0, 1);
        }
        if ($orden['numero_serie']) {
            $this->Cell(0, 4, 'Serie: ' . $orden['numero_serie'], 0, 1);
        }

        $this->Ln(2);

        // Problema
        $this->SetFont('helvetica', 'B', 8);
        $this->Cell(0, 4, 'Problema Reportado:', 0, 1);
        $this->SetFont('helvetica', '', 8);
        $this->MultiCell(0, 4, $orden['problema_reportado'], 0, 'L');

        $this->Ln(3);
        $this->Line(5, $this->GetY(), $this->ancho - 5, $this->GetY());
        $this->Ln(2);

        // Estado
        $estadoModel = new \App\Models\EstadoOrdenModel();
        $estado = $estadoModel->find($orden['estado_id']);
        if ($estado) {
            $this->SetFont('helvetica', 'B', 8);
            $this->Cell(0, 4, 'Estado: ' . $estado['nombre'], 0, 1);
        }

        if ($orden['fecha_limite_retiro']) {
            $this->SetFont('helvetica', '', 7);
            $this->Cell(0, 3, 'Fecha límite retiro: ' . date('d/m/Y', strtotime($orden['fecha_limite_retiro'])), 0, 1);
        }

        $this->Ln(3);
        $this->Line(5, $this->GetY(), $this->ancho - 5, $this->GetY());
        $this->Ln(2);

        // Pie de página
        $this->SetFont('helvetica', '', 7);
        $this->Cell(0, 3, 'Conserve este comprobante', 0, 1, 'C');
        $this->Cell(0, 3, 'para retirar su equipo', 0, 1, 'C');

        return $this->Output('orden_' . $orden['numero_orden'] . '.pdf', 'I');
    }
}
