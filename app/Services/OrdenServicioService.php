<?php

namespace App\Services;

use App\Models\OrdenServicioModel;
use App\Models\OrdenServicioRepuestoModel;
use App\Models\OrdenServicioManoObraModel;
use App\Models\OrdenServicioHistorialModel;
use App\Models\OrdenServicioImagenModel;
use App\Models\EstadoOrdenModel;
use App\Models\ProductoModel;
use App\Models\StockModel;
use App\Models\EmpresaModel;
use App\Models\AuditoriaModel;
use App\Services\VentaService;
use Config\Database;

class OrdenServicioService
{
    protected $ordenModel;
    protected $repuestoModel;
    protected $manoObraModel;
    protected $historialModel;
    protected $imagenModel;
    protected $estadoModel;
    protected $productoModel;
    protected $stockModel;
    protected $empresaModel;
    protected $auditoriaModel;
    protected $ventaService;
    protected $db;

    public function __construct()
    {
        $this->db = Database::connect();
        // Inyectar conexión DB para compartir transacción
        $this->ordenModel = new OrdenServicioModel($this->db);
        $this->repuestoModel = new OrdenServicioRepuestoModel($this->db);
        $this->manoObraModel = new OrdenServicioManoObraModel($this->db);
        $this->historialModel = new OrdenServicioHistorialModel($this->db);
        $this->imagenModel = new OrdenServicioImagenModel($this->db);
        $this->estadoModel = new EstadoOrdenModel($this->db);
        $this->productoModel = new ProductoModel($this->db);
        $this->stockModel = new StockModel($this->db);
        $this->empresaModel = new EmpresaModel($this->db);
        $this->auditoriaModel = new AuditoriaModel($this->db);
        // VentaService maneja su propia instancia pero idealmente debería aceptar inyección.
        // Por ahora lo dejamos stand-alone ya que factura al final.
        $this->ventaService = new VentaService();
        $this->ventaService->setDb($this->db);
    }

    /**
     * Crear una nueva orden de servicio
     */
    public function create(array $data, array $files = null, int $usuarioId): array
    {
        $this->db->transStart();

        try {
            // 1. Validaciones básicas
            if (empty($data['cliente_id'])) {
                throw new \Exception("El cliente es requerido");
            }

            // 2. Preparar datos
            $empresaId = $data['empresa_id'];

            // Generar número de orden si no existe
            if (empty($data['numero_orden'])) {
                $data['numero_orden'] = $this->generarNumeroOrden($empresaId);
            }

            // Estado inicial: RECEPCIONADA
            $estadoInicial = $this->estadoModel->where('nombre', 'RECEPCIONADA')->first();
            if (!$estadoInicial) {
                // Fallback si no existe el estado por nombre exacto, buscar el primero ordenado
                $estadoInicial = $this->estadoModel->orderBy('orden', 'ASC')->first();
            }

            if (!$estadoInicial) {
                throw new \Exception("No hay estados de orden configurados en el sistema");
            }
            $data['estado_id'] = $estadoInicial['id'];

            // Fecha límite retiro
            if (empty($data['fecha_limite_retiro'])) {
                $empresa = $this->empresaModel->find($empresaId);
                $diasPlazo = $empresa['dias_plazo_retiro'] ?? 90;
                $data['fecha_limite_retiro'] = date('Y-m-d', strtotime("+{$diasPlazo} days"));
            }

            // Campos de auditoría
            $data['usuario_creacion'] = $usuarioId;
            $data['fecha_recepcion'] = date('Y-m-d H:i:s');
            $data['estado_activo'] = true;

            // 3. Insertar Orden
            if (!$this->ordenModel->insert($data)) {
                $errors = $this->ordenModel->errors();
                throw new \Exception("Error al crear la orden: " . implode(', ', $errors));
            }
            $ordenId = $this->ordenModel->getInsertID();

            // 4. Registrar Historial
            $this->logHistorial($ordenId, null, $estadoInicial['id'], 'Orden creada (Recepción)', $usuarioId);

            // 5. Manejar Imágenes
            if ($files && isset($files['imagenes'])) {
                $this->procesarImagenes($ordenId, $files['imagenes'], $usuarioId);
            }

            $this->db->transComplete();

            if ($this->db->transStatus() === false) {
                throw new \Exception("Error de transacción al crear la orden");
            }

            return ['success' => true, 'id' => $ordenId, 'message' => 'Orden creada exitosamente'];

        } catch (\Exception $e) {
            $this->db->transRollback();
            log_message('error', 'OrdenServicioService::create - ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Actualizar orden
     */
    public function update(int $id, array $data, int $usuarioId): array
    {
        $orden = $this->ordenModel->find($id);
        if (!$orden) {
            return ['success' => false, 'message' => 'Orden no encontrada'];
        }

        $this->db->transStart();

        try {
            // Actualizar campos básicos
            if (!$this->ordenModel->update($id, $data)) {
                $errors = $this->ordenModel->errors();
                throw new \Exception("Error al actualizar: " . implode(', ', $errors));
            }

            // Procesar Repuestos si vienen en el array (esperamos un array de repuestos completos)
            if (isset($data['repuestos']) && is_array($data['repuestos'])) {
                $this->syncRepuestos($id, $data['repuestos'], $usuarioId);
            }

            // Procesar Mano de Obra si viene
            if (isset($data['mano_obra']) && is_array($data['mano_obra'])) {
                $this->syncManoObra($id, $data['mano_obra'], $usuarioId);
            }

            // Recalcular totales siempre que se actualiza
            $this->calcularTotales($id);

            $this->db->transComplete();

            return ['success' => true, 'message' => 'Orden actualizada exitosamente'];

        } catch (\Exception $e) {
            $this->db->transRollback();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Cambiar estado de la orden
     */
    public function cambiarEstado(int $ordenId, int $nuevoEstadoId, string $observaciones, int $usuarioId): array
    {
        $orden = $this->ordenModel->find($ordenId);
        if (!$orden) {
            return ['success' => false, 'message' => 'Orden no encontrada'];
        }

        $estadoNuevo = $this->estadoModel->find($nuevoEstadoId);
        if (!$estadoNuevo) {
            return ['success' => false, 'message' => 'Estado no encontrado'];
        }

        $estadoAnteriorId = $orden['estado_id'];

        // Validación de transición (opcional: se puede agregar lógica estricta aquí si se requiere)
        // Por ahora permitimos cualquier transición si es usuario autorizado

        $updateData = [
            'estado_id' => $nuevoEstadoId
        ];

        // Lógica específica por estado
        // Si es estado final (ENTREGADO/RETIRADO), marcar fecha entrega
        if (stripos($estadoNuevo['nombre'], 'ENTREGADO') !== false || stripos($estadoNuevo['nombre'], 'RETIRADO') !== false) {
            $updateData['fecha_entrega'] = date('Y-m-d H:i:s');
            // Al entregar, verificar si está facturado o si requiere pago
            // Esto se gestiona usualmente en el momento de facturación, pero marcamos la fecha aquí
        }

        // Si es TERMINADO/FINALIZADO (Reparación completa), marcar fecha finalización
        if (stripos($estadoNuevo['nombre'], 'TERMINADO') !== false || stripos($estadoNuevo['nombre'], 'FINALIZADO') !== false) {
            $updateData['fecha_finalizacion'] = date('Y-m-d H:i:s');
        }

        // Si es APROBADO (Presupuesto), marcar fecha aprobación
        if (stripos($estadoNuevo['nombre'], 'APROBADO') !== false) {
            $updateData['fecha_aprobacion'] = date('Y-m-d H:i:s');
            $updateData['presupuesto_aprobado'] = true;
        }

        $this->db->transStart();

        try {
            $this->ordenModel->update($ordenId, $updateData);

            // Log Historial
            $this->logHistorial($ordenId, $estadoAnteriorId, $nuevoEstadoId, $observaciones, $usuarioId);

            // Si el estado notifica al cliente, se podría disparar evento aquí (WIP)

            $this->db->transComplete();

            return ['success' => true, 'message' => 'Estado actualizado exitosamente'];

        } catch (\Exception $e) {
            $this->db->transRollback();
            return ['success' => false, 'message' => 'Error al cambiar estado: ' . $e->getMessage()];
        }
    }

    /**
     * Agregar un repuesto individualmente
     */
    public function agregarRepuesto(int $ordenId, array $repuestoData, int $usuarioId): array
    {
        $orden = $this->ordenModel->find($ordenId);
        if (!$orden)
            return ['success' => false, 'message' => 'Orden no encontrada'];

        // Validaciones de producto y precio
        $producto = $this->productoModel->find($repuestoData['producto_id']);
        if (!$producto)
            return ['success' => false, 'message' => 'Producto no encontrado'];

        // Calcular valores
        $cantidad = (float) $repuestoData['cantidad'];
        $precio = (float) $repuestoData['precio']; // Precio unitario
        $subtotal = $precio * $cantidad;

        $tipoIva = $producto['tipo_iva'] ?? 'GRAVADO_10';
        $porcentajeIva = $tipoIva == 'GRAVADO_10' ? 10 : ($tipoIva == 'GRAVADO_5' ? 5 : 0);
        $montoIva = $porcentajeIva > 0 ? ($subtotal * $porcentajeIva) / (100 + $porcentajeIva) : 0;

        // Determinar si consume stock INMEDIATAMENTE o es solo PRESUPUESTO
        // Regla: Si es_presupuesto = true, NO consume stock. Si es parte de la reparación real, sí.
        // Pero por defecto, al agregar repuesto en "Ver Orden", suele ser para Presupuestar.
        // Solo al "Aprobar" o "Iniciar Reparación" se debería reservar/consumir.
        // SIMPLIFICACIÓN: Agregamos como 'es_presupuesto' por defecto si el estado no es 'EN REPARACION'.

        // Por ahora respetamos el flag pasado
        $esPresupuesto = isset($repuestoData['es_presupuesto']) ? (bool) $repuestoData['es_presupuesto'] : true;

        $newItem = [
            'orden_id' => $ordenId,
            'producto_id' => $producto['id'],
            'cantidad' => $cantidad,
            'precio_unitario' => $precio,
            'subtotal' => $subtotal,
            'total' => $subtotal, // Asumiendo precio con IVA
            'monto_iva' => $montoIva,
            'tipo_iva' => $tipoIva,
            'porcentaje_iva' => $porcentajeIva,
            'es_presupuesto' => $esPresupuesto,
            'fue_utilizado' => false, // Se marca true cuando se usa realmente
            'usuario_agrega' => $usuarioId,
            'fecha_agregado' => date('Y-m-d H:i:s')
        ];

        $this->db->transStart();
        try {
            $this->repuestoModel->insert($newItem);
            $this->calcularTotales($ordenId);
            $this->db->transComplete();
            return ['success' => true, 'message' => 'Repuesto agregado correctamente'];
        } catch (\Exception $e) {
            $this->db->transRollback();
            return ['success' => false, 'message' => 'Error al agregar repuesto: ' . $e->getMessage()];
        }
    }

    /**
     * Agregar Mano de Obra
     */
    public function agregarManoObra(int $ordenId, array $moData, int $usuarioId): array
    {
        $orden = $this->ordenModel->find($ordenId);
        if (!$orden)
            return ['success' => false, 'message' => 'Orden no encontrada'];

        $precio = (float) $moData['precio'];
        $subtotal = $precio; // Cantidad 1 por defecto o fija

        // IVA Mano Obra (Servicios suelen ser 10%)
        $tipoIva = 'GRAVADO_10';
        $porcentajeIva = 10;
        $montoIva = ($subtotal * 10) / 110;

        $esPresupuesto = isset($moData['es_presupuesto']) ? (bool) $moData['es_presupuesto'] : true;

        $newItem = [
            'orden_id' => $ordenId,
            'descripcion' => $moData['descripcion'],
            'tipo_servicio' => $moData['tipo_servicio'] ?? 'REPARACION',
            'precio_fijo' => $precio,
            'subtotal' => $subtotal,
            'total' => $subtotal,
            'monto_iva' => $montoIva,
            'tipo_iva' => $tipoIva,
            'porcentaje_iva' => $porcentajeIva,
            'es_presupuesto' => $esPresupuesto,
            'usuario_agrega' => $usuarioId,
            'fecha_agregado' => date('Y-m-d H:i:s')
        ];

        $this->db->transStart();
        try {
            $this->manoObraModel->insert($newItem);
            $this->calcularTotales($ordenId);
            $this->db->transComplete();
            return ['success' => true, 'message' => 'Mano de obra agregada correctamente'];
        } catch (\Exception $e) {
            $this->db->transRollback();
            return ['success' => false, 'message' => 'Error al agregar mano de obra: ' . $e->getMessage()];
        }
    }

    /**
     * Facturar Orden de Servicio
     * Genera una Venta a partir de la Orden
     */
    public function facturar(int $ordenId, int $usuarioId, array $pagoData = []): array
    {
        $orden = $this->ordenModel->find($ordenId);
        if (!$orden)
            return ['success' => false, 'message' => 'Orden no encontrada'];

        // Solo permitir facturar si tiene items que cobrar
        // Items a cobrar: Repuestos utilizados + Mano de obra realizada
        // O si no se marca 'utilizado/realizado' explícitamente, tomamos todo lo que NO sea solo presupuesto descartado.
        // Asunción: Al facturar, cobramos todo lo que está cargado en la orden.

        $repuestos = $this->repuestoModel->where('orden_id', $ordenId)->findAll();
        $manoObra = $this->manoObraModel->where('orden_id', $ordenId)->findAll();

        if (empty($repuestos) && empty($manoObra)) {
            return ['success' => false, 'message' => 'La orden no tiene ítems para facturar'];
        }

        // Preparar Items para VentaService
        $itemsVenta = [];

        foreach ($repuestos as $rep) {
            $prod = $this->productoModel->find($rep['producto_id']);
            $itemsVenta[] = [
                'id' => $rep['producto_id'],
                'type' => 'PRODUCTO',
                'cantidad' => $rep['cantidad'],
                'precio' => $rep['precio_unitario'],
                'descuento' => 0, // O calcular si hubo
                'tipo_iva' => $rep['tipo_iva']
            ];
            // Marcar como utilizado si no lo estaba
            $this->repuestoModel->update($rep['id'], ['fue_utilizado' => true]);
        }

        foreach ($manoObra as $mo) {
            // Mano de obra se pasa como SERVICIO "dummy" o necesitamos un producto ID servicio?
            // VentaService espera productos con ID. 
            // Si VentaService soporta items sin ID de producto real, genial.
            // Si no, podríamos necesitar un producto comodín "Servicio Técnico" o crear items al vuelo.
            // VentaService::procesarItemServicio usa 'orden_servicio_id' pero espera una estructura de item.

            // Simplificación: Pasamos como SERVICIO, VentaService maneja esto.
            $itemsVenta[] = [
                'id' => $ordenId, // ID referencia (orden)
                'type' => 'SERVICIO',
                'nombre' => $mo['descripcion'], // Para descripción
                'codigo' => 'SERV-' . $mo['id'],
                'precio' => $mo['total'], // Total directo como precio unitario (cant 1)
                'cantidad' => 1,
                'tipo_iva' => $mo['tipo_iva']
            ];
            $this->manoObraModel->update($mo['id'], ['fue_realizado' => true]);
        }

        // Datos Cabecera Venta
        $empresa = $this->empresaModel->find($orden['empresa_id']);
        $usaFE = (bool) ($empresa['usa_facturacion_electronica'] ?? false);
        $tipoComprobante = 'FACTURA';

        if ($usaFE) {
            $talonarioModel = new \App\Models\TalonarioModel();
            $talonarioFE = $talonarioModel->where('empresa_id', $orden['empresa_id'])
                ->where('sucursal_id', $orden['sucursal_id'])
                ->where('tipo_comprobante', 'FACTURA_ELECTRONICA')
                ->where('estado', 'ACTIVO')
                ->first();
            if ($talonarioFE) {
                $tipoComprobante = 'FACTURA_ELECTRONICA';
            }
        }

        $cabecera = [
            'empresa_id' => $orden['empresa_id'],
            'sucursal_id' => $orden['sucursal_id'],
            'cliente_id' => $orden['cliente_id'],
            'usuario_id' => $usuarioId,
            'vendedor_id' => $orden['tecnico_asignado'] ?? $usuarioId,
            'orden_servicio_id' => $ordenId,
            'tipo_comprobante' => $tipoComprobante,
            'tipo_venta' => 'SERVICIO',
            'tipo_pago' => $pagoData['tipo_pago'] ?? 'CONTADO', // Tomar de pagoData o defecto CONTADO
            'moneda' => $orden['moneda'],
            'tipo_cambio' => $orden['tipo_cambio'],
            'estado' => 'PENDIENTE'
        ];

        // Formas de pago (si no vienen, asumir EFECTIVO total)
        $formasPago = $pagoData['formas_pago'] ?? [];
        if (empty($formasPago)) {
            // Calcular total para pago por defecto (esto recalcula, VentaService lo volverá a hacer)
            $totalEstimado = $orden['presupuesto_total']; // O costo_real_total
            $formasPago = [
                ['metodo_pago_id' => 1, 'monto' => $totalEstimado, 'moneda' => 'PYG', 'cambio' => 1]
            ];
        }

        // LLAMAR A VENTA SERVICE
        $this->db->transBegin();

        try {
            $resultado = $this->ventaService->crearVenta($cabecera, $itemsVenta, $formasPago, ['id' => $usuarioId]);

            if ($resultado['success']) {
                // Actualizar orden con ID de venta y cambiar estado a ENTREGADO/FACTURADO si aplica
                $this->ordenModel->update($ordenId, [
                    'venta_id' => $resultado['venta_id'],
                    'fecha_facturacion' => date('Y-m-d H:i:s')
                ]);

                // Cambiar estado a ENTREGADO si no lo está (buscando ID)
                $estadoEntregado = $this->estadoModel->like('nombre', 'ENTREGADO')->first();
                if ($estadoEntregado) {
                    $this->cambiarEstado($ordenId, $estadoEntregado['id'], 'Orden Facturada y Entregada automáticamente', $usuarioId);
                }

                $this->db->transCommit();
            } else {
                $this->db->transRollback();
            }

            return $resultado;

        } catch (\Exception $e) {
            $this->db->transRollback();
            return ['success' => false, 'message' => 'Error al facturar: ' . $e->getMessage()];
        }
    }

    /**
     * Recalcular totales de la orden (Presupuesto y Costo Real)
     */
    public function calcularTotales(int $ordenId)
    {
        $repuestos = $this->repuestoModel->where('orden_id', $ordenId)->findAll();
        $manoObra = $this->manoObraModel->where('orden_id', $ordenId)->findAll();

        $totalRepuestos = 0;
        $ivaRepuestos = 0;
        foreach ($repuestos as $r) {
            $totalRepuestos += $r['total'];
            $ivaRepuestos += $r['monto_iva'];
        }

        $totalManoObra = 0;
        $ivaManoObra = 0;
        foreach ($manoObra as $mo) {
            $totalManoObra += $mo['total'];
            $ivaManoObra += $mo['monto_iva'];
        }

        $totalGeneral = $totalRepuestos + $totalManoObra;
        $ivaGeneral = $ivaRepuestos + $ivaManoObra;
        $subtotalGeneral = $totalGeneral - $ivaGeneral; // Aproximado para display

        // Actualizar tanto presupuesto como costo real por ahora (simplificado)
        // En un sistema avanzado separariamos "Presupuesto" de "Lo que realmente se usó".
        $this->ordenModel->update($ordenId, [
            'presupuesto_repuestos' => $totalRepuestos,
            'presupuesto_mano_obra' => $totalManoObra,
            'presupuesto_iva' => $ivaGeneral,
            'presupuesto_subtotal' => $subtotalGeneral,
            'presupuesto_total' => $totalGeneral,
            // Mirroring to costo real for simple flow
            'costo_real_repuestos' => $totalRepuestos,
            'costo_real_mano_obra' => $totalManoObra,
            'costo_real_iva' => $ivaGeneral,
            'costo_real_subtotal' => $subtotalGeneral,
            'costo_real_total' => $totalGeneral,
        ]);
    }

    // --- Helpers Privados ---

    private function generarNumeroOrden($empresaId)
    {
        $ultimo = $this->ordenModel->where('empresa_id', $empresaId)->orderBy('id', 'DESC')->first();
        $num = $ultimo ? ((int) str_replace('OS-', '', $ultimo['numero_orden'] ?? '0') + 1) : 1;
        return 'OS-' . str_pad($num, 8, '0', STR_PAD_LEFT);
    }

    private function logHistorial($ordenId, $estadoAnterior, $estadoNuevo, $observaciones, $usuarioId)
    {
        $this->historialModel->insert([
            'orden_id' => $ordenId,
            'estado_anterior_id' => $estadoAnterior,
            'estado_nuevo_id' => $estadoNuevo,
            'observaciones' => $observaciones,
            'usuario_id' => $usuarioId,
            'fecha_cambio' => date('Y-m-d H:i:s'),
            'ip_address' => service('request')->getIPAddress()
        ]);
    }

    private function procesarImagenes($ordenId, $imagenes, $usuarioId)
    {
        $uploadPath = ROOTPATH . 'public/uploads/ordenes';
        if (!is_dir($uploadPath))
            mkdir($uploadPath, 0755, true);

        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];

        // Si $imagenes no es un array, convertirlo en uno
        if (!is_array($imagenes)) {
            $imagenes = [$imagenes];
        }

        foreach ($imagenes as $imagen) {
            // Si es un array (múltiples archivos con el mismo nombre de campo), procesar cada uno
            if (is_array($imagen)) {
                foreach ($imagen as $file) {
                    $this->procesarArchivoImagen($file, $ordenId, $uploadPath, $allowedTypes, $usuarioId);
                }
            } else {
                // Es un solo archivo
                $this->procesarArchivoImagen($imagen, $ordenId, $uploadPath, $allowedTypes, $usuarioId);
            }
        }
    }

    private function procesarArchivoImagen($imagen, $ordenId, $uploadPath, $allowedTypes, $usuarioId)
    {
        // Verificar que sea un objeto de archivo válido
        if (!is_object($imagen) || !method_exists($imagen, 'isValid')) {
            log_message('warning', 'procesarArchivoImagen: No es un objeto de archivo válido');
            return;
        }

        if ($imagen->isValid() && !$imagen->hasMoved()) {
            if (!in_array($imagen->getMimeType(), $allowedTypes)) {
                log_message('warning', 'Tipo de archivo no permitido: ' . $imagen->getMimeType());
                return;
            }
            if ($imagen->getSize() > 5242880) {
                log_message('warning', 'Archivo demasiado grande: ' . $imagen->getSize());
                return; // 5MB
            }

            $newName = $imagen->getRandomName();
            if ($imagen->move($uploadPath, $newName)) {
                $this->imagenModel->insert([
                    'orden_id' => $ordenId,
                    'url' => $newName,
                    'tipo' => 'ANTES', // Default
                    'visible_cliente' => true,
                    'usuario_creacion' => $usuarioId
                ]);
            }
        }
    }

    private function syncRepuestos($ordenId, $repuestos, $usuarioId)
    {
        // Estrategia: Borrar y recrear es drástico si hay historial.
        // Mejor: Identificar nuevos, existentes y eliminados.
        // Por simplicidad para este refactor: Si el ID viene, update. Si no, insert.
        // Los que no vienen, delete? 
        // PRECAUCIÓN: Si borramos sin cuidado podemos romper stock si ya se descontó.
        // Dado que es un refactor "seguro", asumimos que 'addRepuesto' se usa para incremental
        // y esto se usa tal vez para edición masiva.
        // Si la edición masiva es desde un formulario que manda TODO el estado actual:

        $existingIds = array_column($this->repuestoModel->where('orden_id', $ordenId)->findAll(), 'id');
        $incomingIds = array_filter(array_column($repuestos, 'id')); // Solo IDs validos

        // IDs a borrar
        $toDelete = array_diff($existingIds, $incomingIds);
        if (!empty($toDelete)) {
            $this->repuestoModel->delete($toDelete);
        }

        foreach ($repuestos as $rep) {
            $data = [
                'orden_id' => $ordenId,
                'producto_id' => $rep['producto_id'],
                'cantidad' => $rep['cantidad'],
                'precio_unitario' => $rep['precio_unitario'],
                'subtotal' => $rep['subtotal'],
                'total' => $rep['total'],
                'monto_iva' => $rep['monto_iva'] ?? 0,
                'tipo_iva' => $rep['tipo_iva'] ?? 'GRAVADO_10',
            ];

            if (isset($rep['id']) && in_array($rep['id'], $existingIds)) {
                $this->repuestoModel->update($rep['id'], $data);
            } else {
                $data['usuario_agrega'] = $usuarioId;
                $this->repuestoModel->insert($data);
            }
        }
    }

    private function syncManoObra($ordenId, $manoObra, $usuarioId)
    {
        $existingIds = array_column($this->manoObraModel->where('orden_id', $ordenId)->findAll(), 'id');
        $incomingIds = array_filter(array_column($manoObra, 'id'));

        $toDelete = array_diff($existingIds, $incomingIds);
        if (!empty($toDelete)) {
            $this->manoObraModel->delete($toDelete);
        }

        foreach ($manoObra as $mo) {
            $data = [
                'orden_id' => $ordenId,
                'descripcion' => $mo['descripcion'],
                'precio_fijo' => $mo['precio'], // Normalizar nombre campo
                'total' => $mo['precio'],
                'monto_iva' => ($mo['precio'] * 10) / 110,
            ];

            if (isset($mo['id']) && in_array($mo['id'], $existingIds)) {
                $this->manoObraModel->update($mo['id'], $data);
            } else {
                $data['usuario_agrega'] = $usuarioId;
                $this->manoObraModel->insert($data);
            }
        }
    }
    /**
     * Agregar imágenes a una orden existente
     */
    public function agregarImagenes(int $ordenId, array $imagenes, int $usuarioId): array
    {
        $orden = $this->ordenModel->find($ordenId);
        if (!$orden) {
            return ['success' => false, 'message' => 'Orden no encontrada'];
        }

        try {
            $count = 0;
            if (!empty($imagenes)) {
                $this->procesarImagenes($ordenId, $imagenes, $usuarioId);
                $count = count($imagenes);
            }
            return ['success' => true, 'message' => "$count imágenes procesadas"];
        } catch (\Exception $e) {
            log_message('error', 'Error subiendo imágenes: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Error al procesar imágenes: ' . $e->getMessage()];
        }
    }

    /**
     * Eliminar una imagen
     */
    public function eliminarImagen(int $imagenId, int $usuarioId): array
    {
        $imagen = $this->imagenModel->find($imagenId);
        if (!$imagen) {
            return ['success' => false, 'message' => 'Imagen no encontrada'];
        }

        try {
            // Eliminar archivo físico
            $filepath = ROOTPATH . 'public/uploads/ordenes/' . $imagen['url'];
            if (file_exists($filepath)) {
                unlink($filepath);
            }

            // Eliminar registro
            $this->imagenModel->delete($imagenId);
            return ['success' => true, 'message' => 'Imagen eliminada'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Error al eliminar imagen: ' . $e->getMessage()];
        }
    }

    /**
     * Eliminar un repuesto de la orden
     */
    public function eliminarRepuesto(int $repuestoId): array
    {
        $repuesto = $this->repuestoModel->find($repuestoId);
        if (!$repuesto) {
            return ['success' => false, 'message' => 'Repuesto no encontrado'];
        }

        $this->db->transStart();
        try {
            $this->repuestoModel->delete($repuestoId);
            $this->calcularTotales($repuesto['orden_id']);

            // Obtener nuevos totales
            $orden = $this->ordenModel->find($repuesto['orden_id']);
            $nuevosTotales = [
                'repuestos' => $orden['presupuesto_repuestos'],
                'mano_obra' => $orden['presupuesto_mano_obra'],
                'total' => $orden['presupuesto_total']
            ];

            $this->db->transComplete();

            return [
                'success' => true,
                'message' => 'Repuesto eliminado',
                'nuevos_totales' => $nuevosTotales
            ];
        } catch (\Exception $e) {
            $this->db->transRollback();
            return ['success' => false, 'message' => 'Error al eliminar repuesto: ' . $e->getMessage()];
        }
    }

    /**
     * Eliminar una mano de obra de la orden
     */
    public function eliminarManoObra(int $manoObraId): array
    {
        $mo = $this->manoObraModel->find($manoObraId);
        if (!$mo) {
            return ['success' => false, 'message' => 'Item no encontrado'];
        }

        $this->db->transStart();
        try {
            $this->manoObraModel->delete($manoObraId);
            $this->calcularTotales($mo['orden_id']);

            // Obtener nuevos totales
            $orden = $this->ordenModel->find($mo['orden_id']);
            $nuevosTotales = [
                'repuestos' => $orden['presupuesto_repuestos'],
                'mano_obra' => $orden['presupuesto_mano_obra'],
                'total' => $orden['presupuesto_total']
            ];

            $this->db->transComplete();

            return [
                'success' => true,
                'message' => 'Item eliminado',
                'nuevos_totales' => $nuevosTotales
            ];
        } catch (\Exception $e) {
            $this->db->transRollback();
            return ['success' => false, 'message' => 'Error al eliminar item: ' . $e->getMessage()];
        }
    }
}
