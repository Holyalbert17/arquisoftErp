<?php

namespace App\Controllers\Inventario;

use App\Controllers\BaseController;
use App\Models\CategoriaModel;

class CategoriasController extends BaseController
{
    protected $categoriaModel;

    public function __construct()
    {
        $this->categoriaModel = new CategoriaModel();
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
            
            $categorias = [];
            try {
                $categorias = $this->categoriaModel->where('empresa_id', $empresaId)
                                                  ->where('estado', true)
                                                  ->orderBy('nombre', 'ASC')
                                                  ->findAll();
            } catch (\Exception $e) {
                log_message('error', 'Error al obtener categorías: ' . $e->getMessage());
            }
            
            $data = [
                'title' => 'Gestión de Categorías',
                'categorias' => $categorias
            ];

            return $this->render('inventario/categorias/index', $data);
        } catch (\Exception $e) {
            log_message('error', 'Error en index categorías: ' . $e->getMessage());
            return redirect()->to('/dashboard')
                ->with('error', 'Error al cargar las categorías');
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
            
            // Obtener categorías padre para el select con manejo de errores
            $categoriasPadre = [];
            try {
                $categoriasPadre = $this->categoriaModel->where('empresa_id', $empresaId)
                                                         ->where('estado', true)
                                                         ->where('categoria_padre_id IS NULL')
                                                         ->findAll();
            } catch (\Exception $e) {
                log_message('error', 'Error al obtener categorías padre: ' . $e->getMessage());
            }
            
            $data = [
                'title' => 'Nueva Categoría',
                'categoria' => null,
                'categorias_padre' => $categoriasPadre
            ];

            return $this->render('inventario/categorias/form', $data);
        } catch (\Exception $e) {
            log_message('error', 'Error en crear categoría: ' . $e->getMessage());
            return redirect()->to('/inventario/categorias')
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
        $existe = $this->categoriaModel->where('empresa_id', $empresaId)
                                       ->where('nombre', $nombre)
                                       ->countAllResults();
        
        if ($existe > 0) {
            return $this->errorResponse('Ya existe una categoría con ese nombre');
        }

        $data = [
            'empresa_id' => $empresaId,
            'nombre' => $nombre,
            'descripcion' => $this->request->getPost('descripcion'),
            'categoria_padre_id' => $this->request->getPost('categoria_padre_id') ?: null,
            'orden' => $this->request->getPost('orden') ?? 0,
            'icono' => $this->request->getPost('icono'),
            'color' => $this->request->getPost('color'),
            'estado' => true,
            'usuario_creacion' => $this->user['id'] ?? null
        ];

        // Subir imagen si existe
        try {
            $imagen = $this->request->getFile('imagen');
            if ($imagen && $imagen->isValid() && !$imagen->hasMoved()) {
                $uploadPath = ROOTPATH . 'public/uploads/categorias';
                
                if (!is_dir($uploadPath)) {
                    mkdir($uploadPath, 0755, true);
                }
                
                $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                if (!in_array($imagen->getMimeType(), $allowedTypes)) {
                    return $this->errorResponse('Tipo de archivo no permitido');
                }
                
                if ($imagen->getSize() > 2097152) {
                    return $this->errorResponse('La imagen es demasiado grande. Tamaño máximo: 2MB');
                }
                
                $newName = $imagen->getRandomName();
                if ($imagen->move($uploadPath, $newName)) {
                    $data['imagen'] = $newName;
                }
            }
        } catch (\Exception $e) {
            log_message('error', 'Error al subir imagen de categoría: ' . $e->getMessage());
        }

        if ($this->categoriaModel->insert($data)) {
            return $this->successResponse('Categoría creada exitosamente', [
                'redirect' => base_url('inventario/categorias')
            ]);
        }

            return $this->errorResponse('Error al crear la categoría');
        } catch (\Exception $e) {
            log_message('error', 'Error en guardar categoría: ' . $e->getMessage());
            return $this->errorResponse('Error al crear la categoría: ' . $e->getMessage());
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
            
            $categoria = $this->categoriaModel->find($id);
            
            if (!$categoria || $categoria['empresa_id'] != $empresaId) {
                throw new \CodeIgniter\Exceptions\PageNotFoundException('Categoría no encontrada');
            }
            
            // Obtener categorías padre para el select (excluyendo la actual y sus hijas) con manejo de errores
            $categoriasPadre = [];
            try {
                $categoriasPadre = $this->categoriaModel->where('empresa_id', $empresaId)
                                                         ->where('estado', true)
                                                         ->where('id !=', $id)
                                                         ->where('categoria_padre_id IS NULL')
                                                         ->findAll();
            } catch (\Exception $e) {
                log_message('error', 'Error al obtener categorías padre: ' . $e->getMessage());
            }
            
            $data = [
                'title' => 'Editar Categoría',
                'categoria' => $categoria,
                'categorias_padre' => $categoriasPadre
            ];

            return $this->render('inventario/categorias/form', $data);
        } catch (\CodeIgniter\Exceptions\PageNotFoundException $e) {
            throw $e;
        } catch (\Exception $e) {
            log_message('error', 'Error en editar categoría: ' . $e->getMessage());
            return redirect()->to('/inventario/categorias')
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

            $categoria = $this->categoriaModel->find($id);
            
            if (!$categoria || $categoria['empresa_id'] != $empresaId) {
                return $this->errorResponse('Categoría no encontrada', 404);
            }

        $rules = [
            'nombre' => 'required|min_length[2]|max_length[100]'
        ];

        if (!$this->validate($rules)) {
            return $this->errorResponse('Error de validación: ' . implode(', ', $this->validator->getErrors()));
        }

        $nombre = $this->request->getPost('nombre');
        
        // Verificar nombre único (excepto la actual)
        if ($nombre != $categoria['nombre']) {
            $existe = $this->categoriaModel->where('empresa_id', $empresaId)
                                           ->where('nombre', $nombre)
                                           ->where('id !=', $id)
                                           ->countAllResults();
            
            if ($existe > 0) {
                return $this->errorResponse('Ya existe una categoría con ese nombre');
            }
        }

        $data = [
            'nombre' => $nombre,
            'descripcion' => $this->request->getPost('descripcion'),
            'categoria_padre_id' => $this->request->getPost('categoria_padre_id') ?: null,
            'orden' => $this->request->getPost('orden') ?? 0,
            'icono' => $this->request->getPost('icono'),
            'color' => $this->request->getPost('color')
        ];

        // Subir imagen si existe
        try {
            $imagen = $this->request->getFile('imagen');
            if ($imagen && $imagen->isValid() && !$imagen->hasMoved()) {
                $uploadPath = ROOTPATH . 'public/uploads/categorias';
                
                if (!is_dir($uploadPath)) {
                    mkdir($uploadPath, 0755, true);
                }
                
                $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                if (in_array($imagen->getMimeType(), $allowedTypes) && $imagen->getSize() <= 2097152) {
                    // Eliminar imagen anterior
                    if ($categoria['imagen'] && file_exists($uploadPath . '/' . $categoria['imagen'])) {
                        @unlink($uploadPath . '/' . $categoria['imagen']);
                    }
                    
                    $newName = $imagen->getRandomName();
                    if ($imagen->move($uploadPath, $newName)) {
                        $data['imagen'] = $newName;
                    }
                }
            }
        } catch (\Exception $e) {
            log_message('error', 'Error al subir imagen de categoría: ' . $e->getMessage());
        }

        if ($this->categoriaModel->update($id, $data)) {
            return $this->successResponse('Categoría actualizada exitosamente', [
                'redirect' => base_url('inventario/categorias')
            ]);
        }

            return $this->errorResponse('Error al actualizar la categoría');
        } catch (\Exception $e) {
            log_message('error', 'Error en actualizar categoría: ' . $e->getMessage());
            return $this->errorResponse('Error al actualizar la categoría: ' . $e->getMessage());
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

            $categoria = $this->categoriaModel->find($id);
            
            if (!$categoria || $categoria['empresa_id'] != $empresaId) {
                return $this->errorResponse('Categoría no encontrada', 404);
            }

            // Verificar si tiene productos asociados
            try {
                $productoModel = new \App\Models\ProductoModel();
                $tieneProductos = $productoModel->where('categoria_id', $id)->countAllResults();
                
                if ($tieneProductos > 0) {
                    return $this->errorResponse('No se puede eliminar la categoría porque tiene productos asociados');
                }
            } catch (\Exception $e) {
                log_message('error', 'Error al verificar productos asociados: ' . $e->getMessage());
            }

            // Verificar si tiene subcategorías
            try {
                $tieneHijas = $this->categoriaModel->where('categoria_padre_id', $id)->countAllResults();
                
                if ($tieneHijas > 0) {
                    return $this->errorResponse('No se puede eliminar la categoría porque tiene subcategorías');
                }
            } catch (\Exception $e) {
                log_message('error', 'Error al verificar subcategorías: ' . $e->getMessage());
            }

            // Solo desactivar
            if ($this->categoriaModel->update($id, ['estado' => false])) {
                return $this->successResponse('Categoría eliminada exitosamente');
            }

            return $this->errorResponse('Error al eliminar la categoría');
        } catch (\Exception $e) {
            log_message('error', 'Error en eliminar categoría: ' . $e->getMessage());
            return $this->errorResponse('Error al eliminar la categoría: ' . $e->getMessage());
        }
    }

    public function obtener($id)
    {
        $this->checkAuth();
        $empresaId = $this->user['empresa_id'] ?? null;
        
        if (!$empresaId) {
            return $this->errorResponse('Usuario no tiene empresa asignada', 403);
        }

        $categoria = $this->categoriaModel->find($id);
        
        if (!$categoria || $categoria['empresa_id'] != $empresaId) {
            return $this->errorResponse('Categoría no encontrada', 404);
        }

        return $this->jsonResponse($categoria);
    }
}
