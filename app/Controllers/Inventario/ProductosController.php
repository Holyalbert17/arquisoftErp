<?php

namespace App\Controllers\Inventario;

use App\Controllers\BaseController;
use App\Services\ProductoService;
use App\Models\CategoriaModel;
use App\Models\MarcaModel;
use App\Models\UnidadMedidaModel;
use CodeIgniter\Exceptions\PageNotFoundException;

class ProductosController extends BaseController
{
    protected $productoService;
    protected $categoriaModel;
    protected $marcaModel;
    protected $unidadMedidaModel;

    public function __construct()
    {
        // En un futuro, usar DI en el constructor: public function __construct(ProductoService $service)
        $this->productoService = new ProductoService();

        // Modelos auxiliares para llenar selects en la vista
        // Idealmente estos datos vendrían de metodos 'getFormOptions' en sus respectivos servicios
        $this->categoriaModel = new CategoriaModel();
        $this->marcaModel = new MarcaModel();
        $this->unidadMedidaModel = new UnidadMedidaModel();
    }

    protected function checkAuth()
    {
        if (!$this->isLoggedIn()) {
            return redirect()->to('/auth/login');
        }
        $this->requirePermission('INVENTARIO', 'ver');
    }

    public function index()
    {
        $this->checkAuth();
        $empresaId = $this->user['empresa_id'] ?? null;

        if (!$empresaId) {
            return redirect()->to('/auth/login');
        }

        try {
            $productos = $this->productoService->getAll($empresaId);

            $data = [
                'title' => 'Gestión de Productos',
                'productos' => $productos
            ];

            return $this->render('inventario/productos/index', $data);
        } catch (\Exception $e) {
            log_message('error', 'Error en index productos: ' . $e->getMessage());
            return redirect()->to('/dashboard')->with('error', 'Error al cargar productos');
        }
    }

    public function crear()
    {
        $this->checkAuth();
        $empresaId = $this->user['empresa_id'] ?? null;

        if (!$empresaId)
            return redirect()->to('/auth/login');

        // Cargar opciones para el formulario
        $data = [
            'title' => 'Nuevo Producto',
            'producto' => null,
            'categorias' => $this->categoriaModel->where('empresa_id', $empresaId)->where('estado', true)->findAll(),
            'marcas' => $this->marcaModel->where('empresa_id', $empresaId)->where('estado', true)->findAll(),
            'unidades' => $this->unidadMedidaModel->where('estado', true)->findAll()
        ];

        return $this->render('inventario/productos/form', $data);
    }

    public function guardar()
    {
        $this->checkAuth();
        $empresaId = $this->user['empresa_id'] ?? null;

        // Obtain all Post Data
        $data = $this->request->getPost();

        // 1. Pre-process / Normalize Data BEFORE Validation
        // This ensures that auto-generated fields or normalized numbers pass validation

        // Auto-generate SKU (Codigo) if empty
        if (empty($data['codigo'])) {
            // Generate a simple SKU: PROD-RANDOM
            $data['codigo'] = 'PROD-' . strtoupper(substr(uniqid(), -6));
        }

        // Auto-generate Barcode if empty (Logic moved here from below)
        if (empty($data['codigo_barra'])) {
            $data['codigo_barra'] = '2' . str_pad(mt_rand(0, 999999999999), 12, '0', STR_PAD_LEFT);
            // Simple uniqueness check could be added here similar to previous logic
        }

        // Normalize numbers to ensure they pass 'numeric' rule
        // (Removing thousand separators if any found in string)
        $numericFields = [
            'precio_compra',
            'precio_venta',
            'precio_mayorista',
            'precio_minimo',
            'stock_minimo',
            'stock_maximo',
            'punto_reorden',
            'stock_inicial'
        ];

        foreach ($numericFields as $field) {
            if (isset($data[$field])) {
                $data[$field] = $this->normalizarNumero($data[$field]);
            }
        }

        // 2. Define Validation Rules
        $rules = [
            'codigo' => 'required|max_length[50]',
            'nombre' => 'required|min_length[3]|max_length[200]',
            'tipo_producto' => 'in_list[PRODUCTO,SERVICIO,COMBO,REPUESTO]',
            'precio_venta' => 'required|numeric|greater_than_equal_to[0]',
        ];

        // 3. Run Validation on the Modified Data
        if (!$this->validateData($data, $rules)) {
            return $this->errorResponse('Error de validación: ' . implode(', ', $this->validator->getErrors()));
        }

        // 4. Prepare Final Data for Service
        // Sanitizar/Normalizar datos específicos
        $data['empresa_id'] = $empresaId;
        $data['usuario_creacion'] = $this->user['id'];
        $data['sucursal_id'] = $this->sucursal['id'] ?? null; // Para stock inicial

        // Checkboxes
        $data['controla_stock'] = isset($data['controla_stock']) ? 't' : 'f';
        $data['permite_venta_sin_stock'] = isset($data['permite_venta_sin_stock']) ? 't' : 'f';
        $data['usa_lote'] = isset($data['usa_lote']) ? 't' : 'f';
        $data['usa_vencimiento'] = isset($data['usa_vencimiento']) ? 't' : 'f';
        $data['usa_serie'] = isset($data['usa_serie']) ? 't' : 'f';
        $data['estado'] = true;

        if ($data['precio_compra'] > 0 && $data['precio_venta'] > 0) {
            $data['margen_utilidad'] = (($data['precio_venta'] - $data['precio_compra']) / $data['precio_compra']) * 100;
        }

        // Imagen
        $file = $this->request->getFile('imagen_principal');
        if ($file && (!$file->isValid() || $file->hasMoved())) {
            $file = null;
        }

        // Llamar Servicio
        $resultado = $this->productoService->create($data, $file, $this->user);

        if ($resultado['success']) {
            return $this->successResponse('Producto creado exitosamente', [
                'redirect' => base_url('inventario/productos')
            ]);
        } else {
            return $this->errorResponse($resultado['message']);
        }
    }

    public function editar($id)
    {
        $this->checkAuth();
        $empresaId = $this->user['empresa_id'] ?? null;

        $producto = $this->productoService->getById($id, $empresaId);

        if (!$producto) {
            throw new PageNotFoundException("Producto no encontrado");
        }

        $data = [
            'title' => 'Editar Producto',
            'producto' => $producto,
            'categorias' => $this->categoriaModel->where('empresa_id', $empresaId)->where('estado', true)->findAll(),
            'marcas' => $this->marcaModel->where('empresa_id', $empresaId)->where('estado', true)->findAll(),
            'unidades' => $this->unidadMedidaModel->where('estado', true)->findAll()
        ];

        return $this->render('inventario/productos/form', $data);
    }

    public function actualizar($id)
    {
        $this->checkAuth();
        $empresaId = $this->user['empresa_id'] ?? null;

        // Obtain all Post Data
        $data = $this->request->getPost();
        unset($data['imagen_principal']); // Prevent saving raw binary/string from POST

        // 1. Pre-process / Normalize Data BEFORE Validation
        // Normalize numbers to ensure they pass 'numeric' rule
        $numericFields = [
            'precio_compra',
            'precio_venta',
            'precio_mayorista',
            'precio_minimo',
            'stock_minimo',
            'stock_maximo',
            'punto_reorden'
        ];

        foreach ($numericFields as $field) {
            if (isset($data[$field])) {
                $data[$field] = $this->normalizarNumero($data[$field]);
            }
        }

        // 2. Define Validation Rules
        $rules = [
            'codigo' => "required|max_length[50]",
            'nombre' => 'required|min_length[3]',
            'precio_venta' => 'required|numeric'
        ];

        // 3. Run Validation on the Modified Data
        if (!$this->validateData($data, $rules)) {
            return $this->errorResponse('Error validación: ' . implode(',', $this->validator->getErrors()));
        }

        // 4. Prepare Final Data
        $data['empresa_id'] = $empresaId;

        // Checkboxes
        $data['controla_stock'] = isset($data['controla_stock']) ? 't' : 'f';
        $data['permite_venta_sin_stock'] = isset($data['permite_venta_sin_stock']) ? 't' : 'f';
        $data['usa_lote'] = isset($data['usa_lote']) ? 't' : 'f';
        $data['usa_vencimiento'] = isset($data['usa_vencimiento']) ? 't' : 'f';
        $data['usa_serie'] = isset($data['usa_serie']) ? 't' : 'f';

        if ($data['precio_compra'] > 0 && $data['precio_venta'] > 0) {
            $data['margen_utilidad'] = (($data['precio_venta'] - $data['precio_compra']) / $data['precio_compra']) * 100;
        }

        // Imagen
        $file = $this->request->getFile('imagen_principal');
        if ($file && (!$file->isValid() || $file->hasMoved())) {
            $file = null;
        }

        $resultado = $this->productoService->update($id, $data, $file, $this->user);

        if ($resultado['success']) {
            return $this->successResponse('Producto actualizado', ['redirect' => base_url('inventario/productos')]);
        } else {
            return $this->errorResponse($resultado['message']);
        }
    }

    public function eliminar($id)
    {
        $this->checkAuth();
        $empresaId = $this->user['empresa_id'] ?? null;

        $resultado = $this->productoService->delete($id, $empresaId, $this->user);

        if ($resultado['success']) {
            return $this->successResponse($resultado['message']);
        } else {
            return $this->errorResponse($resultado['message']);
        }
    }

    public function ver($id)
    {
        $this->checkAuth();
        $empresaId = $this->user['empresa_id'];

        $producto = $this->productoService->getById($id, $empresaId);
        if (!$producto)
            throw new PageNotFoundException("Producto no encontrado");

        // Obtener stock detallado (esto podría moverse al service también, getStockDetail($id))
        $stock = [];
        try {
            $db = \Config\Database::connect();
            if ($db->tableExists('stock')) {
                $stock = $db->table('stock s')
                    ->select('s.*, suc.nombre as sucursal_nombre')
                    ->join('sucursales suc', 'suc.id = s.sucursal_id', 'inner')
                    ->where('s.producto_id', $id)
                    ->get()->getResultArray();
            }
        } catch (\Exception $e) {
        }

        $data = [
            'title' => 'Ver Producto',
            'producto' => $producto,
            'stock' => $stock
        ];

        return $this->render('inventario/productos/ver', $data);
    }

    public function imprimirCodigoBarra($id)
    {
        $this->checkAuth();
        $empresaId = $this->user['empresa_id'];

        $producto = $this->productoService->getById($id, $empresaId);
        if (!$producto) {
            throw new PageNotFoundException("Producto no encontrado");
        }

        if (empty($producto['codigo_barra'])) {
            return redirect()->back()->with('error', 'El producto no tiene código de barras asignado.');
        }

        return view('inventario/productos/barcode_print', ['producto' => $producto]);
    }

    protected function normalizarNumero($valor, $default = 0, $maxValue = 9999999999999.99)
    {
        if (is_string($valor)) {
            $valor = str_replace('.', '', $valor);
            $valor = str_replace(',', '.', $valor);
        }

        $valor = (float) $valor;
        return $valor ?: $default;
    }
}
