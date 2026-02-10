<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\EmpresaModel;

class EmpresasController extends BaseController
{
    protected $empresaModel;

    public function __construct()
    {
        $this->empresaModel = new EmpresaModel();
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
        $this->requirePermission('CONFIGURACION', 'ver');
    }

    public function index()
    {
        try {
            $this->checkAuth();
            $empresas = [];
            try {
                $empresas = $this->empresaModel->findAll();
            } catch (\Exception $e) {
                log_message('error', 'Error al obtener empresas: ' . $e->getMessage());
            }
            
            $data = [
                'title' => 'Gestión de Empresas',
                'empresas' => $empresas
            ];

            return $this->render('admin/empresas/index', $data);
        } catch (\Exception $e) {
            log_message('error', 'Error en index empresas: ' . $e->getMessage());
            return redirect()->to('/dashboard')
                ->with('error', 'Error al cargar las empresas');
        }
    }

    public function crear()
    {
        try {
            $this->checkAuth();
            $data = [
                'title' => 'Nueva Empresa',
                'empresa' => null
            ];

            return $this->render('admin/empresas/form', $data);
        } catch (\Exception $e) {
            log_message('error', 'Error en crear empresa: ' . $e->getMessage());
            return redirect()->to('/admin/empresas')
                ->with('error', 'Error al cargar el formulario');
        }
    }

    public function guardar()
    {
        // Verificar autenticación sin redirigir (para AJAX)
        if (!$this->isLoggedIn()) {
            return $this->errorResponse('No autenticado', 401);
        }
        
        // Cargar datos del usuario (necesario antes de verificar permisos)
        $this->loadUserData();
        
        // Verificar que el usuario esté cargado
        if (!$this->user) {
            return $this->errorResponse('No autenticado', 401);
        }
        
        try {
            // Log para debug en desarrollo
            if (ENVIRONMENT === 'development') {
                log_message('debug', 'EmpresasController::guardar - POST data: ' . json_encode($this->request->getPost()));
            }
            
            // Obtener datos del POST
            $rucPost = trim($this->request->getPost('ruc'));
            $nombrePost = trim($this->request->getPost('nombre'));
            $emailPost = trim($this->request->getPost('email'));
            
            // Validar que los datos requeridos estén presentes
            if (empty($nombrePost) || empty($rucPost)) {
                if (ENVIRONMENT === 'development') {
                    log_message('debug', 'EmpresasController::guardar - Campos requeridos faltantes. nombre: ' . var_export($nombrePost, true) . ', ruc: ' . var_export($rucPost, true));
                }
                return $this->errorResponse('El nombre y RUC son requeridos');
            }
            
            // Limpiar RUC (remover espacios y caracteres especiales, excepto el guión)
            $rucPost = preg_replace('/[^0-9-]/', '', $rucPost);
            
            // Log para debug
            if (ENVIRONMENT === 'development') {
                log_message('debug', 'EmpresasController::guardar - RUC después de limpiar: ' . $rucPost);
            }
            
            // Validar formato de RUC
            if (!preg_match('/^[0-9]{8}-[0-9]{1}$/', $rucPost)) {
                if (ENVIRONMENT === 'development') {
                    log_message('debug', 'EmpresasController::guardar - RUC con formato inválido: ' . $rucPost);
                }
                return $this->errorResponse('El RUC debe tener el formato XXXXXXXX-X (8 dígitos, guión, 1 dígito verificador)');
            }
            
            // Validar RUC (verificación de dígito verificador)
            // Hacer la validación más flexible: si falla la validación del dígito verificador,
            // permitir el RUC pero registrar un warning (en producción se puede hacer más estricto)
            $rucValido = $this->empresaModel->validarRUC($rucPost);
            if (!$rucValido) {
                if (ENVIRONMENT === 'production') {
                    // En producción, rechazar RUCs con dígito verificador inválido
                    return $this->errorResponse('El RUC no es válido. El dígito verificador no coincide.');
                } else {
                    // En desarrollo, solo registrar un warning pero permitir continuar
                    log_message('warning', 'EmpresasController::guardar - RUC con dígito verificador inválido (permitido en desarrollo): ' . $rucPost);
                }
            }
            
            // Verificar que el RUC no esté duplicado
            $rucExiste = $this->empresaModel->where('ruc', $rucPost)->countAllResults();
            if ($rucExiste > 0) {
                if (ENVIRONMENT === 'development') {
                    log_message('debug', 'EmpresasController::guardar - RUC duplicado: ' . $rucPost);
                }
                return $this->errorResponse('El RUC ya está registrado por otra empresa');
            }
            
            // Validar email si está presente
            if (!empty($emailPost) && !filter_var($emailPost, FILTER_VALIDATE_EMAIL)) {
                if (ENVIRONMENT === 'development') {
                    log_message('debug', 'EmpresasController::guardar - Email inválido: ' . $emailPost);
                }
                return $this->errorResponse('El email no tiene un formato válido');
            }
            
            // Validar porcentaje_iva manualmente (más flexible)
            $porcentajeIvaPost = $this->request->getPost('porcentaje_iva');
            if (ENVIRONMENT === 'development') {
                log_message('debug', 'EmpresasController::guardar - porcentaje_iva recibido: ' . var_export($porcentajeIvaPost, true));
            }
            
            if (!empty($porcentajeIvaPost)) {
                $porcentajeIvaFloat = (float)$porcentajeIvaPost;
                if ($porcentajeIvaFloat != 5.0 && $porcentajeIvaFloat != 10.0) {
                    if (ENVIRONMENT === 'development') {
                        log_message('debug', 'EmpresasController::guardar - porcentaje_iva inválido: ' . $porcentajeIvaFloat);
                    }
                    return $this->errorResponse('El porcentaje de IVA debe ser 5% o 10%');
                }
            } else {
                // Si no se envía porcentaje_iva, usar 10% por defecto
                $porcentajeIvaPost = '10.00';
                if (ENVIRONMENT === 'development') {
                    log_message('debug', 'EmpresasController::guardar - porcentaje_iva no enviado, usando 10% por defecto');
                }
            }
            
            // Validar moneda_principal
            $monedaPrincipalPost = $this->request->getPost('moneda_principal');
            if (ENVIRONMENT === 'development') {
                log_message('debug', 'EmpresasController::guardar - moneda_principal recibida: ' . var_export($monedaPrincipalPost, true));
            }
            
            if (!empty($monedaPrincipalPost) && !in_array($monedaPrincipalPost, ['PYG', 'USD', 'BRL'])) {
                return $this->errorResponse('La moneda principal debe ser PYG, USD o BRL');
            }
            
            // Validaciones básicas sin is_unique (ya validamos manualmente arriba)
            $rules = [
                'nombre' => 'required|min_length[3]|max_length[200]'
            ];

            if (!$this->validate($rules)) {
                $errors = $this->validator->getErrors();
                if (ENVIRONMENT === 'development') {
                    log_message('debug', 'EmpresasController::guardar - Errores de validación: ' . json_encode($errors));
                }
                return $this->errorResponse('Error de validación: ' . implode(', ', $errors));
            }

            // Construir array de datos con normalización
            $data = [
                'nombre' => trim($this->request->getPost('nombre')),
                'ruc' => $rucPost, // Ya limpiado arriba
                'direccion' => $this->request->getPost('direccion') ? trim($this->request->getPost('direccion')) : null,
                'ciudad' => $this->request->getPost('ciudad') ? trim($this->request->getPost('ciudad')) : null,
                'telefono' => $this->request->getPost('telefono') ? trim($this->request->getPost('telefono')) : null,
                'email' => $emailPost ? trim($emailPost) : null,
                'porcentaje_iva' => (float)$porcentajeIvaPost, // Ya validado arriba
                'timbrado' => $this->request->getPost('timbrado') ? (int)$this->request->getPost('timbrado') : null,
                'establecimiento' => $this->request->getPost('establecimiento') ? (int)$this->request->getPost('establecimiento') : null,
                'punto_expedicion' => $this->request->getPost('punto_expedicion') ? (int)$this->request->getPost('punto_expedicion') : null,
                'moneda_principal' => $monedaPrincipalPost ? $monedaPrincipalPost : 'PYG',
                'dias_plazo_retiro' => $this->request->getPost('dias_plazo_retiro') ? (int)$this->request->getPost('dias_plazo_retiro') : 90,
                'aplica_iva' => $this->request->getPost('aplica_iva') ? true : false,
                'usa_facturacion_electronica' => $this->request->getPost('usa_facturacion_electronica') ? true : false,
                'terminos_condiciones' => $this->request->getPost('terminos_condiciones') ? trim($this->request->getPost('terminos_condiciones')) : null,
                'politica_garantia' => $this->request->getPost('politica_garantia') ? trim($this->request->getPost('politica_garantia')) : null,
                'usuario_creacion' => $this->session->get('user_id') ? (int)$this->session->get('user_id') : null
            ];
            
            // Remover campos vacíos que puedan causar problemas
            foreach ($data as $key => $value) {
                if ($value === '' && !in_array($key, ['direccion', 'ciudad', 'telefono', 'email', 'terminos_condiciones', 'politica_garantia'])) {
                    unset($data[$key]);
                }
            }
            
            // Asegurar que no se pasen campos que CodeIgniter maneja automáticamente
            unset($data['fecha_creacion']);
            unset($data['fecha_modificacion']);

        // Subir logo si existe
        try {
            $logo = $this->request->getFile('logo');
            if ($logo && $logo->isValid() && !$logo->hasMoved()) {
                $uploadPath = ROOTPATH . 'public/uploads/empresas';
                
                // Crear directorio si no existe
                if (!is_dir($uploadPath)) {
                    mkdir($uploadPath, 0755, true);
                }
                
                // Validar tipo de archivo
                $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                if (!in_array($logo->getMimeType(), $allowedTypes)) {
                    return $this->errorResponse('Tipo de archivo no permitido. Solo se permiten imágenes (JPG, PNG, GIF, WEBP)');
                }
                
                // Validar tamaño (max 2MB)
                if ($logo->getSize() > 2097152) {
                    return $this->errorResponse('La imagen es demasiado grande. Tamaño máximo: 2MB');
                }
                
                $newName = $logo->getRandomName();
                if ($logo->move($uploadPath, $newName)) {
                    $data['logo'] = $newName;
                } else {
                    return $this->errorResponse('Error al subir el logo: ' . $logo->getErrorString());
                }
            }
        } catch (\Exception $e) {
            return $this->errorResponse('Error al procesar el logo: ' . $e->getMessage());
        }

            // Desactivar validación del modelo para inserciones
            // Ya validamos manualmente en el controlador (incluyendo unicidad)
            $this->empresaModel->skipValidation(true);
            
            // Log para debug en desarrollo
            if (ENVIRONMENT === 'development') {
                log_message('debug', 'EmpresasController::guardar - Datos antes de insert: ' . json_encode($data));
                log_message('debug', 'EmpresasController::guardar - Tipos de datos: ' . json_encode(array_map('gettype', $data)));
            }
            
            // Intentar insertar con manejo de errores mejorado
            try {
                $result = $this->empresaModel->insert($data);
            } catch (\Exception $e) {
                log_message('error', 'EmpresasController::guardar - Excepción en insert: ' . $e->getMessage());
                log_message('error', 'EmpresasController::guardar - Stack trace: ' . $e->getTraceAsString());
                log_message('error', 'EmpresasController::guardar - Datos enviados: ' . json_encode($data));
                // Reactivar validación
                $this->empresaModel->skipValidation(false);
                return $this->errorResponse('Error al crear la empresa: ' . $e->getMessage());
            }
            
            if (!$result) {
                $errors = $this->empresaModel->errors();
                $errorMessage = 'Error al crear la empresa';
                if (!empty($errors)) {
                    $errorMessage .= ': ' . implode(', ', $errors);
                }
                log_message('error', 'EmpresasController::guardar - Error en insert: ' . $errorMessage);
                log_message('error', 'EmpresasController::guardar - Datos enviados: ' . json_encode($data));
                log_message('error', 'EmpresasController::guardar - Errores del modelo: ' . json_encode($errors));
                // Reactivar validación
                $this->empresaModel->skipValidation(false);
                return $this->errorResponse($errorMessage);
            }
            
            // Reactivar validación para futuras operaciones
            $this->empresaModel->skipValidation(false);
            
            if ($result) {
                $empresaId = $this->empresaModel->getInsertID();
                
                // Registrar auditoría
                try {
                    $auditoriaModel = new \App\Models\AuditoriaModel();
                    $auditoriaModel->registrar([
                        'usuario_id' => $this->session->get('user_id'),
                        'empresa_id' => $empresaId,
                        'tabla_afectada' => 'empresas',
                        'accion' => 'INSERT',
                        'datos_nuevos' => $data,
                        'ip_address' => $this->request->getIPAddress()
                    ]);
                } catch (\Exception $e) {
                    log_message('error', 'EmpresasController::guardar - Error al registrar auditoría: ' . $e->getMessage());
                }

                return $this->successResponse('Empresa creada exitosamente', [
                    'redirect' => base_url('admin/empresas')
                ]);
            }

            return $this->errorResponse('Error al crear la empresa');
        } catch (\Exception $e) {
            log_message('error', 'Error en guardar empresa: ' . $e->getMessage());
            return $this->errorResponse('Error al crear la empresa: ' . $e->getMessage());
        }
    }

    public function editar($id)
    {
        try {
            $this->checkAuth();
            $empresa = $this->empresaModel->find($id);
            
            if (!$empresa) {
                throw new \CodeIgniter\Exceptions\PageNotFoundException('Empresa no encontrada');
            }

            $data = [
                'title' => 'Editar Empresa',
                'empresa' => $empresa
            ];

            return $this->render('admin/empresas/form', $data);
        } catch (\CodeIgniter\Exceptions\PageNotFoundException $e) {
            throw $e;
        } catch (\Exception $e) {
            log_message('error', 'Error en editar empresa: ' . $e->getMessage());
            return redirect()->to('/admin/empresas')
                ->with('error', 'Error al cargar el formulario de edición');
        }
    }

    public function actualizar($id)
    {
        // Verificar autenticación sin redirigir (para AJAX)
        if (!$this->isLoggedIn()) {
            return $this->errorResponse('No autenticado', 401);
        }
        
        // Cargar datos del usuario (necesario antes de verificar permisos)
        $this->loadUserData();
        
        // Verificar que el usuario esté cargado
        if (!$this->user) {
            return $this->errorResponse('No autenticado', 401);
        }
        
        try {
            $empresa = $this->empresaModel->find($id);
            
            if (!$empresa) {
                return $this->errorResponse('Empresa no encontrada', 404);
            }

            // Obtener datos del POST
            $rucPost = trim($this->request->getPost('ruc'));
            $nombrePost = trim($this->request->getPost('nombre'));
            $emailPost = trim($this->request->getPost('email'));
            
            // Validar que los datos requeridos estén presentes
            if (empty($nombrePost) || empty($rucPost)) {
                return $this->errorResponse('El nombre y RUC son requeridos');
            }
            
            // Limpiar RUC (remover espacios y caracteres especiales, excepto el guión)
            $rucPost = preg_replace('/[^0-9-]/', '', $rucPost);
            
            // Validar formato de RUC
            if (!preg_match('/^[0-9]{8}-[0-9]{1}$/', $rucPost)) {
                return $this->errorResponse('El RUC debe tener el formato XXXXXXXX-X (8 dígitos, guión, 1 dígito verificador)');
            }
            
            // Validar RUC (verificación de dígito verificador)
            // Hacer la validación más flexible: si falla la validación del dígito verificador,
            // permitir el RUC pero registrar un warning (en producción se puede hacer más estricto)
            $rucValido = $this->empresaModel->validarRUC($rucPost);
            if (!$rucValido && ENVIRONMENT === 'production') {
                // En producción, rechazar RUCs con dígito verificador inválido
                return $this->errorResponse('El RUC no es válido. El dígito verificador no coincide.');
            } elseif (!$rucValido) {
                // En desarrollo, solo registrar un warning pero permitir continuar
                log_message('warning', 'EmpresasController::actualizar - RUC con dígito verificador inválido: ' . $rucPost);
            }
            
            // Verificar que el RUC no esté duplicado (excepto la empresa actual)
            $rucExiste = $this->empresaModel->where('ruc', $rucPost)->where('id !=', $id)->countAllResults();
            if ($rucExiste > 0) {
                return $this->errorResponse('El RUC ya está registrado por otra empresa');
            }
            
            // Validar email si está presente
            if (!empty($emailPost) && !filter_var($emailPost, FILTER_VALIDATE_EMAIL)) {
                return $this->errorResponse('El email no tiene un formato válido');
            }
            
            // Validar porcentaje_iva manualmente (más flexible)
            $porcentajeIvaPost = $this->request->getPost('porcentaje_iva');
            if (!empty($porcentajeIvaPost)) {
                $porcentajeIvaFloat = (float)$porcentajeIvaPost;
                if ($porcentajeIvaFloat != 5.0 && $porcentajeIvaFloat != 10.0) {
                    return $this->errorResponse('El porcentaje de IVA debe ser 5% o 10%');
                }
            } else {
                // Si no se envía porcentaje_iva, usar 10% por defecto
                $porcentajeIvaPost = '10.00';
            }
            
            // Validar moneda_principal
            $monedaPrincipalPost = $this->request->getPost('moneda_principal');
            if (!empty($monedaPrincipalPost) && !in_array($monedaPrincipalPost, ['PYG', 'USD', 'BRL'])) {
                return $this->errorResponse('La moneda principal debe ser PYG, USD o BRL');
            }

        // Validaciones básicas sin is_unique (ya validamos manualmente arriba)
        $rules = [
            'nombre' => 'required|min_length[3]|max_length[200]'
        ];

        if (!$this->validate($rules)) {
            return $this->errorResponse('Error de validación: ' . implode(', ', $this->validator->getErrors()));
        }

        // Construir array de datos con normalización
        $data = [
            'nombre' => trim($this->request->getPost('nombre')),
            'ruc' => $rucPost, // Ya limpiado arriba
            'direccion' => $this->request->getPost('direccion') ? trim($this->request->getPost('direccion')) : null,
            'ciudad' => $this->request->getPost('ciudad') ? trim($this->request->getPost('ciudad')) : null,
            'telefono' => $this->request->getPost('telefono') ? trim($this->request->getPost('telefono')) : null,
            'email' => $emailPost ? trim($emailPost) : null,
            'porcentaje_iva' => (float)$porcentajeIvaPost, // Ya validado arriba
            'timbrado' => $this->request->getPost('timbrado') ? (int)$this->request->getPost('timbrado') : null,
            'establecimiento' => $this->request->getPost('establecimiento') ? (int)$this->request->getPost('establecimiento') : null,
            'punto_expedicion' => $this->request->getPost('punto_expedicion') ? (int)$this->request->getPost('punto_expedicion') : null,
            'moneda_principal' => $monedaPrincipalPost ? $monedaPrincipalPost : 'PYG',
            'dias_plazo_retiro' => $this->request->getPost('dias_plazo_retiro') ? (int)$this->request->getPost('dias_plazo_retiro') : 90,
            'aplica_iva' => $this->request->getPost('aplica_iva') ? true : false,
            'usa_facturacion_electronica' => $this->request->getPost('usa_facturacion_electronica') ? true : false,
            'terminos_condiciones' => $this->request->getPost('terminos_condiciones') ? trim($this->request->getPost('terminos_condiciones')) : null,
            'politica_garantia' => $this->request->getPost('politica_garantia') ? trim($this->request->getPost('politica_garantia')) : null
        ];
        
        // Remover campos vacíos que puedan causar problemas
        foreach ($data as $key => $value) {
            if ($value === '' && !in_array($key, ['direccion', 'ciudad', 'telefono', 'email', 'terminos_condiciones', 'politica_garantia'])) {
                unset($data[$key]);
            }
        }
        
        // Asegurar que no se pasen campos que CodeIgniter maneja automáticamente
        unset($data['fecha_creacion']);
        unset($data['fecha_modificacion']);

        // Subir logo si existe
        try {
            $logo = $this->request->getFile('logo');
            if ($logo && $logo->isValid() && !$logo->hasMoved()) {
                $uploadPath = ROOTPATH . 'public/uploads/empresas';
                
                // Crear directorio si no existe
                if (!is_dir($uploadPath)) {
                    mkdir($uploadPath, 0755, true);
                }
                
                // Validar tipo de archivo
                $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                if (!in_array($logo->getMimeType(), $allowedTypes)) {
                    return $this->errorResponse('Tipo de archivo no permitido. Solo se permiten imágenes (JPG, PNG, GIF, WEBP)');
                }
                
                // Validar tamaño (max 2MB)
                if ($logo->getSize() > 2097152) {
                    return $this->errorResponse('La imagen es demasiado grande. Tamaño máximo: 2MB');
                }
                
                // Eliminar logo anterior si existe
                if ($empresa['logo'] && file_exists($uploadPath . '/' . $empresa['logo'])) {
                    @unlink($uploadPath . '/' . $empresa['logo']);
                }
                
                $newName = $logo->getRandomName();
                if ($logo->move($uploadPath, $newName)) {
                    $data['logo'] = $newName;
                } else {
                    return $this->errorResponse('Error al subir el logo: ' . $logo->getErrorString());
                }
            }
        } catch (\Exception $e) {
            return $this->errorResponse('Error al procesar el logo: ' . $e->getMessage());
        }

        // Desactivar validación del modelo para actualizaciones
        // Ya validamos manualmente en el controlador (incluyendo unicidad)
        $this->empresaModel->skipValidation(true);
        
        // Log para debug en desarrollo
        if (ENVIRONMENT === 'development') {
            log_message('debug', 'EmpresasController::actualizar - Datos antes de update: ' . json_encode($data));
            log_message('debug', 'EmpresasController::actualizar - Tipos de datos: ' . json_encode(array_map('gettype', $data)));
        }
        
        // Intentar actualizar con manejo de errores mejorado
        try {
            $result = $this->empresaModel->update($id, $data);
        } catch (\Exception $e) {
            log_message('error', 'EmpresasController::actualizar - Excepción en update: ' . $e->getMessage());
            log_message('error', 'EmpresasController::actualizar - Stack trace: ' . $e->getTraceAsString());
            log_message('error', 'EmpresasController::actualizar - Datos enviados: ' . json_encode($data));
            // Reactivar validación
            $this->empresaModel->skipValidation(false);
            return $this->errorResponse('Error al actualizar la empresa: ' . $e->getMessage());
        }
        
        if (!$result) {
            $errors = $this->empresaModel->errors();
            $errorMessage = 'Error al actualizar la empresa';
            if (!empty($errors)) {
                $errorMessage .= ': ' . implode(', ', $errors);
            }
            log_message('error', 'EmpresasController::actualizar - Error en update: ' . $errorMessage);
            log_message('error', 'EmpresasController::actualizar - Datos enviados: ' . json_encode($data));
            log_message('error', 'EmpresasController::actualizar - ID: ' . $id);
            log_message('error', 'EmpresasController::actualizar - Errores del modelo: ' . json_encode($errors));
            // Reactivar validación
            $this->empresaModel->skipValidation(false);
            return $this->errorResponse($errorMessage);
        }
        
        // Reactivar validación para futuras operaciones
        $this->empresaModel->skipValidation(false);
        
        if ($result) {
            // Registrar auditoría
            try {
                $auditoriaModel = new \App\Models\AuditoriaModel();
                $auditoriaModel->registrar([
                    'usuario_id' => $this->session->get('user_id'),
                    'empresa_id' => $id,
                    'tabla_afectada' => 'empresas',
                    'accion' => 'UPDATE',
                    'registro_id' => $id,
                    'datos_anteriores' => $empresa,
                    'datos_nuevos' => $data,
                    'ip_address' => $this->request->getIPAddress()
                ]);
            } catch (\Exception $e) {
                log_message('error', 'EmpresasController::actualizar - Error al registrar auditoría: ' . $e->getMessage());
            }

            return $this->successResponse('Empresa actualizada exitosamente', [
                'redirect' => base_url('admin/empresas')
            ]);
        }

            return $this->errorResponse('Error al actualizar la empresa');
        } catch (\Exception $e) {
            // Asegurar que la validación se reactive incluso si hay excepción
            $this->empresaModel->skipValidation(false);
            log_message('error', 'EmpresasController::actualizar - Excepción general: ' . $e->getMessage());
            log_message('error', 'EmpresasController::actualizar - Stack trace: ' . $e->getTraceAsString());
            log_message('error', 'EmpresasController::actualizar - Datos: ' . json_encode($data ?? []));
            return $this->errorResponse('Error al actualizar la empresa: ' . $e->getMessage());
        }
    }

    public function eliminar($id)
    {
        try {
            $this->checkAuth();
            $empresa = $this->empresaModel->find($id);
            
            if (!$empresa) {
                return $this->errorResponse('Empresa no encontrada', 404);
            }

            // Verificar si tiene usuarios o sucursales
            try {
                $sucursalModel = new \App\Models\SucursalModel();
                $sucursales = $sucursalModel->where('empresa_id', $id)->countAllResults();
                
                if ($sucursales > 0) {
                    return $this->errorResponse('No se puede eliminar la empresa porque tiene sucursales asociadas');
                }
            } catch (\Exception $e) {
                log_message('error', 'Error al verificar sucursales asociadas: ' . $e->getMessage());
            }

            // Desactivar validación para la actualización
            $this->empresaModel->skipValidation(true);
            
            // Preparar datos para actualizar
            $data = ['estado' => false];
            
            // Log para debug en desarrollo
            if (ENVIRONMENT === 'development') {
                log_message('debug', 'EmpresasController::eliminar - Datos antes de update: ' . json_encode($data));
                log_message('debug', 'EmpresasController::eliminar - ID: ' . $id);
            }
            
            // Intentar actualizar con manejo de errores mejorado
            try {
                $result = $this->empresaModel->update($id, $data);
            } catch (\Exception $e) {
                log_message('error', 'EmpresasController::eliminar - Excepción en update: ' . $e->getMessage());
                log_message('error', 'EmpresasController::eliminar - Stack trace: ' . $e->getTraceAsString());
                $this->empresaModel->skipValidation(false);
                return $this->errorResponse('Error al desactivar la empresa: ' . $e->getMessage());
            }
            
            // Reactivar validación
            $this->empresaModel->skipValidation(false);
            
            if (!$result) {
                $errors = $this->empresaModel->errors();
                $errorMessage = 'Error al desactivar la empresa';
                if (!empty($errors)) {
                    $errorMessage .= ': ' . implode(', ', $errors);
                }
                log_message('error', 'EmpresasController::eliminar - Error en update: ' . $errorMessage);
                log_message('error', 'EmpresasController::eliminar - Datos enviados: ' . json_encode($data));
                log_message('error', 'EmpresasController::eliminar - ID: ' . $id);
                log_message('error', 'EmpresasController::eliminar - Errores del modelo: ' . json_encode($errors));
                return $this->errorResponse($errorMessage);
            }

            // Verificar que realmente se actualizó
            // PostgreSQL devuelve 'f' o 't' como strings para booleanos
            $empresaActualizada = $this->empresaModel->find($id);
            if ($empresaActualizada) {
                $estadoActual = $empresaActualizada['estado'];
                // Verificar si está activo (true, 't', 1, '1')
                $estaActivo = ($estadoActual === true || $estadoActual === 't' || $estadoActual === 1 || $estadoActual === '1');
                if ($estaActivo) {
                    log_message('error', 'EmpresasController::eliminar - La empresa no se desactivó correctamente. Estado actual: ' . var_export($estadoActual, true));
                    return $this->errorResponse('La empresa no se desactivó correctamente');
                }
            }

            // Registrar auditoría con manejo de errores
            try {
                $auditoriaModel = new \App\Models\AuditoriaModel();
                $auditoriaModel->registrar([
                    'usuario_id' => $this->session->get('user_id'),
                    'empresa_id' => $id,
                    'tabla_afectada' => 'empresas',
                    'accion' => 'DELETE',
                    'registro_id' => $id,
                    'datos_anteriores' => $empresa,
                    'ip_address' => $this->request->getIPAddress()
                ]);
            } catch (\Exception $e) {
                log_message('error', 'Error al registrar auditoría: ' . $e->getMessage());
            }

            return $this->successResponse('Empresa desactivada exitosamente');
        } catch (\Exception $e) {
            log_message('error', 'Error en eliminar empresa: ' . $e->getMessage());
            log_message('error', 'Error en eliminar empresa - Stack trace: ' . $e->getTraceAsString());
            return $this->errorResponse('Error al eliminar la empresa: ' . $e->getMessage());
        }
    }
}
