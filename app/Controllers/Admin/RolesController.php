<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\RolModel;
use App\Models\PermisoModel;

class RolesController extends BaseController
{
    protected $rolModel;
    protected $permisoModel;

    public function __construct()
    {
        $this->rolModel = new RolModel();
        $this->permisoModel = new PermisoModel();
    }

    protected function checkAuth()
    {
        if (!$this->isLoggedIn()) {
            return redirect()->to('/auth/login');
        }
        $this->loadUserData();
        // Administrador empresa (Dueño) y Gerente: nivel <= 3
        $rolModel = new \App\Models\RolModel();
        $rol = isset($this->user['rol_id']) ? $rolModel->find($this->user['rol_id']) : null;
        if (!$rol || (int) ($rol['nivel'] ?? 99) > 3) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('No tiene permisos para acceder');
        }
    }

    public function index()
    {
        $this->checkAuth();

        $roles = $this->rolModel->orderBy('nivel', 'ASC')->findAll();

        // Obtener permisos para cada rol
        foreach ($roles as &$rol) {
            $rol['permisos'] = $this->permisoModel->getPorRol($rol['id']);
        }

        $data = [
            'title' => 'Gestión de Roles y Permisos',
            'roles' => $roles
        ];

        return $this->render('admin/roles/index', $data);
    }

    public function crear()
    {
        $this->checkAuth();

        $modulos = [
            'DASHBOARD',
            'CLIENTES',
            'PROVEEDORES',
            'PRODUCTOS',
            'INVENTARIO',
            'VENTAS',
            'COMPRAS',
            'SERVICIOS',
            'FINANZAS',
            'REPORTES',
            'USUARIOS',
            'EMPRESAS',
            'SUCURSALES',
            'ROLES',
            'MODULOS',
            'CONFIGURACION',
            'NOTIFICACIONES'
        ];

        $data = [
            'title' => 'Nuevo Rol',
            'rol' => [
                'id' => null,
                'nombre' => '',
                'descripcion' => '',
                'nivel' => 4,
                'estado' => true,
                'es_rol_sistema' => false,
                'permisos' => []
            ],
            'modulos' => $modulos
        ];

        return $this->render('admin/roles/form', $data);
    }

    public function guardar()
    {
        $this->checkAuth();

        $rules = [
            'nombre' => 'required|min_length[2]|max_length[100]',
            'descripcion' => 'permit_empty|max_length[500]',
            'nivel' => 'required|integer|greater_than[0]|less_than[10]',
            'estado' => 'permit_empty'
        ];

        if (!$this->validate($rules)) {
            return $this->errorResponse('Error de validación: ' . implode(', ', $this->validator->getErrors()));
        }

        try {
            $data = [
                'nombre' => $this->request->getPost('nombre'),
                'descripcion' => $this->request->getPost('descripcion') ?: null,
                'nivel' => (int) $this->request->getPost('nivel'),
                'es_rol_sistema' => false,
                'estado' => $this->request->getPost('estado') ? true : false
            ];

            $this->rolModel->insert($data);
            $nuevoId = (int) $this->rolModel->getInsertID();

            $permisos = $this->request->getPost('permisos');
            if (is_array($permisos) && !empty($permisos)) {
                foreach ($permisos as $modulo => $acciones) {
                    if (is_array($acciones)) {
                        $this->permisoModel->insert([
                            'rol_id' => $nuevoId,
                            'modulo' => $modulo,
                            'ver' => isset($acciones['ver']) ? 1 : 0,
                            'crear' => isset($acciones['crear']) ? 1 : 0,
                            'editar' => isset($acciones['editar']) ? 1 : 0,
                            'eliminar' => isset($acciones['eliminar']) ? 1 : 0,
                            'anular' => isset($acciones['anular']) ? 1 : 0
                        ]);
                    }
                }
            }

            return $this->successResponse('Rol creado exitosamente', [
                'redirect' => base_url('admin/roles/editar/' . $nuevoId)
            ]);
        } catch (\Exception $e) {
            log_message('error', 'Error en guardar rol: ' . $e->getMessage());
            return $this->errorResponse('Error al crear el rol: ' . $e->getMessage());
        }
    }

    public function editar($id)
    {
        $this->checkAuth();

        $rol = $this->rolModel->getConPermisos($id);

        if (!$rol) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Rol no encontrado');
        }

        // Obtener todos los módulos disponibles
        $modulos = [
            'DASHBOARD',
            'CLIENTES',
            'PROVEEDORES',
            'PRODUCTOS',
            'INVENTARIO',
            'VENTAS',
            'COMPRAS',
            'SERVICIOS',
            'FINANZAS',
            'REPORTES',
            'USUARIOS',
            'EMPRESAS',
            'SUCURSALES',
            'ROLES',
            'MODULOS',
            'CONFIGURACION',
            'NOTIFICACIONES'
        ];

        $data = [
            'title' => 'Editar Rol y Permisos',
            'rol' => $rol,
            'modulos' => $modulos
        ];

        return $this->render('admin/roles/form', $data);
    }

    public function actualizar($id)
    {
        $this->checkAuth();

        $rol = $this->rolModel->find($id);

        if (!$rol) {
            if ($this->request->isAJAX()) {
                return $this->errorResponse('Rol no encontrado', 404);
            }
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Rol no encontrado');
        }

        $permisos = $this->request->getPost('permisos');
        $nombre = $this->request->getPost('nombre');

        // Si viene 'permisos' pero NO 'nombre', es el form de permisos solamente
        if (is_array($permisos) && empty($nombre)) {
            return $this->guardarPermisos($id, $permisos);
        }

        // Validar datos del rol
        $rules = [
            'nombre' => 'required|min_length[2]|max_length[100]',
            'descripcion' => 'permit_empty|max_length[500]',
            'nivel' => 'required|integer|greater_than[0]|less_than[10]',
            'estado' => 'permit_empty'
        ];

        if (!$this->validate($rules)) {
            $error = 'Error de validación: ' . implode(', ', $this->validator->getErrors());
            if ($this->request->isAJAX()) {
                return $this->errorResponse($error);
            }
            return redirect()->back()->withInput()->with('error', $error);
        }

        try {
            $data = [
                'nombre' => $this->request->getPost('nombre'),
                'descripcion' => $this->request->getPost('descripcion') ?: null,
                'nivel' => (int) $this->request->getPost('nivel'),
                'estado' => $this->request->getPost('estado') ? true : false
            ];

            if ($this->rolModel->update($id, $data)) {
                if ($this->request->isAJAX()) {
                    return $this->successResponse('Rol actualizado exitosamente');
                }
                return redirect()->to('admin/roles')->with('success', 'Rol actualizado exitosamente');
            } else {
                $error = 'Error al actualizar el rol';
                if ($this->request->isAJAX()) {
                    return $this->errorResponse($error);
                }
                return redirect()->back()->with('error', $error);
            }
        } catch (\Exception $e) {
            log_message('error', 'Error en actualizar rol: ' . $e->getMessage());
            $error = 'Error al actualizar el rol: ' . $e->getMessage();
            if ($this->request->isAJAX()) {
                return $this->errorResponse($error);
            }
            return redirect()->back()->with('error', $error);
        }
    }

    /**
     * Guardar permisos de un rol (separado del update de datos del rol)
     */
    private function guardarPermisos($rolId, $permisos)
    {
        try {
            // Eliminar permisos existentes
            $this->permisoModel->where('rol_id', $rolId)->delete();

            // Insertar nuevos permisos
            foreach ($permisos as $modulo => $acciones) {
                if (is_array($acciones)) {
                    $this->permisoModel->insert([
                        'rol_id' => $rolId,
                        'modulo' => $modulo,
                        'ver' => isset($acciones['ver']) ? true : false,
                        'crear' => isset($acciones['crear']) ? true : false,
                        'editar' => isset($acciones['editar']) ? true : false,
                        'eliminar' => isset($acciones['eliminar']) ? true : false,
                        'anular' => isset($acciones['anular']) ? true : false
                    ]);
                }
            }

            return $this->successResponse('Permisos actualizados exitosamente');
        } catch (\Exception $e) {
            log_message('error', 'Error en guardar permisos: ' . $e->getMessage());
            return $this->errorResponse('Error al guardar permisos: ' . $e->getMessage());
        }
    }
}
