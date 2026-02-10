<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Presupuesto #
        <?= esc($orden['numero_orden']) ?>
    </title>
    <style>
        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 12px;
            margin: 0;
            padding: 10px;
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
        }

        .header {
            text-align: center;
            margin-bottom: 10px;
        }

        .title {
            font-weight: bold;
            font-size: 14px;
        }

        .separator {
            border-bottom: 1px dashed #000;
            margin: 5px 0;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
        }

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        .bold {
            font-weight: bold;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            text-align: left;
            border-bottom: 1px solid #000;
        }

        td {
            vertical-align: top;
        }

        @media print {
            body {
                margin: 0;
            }
        }
    </style>
</head>

<body onload="window.print()">
    <div class="header">
        <div class="title">
            <?= esc($empresa['nombre']) ?>
        </div>
        <div>
            <?= esc($sucursal['nombre']) ?>
        </div>
        <div>Tel:
            <?= esc($sucursal['telefono']) ?>
        </div>
        <div>
            <?= esc($sucursal['direccion']) ?>
        </div>
    </div>

    <div class="separator"></div>

    <div class="text-center bold">PRESUPUESTO DE SERVICIO</div>
    <div class="text-center">Nro:
        <?= esc($orden['numero_orden']) ?>
    </div>
    <div class="text-center">Fecha:
        <?= date('d/m/Y H:i') ?>
    </div>

    <div class="separator"></div>

    <div><span class="bold">Cliente:</span>
        <?= esc($orden['cliente']['nombre'] . ' ' . ($orden['cliente']['apellido'] ?? '')) ?>
    </div>
    <div><span class="bold">RUC/CI:</span>
        <?= esc($orden['cliente']['ruc'] ?? $orden['cliente']['ci'] ?? 'N/A') ?>
    </div>

    <div class="separator"></div>

    <div><span class="bold">Equipo:</span>
        <?= esc($orden['tipo_equipo']) ?>
        <?= esc($orden['marca']) ?>
        <?= esc($orden['modelo']) ?>
    </div>
    <div><span class="bold">Nro Serie/IMEI:</span>
        <?= esc($orden['numero_serie'] ?? $orden['imei']) ?>
    </div>
    <div><span class="bold">Problema:</span>
        <?= esc($orden['problema_reportado']) ?>
    </div>

    <div class="separator"></div>

    <!-- Detalles del Presupuesto -->
    <table>
        <thead>
            <tr>
                <th>Desc.</th>
                <th class="text-right">Cant</th>
                <th class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($orden['repuestos'] as $repuesto): ?>
                <tr>
                    <td>
                        <?= esc($repuesto['producto_nombre'] ?? 'Repuesto') ?>
                    </td>
                    <td class="text-right">
                        <?= $repuesto['cantidad'] ?>
                    </td>
                    <td class="text-right">
                        <?= number_format($repuesto['total'], 0, ',', '.') ?>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php foreach ($orden['mano_obra'] as $mo): ?>
                <tr>
                    <td>
                        <?= esc($mo['descripcion']) ?>
                    </td>
                    <td class="text-right">1</td>
                    <td class="text-right">
                        <?= number_format($mo['total'], 0, ',', '.') ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="separator"></div>

    <div class="info-row">
        <span class="bold">TOTAL PRESUPUESTO:</span>
        <span class="bold">
            <?= number_format($orden['presupuesto_total'], 0, ',', '.') ?> Gs.
        </span>
    </div>

    <div class="separator"></div>

    <div class="text-center">
        Validez del presupuesto: 15 días.<br>
        Gracias por su preferencia.
    </div>
</body>

</html>