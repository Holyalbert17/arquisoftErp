<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Orden de Servicio #
        <?= $orden['numero_orden'] ?>
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
            border-bottom: 1px dashed #000;
            padding-bottom: 5px;
        }

        .title {
            font-size: 16px;
            font-weight: bold;
            margin: 5px 0;
        }

        .info {
            margin-bottom: 10px;
        }

        .info-row {
            display: flex;
            margin-bottom: 2px;
        }

        .label {
            font-weight: bold;
            width: 80px;
        }

        .value {
            flex: 1;
        }

        .section-title {
            font-weight: bold;
            border-bottom: 1px dashed #000;
            margin: 10px 0 5px 0;
        }

        .footer {
            margin-top: 20px;
            text-align: center;
            font-size: 10px;
        }

        .barcode {
            text-align: center;
            margin: 10px 0;
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
        <div style="margin-top: 5px;">RECEPCIÓN DE EQUIPO</div>
        <div style="font-size: 14px; font-weight: bold; margin-top: 5px;">
            <?= $orden['numero_orden'] ?>
        </div>
    </div>

    <div class="info">
        <div class="info-row">
            <span class="label">Fecha:</span>
            <span class="value">
                <?= date('d/m/Y H:i', strtotime($orden['fecha_recepcion'])) ?>
            </span>
        </div>
        <div class="info-row">
            <span class="label">Cliente:</span>
            <span class="value">
                <?php
                if ($orden['cliente']['tipo_cliente'] == 'PERSONA') {
                    echo esc($orden['cliente']['nombre'] . ' ' . ($orden['cliente']['apellido'] ?? ''));
                } else {
                    echo esc($orden['cliente']['nombre_fantasia'] ?? $orden['cliente']['razon_social']);
                }
                ?>
            </span>
        </div>
        <div class="info-row">
            <span class="label">RUC/CI:</span>
            <span class="value">
                <?= esc($orden['cliente']['ruc'] ?? $orden['cliente']['ci']) ?>
            </span>
        </div>
        <div class="info-row">
            <span class="label">Recepción:</span>
            <span class="value">
                <?= esc($orden['usuario_recepcion_nombre']) ?>
            </span>
        </div>
    </div>

    <div class="section-title">DATOS DEL EQUIPO</div>
    <div class="info">
        <div><strong>Tipo:</strong>
            <?= esc($orden['tipo_equipo']) ?>
        </div>
        <div><strong>Marca/Modelo:</strong>
            <?= esc($orden['marca'] . ' / ' . $orden['modelo']) ?>
        </div>
        <?php if ($orden['numero_serie']): ?>
            <div><strong>Serie:</strong>
                <?= esc($orden['numero_serie']) ?>
            </div>
        <?php endif; ?>
        <?php if ($orden['imei']): ?>
            <div><strong>IMEI:</strong>
                <?= esc($orden['imei']) ?>
            </div>
        <?php endif; ?>
        <?php if ($orden['accesorios']): ?>
            <div><strong>Accesorios:</strong>
                <?= esc($orden['accesorios']) ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="section-title">PROBLEMA REPORTADO</div>
    <div class="info">
        <?= nl2br(esc($orden['problema_reportado'])) ?>
    </div>

    <?php if ($orden['observaciones_cliente']): ?>
        <div class="section-title">OBSERVACIONES</div>
        <div class="info">
            <?= nl2br(esc($orden['observaciones_cliente'])) ?>
        </div>
    <?php endif; ?>

    <div class="footer">
        <p>
            Al firmar este documento, el cliente acepta los términos y condiciones del servicio técnico.
            La empresa no se hace responsable por pérdida de datos.
            Equipos no retirados en 90 días serán considerados abandonados.
        </p>
        <br><br>
        <div style="border-top: 1px solid #000; width: 80%; margin: 0 auto;"></div>
        <p>Firma del Cliente</p>
    </div>
</body>

</html>