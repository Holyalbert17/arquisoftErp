<?php

namespace App\Controllers\Clientes;

use App\Controllers\BaseController;
use App\Models\ClienteModel;
use App\Models\ListaPrecioModel;

class ClientesController extends BaseController
{
    protected $clienteModel;
    protected $listaPrecioModel;

    public function __construct()
    {
        $this->clienteModel = new ClienteModel();
        $this->listaPrecioModel = new ListaPrecioModel();
    }
    
    protected function checkAuth()
    {
        if (!$this->isLoggedIn()) {
            return redirect()->to('/auth/login');
        }
        // Asegurar que user data esté cargado
        if (!$this->user) {
            $this->loadUserData();
        }
        $this->requirePermission('CLIENTES', 'ver');
    }

    public function index()
    {
        try {
            $this->checkAuth();
            $empresaId = $this->user['empresa_id'] ?? null;
            
            if (!$empresaId) {
                return redirect()->to('/auth/login');
            }
            
            $clientes = [];
            try {
                $clientes = $this->clienteModel->where('empresa_id', $empresaId)->where('estado', true)->findAll();
            } catch (\Exception $e) {
                log_message('error', 'Error al obtener clientes: ' . $e->getMessage());
            }
            
            $data = [
                'title' => 'Gestión de Clientes',
                'clientes' => $clientes
            ];

            return $this->render('clientes/index', $data);
        } catch (\Exception $e) {
            log_message('error', 'Error en index clientes: ' . $e->getMessage());
            return redirect()->to('/dashboard')
                ->with('error', 'Error al cargar la lista de clientes');
        }
    }

    public function crear()
    {
        try {
            $this->checkAuth();
            $empresaId = $this->user['empresa_id'] ?? null;
            
            if (!$empresaId) {
                return redirect()->to('/auth/login');
            }
            
            $listasPrecios = [];
            try {
                $listasPrecios = $this->listaPrecioModel->where('empresa_id', $empresaId)->findAll();
            } catch (\Exception $e) {
                log_message('error', 'Error al obtener listas de precios: ' . $e->getMessage());
            }
            
            $data = [
                'title' => 'Nuevo Cliente',
                'cliente' => null,
                'listas_precios' => $listasPrecios
            ];

            return $this->render('clientes/form', $data);
        } catch (\Exception $e) {
            log_message('error', 'Error en crear cliente: ' . $e->getMessage());
            return redirect()->to('/clientes')
                ->with('error', 'Error al cargar el formulario');
        }
    }

    public function guardar()
    {
        try {
            $this->checkAuth();
            $rules = [
                'tipo_cliente' => 'required|in_list[PERSONA,EMPRESA]',
                'nombre' => 'permit_empty|min_length[2]|max_length[200]',
                'razon_social' => 'permit_empty|min_length[2]|max_length[200]',
                'email' => 'valid_email|max_length[150]'
            ];

            if (!$this->validate($rules)) {
                return $this->errorResponse('Error de validación: ' . implode(', ', $this->validator->getErrors()));
            }

            $empresaId = $this->user['empresa_id'] ?? null;
            
            if (!$empresaId) {
                return $this->errorResponse('Usuario no tiene empresa asignada', 403);
            }
            
            $tipoCliente = $this->request->getPost('tipo_cliente');

            // Validar que tenga nombre o razón social según el tipo
            if ($tipoCliente == 'PERSONA' && !$this->request->getPost('nombre')) {
                return $this->errorResponse('El nombre es obligatorio para clientes persona');
            }
            if ($tipoCliente == 'EMPRESA' && !$this->request->getPost('razon_social')) {
                return $this->errorResponse('La razón social es obligatoria para clientes empresa');
            }

            // Normalizar fecha_nacimiento (convertir cadena vacía a null)
            $fechaNacimiento = $this->normalizarFecha($this->request->getPost('fecha_nacimiento'));

            // Validar y convertir campos integer
            $listaPrecioId = $this->request->getPost('lista_precio_id');
            if (!empty($listaPrecioId) && !is_numeric($listaPrecioId)) {
                return $this->errorResponse('La lista de precios seleccionada no es válida');
            }
            $listaPrecioId = !empty($listaPrecioId) ? (int)$listaPrecioId : null;

            $usuarioCreacion = $this->user['id'] ?? null;
            if ($usuarioCreacion !== null) {
                $usuarioCreacion = (int)$usuarioCreacion;
            }

            $data = [
                'empresa_id' => (int)$empresaId,
                'tipo_cliente' => $tipoCliente,
                'codigo' => $this->request->getPost('codigo') ?: $this->generarCodigoCliente($empresaId),
                'ruc' => $this->request->getPost('ruc') ? trim($this->request->getPost('ruc')) : null,
                'ci' => $this->request->getPost('ci') ? trim($this->request->getPost('ci')) : null,
                'nombre' => $this->request->getPost('nombre') ? trim($this->request->getPost('nombre')) : null,
                'apellido' => $this->request->getPost('apellido') ? trim($this->request->getPost('apellido')) : null,
                'razon_social' => $this->request->getPost('razon_social') ? trim($this->request->getPost('razon_social')) : null,
                'nombre_fantasia' => $this->request->getPost('nombre_fantasia') ? trim($this->request->getPost('nombre_fantasia')) : null,
                'direccion' => $this->request->getPost('direccion') ? trim($this->request->getPost('direccion')) : null,
                'ciudad' => $this->request->getPost('ciudad') ? trim($this->request->getPost('ciudad')) : null,
                'departamento' => $this->request->getPost('departamento') ? trim($this->request->getPost('departamento')) : null,
                'barrio' => $this->request->getPost('barrio') ? trim($this->request->getPost('barrio')) : null,
                'telefono' => $this->request->getPost('telefono') ? trim($this->request->getPost('telefono')) : null,
                'celular' => $this->request->getPost('celular') ? trim($this->request->getPost('celular')) : null,
                'email' => $this->request->getPost('email') ? trim($this->request->getPost('email')) : null,
                'fecha_nacimiento' => $fechaNacimiento,
                'sexo' => $this->request->getPost('sexo') ?: null,
                'tipo_contribuyente' => $this->request->getPost('tipo_contribuyente') ?: 'CONTRIBUYENTE',
                'categoria_cliente' => $this->request->getPost('categoria_cliente') ?: 'NORMAL',
                'lista_precio_id' => $listaPrecioId,
                'limite_credito' => $this->normalizarNumero($this->request->getPost('limite_credito'), 0, 9999999999999.99),
                'dias_credito' => $this->normalizarNumero($this->request->getPost('dias_credito'), 0),
                'descuento_porcentaje' => $this->normalizarNumero($this->request->getPost('descuento_porcentaje'), 0),
                'observaciones' => $this->request->getPost('observaciones') ? trim($this->request->getPost('observaciones')) : null,
                'estado' => true,
                'usuario_creacion' => $usuarioCreacion
            ];

            // Subir foto si existe
            try {
                $foto = $this->request->getFile('foto');
                if ($foto && $foto->isValid() && !$foto->hasMoved()) {
                    $uploadPath = ROOTPATH . 'public/uploads/clientes';
                    
                    // Crear directorio si no existe
                    if (!is_dir($uploadPath)) {
                        mkdir($uploadPath, 0755, true);
                    }
                    
                    // Validar tipo de archivo
                    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                    if (!in_array($foto->getMimeType(), $allowedTypes)) {
                        return $this->errorResponse('Tipo de archivo no permitido. Solo se permiten imágenes (JPG, PNG, GIF, WEBP)');
                    }
                    
                    // Validar tamaño (max 2MB)
                    if ($foto->getSize() > 2097152) {
                        return $this->errorResponse('La imagen es demasiado grande. Tamaño máximo: 2MB');
                    }
                    
                    $newName = $foto->getRandomName();
                    if ($foto->move($uploadPath, $newName)) {
                        $data['foto'] = $newName;
                    } else {
                        return $this->errorResponse('Error al subir la foto: ' . $foto->getErrorString());
                    }
                }
            } catch (\Exception $e) {
                return $this->errorResponse('Error al procesar la foto: ' . $e->getMessage());
            }

            // Remover campos vacíos que puedan causar problemas
            foreach ($data as $key => $value) {
                if ($value === '' && !in_array($key, ['telefono', 'celular', 'direccion', 'ciudad', 'departamento', 'barrio', 'observaciones', 'foto'])) {
                    $data[$key] = null;
                }
            }

            // Asegurar que no se pasen campos que CodeIgniter maneja automáticamente
            unset($data['fecha_creacion']);
            unset($data['fecha_modificacion']);

            // Log para debug en desarrollo
            if (ENVIRONMENT === 'development') {
                log_message('debug', 'ClientesController::guardar - Datos antes de insert: ' . json_encode($data));
            }

            try {
                if ($this->clienteModel->insert($data)) {
                    try {
                        $auditoriaModel = new \App\Models\AuditoriaModel();
                        $auditoriaModel->insert([
                            'usuario_id' => $this->user['id'] ?? null,
                            'empresa_id' => $empresaId,
                            'tabla_afectada' => 'clientes',
                            'accion' => 'INSERT',
                            'registro_id' => $this->clienteModel->getInsertID(),
                            'datos_nuevos' => json_encode($data),
                            'ip_address' => $this->request->getIPAddress()
                        ]);
                    } catch (\Exception $e) {
                        log_message('error', 'Error al registrar auditoría: ' . $e->getMessage());
                    }

                    return $this->successResponse('Cliente creado exitosamente', [
                        'redirect' => base_url('clientes')
                    ]);
                } else {
                    log_message('error', 'ClientesController::guardar - Error al insertar cliente');
                    return $this->errorResponse('Error al crear el cliente');
                }
            } catch (\Exception $e) {
                log_message('error', 'Error en guardar cliente: ' . $e->getMessage());
                log_message('error', 'Stack trace: ' . $e->getTraceAsString());
                log_message('error', 'Datos: ' . json_encode($data ?? []));
                return $this->errorResponse('Error al crear el cliente: ' . $e->getMessage());
            }
        } catch (\Exception $e) {
            log_message('error', 'Error general en guardar cliente: ' . $e->getMessage());
            log_message('error', 'Stack trace: ' . $e->getTraceAsString());
            return $this->errorResponse('Error al crear el cliente: ' . $e->getMessage());
        }
    }

    public function editar($id)
    {
        try {
            $this->checkAuth();
            $cliente = $this->clienteModel->find($id);
            
            $empresaId = $this->user['empresa_id'] ?? null;
            
            if (!$empresaId) {
                return redirect()->to('/auth/login');
            }
            
            if (!$cliente || $cliente['empresa_id'] != $empresaId) {
                throw new \CodeIgniter\Exceptions\PageNotFoundException('Cliente no encontrado');
            }
            
            $listasPrecios = [];
            try {
                $listasPrecios = $this->listaPrecioModel->where('empresa_id', $empresaId)->findAll();
            } catch (\Exception $e) {
                log_message('error', 'Error al obtener listas de precios: ' . $e->getMessage());
            }
            
            $data = [
                'title' => 'Editar Cliente',
                'cliente' => $cliente,
                'listas_precios' => $listasPrecios
            ];

            return $this->render('clientes/form', $data);
        } catch (\CodeIgniter\Exceptions\PageNotFoundException $e) {
            throw $e;
        } catch (\Exception $e) {
            log_message('error', 'Error en editar cliente: ' . $e->getMessage());
            return redirect()->to('/clientes')
                ->with('error', 'Error al cargar el formulario de edición');
        }
    }

    public function actualizar($id)
    {
        try {
            $this->checkAuth();
            $cliente = $this->clienteModel->find($id);
            
            $empresaId = $this->user['empresa_id'] ?? null;
            
            if (!$empresaId) {
                return $this->errorResponse('Usuario no tiene empresa asignada', 403);
            }
            
            if (!$cliente || $cliente['empresa_id'] != $empresaId) {
                return $this->errorResponse('Cliente no encontrado', 404);
            }

            $rules = [
                'tipo_cliente' => 'required|in_list[PERSONA,EMPRESA]',
                'email' => 'valid_email|max_length[150]'
            ];

            if (!$this->validate($rules)) {
                return $this->errorResponse('Error de validación: ' . implode(', ', $this->validator->getErrors()));
            }

            // Normalizar fecha_nacimiento (convertir cadena vacía a null)
            $fechaNacimiento = $this->normalizarFecha($this->request->getPost('fecha_nacimiento'));

            // Validar y convertir campos integer
            $listaPrecioId = $this->request->getPost('lista_precio_id');
            if (!empty($listaPrecioId) && !is_numeric($listaPrecioId)) {
                return $this->errorResponse('La lista de precios seleccionada no es válida');
            }
            $listaPrecioId = !empty($listaPrecioId) ? (int)$listaPrecioId : null;

            $data = [
                'tipo_cliente' => $this->request->getPost('tipo_cliente'),
                'ruc' => $this->request->getPost('ruc') ? trim($this->request->getPost('ruc')) : null,
                'ci' => $this->request->getPost('ci') ? trim($this->request->getPost('ci')) : null,
                'nombre' => $this->request->getPost('nombre') ? trim($this->request->getPost('nombre')) : null,
                'apellido' => $this->request->getPost('apellido') ? trim($this->request->getPost('apellido')) : null,
                'razon_social' => $this->request->getPost('razon_social') ? trim($this->request->getPost('razon_social')) : null,
                'nombre_fantasia' => $this->request->getPost('nombre_fantasia') ? trim($this->request->getPost('nombre_fantasia')) : null,
                'direccion' => $this->request->getPost('direccion') ? trim($this->request->getPost('direccion')) : null,
                'ciudad' => $this->request->getPost('ciudad') ? trim($this->request->getPost('ciudad')) : null,
                'departamento' => $this->request->getPost('departamento') ? trim($this->request->getPost('departamento')) : null,
                'barrio' => $this->request->getPost('barrio') ? trim($this->request->getPost('barrio')) : null,
                'telefono' => $this->request->getPost('telefono') ? trim($this->request->getPost('telefono')) : null,
                'celular' => $this->request->getPost('celular') ? trim($this->request->getPost('celular')) : null,
                'email' => $this->request->getPost('email') ? trim($this->request->getPost('email')) : null,
                'fecha_nacimiento' => $fechaNacimiento,
                'sexo' => $this->request->getPost('sexo') ?: null,
                'tipo_contribuyente' => $this->request->getPost('tipo_contribuyente') ?: 'CONTRIBUYENTE',
                'categoria_cliente' => $this->request->getPost('categoria_cliente') ?: 'NORMAL',
                'lista_precio_id' => $listaPrecioId,
                'limite_credito' => $this->normalizarNumero($this->request->getPost('limite_credito'), 0, 9999999999999.99),
                'dias_credito' => $this->normalizarNumero($this->request->getPost('dias_credito'), 0),
                'descuento_porcentaje' => $this->normalizarNumero($this->request->getPost('descuento_porcentaje'), 0),
                'observaciones' => $this->request->getPost('observaciones') ? trim($this->request->getPost('observaciones')) : null
            ];

            // Subir foto si existe
            try {
                $foto = $this->request->getFile('foto');
                if ($foto && $foto->isValid() && !$foto->hasMoved()) {
                    $uploadPath = ROOTPATH . 'public/uploads/clientes';
                    
                    // Crear directorio si no existe
                    if (!is_dir($uploadPath)) {
                        mkdir($uploadPath, 0755, true);
                    }
                    
                    // Validar tipo de archivo
                    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                    if (!in_array($foto->getMimeType(), $allowedTypes)) {
                        return $this->errorResponse('Tipo de archivo no permitido. Solo se permiten imágenes (JPG, PNG, GIF, WEBP)');
                    }
                    
                    // Validar tamaño (max 2MB)
                    if ($foto->getSize() > 2097152) {
                        return $this->errorResponse('La imagen es demasiado grande. Tamaño máximo: 2MB');
                    }
                    
                    // Eliminar foto anterior si existe
                    if ($cliente['foto'] && file_exists($uploadPath . '/' . $cliente['foto'])) {
                        @unlink($uploadPath . '/' . $cliente['foto']);
                    }
                    
                    $newName = $foto->getRandomName();
                    if ($foto->move($uploadPath, $newName)) {
                        $data['foto'] = $newName;
                    } else {
                        return $this->errorResponse('Error al subir la foto: ' . $foto->getErrorString());
                    }
                }
            } catch (\Exception $e) {
                return $this->errorResponse('Error al procesar la foto: ' . $e->getMessage());
            }

            // Remover campos vacíos que puedan causar problemas
            foreach ($data as $key => $value) {
                if ($value === '' && !in_array($key, ['telefono', 'celular', 'direccion', 'ciudad', 'departamento', 'barrio', 'observaciones', 'foto'])) {
                    $data[$key] = null;
                }
            }

            // Asegurar que no se pasen campos que CodeIgniter maneja automáticamente
            unset($data['fecha_creacion']);
            unset($data['fecha_modificacion']);

            // Log para debug en desarrollo
            if (ENVIRONMENT === 'development') {
                log_message('debug', 'ClientesController::actualizar - Datos antes de update: ' . json_encode($data));
            }

            try {
                if ($this->clienteModel->update($id, $data)) {
                    try {
                        $auditoriaModel = new \App\Models\AuditoriaModel();
                        $auditoriaModel->insert([
                            'usuario_id' => $this->user['id'] ?? null,
                            'empresa_id' => $cliente['empresa_id'],
                            'tabla_afectada' => 'clientes',
                            'accion' => 'UPDATE',
                            'registro_id' => $id,
                            'datos_anteriores' => json_encode($cliente),
                            'datos_nuevos' => json_encode($data),
                            'ip_address' => $this->request->getIPAddress()
                        ]);
                    } catch (\Exception $e) {
                        log_message('error', 'Error al registrar auditoría: ' . $e->getMessage());
                    }

                    return $this->successResponse('Cliente actualizado exitosamente', [
                        'redirect' => base_url('clientes')
                    ]);
                } else {
                    log_message('error', 'ClientesController::actualizar - Error al actualizar cliente');
                    return $this->errorResponse('Error al actualizar el cliente');
                }
            } catch (\Exception $e) {
                log_message('error', 'Error en actualizar cliente: ' . $e->getMessage());
                log_message('error', 'Stack trace: ' . $e->getTraceAsString());
                log_message('error', 'Datos: ' . json_encode($data ?? []));
                return $this->errorResponse('Error al actualizar el cliente: ' . $e->getMessage());
            }
        } catch (\Exception $e) {
            log_message('error', 'Error general en actualizar cliente: ' . $e->getMessage());
            log_message('error', 'Stack trace: ' . $e->getTraceAsString());
            return $this->errorResponse('Error al actualizar el cliente: ' . $e->getMessage());
        }
    }

    public function ver($id)
    {
        try {
            $this->checkAuth();
            $cliente = $this->clienteModel->find($id);
            
            $empresaId = $this->user['empresa_id'] ?? null;
            
            if (!$empresaId) {
                return redirect()->to('/auth/login');
            }
            
            if (!$cliente || $cliente['empresa_id'] != $empresaId) {
                throw new \CodeIgniter\Exceptions\PageNotFoundException('Cliente no encontrado');
            }

            // Obtener historial de ventas con manejo de errores
            $ventas = [];
            try {
                $ventaModel = new \App\Models\VentaModel();
                $ventas = $ventaModel->where('cliente_id', $id)
                                    ->orderBy('fecha_venta', 'DESC')
                                    ->limit(10)
                                    ->findAll();
            } catch (\Exception $e) {
                log_message('error', 'Error al obtener ventas del cliente: ' . $e->getMessage());
            }

            // Obtener cuentas por cobrar pendientes
            $cuentasCobrar = [];
            $totalPendiente = 0;
            try {
                $db = \Config\Database::connect();
                $cuentasCobrar = $db->table('cuentas_cobrar')
                                   ->where('cliente_id', $id)
                                   ->where('monto_pendiente >', 0)
                                   ->orderBy('fecha_vencimiento', 'ASC')
                                   ->limit(10)
                                   ->get()
                                   ->getResultArray();
                
                $totalPendiente = $db->table('cuentas_cobrar')
                                    ->where('cliente_id', $id)
                                    ->where('monto_pendiente >', 0)
                                    ->selectSum('monto_pendiente')
                                    ->get()
                                    ->getRowArray();
                $totalPendiente = floatval($totalPendiente['monto_pendiente'] ?? 0);
            } catch (\Exception $e) {
                log_message('error', 'Error al obtener cuentas por cobrar: ' . $e->getMessage());
            }

            $data = [
                'title' => 'Ver Cliente',
                'cliente' => $cliente,
                'ventas' => $ventas,
                'cuentas_cobrar' => $cuentasCobrar,
                'total_pendiente' => $totalPendiente
            ];

            return $this->render('clientes/ver', $data);
        } catch (\CodeIgniter\Exceptions\PageNotFoundException $e) {
            throw $e;
        } catch (\Exception $e) {
            log_message('error', 'Error en ver cliente: ' . $e->getMessage());
            return redirect()->to('/clientes')
                ->with('error', 'Error al cargar el cliente');
        }
    }

    public function eliminar($id)
    {
        try {
            $this->checkAuth();
            $empresaId = $this->user['empresa_id'] ?? null;
            
            if (!$empresaId) {
                return $this->errorResponse('Usuario no tiene empresa asignada', 403);
            }
            
            $cliente = $this->clienteModel->find($id);
            
            if (!$cliente || $cliente['empresa_id'] != $empresaId) {
                return $this->errorResponse('Cliente no encontrado', 404);
            }
            
            // Verificar si tiene ventas asociadas
            try {
                $ventaModel = new \App\Models\VentaModel();
                $tieneVentas = $ventaModel->where('cliente_id', $id)->countAllResults();
                
                if ($tieneVentas > 0) {
                    return $this->errorResponse('No se puede eliminar el cliente porque tiene ventas asociadas');
                }
            } catch (\Exception $e) {
                log_message('error', 'Error al verificar ventas asociadas: ' . $e->getMessage());
            }
            
            // Verificar si tiene cuentas por cobrar
            try {
                $cuentaCobrarModel = new \App\Models\CuentaCobrarModel();
                $tieneCuentas = $cuentaCobrarModel->where('cliente_id', $id)
                                                 ->where('monto_pendiente >', 0)
                                                 ->countAllResults();
                
                if ($tieneCuentas > 0) {
                    return $this->errorResponse('No se puede eliminar el cliente porque tiene cuentas por cobrar pendientes');
                }
            } catch (\Exception $e) {
                log_message('error', 'Error al verificar cuentas por cobrar: ' . $e->getMessage());
            }
            
            // Solo desactivar
            if ($this->clienteModel->update($id, ['estado' => false])) {
                try {
                    $auditoriaModel = new \App\Models\AuditoriaModel();
                    $auditoriaModel->insert([
                        'usuario_id' => $this->user['id'] ?? null,
                        'empresa_id' => $cliente['empresa_id'],
                        'tabla_afectada' => 'clientes',
                        'accion' => 'DELETE',
                        'registro_id' => $id,
                        'datos_anteriores' => json_encode($cliente),
                        'ip_address' => $this->request->getIPAddress()
                    ]);
                } catch (\Exception $e) {
                    log_message('error', 'Error al registrar auditoría: ' . $e->getMessage());
                }
                
                return $this->successResponse('Cliente eliminado exitosamente');
            }
            
            return $this->errorResponse('Error al eliminar el cliente');
        } catch (\Exception $e) {
            log_message('error', 'Error en eliminar cliente: ' . $e->getMessage());
            return $this->errorResponse('Error al eliminar el cliente: ' . $e->getMessage());
        }
    }

    private function generarCodigoCliente($empresaId)
    {
        $ultimo = $this->clienteModel->where('empresa_id', $empresaId)
                                     ->orderBy('id', 'DESC')
                                     ->first();
        
        $numero = $ultimo ? ((int)str_replace('CLI-', '', $ultimo['codigo'] ?? '0') + 1) : 1;
        return 'CLI-' . str_pad($numero, 6, '0', STR_PAD_LEFT);
    }
}
