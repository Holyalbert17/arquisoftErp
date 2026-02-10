<?php

namespace App\Controllers\Compras;

use App\Controllers\BaseController;
use App\Models\ProveedorModel;

class ProveedoresController extends BaseController
{
    protected $proveedorModel;

    public function __construct()
    {
        $this->proveedorModel = new ProveedorModel();
    }
    
    protected function checkAuth()
    {
        if (!$this->isLoggedIn()) {
            return redirect()->to('/auth/login');
        }
        if (!$this->user) {
            $this->loadUserData();
        }
        $this->requirePermission('COMPRAS', 'ver');
    }

    public function index()
    {
        try {
            $this->checkAuth();
            $empresaId = $this->user['empresa_id'] ?? null;
            
            if (!$empresaId) {
                return redirect()->to('/auth/login');
            }
            
            $proveedores = [];
            try {
                $proveedores = $this->proveedorModel->where('empresa_id', $empresaId)
                                                   ->where('estado', true)
                                                   ->orderBy('razon_social', 'ASC')
                                                   ->findAll();
            } catch (\Exception $e) {
                log_message('error', 'Error al obtener proveedores: ' . $e->getMessage());
            }
            
            $data = [
                'title' => 'Gestión de Proveedores',
                'proveedores' => $proveedores
            ];

            return $this->render('compras/proveedores/index', $data);
        } catch (\Exception $e) {
            log_message('error', 'Error en index proveedores: ' . $e->getMessage());
            return redirect()->to('/dashboard')
                ->with('error', 'Error al cargar la lista de proveedores');
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
            
            $data = [
                'title' => 'Nuevo Proveedor',
                'proveedor' => null
            ];

            return $this->render('compras/proveedores/form', $data);
        } catch (\Exception $e) {
            log_message('error', 'Error en crear proveedor: ' . $e->getMessage());
            return redirect()->to('/compras/proveedores')
                ->with('error', 'Error al cargar el formulario');
        }
    }

    public function guardar()
    {
        try {
            $this->checkAuth();
            $empresaId = $this->user['empresa_id'] ?? null;
            
            if (!$empresaId) {
                return $this->errorResponse('Usuario no tiene empresa asignada', 403);
            }

        $rules = [
            'ruc' => 'required|max_length[20]',
            'razon_social' => 'required|min_length[3]|max_length[200]',
            'email' => 'permit_empty|valid_email|max_length[150]'
        ];

        if (!$this->validate($rules)) {
            return $this->errorResponse('Error de validación: ' . implode(', ', $this->validator->getErrors()));
        }

        $ruc = $this->request->getPost('ruc');
        
        // Verificar RUC único
        $existe = $this->proveedorModel->where('empresa_id', $empresaId)
                                      ->where('ruc', $ruc)
                                      ->countAllResults();
        
        if ($existe > 0) {
            return $this->errorResponse('Ya existe un proveedor con ese RUC');
        }

        $data = [
            'empresa_id' => $empresaId,
            'codigo' => $this->request->getPost('codigo') ?: $this->generarCodigoProveedor($empresaId),
            'ruc' => $ruc,
            'razon_social' => $this->request->getPost('razon_social'),
            'nombre_comercial' => $this->request->getPost('nombre_comercial'),
            'direccion' => $this->request->getPost('direccion'),
            'ciudad' => $this->request->getPost('ciudad'),
            'pais' => $this->request->getPost('pais') ?? 'Paraguay',
            'telefono' => $this->request->getPost('telefono'),
            'celular' => $this->request->getPost('celular'),
            'email' => $this->request->getPost('email'),
            'sitio_web' => $this->request->getPost('sitio_web'),
            'contacto_nombre' => $this->request->getPost('contacto_nombre'),
            'contacto_cargo' => $this->request->getPost('contacto_cargo'),
            'contacto_telefono' => $this->request->getPost('contacto_telefono'),
            'contacto_email' => $this->request->getPost('contacto_email'),
            'dias_pago' => (int)($this->request->getPost('dias_pago') ?? 0),
            'condiciones_pago' => $this->request->getPost('condiciones_pago'),
            'moneda_preferida' => $this->request->getPost('moneda_preferida') ?? 'PYG',
            'cuenta_bancaria' => $this->request->getPost('cuenta_bancaria'),
            'banco' => $this->request->getPost('banco'),
            'observaciones' => $this->request->getPost('observaciones'),
            'estado' => true,
            'usuario_creacion' => $this->user['id'] ?? null
        ];

        if ($this->proveedorModel->insert($data)) {
            return $this->successResponse('Proveedor creado exitosamente', [
                'redirect' => base_url('compras/proveedores')
            ]);
        }

            return $this->errorResponse('Error al crear el proveedor');
        } catch (\Exception $e) {
            log_message('error', 'Error en guardar proveedor: ' . $e->getMessage());
            return $this->errorResponse('Error al crear el proveedor: ' . $e->getMessage());
        }
    }

    public function editar($id)
    {
        try {
            $this->checkAuth();
            $empresaId = $this->user['empresa_id'] ?? null;
            
            if (!$empresaId) {
                return redirect()->to('/auth/login');
            }
            
            $proveedor = $this->proveedorModel->find($id);
            
            if (!$proveedor || $proveedor['empresa_id'] != $empresaId) {
                throw new \CodeIgniter\Exceptions\PageNotFoundException('Proveedor no encontrado');
            }
            
            $data = [
                'title' => 'Editar Proveedor',
                'proveedor' => $proveedor
            ];

            return $this->render('compras/proveedores/form', $data);
        } catch (\CodeIgniter\Exceptions\PageNotFoundException $e) {
            throw $e;
        } catch (\Exception $e) {
            log_message('error', 'Error en editar proveedor: ' . $e->getMessage());
            return redirect()->to('/compras/proveedores')
                ->with('error', 'Error al cargar el formulario de edición');
        }
    }

    public function actualizar($id)
    {
        try {
            $this->checkAuth();
            $empresaId = $this->user['empresa_id'] ?? null;
            
            if (!$empresaId) {
                return $this->errorResponse('Usuario no tiene empresa asignada', 403);
            }

            $proveedor = $this->proveedorModel->find($id);
            
            if (!$proveedor || $proveedor['empresa_id'] != $empresaId) {
                return $this->errorResponse('Proveedor no encontrado', 404);
            }

        $rules = [
            'ruc' => 'required|max_length[20]',
            'razon_social' => 'required|min_length[3]|max_length[200]',
            'email' => 'permit_empty|valid_email|max_length[150]'
        ];

        if (!$this->validate($rules)) {
            return $this->errorResponse('Error de validación: ' . implode(', ', $this->validator->getErrors()));
        }

        $ruc = $this->request->getPost('ruc');
        
        // Verificar RUC único (excepto el actual)
        if ($ruc != $proveedor['ruc']) {
            $existe = $this->proveedorModel->where('empresa_id', $empresaId)
                                          ->where('ruc', $ruc)
                                          ->where('id !=', $id)
                                          ->countAllResults();
            
            if ($existe > 0) {
                return $this->errorResponse('Ya existe un proveedor con ese RUC');
            }
        }

        $data = [
            'codigo' => $this->request->getPost('codigo') ?: $proveedor['codigo'],
            'ruc' => $ruc,
            'razon_social' => $this->request->getPost('razon_social'),
            'nombre_comercial' => $this->request->getPost('nombre_comercial'),
            'direccion' => $this->request->getPost('direccion'),
            'ciudad' => $this->request->getPost('ciudad'),
            'pais' => $this->request->getPost('pais') ?? 'Paraguay',
            'telefono' => $this->request->getPost('telefono'),
            'celular' => $this->request->getPost('celular'),
            'email' => $this->request->getPost('email'),
            'sitio_web' => $this->request->getPost('sitio_web'),
            'contacto_nombre' => $this->request->getPost('contacto_nombre'),
            'contacto_cargo' => $this->request->getPost('contacto_cargo'),
            'contacto_telefono' => $this->request->getPost('contacto_telefono'),
            'contacto_email' => $this->request->getPost('contacto_email'),
            'dias_pago' => (int)($this->request->getPost('dias_pago') ?? 0),
            'condiciones_pago' => $this->request->getPost('condiciones_pago'),
            'moneda_preferida' => $this->request->getPost('moneda_preferida') ?? 'PYG',
            'cuenta_bancaria' => $this->request->getPost('cuenta_bancaria'),
            'banco' => $this->request->getPost('banco'),
            'observaciones' => $this->request->getPost('observaciones')
        ];

        if ($this->proveedorModel->update($id, $data)) {
            return $this->successResponse('Proveedor actualizado exitosamente', [
                'redirect' => base_url('compras/proveedores')
            ]);
        }

            return $this->errorResponse('Error al actualizar el proveedor');
        } catch (\Exception $e) {
            log_message('error', 'Error en actualizar proveedor: ' . $e->getMessage());
            return $this->errorResponse('Error al actualizar el proveedor: ' . $e->getMessage());
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

            $proveedor = $this->proveedorModel->find($id);
            
            if (!$proveedor || $proveedor['empresa_id'] != $empresaId) {
                return $this->errorResponse('Proveedor no encontrado', 404);
            }

            // Solo desactivar
            if ($this->proveedorModel->update($id, ['estado' => false])) {
                return $this->successResponse('Proveedor eliminado exitosamente');
            }

            return $this->errorResponse('Error al eliminar el proveedor');
        } catch (\Exception $e) {
            log_message('error', 'Error en eliminar proveedor: ' . $e->getMessage());
            return $this->errorResponse('Error al eliminar el proveedor: ' . $e->getMessage());
        }
    }

    private function generarCodigoProveedor($empresaId)
    {
        $ultimo = $this->proveedorModel->where('empresa_id', $empresaId)
                                     ->orderBy('id', 'DESC')
                                     ->first();
        
        $numero = $ultimo ? ((int)str_replace('PROV-', '', $ultimo['codigo'] ?? '0') + 1) : 1;
        return 'PROV-' . str_pad($numero, 6, '0', STR_PAD_LEFT);
    }
}
