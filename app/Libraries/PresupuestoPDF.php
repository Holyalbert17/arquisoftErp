<?php

namespace App\Libraries;

use TCPDF;

class PresupuestoPDF extends TCPDF
{
    protected $empresa;

    public function __construct($empresa = null)
    {
        parent::__construct('P', 'mm', 'A4', true, 'UTF-8', false);
        $this->empresa = $empresa;

        // Desactivar cabeceras y pies de página automáticos de TCPDF
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);

        $this->SetMargins(15, 15, 15);
        $this->SetAutoPageBreak(true, 15);
        $this->SetFont('helvetica', '', 9);
    }

    public function generarPresupuesto($orden, $repuestos, $manoObra)
    {
        $this->AddPage();

        // --- CABECERA ---
        $yInicio = $this->GetY();

        // Empresa
        $this->SetFont('helvetica', 'B', 20);
        $this->Cell(0, 10, strtoupper($this->empresa['nombre_fantasia'] ?? $this->empresa['nombre']), 0, 1, 'C');

        $this->SetFont('helvetica', '', 9);
        $this->Cell(0, 5, ($this->empresa['direccion'] ?? '') . ' - ' . ($this->empresa['ciudad'] ?? ''), 0, 1, 'C');
        $this->Cell(0, 5, 'Tel: ' . ($this->empresa['telefono'] ?? ''), 0, 1, 'C');
        $this->Cell(0, 5, 'RUC: ' . ($this->empresa['ruc'] ?? ''), 0, 1, 'C');

        $this->Ln(5);
        $this->Line(15, $this->GetY(), 195, $this->GetY());
        $this->Ln(5);

        // Título del Documento
        $this->SetFont('helvetica', 'B', 16);
        $this->Cell(0, 10, 'PRESUPUESTO DE SERVICIO TÉCNICO', 0, 1, 'C');
        $this->SetFont('helvetica', 'B', 12);
        $this->Cell(0, 8, 'Orden Nº: ' . $orden['numero_orden'], 0, 1, 'C');

        $this->Ln(5);

        // --- DATOS DEL CLIENTE Y FECHA ---
        $yDatos = $this->GetY();
        $this->SetFont('helvetica', 'B', 10);
        $this->Cell(0, 6, 'DATOS DEL CLIENTE', 0, 1, 'L');
        $this->SetFont('helvetica', '', 10);

        $nombreCliente = $orden['cliente']['nombre_fantasia'] ?? $orden['cliente']['razon_social'];
        if (isset($orden['cliente']['tipo_cliente']) && $orden['cliente']['tipo_cliente'] == 'PERSONA') {
            $nombreCliente = $orden['cliente']['nombre'] . ' ' . ($orden['cliente']['apellido'] ?? '');
        }

        $this->Cell(100, 6, 'Cliente: ' . $nombreCliente, 0, 0);
        $this->Cell(80, 6, 'Fecha: ' . date('d/m/Y', strtotime($orden['fecha_creacion'])), 0, 1, 'R');

        $this->Cell(100, 6, 'RUC/CI: ' . ($orden['cliente']['ruc'] ?? $orden['cliente']['ci'] ?? 'N/A'), 0, 0);
        $this->Cell(80, 6, 'Vencimiento: ' . date('d/m/Y', strtotime('+15 days')), 0, 1, 'R'); // Validez 15 dias ejemplo

        $this->Ln(3);

        // --- DATOS DEL EQUIPO ---
        $this->SetFont('helvetica', 'B', 10);
        $this->Cell(0, 6, 'DATOS DEL EQUIPO', 0, 1, 'L');
        $this->SetFont('helvetica', '', 10);

        $this->Cell(90, 6, 'Equipo: ' . $orden['tipo_equipo'], 0, 0);
        $this->Cell(90, 6, 'Marca/Modelo: ' . $orden['marca'] . ' / ' . $orden['modelo'], 0, 1);

        if (!empty($orden['numero_serie']) || !empty($orden['imei'])) {
            $serie = $orden['numero_serie'] ? 'Serie: ' . $orden['numero_serie'] : '';
            $imei = $orden['imei'] ? 'IMEI: ' . $orden['imei'] : '';
            $sep = ($serie && $imei) ? ' / ' : '';
            $this->Cell(0, 6, $serie . $sep . $imei, 0, 1);
        }

        $this->MultiCell(0, 6, 'Problema Reportado: ' . $orden['problema_reportado'], 0, 'L');
        if (!empty($orden['diagnostico'])) {
            $this->MultiCell(0, 6, 'Diagnóstico: ' . $orden['diagnostico'], 0, 'L');
        }

        $this->Ln(5);

        // --- DETALLE DEL PRESUPUESTO ---
        $this->SetFont('helvetica', 'B', 10);
        $this->Cell(0, 8, 'DETALLE DE COSTOS', 1, 1, 'C', 0, '', 0, false, 'T', 'M');

        // Encabezados
        $this->SetFillColor(240, 240, 240);
        $this->SetFont('helvetica', 'B', 9);
        $this->Cell(15, 7, 'Cant.', 1, 0, 'C', 1);
        $this->Cell(105, 7, 'Descripción', 1, 0, 'L', 1);
        $this->Cell(30, 7, 'Precio Unit.', 1, 0, 'R', 1);
        $this->Cell(30, 7, 'Subtotal', 1, 1, 'R', 1);

        $this->SetFont('helvetica', '', 9);

        $totalGeneral = 0;

        // Repuestos
        if (!empty($repuestos)) {
            foreach ($repuestos as $rep) {
                // Obtener nombre producto si viene en array, sino buscarlo (pero aquí asumimos que ya viene el join o nombre)
                // En el controller se pasan los datos. De momento asumamos que el controller inyecta nombre, 
                // o que $rep tiene 'producto_nombre'
                $nombre = $rep['producto_nombre'] ?? 'Repuesto #' . $rep['producto_id'];

                $this->Cell(15, 6, $rep['cantidad'], 1, 0, 'C');
                $this->Cell(105, 6, substr($nombre, 0, 60), 1, 0, 'L');
                $this->Cell(30, 6, number_format($rep['precio_unitario'], 0, ',', '.'), 1, 0, 'R');
                $this->Cell(30, 6, number_format($rep['total'] ?? ($rep['cantidad'] * $rep['precio_unitario']), 0, ',', '.'), 1, 1, 'R');

                $totalGeneral += ($rep['total'] ?? ($rep['cantidad'] * $rep['precio_unitario']));
            }
        }

        // Mano de Obra
        if (!empty($manoObra)) {
            foreach ($manoObra as $mo) {
                // Cantidad suele ser 1 o horas
                $cant = $mo['horas_estimadas'] ?? 1;
                $precio = $mo['precio_hora'] ?? $mo['precio_fijo'] ?? 0;

                $this->Cell(15, 6, $cant, 1, 0, 'C');
                $this->Cell(105, 6, substr($mo['descripcion'], 0, 60), 1, 0, 'L');
                $this->Cell(30, 6, number_format($precio, 0, ',', '.'), 1, 0, 'R');
                $this->Cell(30, 6, number_format($mo['total'] ?? ($cant * $precio), 0, ',', '.'), 1, 1, 'R');

                $totalGeneral += ($mo['total'] ?? ($cant * $precio));
            }
        }

        // Rellenar si está vacío
        if (empty($repuestos) && empty($manoObra)) {
            $this->Cell(180, 6, 'Sin detalles cargados', 1, 1, 'C');
        }

        // --- TOTALES ---
        $this->SetFont('helvetica', 'B', 10);
        $this->Cell(150, 8, 'TOTAL A PAGAR', 1, 0, 'R');
        $this->Cell(30, 8, number_format($totalGeneral, 0, ',', '.') . ' Gs.', 1, 1, 'R');

        // Total en letras
        $this->Ln(5);
        $formatter = new \NumberFormatter("es", \NumberFormatter::SPELLOUT);
        $letras = strtoupper($formatter->format($totalGeneral)) . ' GUARANÍES';
        $this->SetFont('helvetica', 'I', 9);
        $this->Cell(0, 6, 'Son: ' . $letras, 0, 1, 'L');

        // --- PIE / TERMINOS ---
        $this->Ln(15);
        $this->SetFont('helvetica', 'B', 9);
        $this->Cell(0, 6, 'TÉRMINOS Y CONDICIONES', 0, 1, 'L');
        $this->SetFont('helvetica', '', 8);
        $this->MultiCell(0, 4, "1. Este presupuesto tiene una validez de 15 días.\n2. La aprobación de este presupuesto implica la aceptación de los trabajos descritos.\n3. Los precios incluyen IVA.\n4. La fecha de entrega está sujeta a la disponibilidad de repuestos.", 0, 'L');

        return $this->Output('presupuesto_' . $orden['numero_orden'] . '.pdf', 'S');
    }
}
