<?php

namespace App\Controllers\Servicios;

use App\Controllers\BaseController;
use App\Models\OrdenServicioModel;
use App\Models\ClienteModel;
use App\Models\ProductoModel;
use App\Models\EstadoOrdenModel;
use App\Models\EmpresaModel;
use App\Models\SucursalModel;
use App\Models\UsuarioModel;
use App\Services\OrdenServicioService;

class OrdenesController extends BaseController
{
    protected $ordenModel;
    protected $clienteModel;
    protected $productoModel;
    protected $estadoModel;
    protected $empresaModel;
    protected $sucursalModel;
    protected $usuarioModel;
    protected $ordenService;

    public function __construct()
    {
        $this->ordenModel = new OrdenServicioModel();
        $this->clienteModel = new ClienteModel();
        $this->productoModel = new ProductoModel();
        $this->estadoModel = new EstadoOrdenModel();
        $this->empresaModel = new EmpresaModel();
        $this->sucursalModel = new SucursalModel();
        $this->usuarioModel = new UsuarioModel();
        $this->ordenService = new OrdenServicioService();
    }

    protected function checkAuth()
    {
        if (!$this->isLoggedIn()) {
            return redirect()->to('/auth/login');
        }
        // Asegurar que user data esté cargado (requerido para endpoints AJAX)
        if (!$this->user) {
            $this->loadUserData();
        }
        $this->requirePermission('ORDENES_SERVICIO', 'ver');
    }

    public function index()
    {
        $this->checkAuth();
        $empresaId = $this->user['empresa_id'];
        $sucursalId = $this->sucursal ? $this->sucursal['id'] : null;

        $builder = $this->ordenModel->where('empresa_id', $empresaId);
        if ($sucursalId) {
            $builder->where('sucursal_id', $sucursalId);
        }

        $ordenes = $builder->orderBy('fecha_recepcion', 'DESC')
            ->limit(100)
            ->findAll();

        foreach ($ordenes as &$orden) {
            $orden['cliente'] = $this->clienteModel->find($orden['cliente_id']);
            $orden['estado_nombre'] = $this->estadoModel->find($orden['estado_id']);
        }

        $data = [
            'title' => 'Órdenes de Servicio',
            'ordenes' => $ordenes,
            'estados' => $this->estadoModel->where('estado', true)->findAll()
        ];

        return $this->render('servicios/ordenes/index', $data);
    }

    public function kanban()
    {
        $this->checkAuth();
        $empresaId = $this->user['empresa_id'];
        $sucursalId = $this->sucursal ? $this->sucursal['id'] : null;

        $ordenes = $this->ordenModel->select('ordenes_servicio.*, clientes.nombre, clientes.apellido, clientes.razon_social, clientes.nombre_fantasia')
            ->join('clientes', 'clientes.id = ordenes_servicio.cliente_id')
            ->where('ordenes_servicio.empresa_id', $empresaId);

        if ($sucursalId) {
            $ordenes->where('ordenes_servicio.sucursal_id', $sucursalId);
        }

        $ordenes = $ordenes->where('ordenes_servicio.estado_activo', true)
            ->orderBy('ordenes_servicio.fecha_recepcion', 'DESC')
            ->findAll();

        // Agrupar por estado
        $ordenesPorEstado = [];
        $estados = $this->estadoModel->where('estado', true)->orderBy('orden', 'ASC')->findAll();

        foreach ($estados as $estado) {
            $ordenesPorEstado[$estado['id']] = [
                'estado' => $estado,
                'ordenes' => []
            ];
        }

        foreach ($ordenes as $orden) {
            if (isset($ordenesPorEstado[$orden['estado_id']])) {
                $ordenesPorEstado[$orden['estado_id']]['ordenes'][] = $orden;
            }
        }

        $data = [
            'title' => 'Tablero Kanban - Órdenes de Servicio',
            'ordenesPorEstado' => $ordenesPorEstado,
            'estados' => $estados
        ];

        return $this->render('servicios/ordenes/kanban', $data);
    }

    public function crear()
    {
        $empresaId = $this->user['empresa_id'];
        $sucursalId = $this->sucursal ? $this->sucursal['id'] : $this->user['sucursal_id'];

        $data = [
            'title' => 'Nueva Orden de Servicio',
            'orden' => null,
            'clientes' => $this->clienteModel->where('empresa_id', $empresaId)
                ->where('estado', true)
                ->limit(100)
                ->findAll(),
            'productos' => $this->productoModel->where('empresa_id', $empresaId)
                ->where('tipo_producto', 'REPUESTO')
                ->where('estado', true)
                ->findAll(),
            'estados' => $this->estadoModel->where('estado', true)->findAll(),
            'sucursal_id' => $sucursalId
        ];

        return $this->render('servicios/ordenes/form', $data);
    }

    public function buscarTecnicos()
    {
        $this->checkAuth();
        $term = $this->request->getGet('term');
        $empresaId = $this->user['empresa_id'] ?? null;

        if (!$empresaId) {
            return $this->response->setJSON([]);
        }

        if (empty($term)) {
            return $this->response->setJSON([]);
        }

        try {
            $db = \Config\Database::connect();
            $term = trim((string) $term);
            $termLower = mb_strtolower($term, 'UTF-8');
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $termLower) . '%';

            // Rol técnico: por nombre (case-insensitive). No asumimos ID fijo.
            $builder = $db->table('usuarios u')
                ->select("u.id, u.nombre, u.apellido, u.ci, u.email, r.nombre as rol_nombre")
                ->join('roles r', 'r.id = u.rol_id', 'inner')
                ->where('u.empresa_id', $empresaId)
                ->where('u.estado', true)
                ->where("LOWER(r.nombre) LIKE '%tecnico%'", null, false);

            // En PostgreSQL, si desactivamos el escape, el valor puede quedar sin comillas (LIKE %a%), causando error.
            // Por eso construimos condiciones raw con el valor escapado.
            $likeEscaped = $db->escape($like);
            $builder->groupStart()
                ->where("LOWER(u.nombre) LIKE {$likeEscaped}", null, false)
                ->orWhere("LOWER(u.apellido) LIKE {$likeEscaped}", null, false)
                ->orWhere("LOWER(COALESCE(u.ci, '')) LIKE {$likeEscaped}", null, false)
                ->orWhere("LOWER(COALESCE(u.email, '')) LIKE {$likeEscaped}", null, false)
                ->orWhere("LOWER(COALESCE(u.username, '')) LIKE {$likeEscaped}", null, false)
                ->groupEnd();

            $tecnicos = $builder->orderBy('u.nombre', 'ASC')
                ->limit(20)
                ->get()
                ->getResultArray();

            return $this->response->setJSON($tecnicos);
        } catch (\Exception $e) {
            log_message('error', 'Error en buscarTecnicos: ' . $e->getMessage());
            return $this->response->setJSON([]);
        }
    }

    public function guardar()
    {
        $this->checkAuth();

        $rules = [
            'cliente_id' => 'required|is_natural_no_zero',
            'tipo_equipo' => 'required|max_length[100]',
            'problema_reportado' => 'required'
        ];

        if (!$this->validate($rules)) {
            return $this->errorResponse('Error de validación: ' . implode(', ', $this->validator->getErrors()));
        }

        $data = $this->request->getPost();

        $data['empresa_id'] = $this->user['empresa_id'];
        $data['sucursal_id'] = $this->sucursal ? $this->sucursal['id'] : $this->user['sucursal_id'];
        $data['usuario_recepcion'] = $this->user['id'];

        $files = $this->request->getFiles();

        $resultado = $this->ordenService->create($data, $files, $this->user['id']);

        if ($resultado['success']) {
            return $this->successResponse($resultado['message'], [
                'redirect' => base_url('servicios/ordenes/ver/' . $resultado['id'])
            ]);
        } else {
            return $this->errorResponse($resultado['message']);
        }
    }

    public function editar($id)
    {
        $this->checkAuth();
        $orden = $this->ordenModel->find($id);

        if (!$orden || $orden['empresa_id'] != $this->user['empresa_id']) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Orden no encontrada');
        }

        $empresaId = $this->user['empresa_id'];

        if (!empty($orden['tecnico_asignado'])) {
            $orden['tecnico'] = $this->usuarioModel->find($orden['tecnico_asignado']);
        }

        $data = [
            'title' => 'Editar Orden de Servicio',
            'orden' => $orden,
            'clientes' => $this->clienteModel->where('empresa_id', $empresaId)->limit(100)->findAll(),
            'productos' => $this->productoModel->where('empresa_id', $empresaId)->where('tipo_producto', 'REPUESTO')->findAll(),
            'estados' => $this->estadoModel->findAll(),
            'sucursal_id' => $orden['sucursal_id']
        ];

        $repuestoModel = new \App\Models\OrdenServicioRepuestoModel();
        $data['orden']['repuestos'] = $repuestoModel->where('orden_id', $id)->findAll();

        $manoObraModel = new \App\Models\OrdenServicioManoObraModel();
        $data['orden']['mano_obra'] = $manoObraModel->where('orden_id', $id)->findAll();

        return $this->render('servicios/ordenes/form', $data);
    }

    public function actualizar($id)
    {
        $this->checkAuth();
        $data = $this->request->getPost();

        if (isset($data['repuestos']) && is_string($data['repuestos'])) {
            $data['repuestos'] = json_decode($data['repuestos'], true);
        }
        if (isset($data['mano_obra']) && is_string($data['mano_obra'])) {
            $data['mano_obra'] = json_decode($data['mano_obra'], true);
        }

        $resultado = $this->ordenService->update($id, $data, $this->user['id']);

        if ($resultado['success']) {
            return $this->successResponse($resultado['message'], [
                'redirect' => base_url('servicios/ordenes/ver/' . $id)
            ]);
        } else {
            return $this->errorResponse($resultado['message']);
        }
    }

    public function ver($id)
    {
        $this->checkAuth();
        $orden = $this->ordenModel->find($id);

        if (!$orden || $orden['empresa_id'] != $this->user['empresa_id']) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Orden no encontrada');
        }

        $orden['cliente'] = $this->clienteModel->find($orden['cliente_id']);
        $orden['estado'] = $this->estadoModel->find($orden['estado_id']);

        $repuestoModel = new \App\Models\OrdenServicioRepuestoModel();
        $orden['repuestos'] = $repuestoModel->select('ordenes_servicio_repuestos.*, productos.nombre as producto_nombre')
            ->join('productos', 'productos.id = ordenes_servicio_repuestos.producto_id')
            ->where('orden_id', $id)->findAll();

        $manoObraModel = new \App\Models\OrdenServicioManoObraModel();
        $orden['mano_obra'] = $manoObraModel->where('orden_id', $id)->findAll();

        $historialModel = new \App\Models\OrdenServicioHistorialModel();
        $orden['historial'] = $historialModel->where('orden_id', $id)->orderBy('fecha_cambio', 'DESC')->findAll();

        $imagenModel = new \App\Models\OrdenServicioImagenModel();
        $orden['imagenes'] = $imagenModel->where('orden_id', $id)->findAll();

        $comentarioModel = new \App\Models\OrdenServicioComentarioModel();
        $orden['comentarios'] = $comentarioModel->where('orden_id', $id)->orderBy('fecha_creacion', 'DESC')->findAll();

        $estados = $this->estadoModel->where('estado', true)->orderBy('orden', 'ASC')->findAll();
        $estadosMap = [];
        foreach ($estados as $e) {
            $estadosMap[$e['id']] = $e['nombre'];
        }

        $data = [
            'title' => 'Ver Orden de Servicio',
            'orden' => $orden,
            'estados' => $estados,
            'estadosMap' => $estadosMap,
            'user' => $this->user
        ];

        return $this->render('servicios/ordenes/ver', $data);
    }

    public function cambiarEstado()
    {
        $this->checkAuth();
        $ordenId = $this->request->getPost('orden_id');
        $nuevoEstadoId = $this->request->getPost('estado_id');
        $observaciones = $this->request->getPost('observaciones');

        $resultado = $this->ordenService->cambiarEstado($ordenId, $nuevoEstadoId, $observaciones, $this->user['id']);

        if ($resultado['success']) {
            return $this->successResponse($resultado['message']);
        } else {
            return $this->errorResponse($resultado['message']);
        }
    }

    public function agregarRepuesto()
    {
        $this->checkAuth();
        $ordenId = $this->request->getPost('orden_id');
        $data = $this->request->getPost();

        if (isset($data['precio'])) {
            $data['precio'] = $this->normalizarNumero($data['precio']);
        }

        $resultado = $this->ordenService->agregarRepuesto($ordenId, $data, $this->user['id']);

        if ($resultado['success']) {
            return $this->successResponse($resultado['message']);
        } else {
            return $this->errorResponse($resultado['message']);
        }
    }

    public function agregarManoObra()
    {
        $this->checkAuth();
        $ordenId = $this->request->getPost('orden_id');
        $data = $this->request->getPost();

        if (isset($data['precio'])) {
            $data['precio'] = $this->normalizarNumero($data['precio']);
        }

        $resultado = $this->ordenService->agregarManoObra($ordenId, $data, $this->user['id']);

        if ($resultado['success']) {
            return $this->successResponse($resultado['message']);
        } else {
            return $this->errorResponse($resultado['message']);
        }
    }

    public function eliminarRepuesto()
    {
        $this->checkAuth();
        $id = $this->request->getPost('id');
        $resultado = $this->ordenService->eliminarRepuesto($id);

        if ($resultado['success']) {
            return $this->successResponse($resultado['message'], [
                'nuevos_totales' => $resultado['nuevos_totales'] ?? null
            ]);
        } else {
            return $this->errorResponse($resultado['message']);
        }
    }

    public function eliminarManoObra()
    {
        $this->checkAuth();
        $id = $this->request->getPost('id');
        $resultado = $this->ordenService->eliminarManoObra($id);

        if ($resultado['success']) {
            return $this->successResponse($resultado['message'], [
                'nuevos_totales' => $resultado['nuevos_totales'] ?? null
            ]);
        } else {
            return $this->errorResponse($resultado['message']);
        }
    }

    public function facturar($id)
    {
        $this->checkAuth();

        $pagoData = [
            'tipo_pago' => $this->request->getPost('tipo_pago') ?? 'CONTADO'
        ];

        $resultado = $this->ordenService->facturar($id, $this->user['id'], $pagoData);

        if ($resultado['success']) {
            return $this->successResponse('Orden facturada exitosamente', [
                'redirect' => base_url('ventas/ver/' . $resultado['venta_id'])
            ]);
        } else {
            return $this->errorResponse($resultado['message']);
        }
    }

    public function imprimirRecepcion($id)
    {
        $this->checkAuth();
        $orden = $this->ordenModel->find($id);

        if (!$orden || $orden['empresa_id'] != $this->user['empresa_id']) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Orden no encontrada');
        }

        $orden['cliente'] = $this->clienteModel->find($orden['cliente_id']);
        $orden['usuario_recepcion_nombre'] = $this->user['nombre'] . ' ' . $this->user['apellido'];

        $data = [
            'orden' => $orden,
            'empresa' => $this->empresaModel->find($orden['empresa_id']),
            'sucursal' => $this->sucursalModel->find($orden['sucursal_id'])
        ];

        return view('servicios/ordenes/ticket_recepcion', $data);
    }

    public function subirImagen()
    {
        $this->checkAuth();
        $ordenId = $this->request->getPost('orden_id');
        $files = $this->request->getFiles();

        if (empty($files['imagenes'])) {
            return $this->errorResponse('No se seleccionaron imágenes');
        }

        $resultado = $this->ordenService->agregarImagenes($ordenId, $files['imagenes'], $this->user['id']);

        if ($resultado['success']) {
            return $this->successResponse($resultado['message']);
        } else {
            return $this->errorResponse($resultado['message']);
        }
    }

    public function eliminarImagen()
    {
        $this->checkAuth();
        $imagenId = $this->request->getPost('imagen_id');

        $resultado = $this->ordenService->eliminarImagen($imagenId, $this->user['id']);

        if ($resultado['success']) {
            return $this->successResponse($resultado['message']);
        } else {
            return $this->errorResponse($resultado['message']);
        }
    }

    // Método auxiliar para mantener compatibilidad LOCAL
    protected function normalizarNumero($valor, $default = 0, $maxValue = 9999999999999.99)
    {
        if (is_string($valor)) {
            $valor = str_replace('.', '', $valor);
            $valor = str_replace(',', '.', $valor);
        }
        return (float) $valor;
    }

    // --- Métodos para AJAX del Frontend ---

    public function generarPresupuesto($id)
    {
        $this->checkAuth();
        $orden = $this->ordenModel->find($id);

        if (!$orden || $orden['empresa_id'] != $this->user['empresa_id']) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Orden no encontrada');
        }

        // Cargar datos relacionales necesarios si no vienen cargados (el Model podría cargarlos)
        // Pero OrdenServicioModel tiene finds básicos. VentaService/OrdenService hace joins.
        // Aquí necesito repuestos y mano de obra.
        $orden['cliente'] = $this->clienteModel->find($orden['cliente_id']);

        $repuestoModel = new \App\Models\OrdenServicioRepuestoModel();
        $orden['repuestos'] = $repuestoModel->getPorOrden($id); // O usar where si getPorOrden no existe, pero Service lo usa

        $manoObraModel = new \App\Models\OrdenServicioManoObraModel();
        $orden['mano_obra'] = $manoObraModel->where('orden_id', $id)->findAll();

        // Si repuestos no tiene nombres, cargar productos
        foreach ($orden['repuestos'] as &$rep) {
            $prod = $this->productoModel->find($rep['producto_id']);
            $rep['producto_nombre'] = $prod['nombre'];
        }

        $data = [
            'orden' => $orden,
            'empresa' => $this->empresaModel->find($orden['empresa_id']),
            'sucursal' => $this->sucursalModel->find($orden['sucursal_id'])
        ];

        return view('servicios/ordenes/ticket_presupuesto', $data);
    }

    // --- Métodos para AJAX del Frontend ---

    public function buscarClientes()
    {
        $this->checkAuth();
        $term = $this->request->getGet('term');
        $empresaId = $this->user['empresa_id'] ?? null;

        if (!$empresaId) {
            return $this->response->setJSON([]);
        }

        if (empty($term)) {
            return $this->response->setJSON([]);
        }

        try {
            $term = trim((string) $term);
            $termLower = mb_strtolower($term, 'UTF-8');
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $termLower) . '%';

            $db = \Config\Database::connect();
            $likeEscaped = $db->escape($like);

            // La tabla clientes no tiene columna "documento". Generamos un alias para el frontend.
            // Usamos LOWER(...) LIKE para que la búsqueda no dependa de mayúsculas/minúsculas.
            $builder = $db->table('clientes')
                ->select('clientes.*, COALESCE(clientes.ruc, clientes.ci) as documento', false)
                ->where('empresa_id', $empresaId);

            $builder->groupStart()
                ->where("LOWER(COALESCE(nombre, '')) LIKE {$likeEscaped}", null, false)
                ->orWhere("LOWER(COALESCE(apellido, '')) LIKE {$likeEscaped}", null, false)
                ->orWhere("LOWER(COALESCE(razon_social, '')) LIKE {$likeEscaped}", null, false)
                ->orWhere("LOWER(COALESCE(nombre_fantasia, '')) LIKE {$likeEscaped}", null, false)
                ->orWhere("LOWER(COALESCE(ruc, '')) LIKE {$likeEscaped}", null, false)
                ->orWhere("LOWER(COALESCE(ci, '')) LIKE {$likeEscaped}", null, false)
                ->groupEnd();

            $clientes = $builder->limit(20)->get()->getResultArray();

            return $this->response->setJSON($clientes);
        } catch (\Exception $e) {
            log_message('error', 'Error en buscarClientes: ' . $e->getMessage());
            return $this->response->setJSON([]);
        }
    }

    public function buscarProductos()
    {
        $this->checkAuth();
        $term = $this->request->getGet('q') ?? $this->request->getGet('term'); // Select2 term
        $page = $this->request->getGet('page') ?? 1;
        $empresaId = $this->user['empresa_id'] ?? null;
        $sucursalId = $this->sucursal ? $this->sucursal['id'] : ($this->user['sucursal_id'] ?? 0);

        if (!$empresaId) {
            return $this->response->setJSON([]);
        }

        if (empty($term)) {
            return $this->response->setJSON([]);
        }

        try {
            // Usar Query Builder para JOIN con stock
            $db = \Config\Database::connect();
            $builder = $db->table('productos p')
                ->select('p.id, p.codigo, p.nombre, p.precio_venta, COALESCE(s.cantidad_disponible, 0) as stock')
                ->join('stock s', 's.producto_id = p.id AND s.sucursal_id = ' . $db->escape($sucursalId), 'left')
                ->where('p.empresa_id', $empresaId)
                ->whereIn('p.tipo_producto', ['REPUESTO', 'PRODUCTO'])
                ->where('p.estado', true);

            // Búsqueda
            $builder->groupStart()
                ->like('p.nombre', $term)
                ->orLike('p.codigo', $term) 
                ->orLike('p.codigo_barra', $term)
            ->groupEnd();

            $builder->limit(50);
            
            $productos = $builder->get()->getResultArray();

            return $this->response->setJSON($productos);
        } catch (\Exception $e) {
            log_message('error', 'Error en buscarProductos: ' . $e->getMessage());
            return $this->response->setJSON([]); // Fail graceful
        }
    }
}
