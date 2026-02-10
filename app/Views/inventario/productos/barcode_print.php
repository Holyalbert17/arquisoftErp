<!DOCTYPE html>
<html>

<head>
    <title>Imprimir Código de Barras</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            text-align: center;
            padding: 20px;
        }

        .barcode-container {
            border: 1px solid #ddd;
            padding: 20px;
            display: inline-block;
            margin: 10px;
            border-radius: 8px;
        }

        .product-name {
            font-size: 14px;
            margin-bottom: 5px;
            font-weight: bold;
        }

        .product-price {
            font-size: 12px;
            margin-top: 5px;
        }

        @media print {
            .no-print {
                display: none;
            }

            .barcode-container {
                border: none;
                page-break-inside: avoid;
            }
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.0/dist/JsBarcode.all.min.js"></script>
</head>

<body>
    <div class="no-print">
        <button onclick="window.print()"
            style="padding: 10px 20px; font-size: 16px; cursor: pointer; background: #007bff; color: white; border: none; border-radius: 5px;">
            <i class="fas fa-print"></i> Imprimir
        </button>
        <button onclick="window.close()"
            style="padding: 10px 20px; font-size: 16px; cursor: pointer; background: #6c757d; color: white; border: none; border-radius: 5px; margin-left: 10px;">
            Cerrar
        </button>
        <hr>
    </div>

    <div class="barcode-container">
        <div class="product-name">
            <?= esc($producto['nombre']) ?>
        </div>
        <svg id="barcode"></svg>
        <div class="product-price">
            <?= number_format($producto['precio_venta'], 0, ',', '.') ?> Gs.
        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            JsBarcode("#barcode", "<?= esc($producto['codigo_barra']) ?>", {
                format: "CODE128",
                width: 2,
                height: 50,
                displayValue: true
            });

            // Auto print if requested via query param
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('autoprint')) {
                window.print();
            }
        });
    </script>
</body>

</html>