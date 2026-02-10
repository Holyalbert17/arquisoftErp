<?php

namespace App\Controllers\Ventas;

use App\Controllers\BaseController;
use App\Models\ListaPrecioModel;
use App\Models\ListaPrecioDetalleModel;
use App\Models\ProductoModel;

class ListasPreciosController extends BaseController
{
    protected $listaPrecioModel;
    protected $listaPrecioDetalleModel;
    protected $productoModel;

    public function __construct()
    {
        $this->listaPrecioModel = new ListaPrecioModel();
        $this->listaPrecioDetalleModel = new ListaPrecioDetalleModel();
        $this->productoModel = new ProductoModel();
    }
    
    protected function checkAuth()
    {
        if (!$this->isLoggedIn()) {
            if ($this->request->isAJAX()) {
                return $this->errorResponse('No autenticado', 401);
            }
            return redirect()->to('/auth/login');
        }
        
        // Cargar datos del usuario si no están cargados
        if (!$this->user) {
            $this->loadUserData();
        }
        
        $this->requirePermission('VENTAS', 'ver');
    }

    public function index()
    {
        $this->checkAuth();
        $empresaId = $this->user['empresa_id'];
        
        $listas = $this->listaPrecioModel->where('empresa_id', $empresaId)
                                        ->orderBy('nombre', 'ASC')
                                        ->findAll();
        
        $data = [
            'title' => 'Listas de Precios',
            'listas' => $listas
        ];

        return $this->render('ventas/listas_precios/index', $data);
    }

    public function crear()
    {
        $this->checkAuth();
        $empresaId = $this->user['empresa_id'];
        
        $data = [
            'title' => 'Nueva Lista de Precios',
            'lista' => null,
            'productos' => $this->productoModel->where('empresa_id', $empresaId)
                                              ->where('estado', true)
                                              ->findAll()
        ];

        return $this->render('ventas/listas_precios/form', $data);
    }

    public function guardar()
    {
        $this->checkAuth();
        $rules = [
            'nombre' => 'required|min_length[3]|max_length[100]',
            'tipo_lista' => 'required|in_list[GENERAL,MAYORISTA,MINORISTA,ESPECIAL,PROMOCIONAL]',
            'tipo_descuento' => 'required|in_list[PORCENTAJE,MONTO_FIJO,PRECIO_ESPECIAL]'
        ];

        if (!$this->validate($rules)) {
            return $this->errorResponse('Error de validación: ' . implode(', ', $this->validator->getErrors()));
        }

        $empresaId = $this->user['empresa_id'] ?? null;
        
        if (!$empresaId) {
            return $this->errorResponse('Usuario no tiene empresa asignada', 403);
        }
        
        $productosJson = $this->request->getPost('productos');
        $productos = [];
        
        if (!empty($productosJson)) {
            if (is_string($productosJson)) {
                $productos = json_decode($productosJson, true) ?? [];
            } elseif (is_array($productosJson)) {
                $productos = $productosJson;
            }
        }

        $db = \Config\Database::connect();
        $db->transStart();

        try {
            // Crear lista de precios
            $listaData = [
                'empresa_id' => $empresaId,
                'nombre' => $this->request->getPost('nombre'),
                'descripcion' => $this->request->getPost('descripcion'),
                'tipo_lista' => $this->request->getPost('tipo_lista'),
                'tipo_descuento' => $this->request->getPost('tipo_descuento'),
                'descuento_general' => $this->request->getPost('descuento_general') ?? 0,
                'es_predeterminada' => $this->request->getPost('es_predeterminada') ? true : false,
                'moneda' => $this->request->getPost('moneda') ?? 'PYG',
                'fecha_vigencia_desde' => $this->request->getPost('fecha_vigencia_desde') ?: null,
                'fecha_vigencia_hasta' => $this->request->getPost('fecha_vigencia_hasta') ?: null,
                'cantidad_minima' => $this->request->getPost('cantidad_minima') ?? 1,
                'estado' => true,
                'usuario_creacion' => $this->user['id']
            ];

            // Si es predeterminada, desmarcar las demás
            if ($listaData['es_predeterminada']) {
                $this->listaPrecioModel->where('empresa_id', $empresaId)
                                      ->set('es_predeterminada', false)
                                      ->update();
            }

            $this->listaPrecioModel->insert($listaData);
            $listaId = $this->listaPrecioModel->getInsertID();

            // Crear detalles de productos
            if (!empty($productos) && is_array($productos)) {
                foreach ($productos as $producto) {
                    if (!isset($producto['id']) || empty($producto['id'])) {
                        continue;
                    }
                    
                    $productoData = $this->productoModel->find($producto['id']);
                    if (!$productoData) {
                        log_message('warning', 'Producto no encontrado: ' . $producto['id']);
                        continue;
                    }

                    $tipoAplicacion = $producto['tipo_aplicacion'] ?? 'PORCENTAJE';
                    $precioBase = floatval($productoData['precio_venta'] ?? 0);
                    $precioFinal = $precioBase;

                    // Calcular precio según tipo de aplicación
                    if ($tipoAplicacion == 'PORCENTAJE') {
                        $descuento = floatval($producto['descuento_porcentaje'] ?? 0);
                        $precioFinal = $precioBase * (1 - ($descuento / 100));
                    } elseif ($tipoAplicacion == 'MONTO_FIJO') {
                        $descuento = floatval($producto['descuento_monto'] ?? 0);
                        $precioFinal = max(0, $precioBase - $descuento);
                    } elseif ($tipoAplicacion == 'PRECIO_ESPECIAL') {
                        $precioFinal = floatval($producto['precio_especial'] ?? $precioBase);
                    }

                    $detalleData = [
                        'lista_id' => $listaId,
                        'producto_id' => intval($producto['id']),
                        'tipo_aplicacion' => $tipoAplicacion,
                        'descuento_porcentaje' => ($tipoAplicacion == 'PORCENTAJE') ? floatval($producto['descuento_porcentaje'] ?? 0) : null,
                        'descuento_monto' => ($tipoAplicacion == 'MONTO_FIJO') ? floatval($producto['descuento_monto'] ?? 0) : null,
                        'precio_especial' => ($tipoAplicacion == 'PRECIO_ESPECIAL') ? floatval($producto['precio_especial'] ?? $precioBase) : null,
                        'precio_final' => $precioFinal,
                        'es_oferta' => isset($producto['es_oferta']) ? (bool)$producto['es_oferta'] : false,
                        'fecha_inicio_oferta' => $producto['fecha_inicio_oferta'] ?? null,
                        'fecha_fin_oferta' => $producto['fecha_fin_oferta'] ?? null,
                        'cantidad_minima' => intval($producto['cantidad_minima'] ?? 1)
                    ];

                    $this->listaPrecioDetalleModel->insert($detalleData);
                }
            }

            $db->transComplete();

            if ($db->transStatus() === false) {
                return $this->errorResponse('Error al crear la lista de precios');
            }

            return $this->successResponse('Lista de precios creada exitosamente', [
                'redirect' => base_url('ventas/listas-precios')
            ]);

        } catch (\Exception $e) {
            $db->transRollback();
            return $this->errorResponse('Error: ' . $e->getMessage());
        }
    }

    public function editar($id)
    {
        $this->checkAuth();
        $empresaId = $this->user['empresa_id'];
        
        $lista = $this->listaPrecioModel->find($id);
        
        if (!$lista || $lista['empresa_id'] != $empresaId) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Lista de precios no encontrada');
        }

        $detalles = $this->listaPrecioDetalleModel->where('lista_id', $id)->findAll();
        
        $productos = $this->productoModel->where('empresa_id', $empresaId)
                                        ->where('estado', true)
                                        ->select('id, codigo, nombre, precio_venta, precio_compra')
                                        ->orderBy('nombre', 'ASC')
                                        ->findAll();
        
        $data = [
            'title' => 'Editar Lista de Precios',
            'lista' => $lista,
            'detalles' => $detalles,
            'productos' => $productos
        ];

        return $this->render('ventas/listas_precios/form', $data);
    }

    public function actualizar($id)
    {
        $this->checkAuth();
        $empresaId = $this->user['empresa_id'];
        
        $lista = $this->listaPrecioModel->find($id);
        
        if (!$lista || $lista['empresa_id'] != $empresaId) {
            return $this->errorResponse('Lista de precios no encontrada', 404);
        }

        $rules = [
            'nombre' => 'required|min_length[3]|max_length[100]',
            'tipo_lista' => 'required|in_list[GENERAL,MAYORISTA,MINORISTA,ESPECIAL,PROMOCIONAL]',
            'tipo_descuento' => 'required|in_list[PORCENTAJE,MONTO_FIJO,PRECIO_ESPECIAL]'
        ];

        if (!$this->validate($rules)) {
            return $this->errorResponse('Error de validación: ' . implode(', ', $this->validator->getErrors()));
        }

        $productosJson = $this->request->getPost('productos');
        $productos = [];
        
        if (!empty($productosJson)) {
            if (is_string($productosJson)) {
                $productos = json_decode($productosJson, true) ?? [];
            } elseif (is_array($productosJson)) {
                $productos = $productosJson;
            }
        }

        $db = \Config\Database::connect();
        $db->transStart();

        try {
            // Actualizar lista
            $listaData = [
                'nombre' => $this->request->getPost('nombre'),
                'descripcion' => $this->request->getPost('descripcion'),
                'tipo_lista' => $this->request->getPost('tipo_lista'),
                'tipo_descuento' => $this->request->getPost('tipo_descuento'),
                'descuento_general' => $this->request->getPost('descuento_general') ?? 0,
                'es_predeterminada' => $this->request->getPost('es_predeterminada') ? true : false,
                'moneda' => $this->request->getPost('moneda') ?? 'PYG',
                'fecha_vigencia_desde' => $this->request->getPost('fecha_vigencia_desde') ?: null,
                'fecha_vigencia_hasta' => $this->request->getPost('fecha_vigencia_hasta') ?: null,
                'cantidad_minima' => $this->request->getPost('cantidad_minima') ?? 1
            ];

            // Si es predeterminada, desmarcar las demás
            if ($listaData['es_predeterminada']) {
                $this->listaPrecioModel->where('empresa_id', $empresaId)
                                      ->where('id !=', $id)
                                      ->set('es_predeterminada', false)
                                      ->update();
            }

            $this->listaPrecioModel->update($id, $listaData);

            // Eliminar detalles existentes
            $this->listaPrecioDetalleModel->where('lista_id', $id)->delete();

            // Crear nuevos detalles
            if (!empty($productos) && is_array($productos)) {
                foreach ($productos as $producto) {
                    if (!isset($producto['id']) || empty($producto['id'])) {
                        continue;
                    }
                    
                    $productoData = $this->productoModel->find($producto['id']);
                    if (!$productoData) {
                        log_message('warning', 'Producto no encontrado: ' . $producto['id']);
                        continue;
                    }

                    $tipoAplicacion = $producto['tipo_aplicacion'] ?? 'PORCENTAJE';
                    $precioBase = floatval($productoData['precio_venta'] ?? 0);
                    $precioFinal = $precioBase;

                    if ($tipoAplicacion == 'PORCENTAJE') {
                        $descuento = floatval($producto['descuento_porcentaje'] ?? 0);
                        $precioFinal = $precioBase * (1 - ($descuento / 100));
                    } elseif ($tipoAplicacion == 'MONTO_FIJO') {
                        $descuento = floatval($producto['descuento_monto'] ?? 0);
                        $precioFinal = max(0, $precioBase - $descuento);
                    } elseif ($tipoAplicacion == 'PRECIO_ESPECIAL') {
                        $precioFinal = floatval($producto['precio_especial'] ?? $precioBase);
                    }

                    $detalleData = [
                        'lista_id' => $id,
                        'producto_id' => intval($producto['id']),
                        'tipo_aplicacion' => $tipoAplicacion,
                        'descuento_porcentaje' => ($tipoAplicacion == 'PORCENTAJE') ? floatval($producto['descuento_porcentaje'] ?? 0) : null,
                        'descuento_monto' => ($tipoAplicacion == 'MONTO_FIJO') ? floatval($producto['descuento_monto'] ?? 0) : null,
                        'precio_especial' => ($tipoAplicacion == 'PRECIO_ESPECIAL') ? floatval($producto['precio_especial'] ?? $precioBase) : null,
                        'precio_final' => $precioFinal,
                        'es_oferta' => isset($producto['es_oferta']) ? (bool)$producto['es_oferta'] : false,
                        'fecha_inicio_oferta' => $producto['fecha_inicio_oferta'] ?? null,
                        'fecha_fin_oferta' => $producto['fecha_fin_oferta'] ?? null,
                        'cantidad_minima' => intval($producto['cantidad_minima'] ?? 1)
                    ];

                    $this->listaPrecioDetalleModel->insert($detalleData);
                }
            }

            $db->transComplete();

            if ($db->transStatus() === false) {
                return $this->errorResponse('Error al actualizar la lista de precios');
            }

            return $this->successResponse('Lista de precios actualizada exitosamente', [
                'redirect' => base_url('ventas/listas-precios')
            ]);

        } catch (\Exception $e) {
            $db->transRollback();
            return $this->errorResponse('Error: ' . $e->getMessage());
        }
    }

    public function eliminar($id)
    {
        $this->checkAuth();
        $empresaId = $this->user['empresa_id'];
        
        $lista = $this->listaPrecioModel->find($id);
        
        if (!$lista || $lista['empresa_id'] != $empresaId) {
            return $this->errorResponse('Lista de precios no encontrada', 404);
        }

        // Verificar si está siendo usada
        $clienteModel = new \App\Models\ClienteModel();
        $enUso = $clienteModel->where('lista_precio_id', $id)->countAllResults();
        
        if ($enUso > 0) {
            return $this->errorResponse('No se puede eliminar la lista porque está asignada a clientes');
        }

        $db = \Config\Database::connect();
        $db->transStart();

        try {
            // Eliminar detalles
            $this->listaPrecioDetalleModel->where('lista_id', $id)->delete();
            
            // Eliminar lista
            $this->listaPrecioModel->delete($id);

            $db->transComplete();

            if ($db->transStatus() === false) {
                return $this->errorResponse('Error al eliminar la lista de precios');
            }

            return $this->successResponse('Lista de precios eliminada exitosamente');

        } catch (\Exception $e) {
            $db->transRollback();
            return $this->errorResponse('Error: ' . $e->getMessage());
        }
    }

    public function obtenerPrecio()
    {
        $this->checkAuth();
        $listaId = $this->request->getPost('lista_id');
        $productoId = $this->request->getPost('producto_id');
        $cantidad = $this->request->getPost('cantidad') ?? 1;

        if (!$listaId || !$productoId) {
            return $this->errorResponse('Parámetros incompletos');
        }

        $detalle = $this->listaPrecioDetalleModel->where('lista_id', $listaId)
                                                 ->where('producto_id', $productoId)
                                                 ->first();

        if (!$detalle) {
            // Si no hay detalle específico, usar descuento general
            $lista = $this->listaPrecioModel->find($listaId);
            $producto = $this->productoModel->find($productoId);
            
            if (!$lista || !$producto) {
                return $this->errorResponse('Lista o producto no encontrado');
            }

            $precioBase = $producto['precio_venta'];
            $descuentoGeneral = $lista['descuento_general'] ?? 0;
            $precioFinal = $precioBase * (1 - ($descuentoGeneral / 100));

            return $this->successResponse('Precio obtenido', [
                'precio' => $precioFinal,
                'precio_base' => $precioBase,
                'descuento' => $descuentoGeneral
            ]);
        }

        // Verificar cantidad mínima
        if ($cantidad < $detalle['cantidad_minima']) {
            return $this->errorResponse('La cantidad no cumple con el mínimo requerido: ' . $detalle['cantidad_minima']);
        }

        // Verificar vigencia de oferta
        if ($detalle['es_oferta']) {
            $ahora = time();
            $inicio = $detalle['fecha_inicio_oferta'] ? strtotime($detalle['fecha_inicio_oferta']) : 0;
            $fin = $detalle['fecha_fin_oferta'] ? strtotime($detalle['fecha_fin_oferta']) : PHP_INT_MAX;
            
            if ($ahora < $inicio || $ahora > $fin) {
                // Oferta no vigente, usar precio final normal
                return $this->successResponse('Precio obtenido', [
                    'precio' => $detalle['precio_final'],
                    'precio_base' => $detalle['precio_final'],
                    'descuento' => 0
                ]);
            } else {
                // Oferta vigente
                return $this->successResponse('Precio obtenido', [
                    'precio' => $detalle['precio_oferta'] ?? $detalle['precio_final'],
                    'precio_base' => $detalle['precio_final'],
                    'descuento' => 0,
                    'es_oferta' => true
                ]);
            }
        }

        return $this->successResponse('Precio obtenido', [
            'precio' => $detalle['precio_final'],
            'precio_base' => $detalle['precio_final'],
            'descuento' => 0
        ]);
    }

    public function inactivar($id)
    {
        $this->checkAuth();
        $empresaId = $this->user['empresa_id'];
        
        $lista = $this->listaPrecioModel->find($id);
        
        if (!$lista || $lista['empresa_id'] != $empresaId) {
            return $this->errorResponse('Lista de precios no encontrada', 404);
        }

        try {
            $this->listaPrecioModel->update($id, ['estado' => false]);
            return $this->successResponse('Lista de precios inactivada exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error: ' . $e->getMessage());
        }
    }

    public function activar($id)
    {
        $this->checkAuth();
        $empresaId = $this->user['empresa_id'];
        
        $lista = $this->listaPrecioModel->find($id);
        
        if (!$lista || $lista['empresa_id'] != $empresaId) {
            return $this->errorResponse('Lista de precios no encontrada', 404);
        }

        try {
            $this->listaPrecioModel->update($id, ['estado' => true]);
            return $this->successResponse('Lista de precios activada exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error: ' . $e->getMessage());
        }
    }
}
