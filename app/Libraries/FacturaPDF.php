<?php

namespace App\Libraries;

use TCPDF;

class FacturaPDF extends TCPDF
{
    protected $empresa;
    protected $talonario;
    protected $sucursales;

    public function __construct($empresa = null, $talonario = null, $sucursales = [])
    {
        parent::__construct('P', 'mm', 'A4', true, 'UTF-8', false);
        $this->empresa = $empresa;
        $this->talonario = $talonario;
        $this->sucursales = $sucursales;

        // Desactivar cabeceras y pies de página automáticos de TCPDF
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);

        $this->SetMargins(10, 10, 10);
        $this->SetAutoPageBreak(true, 10);
        $this->SetFont('helvetica', '', 9);
        $this->SetDrawColor(0, 0, 0); // Negro
    }

    public function generarFactura($venta, $detalles, $cliente = null)
    {
        $this->AddPage();

        // --- ENCABEZADO (HEADER) ---
        $yInicio = $this->GetY();
        $xInicio = $this->GetX();

        // Dimensiones
        $wTimbrado = 65;
        $hTimbrado = 40;
        $xTimbrado = 200 - $wTimbrado; // Alinear a la derecha (Margen derecho 10mm -> 210-10=200)

        $wEmpresa = $xTimbrado - 15; // Espacio restante para la empresa (con un gap de 5mm)
        $hEmpresa = 40;

        // --- Recuadro Empresa (Izquierda) ---
        // Dibujar borde redondeado para el bloque de empresa (Estilo Suvinil)
        $this->RoundedRect(10, $yInicio, $wEmpresa, $hEmpresa, 3, '1111');

        // 1. Logo (Dentro del recuadro empresa, a la izquierda)
        if (!empty($this->empresa['logo'])) {
            $logoPath = ROOTPATH . 'public/uploads/empresas/' . $this->empresa['logo'];
            if (file_exists($logoPath)) {
                // Logo centrado verticalmente
                // Espacio para logo: ~35% del ancho del recuadro
                $this->Image($logoPath, 12, $yInicio + 2, 40, 36, '', '', '', false, 300, '', false, false, 0, 'M');
            }
        }

        // 2. Texto Empresa (Dentro del recuadro, a la derecha del logo)
        // Definir zona de texto
        $xText = 10 + 45; // Margen izq + espacio logo
        $wText = $wEmpresa - 45 - 2; // Ancho total menos espacio logo y margen derecho interno

        // --- CALCULO DE ALTURA DINÁMICA ---
        $hTotal = 0;

        // 1. Nombre
        $this->SetFont('helvetica', 'B', 16);
        $nombre = strtoupper($this->empresa['nombre'] ?? 'EMPRESA');
        $nbNombre = $this->getNumLines($nombre, $wText);
        $hNombre = $nbNombre * 6; // 6mm por linea aprox para font 16
        $hTotal += $hNombre;

        // 2. Descripción
        $hDesc = 0;
        if (!empty($this->empresa['descripcion'])) {
            $this->SetFont('helvetica', 'I', 8);
            $nbDesc = $this->getNumLines($this->empresa['descripcion'], $wText);
            $hDesc = $nbDesc * 3.5; // 3.5mm por linea
            $hTotal += $hDesc;
        }

        // Spacer
        $hTotal += 1;

        // 3. Matriz
        $this->SetFont('helvetica', 'B', 8);
        $direccionMatriz = $this->empresa['direccion'] ?? '';
        if (empty($direccionMatriz))
            $direccionMatriz = 'Casa Matriz';
        $textoMatriz = $direccionMatriz . ' - ' . ($this->empresa['ciudad'] ?? '');
        
        $nbMatriz = $this->getNumLines($textoMatriz, $wText);
        $hMatriz = $nbMatriz * 3.5;
        $hTotal += $hMatriz;

        // 4. Sucursales
        $hSucursales = 0;
        $sucursalesAMostrar = [];
        if (!empty($this->sucursales)) {
            $direccionMatrizCheck = $this->empresa['direccion'] ?? '';
            $count = 0;
            foreach ($this->sucursales as $suc) {
                if (trim($suc['direccion']) == trim($direccionMatrizCheck))
                    continue;
                if ($count >= 2)
                    break;
                
                $textoSuc = $suc['nombre'] . ': ' . $suc['direccion'];
                $nbSuc = $this->getNumLines($textoSuc, $wText);
                $hSucursales += ($nbSuc * 3.5);
                
                $sucursalesAMostrar[] = ['texto' => $textoSuc, 'h' => $nbSuc * 3.5];
                $count++;
            }
        }
        $hTotal += $hSucursales;

        // 5. Contacto
        $infoLine = [];
        if (!empty($this->empresa['telefono']))
            $infoLine[] = 'Tel: ' . $this->empresa['telefono'];
        if (!empty($this->empresa['email']))
            $infoLine[] = $this->empresa['email'];

        $hContact = 0;
        if (!empty($infoLine)) {
            $hTotal += 1; // Spacer
            $hContact = 3.5;
            $hTotal += $hContact;
        }

        // Calculo de Y Inicial Centrado
        $topPad = ($hEmpresa - $hTotal) / 2;
        if ($topPad < 2)
            $topPad = 2; // Minimo padding

        $yCurrent = $yInicio + $topPad;

        // --- RENDERIZADO ---

        // Nombre
        $this->SetXY($xText, $yCurrent);
        $this->SetFont('helvetica', 'B', 16);
        $this->MultiCell($wText, 6, $nombre, 0, 'C');
        $yCurrent = $this->GetY();

        // Descripción
        if (!empty($this->empresa['descripcion'])) {
            $this->SetXY($xText, $yCurrent);
            $this->SetFont('helvetica', 'I', 8);
            $this->MultiCell($wText, 3.5, $this->empresa['descripcion'], 0, 'C');
            $yCurrent = $this->GetY();
        }

        $yCurrent += 1; // Spacer

        // Matriz
        $this->SetXY($xText, $yCurrent);
        $this->SetFont('helvetica', 'B', 8);
        $this->SetTextColor(50, 50, 50);

        $this->MultiCell($wText, 3.5, $textoMatriz, 0, 'C');
        $yCurrent = $this->GetY();

        // Sucursales
        $this->SetFont('helvetica', '', 7);
        foreach ($sucursalesAMostrar as $sucData) {
            $this->SetXY($xText, $yCurrent);
            $this->MultiCell($wText, 3.5, $sucData['texto'], 0, 'C');
            $yCurrent = $this->GetY();
        }

        // Contacto
        if (!empty($infoLine)) {
            $yCurrent += 1; // Spacer
            $this->SetXY($xText, $yCurrent);
            $this->SetFont('helvetica', '', 8);
            $this->Cell($wText, 3.5, implode(' | ', $infoLine), 0, 1, 'C');
        }
        $this->SetTextColor(0, 0, 0);


        // --- Recuadro Timbrado (Derecha) ---
        $this->SetXY($xTimbrado, $yInicio);
        // Dibujar borde redondeado
        $this->RoundedRect($xTimbrado, $yInicio, $wTimbrado, $hTimbrado, 3, '1111');

        $this->SetXY($xTimbrado, $yInicio + 3);

        // RUC
        $this->SetFont('helvetica', 'B', 11);
        $this->Cell($wTimbrado, 5, 'R.U.C.: ' . ($this->empresa['ruc'] ?? '0000000-0'), 0, 1, 'C');

        // Timbrado
        $this->SetX($xTimbrado);
        $this->SetFont('helvetica', 'B', 9);
        $timbrado = $venta['timbrado'] ?? $this->talonario['timbrado'] ?? '-------';
        $this->Cell($wTimbrado, 5, 'TIMBRADO Nº: ' . $timbrado, 0, 1, 'C');

        // Fechas
        $this->SetFont('helvetica', '', 8);
        $fechaInicio = isset($this->talonario['fecha_inicio_vigencia']) ? date('d/m/Y', strtotime($this->talonario['fecha_inicio_vigencia'])) : '--/--/----';
        $fechaFin = isset($this->talonario['fecha_fin_vigencia']) ? date('d/m/Y', strtotime($this->talonario['fecha_fin_vigencia'])) : '--/--/----';

        $this->SetX($xTimbrado);
        $this->Cell($wTimbrado, 4, 'Inicio Vigencia: ' . $fechaInicio, 0, 1, 'C');
        $this->SetX($xTimbrado);
        $this->Cell($wTimbrado, 4, 'Fin Vigencia: ' . $fechaFin, 0, 1, 'C');

        // Título Factura
        $this->SetFont('helvetica', 'B', 14);
        $this->Ln(2);
        $this->SetX($xTimbrado);
        $this->Cell($wTimbrado, 6, 'FACTURA', 0, 1, 'C');

        // Número
        $this->SetFont('helvetica', '', 10);
        $establecimiento = $venta['establecimiento'] ?? $this->talonario['establecimiento'] ?? '001';
        $puntoExpedicion = $venta['punto_expedicion'] ?? $this->talonario['punto_expedicion'] ?? '001';
        $numeroFactura = str_pad($venta['numero_factura'] ?? $venta['numero_venta'] ?? '0', 7, '0', STR_PAD_LEFT);

        $this->SetX($xTimbrado);
        $this->Cell($wTimbrado, 6, $establecimiento . '-' . $puntoExpedicion . ' Nº ' . $numeroFactura, 0, 1, 'C');

        // --- DATOS CLIENTE (Boxed) ---
        $this->SetY($yInicio + $hTimbrado + 5);
        $yCliente = $this->GetY();

        // Dibujamos el cuadro contenedor
        // Altura aprox: 3 filas de 6mm = 18mm
        $this->Rect(10, $yCliente, 190, 22);

        $this->SetFont('helvetica', '', 9);

        // Fila 1: Fecha Emision y Condición
        $this->SetXY(12, $yCliente + 2);
        $this->Cell(30, 4, 'Fecha de Emisión:', 0, 0);
        $this->SetFont('helvetica', 'B', 9);
        $this->Cell(80, 4, date('d/m/Y', strtotime($venta['fecha_facturacion'] ?? $venta['fecha_venta'])), 0, 0);

        $this->SetFont('helvetica', '', 9);
        $this->Cell(30, 4, 'Condición de Venta:', 0, 0);
        
        // CORRECCIÓN: Usar tipo_pago en lugar de tipo_venta
        // tipo_venta almacena 'PRODUCTO', 'SERVICIO', etc.
        // tipo_pago almacena 'CONTADO', 'CREDITO', 'MIXTO'
        $condicion = isset($venta['tipo_pago']) ? $venta['tipo_pago'] : ($venta['tipo_venta'] ?? 'CONTADO');
        
        // Si es MIXTO, asumimos Contado salvo que se especifique lógica de cuotas,
        // pero para factura legal suele ser uno u otro.
        // Si el sistema permite Mixto, generalmente se marca Contado si se paga todo en el momento.
        // Si hay saldo pendiente (monto_pendiente > 0), es Crédito.
        
        $esCredito = ($condicion == 'CREDITO' || ($venta['monto_pendiente'] ?? 0) > 0);
        
        $this->SetFont('helvetica', 'B', 9);
        $this->Cell(20, 4, 'Contado (' . (!$esCredito ? 'X' : ' ') . ')', 0, 0);
        $this->Cell(20, 4, 'Crédito (' . ($esCredito ? 'X' : ' ') . ')', 0, 1);

        // Fila 2: Nombre y RUC
        $this->SetXY(12, $yCliente + 8);
        $this->SetFont('helvetica', '', 9);
        $this->Cell(35, 4, 'Nombre o Razón Social:', 0, 0);

        $this->SetFont('helvetica', 'B', 9);
        $nombreCliente = $cliente ? trim(($cliente['nombre'] ?? '') . ' ' . ($cliente['apellido'] ?? '')) : 'Sin Nombre';
        if (empty($nombreCliente) && $cliente)
            $nombreCliente = $cliente['razon_social'] ?? 'Sin Nombre';

        $this->Cell(95, 4, $nombreCliente, 0, 0);

        $this->SetFont('helvetica', '', 9);
        $this->Cell(15, 4, 'RUC / CI:', 0, 0);
        $this->SetFont('helvetica', 'B', 9);
        $rucCliente = $cliente['ruc'] ?? $cliente['documento'] ?? $cliente['ci'] ?? 'X';
        $this->Cell(35, 4, $rucCliente, 0, 1);

        // Fila 3: Dirección y Teléfono
        $this->SetXY(12, $yCliente + 14);
        $this->SetFont('helvetica', '', 9);
        $this->Cell(18, 4, 'Dirección:', 0, 0);
        $this->SetFont('helvetica', '', 9); // Regular
        $this->Cell(112, 4, substr($cliente['direccion'] ?? 'Sin dirección', 0, 70), 0, 0);

        $this->Cell(15, 4, 'Teléfono:', 0, 0);
        $this->Cell(35, 4, $cliente['telefono'] ?? $cliente['celular'] ?? '', 0, 1);


        // --- DETALLES DE LA FACTURA ---
        $yDetalles = $yCliente + 25;
        $this->SetXY(10, $yDetalles);

        // Encabezados
        $this->SetFont('helvetica', 'B', 8);
        $this->SetFillColor(240, 240, 240); // Gris muy claro

        // Anchos columnas
        // Cod(15), Cant(10), Descripcion(75), PrecioUnit(30), Exentas(20), 5%(20), 10%(20)
        $w = [15, 10, 75, 30, 20, 20, 20];

        // Fila Header
        $this->Cell($w[0], 8, 'Código', 1, 0, 'C', true);
        $this->Cell($w[1], 8, 'Cant.', 1, 0, 'C', true);
        $this->Cell($w[2], 8, 'Descripción', 1, 0, 'C', true);
        $this->Cell($w[3], 8, 'Precio Unit.', 1, 0, 'C', true);
        $this->Cell($w[4] + $w[5] + $w[6], 4, 'VALOR DE VENTA', 1, 1, 'C', true);

        // Sub-header para impuestos
        $this->SetXY(10 + $w[0] + $w[1] + $w[2] + $w[3], $yDetalles + 4);
        $this->Cell($w[4], 4, 'Exentas', 1, 0, 'C', true);
        $this->Cell($w[5], 4, '5%', 1, 0, 'C', true);
        $this->Cell($w[6], 4, '10%', 1, 1, 'C', true);

        // Filas
        $this->SetFont('helvetica', '', 8);
        $this->SetY($yDetalles + 8);

        $totalExenta = 0;
        $total5 = 0;
        $total10 = 0;

        foreach ($detalles as $row) {
            $this->SetX(10);

            // Altura dinámica según descripción
            $nb = $this->getNumLines($row['nombre_producto'], $w[2]);
            $h = 5 * $nb;

            // Check page break
            if ($this->GetY() + $h > 260) {
                $this->AddPage();
                $this->SetY(20); // Reset Y
            }

            // Datos
            $cantidad = (float) $row['cantidad'];
            $precioUnit = (float) $row['precio_unitario'];
            $totalItem = (float) $row['total']; // Precio * Cantidad - Descuento (Valor de Venta Real)

            // Clasificar por IVA
            $ivaPorc = (float) ($row['iva'] ?? 10);
            $exenta = '';
            $grav5 = '';
            $grav10 = '';

            if ($ivaPorc == 0) {
                $exenta = number_format($totalItem, 0, ',', '.');
                $totalExenta += $totalItem;
            } elseif ($ivaPorc == 5) {
                $grav5 = number_format($totalItem, 0, ',', '.');
                $total5 += $totalItem;
            } else {
                $grav10 = number_format($totalItem, 0, ',', '.');
                $total10 += $totalItem;
            }

            // Imprimir celda multilínea para descripción, otras celdas normales pero con misma altura
            // Usamos Cell para las fijas y MultiCell para descripción, manejando cursor

            $yStart = $this->GetY();

            $this->Cell($w[0], $h, $row['codigo'] ?? '', 1, 0, 'C');
            $this->Cell($w[1], $h, number_format($cantidad, 2, ',', '.'), 1, 0, 'C');

            $xDesc = $this->GetX();
            $this->MultiCell($w[2], $h / $nb, $row['nombre_producto'], 1, 'L', false, 0); // MultiCell sin ln
            $this->SetXY($xDesc + $w[2], $yStart); // Mover cursor al final de descripcion

            $this->Cell($w[3], $h, number_format($precioUnit, 0, ',', '.'), 1, 0, 'R');
            $this->Cell($w[4], $h, $exenta, 1, 0, 'R');
            $this->Cell($w[5], $h, $grav5, 1, 0, 'R');
            $this->Cell($w[6], $h, $grav10, 1, 1, 'R');
        }

        // Rellenar filas vacías si queda espacio (Opcional, pero da aspecto de factura preimpresa)
        // Calculamos espacio restante hasta el pie
        $yFooter = 250;
        $yCurrent = $this->GetY();
        if ($yCurrent < $yFooter) {
            $hFiller = $yFooter - $yCurrent;
            $this->SetX(10);
            // Dibujar lineas verticales solamente para "cerrar" el cuadro visualmente
            $this->Cell($w[0], $hFiller, '', 'LR', 0);
            $this->Cell($w[1], $hFiller, '', 'LR', 0);
            $this->Cell($w[2], $hFiller, '', 'LR', 0);
            $this->Cell($w[3], $hFiller, '', 'LR', 0);
            $this->Cell($w[4], $hFiller, '', 'LR', 0);
            $this->Cell($w[5], $hFiller, '', 'LR', 0);
            $this->Cell($w[6], $hFiller, '', 'LR', 1);

            // Linea de cierre inferior
            $this->SetX(10);
            $this->Cell(array_sum($w), 0, '', 'T');
        }

        // --- PIE DE PÁGINA (Calculos) ---
        $this->SetY($yFooter);
        $this->SetFont('helvetica', 'B', 8);

        // Totales
        $totalGeneral = $totalExenta + $total5 + $total10;

        // Convertir a letras
        $formatter = new \NumberFormatter("es", \NumberFormatter::SPELLOUT);
        $letras = strtoupper($formatter->format($totalGeneral));
        // Ajuste tipico: "GUARANIES" al final
        if (substr($letras, -9) !== 'GUARANIES') {
            $letras .= ' GUARANIES';
        }

        // --- FILA 1: Son Guaranies y Subtotales ---
        // Izquierda: Son Guaranies
        $this->SetFont('helvetica', 'B', 8);
        $wLeft = $w[0] + $w[1] + $w[2] + $w[3]; // Ancho columnas previas a impuestos

        $this->Cell($wLeft, 6, 'SON GUARANIES: ' . $letras, 1, 0, 'L');

        // Derecha: Subtotales (alineados a columnas)
        $this->SetFont('helvetica', '', 8);
        $this->Cell($w[4], 6, number_format($totalExenta, 0, ',', '.'), 1, 0, 'R');
        $this->Cell($w[5], 6, number_format($total5, 0, ',', '.'), 1, 0, 'R');
        $this->Cell($w[6], 6, number_format($total10, 0, ',', '.'), 1, 1, 'R');

        // --- FILA 2: Firma y Total Pagar ---
        // Izquierda: Recibí Conforme (Espacio para firma)
        $this->SetFont('helvetica', '', 7);
        $this->Cell($wLeft, 10, 'Recibí conforme: ................................................  Firma: ............................................  C.I.: ..........................', 1, 0, 'L');

        // Derecha: Total a Pagar (Caja compuesta)
        $xTotal = $this->GetX();
        $yTotal = $this->GetY();
        $wTotal = $w[4] + $w[5] + $w[6];

        // Dibujamos borde de la celda total
        $this->Rect($xTotal, $yTotal, $wTotal, 10);

        // Etiqueta pequeña
        $this->SetFont('helvetica', '', 6);
        $this->SetXY($xTotal + 1, $yTotal + 1);
        $this->Cell($wTotal - 2, 3, 'Total a Pagar', 0, 1, 'L');

        // Valor Grande
        $this->SetFont('helvetica', 'B', 11);
        $this->SetXY($xTotal + 1, $yTotal + 4);
        $this->Cell($wTotal - 2, 5, number_format($totalGeneral, 0, ',', '.') . ' Gs.', 0, 0, 'R');

        // Mover cursor al final de la fila 2 para la siguiente linea
        $this->SetXY(10, $yTotal + 10); // Margen izq (10) + Altura (10)

        // --- FILA 3: Liquidación IVA ---
        $this->SetFont('helvetica', '', 8);

        // Calculo IVA (Dividido 11 y 21)
        $iva5 = round($total5 / 21, 0);
        $iva10 = round($total10 / 11, 0);
        $totalIva = $iva5 + $iva10;

        $textIva = 'LIQUIDACIÓN DEL IVA:      (5%) ' . number_format($iva5, 0, ',', '.') . '             (10%) ' . number_format($iva10, 0, ',', '.') . '             TOTAL IVA: ' . number_format($totalIva, 0, ',', '.');

        $this->Cell(array_sum($w), 6, $textIva, 1, 1, 'L');

        return $this->Output('factura.pdf', 'S');
    }
}
