<?php

namespace App\Models;

use CodeIgniter\Model;

class ProductoModel extends Model
{
    protected $table            = 'productos';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'empresa_id', 'categoria_id', 'marca_id', 'unidad_medida_id',
        'codigo', 'codigo_barra', 'codigo_fabricante', 'nombre', 'descripcion',
        'descripcion_corta', 'especificaciones_tecnicas', 'tipo_producto',
        'tipo_iva', 'porcentaje_iva', 'precio_compra', 'precio_venta',
        'precio_mayorista', 'precio_minimo', 'margen_utilidad', 'moneda',
        'stock_minimo', 'stock_maximo', 'punto_reorden', 'controla_stock',
        'permite_venta_sin_stock', 'usa_lote', 'usa_vencimiento', 'usa_serie',
        'imagen_principal', 'peso', 'dimensiones', 'garantia_meses',
        'observaciones', 'tags', 'es_destacado', 'es_novedad', 'es_oferta',
        'estado', 'fecha_creacion', 'fecha_modificacion', 'usuario_creacion'
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'fecha_creacion';
    protected $updatedField  = 'fecha_modificacion';

    /**
     * Obtener productos con stock bajo
     */
    public function getStockBajo($empresaId, $sucursalId = null)
    {
        try {
            $db = \Config\Database::connect();
            
            // Verificar si la tabla stock existe
            if (!$db->tableExists('stock')) {
                return [];
            }
            
            // Construir la consulta usando query builder con subquery
            $builder = $db->table('productos p')
                ->select('p.id, p.codigo, p.nombre, 
                         COALESCE(s.cantidad_disponible, 0) as stock_actual, 
                         p.stock_minimo, p.punto_reorden')
                ->join('stock s', 's.producto_id = p.id', 'left')
                ->where('p.empresa_id', $empresaId)
                ->where('p.controla_stock', true)
                ->where('p.estado', true);
                
            if ($sucursalId) {
                $builder->where('s.sucursal_id', $sucursalId);
            }
            
            // NO usar orderBy aquí para evitar problemas con COALESCE en PostgreSQL
            // Obtener todos los productos y filtrar en PHP para evitar problemas con COALESCE en WHERE
            $productos = $builder->get()->getResultArray();
            
            // Filtrar productos con stock bajo
            $productosStockBajo = [];
            foreach ($productos as $producto) {
                $stockActual = (float)($producto['stock_actual'] ?? 0);
                $puntoReorden = (float)($producto['punto_reorden'] ?? 0);
                $stockMinimo = (float)($producto['stock_minimo'] ?? 0);
                $limite = $puntoReorden > 0 ? $puntoReorden : $stockMinimo;
                
                if ($stockActual <= $limite) {
                    $productosStockBajo[] = $producto;
                }
            }
            
            // Ordenar por stock actual
            usort($productosStockBajo, function($a, $b) {
                return (float)($a['stock_actual'] ?? 0) <=> (float)($b['stock_actual'] ?? 0);
            });
            
            return array_slice($productosStockBajo, 0, 10);
        } catch (\Exception $e) {
            // Si hay error, retornar array vacío
            return [];
        }
    }

    /**
     * Buscar productos
     */
    public function buscar($termino, $empresaId, $limite = 50)
    {
        $termino = trim((string) $termino);
        $terminoLower = mb_strtolower($termino, 'UTF-8');
        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $terminoLower) . '%';

        return $this->where('empresa_id', $empresaId)
                    ->where('estado', true)
                    ->groupStart()
                        ->where('LOWER(codigo) LIKE', $like, false)
                        ->orWhere('LOWER(nombre) LIKE', $like, false)
                        ->orWhere('LOWER(codigo_barra) LIKE', $like, false)
                    ->groupEnd()
                    ->limit($limite)
                    ->findAll();
    }
}
