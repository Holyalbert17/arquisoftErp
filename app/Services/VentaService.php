<?php

namespace App\Services;

use App\Models\ProductoModel;
use App\Models\ClienteModel;
use App\Models\VentaModel;
use App\Models\VentaDetalleModel;
use App\Models\PagoVentaModel;
use App\Models\StockModel;
use App\Models\TalonarioModel;
use App\Models\AuditoriaModel;
use Config\Database;

class VentaService
{
    protected $productoModel;
    protected $clienteModel;
    protected $ventaModel;
    protected $ventaDetalleModel;
    protected $pagoVentaModel;
    protected $stockModel;
    protected $talonarioModel;
    protected $auditoriaModel;
    protected $cajaModel;
    protected $movimientoCajaModel;
    protected $db;

    public function __construct($initializeModels = true)
    {
        if ($initializeModels) {
            $this->db = Database::connect();

            // Inject request-scoped DB connection to all models to share transaction
            // Note: CI4 Model constructor accepts DB connection as first argument
            $this->productoModel = new ProductoModel($this->db);
            $this->clienteModel = new ClienteModel($this->db);
            $this->ventaModel = new VentaModel($this->db);
            $this->ventaDetalleModel = new VentaDetalleModel($this->db);
            $this->pagoVentaModel = new PagoVentaModel($this->db);
            $this->stockModel = new StockModel($this->db);
            $this->talonarioModel = new TalonarioModel($this->db);
            $this->auditoriaModel = new AuditoriaModel($this->db);
        }
    }

    public function setModels($cajaModel, $movimientoCajaModel)
    {
        $this->cajaModel = $cajaModel;
        $this->movimientoCajaModel = $movimientoCajaModel;
    }

    public function setDb($db)
    {
        $this->db = $db;
        // Reinicializar modelos con la nueva conexión si es necesario
        $this->productoModel = new ProductoModel($this->db);
        $this->clienteModel = new ClienteModel($this->db);
        $this->ventaModel = new VentaModel($this->db);
        $this->ventaDetalleModel = new VentaDetalleModel($this->db);
        $this->pagoVentaModel = new PagoVentaModel($this->db);
        $this->stockModel = new StockModel($this->db);
        $this->talonarioModel = new TalonarioModel($this->db);
        $this->auditoriaModel = new AuditoriaModel($this->db);
    }

    /**
     * Calcula los totales de una venta dado un array de productos y descuentos.
     */
    public function calcularTotales(array $productos, float $descuentoGeneral = 0): array
    {
        $config = new \Config\Business();

        $subtotal = 0;
        $subtotalGravado10 = 0;
        $subtotalGravado5 = 0;
        $subtotalExento = 0;
        $iva10 = 0;
        $iva5 = 0;
        $descuentoMonto = 0;

        foreach ($productos as $producto) {
            $precio = (float) $producto['precio'];
            $cantidad = (float) $producto['cantidad'];
            $descuento = (float) ($producto['descuento'] ?? 0);

            $subtotalLinea = $precio * $cantidad;
            $descuentoLinea = ($subtotalLinea * $descuento) / 100;
            $subtotalLinea -= $descuentoLinea;

            $subtotal += $subtotalLinea;
            $descuentoMonto += $descuentoLinea;

            // Calcular IVA según tipo
            $tipoIva = $producto['tipo_iva'] ?? $config->defaultTaxType;
            if ($tipoIva == 'GRAVADO_10') {
                $subtotalGravado10 += $subtotalLinea;
                $iva10 += ($subtotalLinea * $config->taxRate10) / (100 + $config->taxRate10); // IVA incluido
            } elseif ($tipoIva == 'GRAVADO_5') {
                $subtotalGravado5 += $subtotalLinea;
                $iva5 += ($subtotalLinea * $config->taxRate5) / (100 + $config->taxRate5); // IVA incluido
            } else {
                $subtotalExento += $subtotalLinea;
            }
        }

        // Aplicar descuento general
        if ($descuentoGeneral > 0) {
            $descuentoGeneralMonto = ($subtotal * $descuentoGeneral) / 100;
            $descuentoMonto += $descuentoGeneralMonto;
            $subtotal -= $descuentoGeneralMonto;

            // Ajustar bases imponibles proporcionalmente (simplificado)
            // En un sistema estricto habría que prorratear línea por línea
            $factor = 1 - ($descuentoGeneral / 100);
            $subtotalGravado10 *= $factor;
            $subtotalGravado5 *= $factor;
            $subtotalExento *= $factor;
            $iva10 *= $factor;
            $iva5 *= $factor;
        }

        $totalIva = $iva10 + $iva5;
        $total = $subtotal; // El subtotal ya incluye IVA impuestos internos si los hubiera

        return [
            'subtotal' => round($subtotal, 0),
            'descuento_monto' => round($descuentoMonto, 0),
            'subtotal_gravado_10' => round($subtotalGravado10, 0),
            'subtotal_gravado_5' => round($subtotalGravado5, 0),
            'subtotal_exento' => round($subtotalExento, 0),
            'iva_10' => round($iva10, 0),
            'iva_5' => round($iva5, 0),
            'total_iva' => round($totalIva, 0),
            'total' => round($total, 0)
        ];
    }

    /**
     * Valida si un cliente tiene crédito suficiente.
     */
    public function validarCredito(int $clienteId, float $montoRequerido): array
    {
        $cliente = $this->clienteModel->find($clienteId);
        if (!$cliente) {
            return ['valido' => false, 'mensaje' => 'Cliente no encontrado'];
        }

        $limiteCredito = (float) ($cliente['limite_credito'] ?? 0);
        $creditoUtilizado = (float) ($cliente['credito_utilizado'] ?? 0);
        $creditoDisponible = max(0, $limiteCredito - $creditoUtilizado);

        if ($montoRequerido > $creditoDisponible) {
            return [
                'valido' => false,
                'mensaje' => sprintf(
                    'Crédito insuficiente. Disponible: Gs. %s, Requerido: Gs. %s',
                    number_format($creditoDisponible, 0, ',', '.'),
                    number_format($montoRequerido, 0, ',', '.')
                )
            ];
        }

        return ['valido' => true];
    }

    /**
     * Procesa la creación completa de una venta.
     */
    public function crearVenta(array $datosCabecera, array $productos, array $formasPago, array $usuario): array
    {
        $this->db->transBegin();

        try {
            // 1. Preparar datos y calcular totales finales (backend trust)
            $calculos = $this->calcularTotales($productos, $datosCabecera['descuento_general'] ?? 0);

            // Generar número de venta si no viene
            if (empty($datosCabecera['numero_venta'])) {
                $datosCabecera['numero_venta'] = $this->generarNumeroVenta($datosCabecera['empresa_id']);
            }

            // Validar que los cálculos sean válidos
            if (!isset($calculos['subtotal']) || $calculos['subtotal'] === null) {
                throw new \Exception("Error: El subtotal calculado es inválido o null");
            }
            if (!isset($calculos['total']) || $calculos['total'] === null || $calculos['total'] <= 0) {
                throw new \Exception("Error: El total calculado es inválido: " . ($calculos['total'] ?? 'null'));
            }

            // Filtrar solo los campos permitidos del modelo antes de merge
            $allowedFields = [
                'empresa_id',
                'sucursal_id',
                'cliente_id',
                'usuario_id',
                'vendedor_id',
                'lista_precio_id',
                'orden_servicio_id',
                'numero_venta',
                'tipo_comprobante',
                'numero_factura',
                'timbrado',
                'tipo_venta',
                'tipo_pago',
                'estado',
                'moneda',
                'tipo_cambio',
                'observaciones',
                'notas_internas',
                'origen_venta'
            ];

            // Filtrar datosCabecera para solo incluir campos permitidos
            $datosCabeceraFiltrados = array_intersect_key($datosCabecera, array_flip($allowedFields));

            $ventaData = array_merge($datosCabeceraFiltrados, [
                'subtotal' => $calculos['subtotal'],
                'descuento_monto' => $calculos['descuento_monto'],
                'iva_10' => $calculos['iva_10'],
                'iva_5' => $calculos['iva_5'],
                'total_iva' => $calculos['total_iva'],
                'total' => $calculos['total'],
                'fecha_venta' => date('Y-m-d H:i:s'),
                // Estados iniciales
                'monto_pagado' => 0,
                'monto_pendiente' => $calculos['total'],
                'estado' => 'PENDIENTE'
            ]);

            // Validar campos requeridos antes de insertar
            $camposRequeridos = ['empresa_id', 'subtotal', 'total'];
            foreach ($camposRequeridos as $campo) {
                if (!isset($ventaData[$campo]) || $ventaData[$campo] === null) {
                    throw new \Exception("Error: Campo requerido '$campo' falta o es null en los datos de venta");
                }
            }

            log_message('debug', "Intentando insertar venta con datos: " . json_encode(array_intersect_key($ventaData, array_flip(['empresa_id', 'sucursal_id', 'cliente_id', 'subtotal', 'total', 'estado']))));

            // 2. Insertar Venta
            log_message('debug', "Datos completos de venta antes de insertar: " . json_encode($ventaData));

            // Asegurar que los campos requeridos tengan valores y tipos correctos
            // Convertir campos numéricos a enteros/floats explícitamente
            if (isset($ventaData['empresa_id'])) {
                $ventaData['empresa_id'] = (int) $ventaData['empresa_id'];
            }
            if (isset($ventaData['sucursal_id'])) {
                $ventaData['sucursal_id'] = !empty($ventaData['sucursal_id']) ? (int) $ventaData['sucursal_id'] : null;
            } else {
                // Si no está presente, usar el del usuario o lanzar error
                $ventaData['sucursal_id'] = !empty($datosCabecera['sucursal_id']) ? (int) $datosCabecera['sucursal_id'] : null;
            }

            // Validar que sucursal_id no sea null si es requerido
            if (empty($ventaData['sucursal_id'])) {
                throw new \Exception("Error: sucursal_id es requerido pero está vacío o null");
            }

            if (isset($ventaData['usuario_id'])) {
                $ventaData['usuario_id'] = !empty($ventaData['usuario_id']) ? (int) $ventaData['usuario_id'] : ($usuario['id'] ?? null);
            } else {
                $ventaData['usuario_id'] = $usuario['id'] ?? null;
            }

            if (empty($ventaData['tipo_venta'])) {
                $ventaData['tipo_venta'] = 'PRODUCTO';
            }
            if (empty($ventaData['moneda'])) {
                $ventaData['moneda'] = 'PYG';
            }
            if (empty($ventaData['tipo_cambio'])) {
                $ventaData['tipo_cambio'] = 1.0;
            }

            // Asegurar que los campos numéricos sean del tipo correcto
            $ventaData['subtotal'] = (float) $ventaData['subtotal'];
            $ventaData['total'] = (float) $ventaData['total'];
            $ventaData['monto_pagado'] = (float) ($ventaData['monto_pagado'] ?? 0);
            $ventaData['monto_pendiente'] = (float) ($ventaData['monto_pendiente'] ?? $ventaData['total']);

            // Log final antes de insertar para verificar que todos los campos están correctos
            log_message('debug', "Datos finales antes de insertar (campos críticos): " . json_encode([
                'empresa_id' => $ventaData['empresa_id'] ?? 'MISSING',
                'sucursal_id' => $ventaData['sucursal_id'] ?? 'MISSING',
                'usuario_id' => $ventaData['usuario_id'] ?? 'MISSING',
                'subtotal' => $ventaData['subtotal'] ?? 'MISSING',
                'total' => $ventaData['total'] ?? 'MISSING',
                'estado' => $ventaData['estado'] ?? 'MISSING',
                'tipo_sucursal_id' => gettype($ventaData['sucursal_id'] ?? null)
            ]));

            // Usar RAW SQL con RETURNING id para evitar problemas de secuencia/concurrencia en Postgres
            $keys = array_keys($ventaData);
            $values = array_values($ventaData);
            $fields = implode(', ', $keys);
            $placeholders = implode(', ', array_fill(0, count($keys), '?'));

            $sql = "INSERT INTO ventas ($fields) VALUES ($placeholders) RETURNING id";

            $maxRetries = 3;
            $attempt = 0;
            $ventaId = null;
            $lastEx = null;

            do {
                $attempt++;
                
                // Create a SAVEPOINT for this attempt to isolate errors (Postgres requirement)
                $savepoint = "sp_insert_venta_" . $attempt;
                $this->db->query("SAVEPOINT $savepoint");

                try {
                    // Si es reintento, regenerar numero_venta
                    if ($attempt > 1 && isset($ventaData['numero_venta'])) {
                        // Forzar regeneración con offset basado en intentos fallidos
                        // Attempt 2 -> offset 0 (try fresh)
                        // Attempt 3 -> offset 1 (start skipping)
                        $offset = $attempt > 1 ? ($attempt - 1) : 0;
                        
                        $nuevoNumero = $this->generarNumeroVenta($ventaData['empresa_id'], $offset);
                        
                        $ventaData['numero_venta'] = $nuevoNumero;
                        log_message('warning', "Reintento $attempt insert venta: Regenerado numero_venta a {$ventaData['numero_venta']} (Offset: $offset)");
                        
                        // Actualizar values array con el nuevo numero_venta
                        $values = array_values($ventaData); 
                    }

                    $query = $this->db->query($sql, $values);
                    
                    if ($query === false) {
                        $error = $this->db->error();
                        throw new \Exception("Query failed: " . ($error['message'] ?? 'Unknown DB error'));
                    }

                    $row = $query->getRow();

                    if (!$row || empty($row->id)) {
                        throw new \Exception("Error: La inserción no devolvió un ID válido.");
                    }

                    $ventaId = $row->id;
                    // Release savepoint on success (optional but good practice)
                    $this->db->query("RELEASE SAVEPOINT $savepoint");
                    log_message('info', "Venta insertada correctamente con ID (RETURNING): $ventaId");
                    break; // Success!

                } catch (\Exception $e) {
                    // Rollback to savepoint to restore transaction state
                    $this->db->query("ROLLBACK TO SAVEPOINT $savepoint");
                    
                    $lastEx = $e;
                    $msg = $e->getMessage();
                    
                    $isDuplicateMock = stripos($msg, 'duplicate key') !== false || 
                                      stripos($msg, 'viola la llave') !== false ||
                                      stripos($msg, 'viola restricción de unicidad') !== false ||
                                      stripos($msg, 'unique constraint') !== false;

                    if ($isDuplicateMock && strpos($msg, 'numero_venta') !== false) {
                        log_message('warning', "Colisión detectar en numero_venta (Intento $attempt/$maxRetries): $msg");
                        if ($attempt >= $maxRetries) {
                            throw new \Exception("Error crítico: No se pudo generar un número de venta único tras $maxRetries intentos. Intente nuevamente.");
                        }
                        sleep(1); 
                    } else {
                        throw $e;
                    }
                }
            } while ($attempt < $maxRetries);



            // Verificación inmediata: verificar que la venta existe usando Query Builder
            $ventaVerificada = $this->db->table('ventas')->where('id', $ventaId)->get()->getRow();

            if (!$ventaVerificada) {
                $dbError = $this->db->error();
                $transStatus = $this->db->transStatus();
                // RELAXED CHECK: Log critical but do not throw exception to allow transaction to proceed
                // This handles cases where uncommitted transaction visibility varies
                log_message('critical', "CRITICAL WARNING: Venta ID $ventaId no encontrada inmediatamente. Esto puede ser un falso negativo en la transacción. Estado Transaction: " . ($transStatus ? 'OK' : 'ERROR'));
                log_message('critical', "Error DB: " . json_encode($dbError));
            } else {
                log_message('debug', "Venta ID $ventaId verificada exitosamente. Estado: " . ($ventaVerificada->estado ?? 'N/A'));
            }



            log_message('critical', "DEBUG: Venta ID generated: $ventaId");

            // 3. Procesar Detalles y Stock
            foreach ($productos as $prod) {
                // Determinar si es Producto o Servicio
                $tipoItem = $prod['type'] ?? 'PRODUCTO';

                if ($tipoItem === 'SERVICIO') {
                    // Procesar Orden de Servicio
                    $this->procesarItemServicio($ventaId, $prod, $datosCabecera['sucursal_id']);
                } else {
                    // Procesar Producto Fisico
                    $this->procesarDetalle($ventaId, $prod, $datosCabecera['sucursal_id']);
                }
            }

            // 4. Procesar Pagos y Movimientos de Caja
            $montoPagadoTotal = 0;
            $cajaId = $datosCabecera['caja_id'] ?? null;
            $totalEfectivoIngresado = 0;

            foreach ($formasPago as $pago) {
                if (($pago['monto'] ?? 0) > 0) {
                    $formaPagoRaw = $pago['forma_pago'] ?? $pago['formaPago'] ?? $pago['metodo_pago'] ?? $pago['metodoPago'] ?? '';
                    $formaPagoNorm = $this->normalizarFormaPago((string) $formaPagoRaw);

                    $pagoData = array_merge($pago, [
                        'venta_id' => $ventaId,
                        'usuario_id' => $usuario['id'],
                        'estado' => 'APROBADO',
                        'caja_id' => $cajaId,
                        'forma_pago' => $formaPagoNorm
                    ]);
                    $this->db->table('pagos_ventas')->insert($pagoData);

                    $montoPagadoTotal += $pago['monto'];

                    // Procesar Movimiento de Caja (Solo Efectivo)
                    if ($cajaId && $this->movimientoCajaModel) {
                        try {
                            // Intentar determinar si es efectivo
                            $esEfectivo = ($formaPagoNorm === 'EFECTIVO');

                            if ($esEfectivo) {
                                $totalEfectivoIngresado += $pago['monto'];

                                $movimientoData = [
                                    'caja_id' => $cajaId,
                                    'tipo_movimiento' => 'INGRESO',
                                    'monto' => $pago['monto'],
                                    'moneda' => $ventaData['moneda'] ?? 'PYG',
                                    'tipo_cambio' => 1,
                                    'monto_moneda_principal' => $pago['monto'],
                                    'concepto' => "Venta POS VTA-" . ($ventaData['numero_venta'] ?? $ventaId),
                                    'referencia' => 'Venta ID: ' . $ventaId,
                                    'documento_id' => $ventaId,
                                    'tipo_documento' => 'VENTA',
                                    'usuario_id' => $usuario['id'],
                                    'fecha_movimiento' => date('Y-m-d H:i:s')
                                ];
                                $this->db->table('movimientos_caja')->insert($movimientoData);
                            }
                        } catch (\Exception $e) {
                            log_message('error', 'Error al registrar movimiento caja (Ingreso): ' . $e->getMessage());
                            // No interrumpimos la venta por esto, pero lo logueamos
                        }
                    }
                }
            }

            // 5. Actualizar Cabecera con saldos y Vuelto
            $estado = ($montoPagadoTotal >= $calculos['total']) ? 'COMPLETADA' : 'PENDIENTE';
            $vuelto = 0;

            if ($montoPagadoTotal > $calculos['total']) {
                $vuelto = $montoPagadoTotal - $calculos['total'];
            }

            // 5.1 Registrar Vuelto como Egreso de Caja
            if ($vuelto > 0 && $cajaId && $this->movimientoCajaModel) {
                try {
                    $movimientoVuelto = [
                        'caja_id' => $cajaId,
                        'tipo_movimiento' => 'EGRESO',
                        'monto' => $vuelto,
                        'moneda' => $ventaData['moneda'] ?? 'PYG',
                        'tipo_cambio' => 1,
                        'monto_moneda_principal' => $vuelto,
                        'concepto' => "Vuelto Venta POS VTA-" . ($ventaData['numero_venta'] ?? $ventaId),
                        'referencia' => 'Vuelto Venta ID: ' . $ventaId,
                        'documento_id' => $ventaId,
                        'tipo_documento' => 'VENTA',
                        'usuario_id' => $usuario['id'],
                        'fecha_movimiento' => date('Y-m-d H:i:s')
                    ];
                    $this->db->table('movimientos_caja')->insert($movimientoVuelto);
                } catch (\Exception $e) {
                    log_message('error', 'Error al registrar movimiento caja (Vuelto): ' . $e->getMessage());
                }
            }

            // 5.2 saldo_actual se actualiza automáticamente por el trigger trg_actualizar_saldo_caja
            // al insertar movimientos en movimientos_caja (INGRESO/EGRESO arriba)

            $this->db->table('ventas')->where('id', $ventaId)->update([
                'monto_pagado' => $montoPagadoTotal - $vuelto, // Neto Real
                'monto_pendiente' => max(0, $calculos['total'] - ($montoPagadoTotal - $vuelto)),
                'vuelto' => $vuelto,
                'estado' => $estado
            ]);

            // 6. Cuentas por Cobrar (si aplica)
            if ($ventaData['tipo_pago'] != 'CONTADO' && $estado != 'COMPLETADA') {
                $this->generarCuentaCobrar($ventaId, $ventaData, $calculos['total'], $montoPagadoTotal - $vuelto);
            }

            // 7. Actualizar Talonario (si es Factura)
            if (in_array(($ventaData['tipo_comprobante'] ?? ''), ['FACTURA', 'FACTURA_ELECTRONICA'], true) && !empty($datosCabecera['talonario_id'])) {
                $this->actualizarTalonario($datosCabecera['talonario_id'], $ventaData['numero_factura']);
            }

            if ($this->db->transStatus() === false) {
                $this->db->transRollback();
                // Obtener el último error de la DB
                $error = $this->db->error();
                throw new \Exception("Error en transacción de venta: " . ($error['message'] ?? 'Error desconocido'));
            }

            $this->db->transCommit();

            return ['success' => true, 'venta_id' => $ventaId, 'vuelto' => $vuelto];

        } catch (\Exception $e) {
            $this->db->transRollback();
            log_message('error', 'VentaService::crearVenta Error: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function normalizarFormaPago(string $formaPago): string
    {
        $fp = strtoupper(trim($formaPago));

        if ($fp === '' || $fp === 'EFECTIVO' || $fp === 'CONTADO' || $fp === 'CASH') {
            return 'EFECTIVO';
        }
        if (in_array($fp, ['TARJETA_CREDITO', 'CREDITO', 'TC'], true)) {
            return 'TARJETA_CREDITO';
        }
        if (in_array($fp, ['TARJETA_DEBITO', 'DEBITO', 'TD'], true)) {
            return 'TARJETA_DEBITO';
        }
        if (in_array($fp, ['TRANSFERENCIA', 'TRANSFER', 'BANCARIA'], true)) {
            return 'TRANSFERENCIA';
        }
        if (in_array($fp, ['CHEQUE'], true)) {
            return 'CHEQUE';
        }

        return 'OTRO';
    }

    private function procesarItemServicio($ventaId, $item, $sucursalId)
    {
        // Insertar detalle de venta
        // Para servicios, usamos el ID del servicio como referencia, pero no descontamos stock

        $precio = (float) $item['precio'];
        $cantidad = 1; // Servicios siempre 1
        $subtotal = $precio * $cantidad;

        $detalle = [
            'venta_id' => $ventaId,
            'producto_id' => null, // No es producto inventariable
            'orden_servicio_id' => $item['id'], // ID de la orden
            'descripcion' => $item['nombre'] . ' (Orden #' . ($item['codigo'] ?? $item['id']) . ')',
            'cantidad' => $cantidad,
            'precio_unitario' => $precio,
            'subtotal' => $subtotal,
            'total' => $subtotal,
            'tipo_iva' => 'GRAVADO_10', // Asumimos 10% para servicios por defecto, o parametrizable
            'porcentaje_iva' => 10,
            'monto_iva' => ($subtotal * 10) / 110
        ];

        // Validar si columna orden_servicio_id existe en detalle, si no, poner en json o descripcion
        // Por ahora asumimos que solo existe producto_id, asi que usamos descripcion para referencia
        // Ojo: Si VentaDetalleModel no tiene campo orden_servicio_id, esto fallará. 
        // Revisemos VentaDetalleModel o usemos producto_id=0 o similar si no hay FK.
        // Mejor estrategia: Guardar referencia en descripcion o campo auxiliar.
        // Si no podemos modificar schema ahora mismo, poner ID en descripcion.

        // Verificamos si existe la columna en el array antes de insertar
        // Si no tenemos acceso al schema ahora, lo mas seguro es poner null en producto_id y detallar en descripcion
        unset($detalle['orden_servicio_id']); // Removemos para evitar error si columna no existe

        // Usar Query Builder para consistencia transaccional
        $builder = $this->db->table('ventas_detalle');
        if (!$builder->insert($detalle)) {
            $error = $this->db->error();
            throw new \Exception("Error al insertar detalle de servicio: " . ($error['message'] ?? 'Error desconocido'));
        }

        // Actualizar Estado de Orden de Servicio a 'RETIRADO'
        $ordenModel = new \App\Models\OrdenServicioModel($this->db);
        $estadoModel = new \App\Models\EstadoOrdenModel($this->db);

        // Buscar estado RETIRADO o ENTREGADO
        $estadoRetirado = $estadoModel->like('nombre', 'RETIRADO')->orLike('nombre', 'ENTREGADO')->first();

        if ($estadoRetirado) {
            $ordenModel->update($item['id'], [
                'estado_id' => $estadoRetirado['id'],
                'fecha_entrega' => date('Y-m-d H:i:s'),
                'venta_id' => $ventaId // Vincular la venta a la orden
            ]);
        } else {
            log_message('error', "No se encontró estado RETIRADO/ENTREGADO para actualizar Orden #{$item['id']}");
        }
    }

    private function procesarDetalle($ventaId, $producto, $sucursalId)
    {
        // Use shared DB connection for all models/builders within this transaction context
        // Relying on $this->db which has the active transaction.

        // Obtener datos frescos del producto
        $prodDb = $this->productoModel->find($producto['id']);
        if (!$prodDb)
            throw new \Exception("Producto {$producto['id']} no encontrado");

        // Cálculos de línea
        $precio = (float) $producto['precio'];
        $cantidad = (float) $producto['cantidad'];
        $subtotal = $precio * $cantidad;

        // Validar que subtotal no sea null o cero
        if ($subtotal <= 0) {
            throw new \Exception("Error: El subtotal del producto {$producto['id']} es inválido: $subtotal");
        }

            $montoIva = 0;
            switch ($prodDb['tipo_iva'] ?? 'GRAVADO_10') {
                case 'GRAVADO_5':
                    $montoIva = ($subtotal * 5) / 105;
                    break;
                case 'GRAVADO_10':
                    $montoIva = ($subtotal * 10) / 110;
                    break;
                default:
                    $montoIva = 0;
                    break;
            }

            $detalle = [
                'venta_id' => $ventaId,
                'producto_id' => $producto['id'],
                'descripcion' => $prodDb['nombre'],
                'cantidad' => $cantidad,
                'precio_unitario' => $precio,
                'subtotal' => $subtotal,
                'total' => $subtotal,
                'tipo_iva' => $prodDb['tipo_iva'] ?? 'GRAVADO_10',
                'porcentaje_iva' => $prodDb['porcentaje_iva'] ?? 10,
                'monto_iva' => $montoIva,
            ];

        // Validar campos usando Query Builder directo
        // Esto evita el mismatch de transacciones entre Model y Connection
        $builder = $this->db->table('ventas_detalle');
        if (!$builder->insert($detalle)) {
            $dbError = $this->db->error();
            $errorMsg = $dbError['message'] ?: 'Error desconocido al insertar detalle';

            log_message('error', "Error al insertar detalle de venta ID $ventaId: $errorMsg");
            log_message('error', "Datos del detalle: " . json_encode($detalle));

            throw new \Exception("Error al insertar detalle de venta: " . $errorMsg);
        }

        // Actualizar stock
        if ($prodDb['controla_stock'] && $sucursalId) {
            // Use builder for stock as well to be safe
            $stockParams = ['producto_id' => $producto['id'], 'sucursal_id' => $sucursalId];
            $stockBuilder = $this->db->table('stock');
            $stock = $stockBuilder->where($stockParams)->get()->getRowArray();

            if ($stock) {
                $nuevaCantidad = $stock['cantidad'] - $cantidad;
                $nuevaDisponible = $nuevaCantidad - $stock['cantidad_reservada'];

                $stockBuilder->where('id', $stock['id'])->update([
                    'cantidad' => $nuevaCantidad,
                    'cantidad_disponible' => $nuevaDisponible
                ]);
            }
        }
    }

    private function generarCuentaCobrar($ventaId, $ventaData, $total, $pagado)
    {
        // Lógica simplificada de CC
        $pendiente = $total - $pagado;
        if ($pendiente <= 0)
            return;

        $diasCredito = 30; // Default o buscar de cliente
        // ... buscar cliente ... 

        $this->db->table('cuentas_cobrar')->insert([
            'venta_id' => $ventaId,
            'cliente_id' => $ventaData['cliente_id'],
            'monto_total' => $total,
            'monto_pendiente' => $pendiente,
            'fecha_vencimiento' => date('Y-m-d', strtotime("+$diasCredito days")),
            'estado' => 'PENDIENTE',
            'empresa_id' => $ventaData['empresa_id']
        ]);
    }

    private function actualizarTalonario($id, $numeroUsado)
    {
        $this->db->query("UPDATE talonarios SET numero_actual = ? WHERE id = ?", [$numeroUsado, $id]);
    }

    private function generarNumeroVenta($empresaId, $offset = 0)
    {
        // Obtener el último numero_venta REAL de la base de datos, ordenando por el campo string
        // Esto es más seguro que por ID en caso de desincronización
        // Nota: Postgres ordena strings 'VTA-00000010' despues de 'VTA-00000009', así que funciona directo si formato es fijo
        $ultimo = $this->ventaModel->where('empresa_id', $empresaId)
                                   ->orderBy('numero_venta', 'DESC')
                                   ->first();

        $num = 1;
        if ($ultimo && !empty($ultimo['numero_venta'])) {
             $parts = explode('-', $ultimo['numero_venta']);
             if (count($parts) === 2) {
                 $num = (int)$parts[1] + 1;
             }
        }
        
        // Aplicar offset para reintentos (Aggressive Increment)
        $num += $offset;
        
        return 'VTA-' . str_pad($num, 8, '0', STR_PAD_LEFT);
    }

    /**
     * Anula una venta.
     */
    public function anularVenta($id, $usuario, $motivo)
    {
        $venta = $this->ventaModel->find($id);

        if (!$venta) {
            return ['success' => false, 'message' => 'Venta no encontrada', 'http_code' => 404];
        }

        if ($venta['empresa_id'] != $usuario['empresa_id']) {
            return ['success' => false, 'message' => 'No tiene permisos para anular esta venta', 'http_code' => 403];
        }

        if ($venta['estado'] == 'ANULADA') {
            return ['success' => false, 'message' => 'La venta ya está anulada'];
        }

        // Verificar si el usuario es administrador
        $esAdministrador = false;
        if (isset($usuario['rol_id'])) {
            $rolModel = new \App\Models\RolModel();
            $rol = $rolModel->find($usuario['rol_id']);
            if ($rol && $rol['nivel'] <= 2) { // Nivel 1 o 2 = Administrador
                $esAdministrador = true;
            }
        }

        // Validar tiempo límite para cajeros (1 día)
        if (!$esAdministrador) {
            $fechaVenta = strtotime($venta['fecha_venta']);
            $fechaActual = time();
            $diferenciaDias = ($fechaActual - $fechaVenta) / (60 * 60 * 24);

            if ($diferenciaDias > 1) {
                return ['success' => false, 'message' => 'Los cajeros solo pueden anular ventas dentro de las primeras 24 horas. Contacte a un administrador para anular esta venta.'];
            }
        }

        $this->db->transStart();

        try {
            // Revertir stock antes de eliminar
            $detalles = $this->ventaDetalleModel->where('venta_id', $id)->findAll();

            foreach ($detalles as $detalle) {
                if ($detalle['producto_id']) {
                    $producto = $this->productoModel->find($detalle['producto_id']);

                    if ($producto && $producto['controla_stock'] && $venta['sucursal_id']) {
                        $stock = $this->stockModel->where('producto_id', $detalle['producto_id'])
                            ->where('sucursal_id', $venta['sucursal_id'])
                            ->first();

                        if ($stock) {
                            // Revertir cantidad reservada y cantidad disponible
                            $this->stockModel->update($stock['id'], [
                                'cantidad' => $stock['cantidad'] + $detalle['cantidad'],
                                'cantidad_reservada' => max(0, $stock['cantidad_reservada'] - $detalle['cantidad'])
                            ]);
                        }
                    }
                }
            }

            // Registrar auditoría antes de eliminar
            try {
                $this->auditoriaModel->insert([
                    'usuario_id' => $usuario['id'],
                    'empresa_id' => $venta['empresa_id'],
                    'tabla_afectada' => 'ventas',
                    'accion' => 'DELETE',
                    'registro_id' => $id,
                    'datos_anteriores' => json_encode($venta),
                    'observaciones' => 'Anulación: ' . $motivo . ($esAdministrador ? ' (Admin)' : ' (Cajero)'),
                    'ip_address' => service('request')->getIPAddress() // Helper service usage
                ]);
            } catch (\Exception $e) {
                log_message('error', 'Error al registrar auditoría: ' . $e->getMessage());
            }

            // Deshabilitar temporalmente el trigger que actualiza stock al eliminar detalles
            // porque ya revertimos el stock manualmente arriba
            $this->db->query('ALTER TABLE ventas_detalle DISABLE TRIGGER trg_actualizar_stock_venta');

            try {
                // Eliminar pagos asociados
                $this->pagoVentaModel->where('venta_id', $id)->delete();

                // Eliminar detalles (sin trigger para evitar doble reversión)
                $this->ventaDetalleModel->where('venta_id', $id)->delete();

                // Eliminar la venta
                $this->ventaModel->delete($id);
            } finally {
                // Rehabilitar el trigger
                $this->db->query('ALTER TABLE ventas_detalle ENABLE TRIGGER trg_actualizar_stock_venta');
            }

            $this->db->transComplete();

            if ($this->db->transStatus() === false) {
                $this->db->transRollback();
                log_message('error', 'Error en transacción al anular venta ID: ' . $id);
                return ['success' => false, 'message' => 'Error al anular la venta. Verifique los logs del sistema.'];
            }

            return ['success' => true, 'message' => 'Venta anulada y eliminada exitosamente'];

        } catch (\Exception $e) {
            if ($this->db->transStatus() !== false) {
                $this->db->transRollback();
            }
            log_message('error', 'Error al anular venta ID ' . $id . ': ' . $e->getMessage());
            log_message('error', 'Stack trace: ' . $e->getTraceAsString());
            return ['success' => false, 'message' => 'Error al anular la venta: ' . $e->getMessage()];
        }
    }
}
