<?php

namespace App\Models;

use CodeIgniter\Model;

class OrdenServicioModel extends Model
{
    protected $table            = 'ordenes_servicio';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'empresa_id', 'sucursal_id', 'cliente_id', 'usuario_recepcion',
        'tecnico_asignado', 'numero_orden', 'estado_id', 'prioridad',
        'tipo_equipo', 'marca', 'modelo', 'numero_serie', 'imei', 'color',
        'estado_fisico', 'accesorios', 'patron_desbloqueo', 'clave_equipo',
        'problema_reportado', 'diagnostico', 'solucion_aplicada',
        'observaciones_tecnico', 'observaciones_internas', 'observaciones_cliente',
        'fecha_recepcion', 'fecha_diagnostico', 'fecha_aprobacion',
        'fecha_inicio_reparacion', 'fecha_finalizacion', 'fecha_entrega',
        'fecha_limite_retiro', 'fecha_vencimiento_presupuesto', 'moneda',
        'tipo_cambio', 'presupuesto_diagnostico', 'presupuesto_repuestos',
        'presupuesto_mano_obra', 'presupuesto_otros', 'presupuesto_subtotal',
        'presupuesto_descuento', 'presupuesto_iva', 'presupuesto_total',
        'costo_real_repuestos', 'costo_real_mano_obra', 'costo_real_otros',
        'costo_real_subtotal', 'costo_real_iva', 'costo_real_total',
        'anticipo_monto', 'saldo_pendiente', 'venta_id', 'fecha_facturacion',
        'garantia_dias', 'fecha_vencimiento_garantia', 'terminos_garantia',
        'presupuesto_aprobado', 'cliente_contactado', 'cliente_notificado_listo',
        'requiere_autorizacion', 'autorizado_por', 'fecha_autorizacion',
        'estado_activo', 'fecha_creacion', 'fecha_modificacion', 'usuario_creacion'
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'fecha_creacion';
    protected $updatedField  = 'fecha_modificacion';

    // Callbacks
    protected $allowCallbacks = true;
    protected $beforeInsert   = ['normalizeData'];
    protected $beforeUpdate   = ['normalizeData'];

    /**
     * Normalizar datos antes de insert/update
     */
    protected function normalizeData(array $data)
    {
        if (!isset($data['data'])) {
            return $data;
        }
        
        // Campos que deben ser integer
        $integerFields = [
            'empresa_id', 'sucursal_id', 'cliente_id', 'usuario_recepcion',
            'tecnico_asignado', 'estado_id', 'autorizado_por', 'usuario_creacion',
            'venta_id', 'garantia_dias'
        ];
        
        // Campos que pueden ser null o integer
        $nullableIntegerFields = [
            'tecnico_asignado', 'autorizado_por', 'usuario_creacion', 'venta_id'
        ];
        
        foreach ($data['data'] as $field => $value) {
            if (in_array($field, $integerFields)) {
                if (in_array($field, $nullableIntegerFields)) {
                    // Campos opcionales, pueden ser null
                    if ($value === null || $value === '' || $value === '0') {
                        $data['data'][$field] = null;
                    } else {
                        if (!is_numeric($value)) {
                            log_message('error', "OrdenServicioModel::normalizeData - Campo {$field} tiene valor no numérico: " . var_export($value, true));
                            throw new \RuntimeException("El campo {$field} debe ser numérico o null. Valor recibido: " . var_export($value, true));
                        }
                        $data['data'][$field] = (int)$value;
                    }
                } else {
                    // Campos requeridos, siempre deben ser integer
                    if (!is_numeric($value) && $value !== null && $value !== '') {
                        log_message('error', "OrdenServicioModel::normalizeData - Campo {$field} tiene valor no numérico: " . var_export($value, true));
                        throw new \RuntimeException("El campo {$field} debe ser numérico. Valor recibido: " . var_export($value, true));
                    }
                    $data['data'][$field] = (int)$value;
                }
            }
        }
        
        // Log para debug (solo en desarrollo)
        if (ENVIRONMENT === 'development') {
            log_message('debug', 'OrdenServicioModel::normalizeData - Datos normalizados: ' . json_encode($data['data']));
        }
        
        return $data;
    }

    /**
     * Obtener órdenes pendientes
     */
    public function getPendientes($empresaId, $sucursalId = null)
    {
        try {
            $db = \Config\Database::connect();
            
            // Verificar si las tablas existen
            if (!$db->tableExists('ordenes_servicio') || !$db->tableExists('clientes')) {
                return [];
            }
            
            $builder = $db->table('ordenes_servicio o')
                ->select('o.id, o.numero_orden, o.prioridad, 
                         CASE 
                             WHEN c.tipo_cliente = \'PERSONA\' THEN (c.nombre || \' \' || COALESCE(c.apellido, \'\'))
                             ELSE COALESCE(c.nombre_fantasia, c.razon_social, c.nombre, \'N/A\')
                         END as cliente_nombre, 
                         o.tipo_equipo, 
                         COALESCE(eo.nombre, \'PENDIENTE\') as estado')
                ->join('clientes c', 'c.id = o.cliente_id', 'left');
            
            // Solo hacer join con estados_orden si la tabla existe
            if ($db->tableExists('estados_orden')) {
                $builder->join('estados_orden eo', 'eo.id = o.estado_id', 'left')
                        ->where('COALESCE(eo.es_final, false)', false);
            } else {
                // Si no existe la tabla estados_orden, usar un valor por defecto
                $builder->selectAdd('o.estado_id, \'PENDIENTE\' as estado');
            }
            
            $builder->where('o.empresa_id', $empresaId)
                    ->where('o.estado_activo', true);
                    
            if ($sucursalId) {
                $builder->where('o.sucursal_id', $sucursalId);
            }
            
            return $builder->orderBy('o.fecha_recepcion', 'DESC')
                           ->limit(10)
                           ->get()
                           ->getResultArray();
        } catch (\Exception $e) {
            // Si hay error, retornar array vacío
            return [];
        }
    }
}
