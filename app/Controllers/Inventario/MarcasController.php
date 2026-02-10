<?php

namespace App\Controllers\Inventario;

use App\Controllers\BaseController;
use App\Models\MarcaModel;

class MarcasController extends BaseController
{
    protected $marcaModel;

    public function __construct()
    {
        $this->marcaModel = new MarcaModel();
    }
    
    protected function checkAuth()
    {
        if (!$this->isLoggedIn()) {
            return redirect()->to('/auth/login');
        }
        if (!$this->user) {
            $this->loadUserData();
        }
        $this->requirePermission('INVENTARIO', 'ver');
    }

    public function index()
    {
        try {
            $this->checkAuth();
            $empresaId = $this->user['empresa_id'] ?? null;
            
            if (!$empresaId) {
                return redirect()->to('/auth/login');
            }
            
            $marcas = [];
            try {
                $marcas = $this->marcaModel->where('empresa_id', $empresaId)
                                           ->where('estado', true)
                                           ->orderBy('nombre', 'ASC')
                                           ->findAll();
            } catch (\Exception $e) {
                log_message('error', 'Error al obtener marcas: ' . $e->getMessage());
            }
            
            $data = [
                'title' => 'Gestión de Marcas',
                'marcas' => $marcas
            ];

            return $this->render('inventario/marcas/index', $data);
        } catch (\Exception $e) {
            log_message('error', 'Error en index marcas: ' . $e->getMessage());
            return redirect()->to('/dashboard')
                ->with('error', 'Error al cargar las marcas');
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
                'title' => 'Nueva Marca',
                'marca' => null
            ];

            return $this->render('inventario/marcas/form', $data);
        } catch (\Exception $e) {
            log_message('error', 'Error en crear marca: ' . $e->getMessage());
            return redirect()->to('/inventario/marcas')
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
            'nombre' => 'required|min_length[2]|max_length[100]'
        ];

        if (!$this->validate($rules)) {
            return $this->errorResponse('Error de validación: ' . implode(', ', $this->validator->getErrors()));
        }

        $nombre = $this->request->getPost('nombre');
        
        // Verificar nombre único
        $existe = $this->marcaModel->where('empresa_id', $empresaId)
                                   ->where('nombre', $nombre)
                                   ->countAllResults();
        
        if ($existe > 0) {
            return $this->errorResponse('Ya existe una marca con ese nombre');
        }

        $data = [
            'empresa_id' => $empresaId,
            'nombre' => $nombre,
            'descripcion' => $this->request->getPost('descripcion'),
            'pais_origen' => $this->request->getPost('pais_origen'),
            'sitio_web' => $this->request->getPost('sitio_web'),
            'estado' => true
        ];

        // Subir logo si existe
        try {
            $logo = $this->request->getFile('logo');
            if ($logo && $logo->isValid() && !$logo->hasMoved()) {
                $uploadPath = ROOTPATH . 'public/uploads/marcas';
                
                if (!is_dir($uploadPath)) {
                    mkdir($uploadPath, 0755, true);
                }
                
                $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                if (!in_array($logo->getMimeType(), $allowedTypes)) {
                    return $this->errorResponse('Tipo de archivo no permitido');
                }
                
                if ($logo->getSize() > 2097152) {
                    return $this->errorResponse('La imagen es demasiado grande. Tamaño máximo: 2MB');
                }
                
                $newName = $logo->getRandomName();
                if ($logo->move($uploadPath, $newName)) {
                    $data['logo'] = $newName;
                }
            }
        } catch (\Exception $e) {
            log_message('error', 'Error al subir logo de marca: ' . $e->getMessage());
        }

        if ($this->marcaModel->insert($data)) {
            return $this->successResponse('Marca creada exitosamente', [
                'redirect' => base_url('inventario/marcas')
            ]);
        }

            return $this->errorResponse('Error al crear la marca');
        } catch (\Exception $e) {
            log_message('error', 'Error en guardar marca: ' . $e->getMessage());
            return $this->errorResponse('Error al crear la marca: ' . $e->getMessage());
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
            
            $marca = $this->marcaModel->find($id);
            
            if (!$marca || $marca['empresa_id'] != $empresaId) {
                throw new \CodeIgniter\Exceptions\PageNotFoundException('Marca no encontrada');
            }
            
            $data = [
                'title' => 'Editar Marca',
                'marca' => $marca
            ];

            return $this->render('inventario/marcas/form', $data);
        } catch (\CodeIgniter\Exceptions\PageNotFoundException $e) {
            throw $e;
        } catch (\Exception $e) {
            log_message('error', 'Error en editar marca: ' . $e->getMessage());
            return redirect()->to('/inventario/marcas')
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

            $marca = $this->marcaModel->find($id);
            
            if (!$marca || $marca['empresa_id'] != $empresaId) {
                return $this->errorResponse('Marca no encontrada', 404);
            }

        $rules = [
            'nombre' => 'required|min_length[2]|max_length[100]'
        ];

        if (!$this->validate($rules)) {
            return $this->errorResponse('Error de validación: ' . implode(', ', $this->validator->getErrors()));
        }

        $nombre = $this->request->getPost('nombre');
        
        // Verificar nombre único (excepto la actual)
        if ($nombre != $marca['nombre']) {
            $existe = $this->marcaModel->where('empresa_id', $empresaId)
                                       ->where('nombre', $nombre)
                                       ->where('id !=', $id)
                                       ->countAllResults();
            
            if ($existe > 0) {
                return $this->errorResponse('Ya existe una marca con ese nombre');
            }
        }

        $data = [
            'nombre' => $nombre,
            'descripcion' => $this->request->getPost('descripcion'),
            'pais_origen' => $this->request->getPost('pais_origen'),
            'sitio_web' => $this->request->getPost('sitio_web')
        ];

        // Subir logo si existe
        try {
            $logo = $this->request->getFile('logo');
            if ($logo && $logo->isValid() && !$logo->hasMoved()) {
                $uploadPath = ROOTPATH . 'public/uploads/marcas';
                
                if (!is_dir($uploadPath)) {
                    mkdir($uploadPath, 0755, true);
                }
                
                $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                if (in_array($logo->getMimeType(), $allowedTypes) && $logo->getSize() <= 2097152) {
                    // Eliminar logo anterior
                    if ($marca['logo'] && file_exists($uploadPath . '/' . $marca['logo'])) {
                        @unlink($uploadPath . '/' . $marca['logo']);
                    }
                    
                    $newName = $logo->getRandomName();
                    if ($logo->move($uploadPath, $newName)) {
                        $data['logo'] = $newName;
                    }
                }
            }
        } catch (\Exception $e) {
            log_message('error', 'Error al subir logo de marca: ' . $e->getMessage());
        }

        if ($this->marcaModel->update($id, $data)) {
            return $this->successResponse('Marca actualizada exitosamente', [
                'redirect' => base_url('inventario/marcas')
            ]);
        }

            return $this->errorResponse('Error al actualizar la marca');
        } catch (\Exception $e) {
            log_message('error', 'Error en actualizar marca: ' . $e->getMessage());
            return $this->errorResponse('Error al actualizar la marca: ' . $e->getMessage());
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

            $marca = $this->marcaModel->find($id);
            
            if (!$marca || $marca['empresa_id'] != $empresaId) {
                return $this->errorResponse('Marca no encontrada', 404);
            }

            // Verificar si tiene productos asociados
            try {
                $productoModel = new \App\Models\ProductoModel();
                $tieneProductos = $productoModel->where('marca_id', $id)->countAllResults();
                
                if ($tieneProductos > 0) {
                    return $this->errorResponse('No se puede eliminar la marca porque tiene productos asociados');
                }
            } catch (\Exception $e) {
                log_message('error', 'Error al verificar productos asociados: ' . $e->getMessage());
            }

            // Solo desactivar
            if ($this->marcaModel->update($id, ['estado' => false])) {
                return $this->successResponse('Marca eliminada exitosamente');
            }

            return $this->errorResponse('Error al eliminar la marca');
        } catch (\Exception $e) {
            log_message('error', 'Error en eliminar marca: ' . $e->getMessage());
            return $this->errorResponse('Error al eliminar la marca: ' . $e->getMessage());
        }
    }

    public function obtener($id)
    {
        $this->checkAuth();
        $empresaId = $this->user['empresa_id'] ?? null;
        
        if (!$empresaId) {
            return $this->errorResponse('Usuario no tiene empresa asignada', 403);
        }

        $marca = $this->marcaModel->find($id);
        
        if (!$marca || $marca['empresa_id'] != $empresaId) {
            return $this->errorResponse('Marca no encontrada', 404);
        }

        return $this->jsonResponse($marca);
    }
}
