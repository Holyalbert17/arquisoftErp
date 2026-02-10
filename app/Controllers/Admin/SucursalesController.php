<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\SucursalModel;
use App\Models\EmpresaModel;

class SucursalesController extends BaseController
{
    protected $sucursalModel;
    protected $empresaModel;

    public function __construct()
    {
        // No verificar permisos en constructor para peticiones AJAX
        // La verificación se hará en cada método individualmente
        $this->sucursalModel = new SucursalModel();
        $this->empresaModel = new EmpresaModel();
    }

    public function index()
    {
        $this->requireLogin();
        $this->requirePermission('CONFIGURACION', 'ver');
        $this->checkAuth();
        $empresaId = $this->user['rol_id'] == \App\Models\RolModel::SUPER_ADMIN ? null : $this->user['empresa_id'];

        $builder = $this->sucursalModel;
        if ($empresaId) {
            $builder->where('empresa_id', $empresaId);
        }

        $data = [
            'title' => 'Gestión de Sucursales',
            'sucursales' => $builder->findAll(),
            'empresas' => $this->empresaModel->getActivas()
        ];

        return $this->render('admin/sucursales/index', $data);
    }

    public function crear()
    {
        $this->requireLogin();
        $this->requirePermission('CONFIGURACION', 'crear');
        $this->checkAuth();
        $empresaId = $this->user['rol_id'] == 1 ? null : $this->user['empresa_id'];

        $data = [
            'title' => 'Nueva Sucursal',
            'sucursal' => null,
            'empresas' => $this->empresaModel->getActivas(),
            'empresa_id' => $empresaId
        ];

        return $this->render('admin/sucursales/form', $data);
    }

    public function guardar()
    {
        $this->requireLogin();
        $this->requirePermission('CONFIGURACION', 'crear');
        $this->checkAuth();

        $rules = [
            'empresa_id' => 'required|is_natural_no_zero',
            'nombre' => 'required|min_length[3]|max_length[200]',
            'departamento' => 'in_list[CAPITAL,CONCEPCIÓN,SAN PEDRO,CORDILLERA,GUAIRÁ,CAAAGUAZÚ,CAAZAPÁ,ITAPÚA,MISIONES,PARAGUARÍ,ALTO PARANÁ,CENTRAL,ÑEEMBUCÚ,AMAMBAY,CANINDEYÚ,PRESIDENTE HAYES,BOQUERÓN,ALTO PARAGUAY]'
        ];

        if (!$this->validate($rules)) {
            return $this->errorResponse('Error de validación: ' . implode(', ', $this->validator->getErrors()));
        }

        $empresaId = $this->request->getPost('empresa_id');

        // Enforce multiempresa: admin/gerente solo pueden operar dentro de su empresa
        if (($this->user['rol_id'] ?? null) != \App\Models\RolModel::SUPER_ADMIN) {
            $empresaIdUser = $this->user['empresa_id'] ?? null;
            if (!$empresaIdUser) {
                return $this->errorResponse('Usuario no tiene empresa asignada', 403);
            }
            if ((int) $empresaId !== (int) $empresaIdUser) {
                return $this->errorResponse('No tiene permisos para operar sobre otra empresa', 403);
            }
            $empresaId = $empresaIdUser;
        }
        $esMatriz = $this->request->getPost('es_matriz') ? true : false;

        // Validar que solo haya una matriz
        if ($esMatriz && !$this->sucursalModel->validarUnicaMatriz($empresaId)) {
            return $this->errorResponse('Ya existe una sucursal matriz para esta empresa');
        }

        $data = [
            'empresa_id' => $empresaId,
            'nombre' => $this->request->getPost('nombre'),
            'codigo' => $this->request->getPost('codigo'),
            'direccion' => $this->request->getPost('direccion'),
            'ciudad' => $this->request->getPost('ciudad'),
            'departamento' => $this->request->getPost('departamento'),
            'telefono' => $this->request->getPost('telefono'),
            'email' => $this->request->getPost('email'),
            'latitud' => $this->request->getPost('latitud'),
            'longitud' => $this->request->getPost('longitud'),
            'horario_apertura' => $this->request->getPost('horario_apertura'),
            'horario_cierre' => $this->request->getPost('horario_cierre'),
            'es_matriz' => $esMatriz,
            'codigo_establecimiento' => $this->request->getPost('codigo_establecimiento'),
            'responsable' => $this->request->getPost('responsable'),
            'estado' => true
        ];

        if ($this->sucursalModel->insert($data)) {
            $auditoriaModel = new \App\Models\AuditoriaModel();
            $auditoriaModel->registrar([
                'usuario_id' => $this->session->get('user_id'),
                'empresa_id' => $empresaId,
                'tabla_afectada' => 'sucursales',
                'accion' => 'INSERT',
                'datos_nuevos' => $data,
                'ip_address' => $this->request->getIPAddress()
            ]);

            return $this->successResponse('Sucursal creada exitosamente', [
                'redirect' => base_url('admin/sucursales')
            ]);
        }

        return $this->errorResponse('Error al crear la sucursal');
    }

    public function editar($id)
    {
        $this->requireLogin();
        $this->requirePermission('CONFIGURACION', 'editar');
        $this->checkAuth();

        $sucursal = $this->sucursalModel->find($id);

        if (!$sucursal) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Sucursal no encontrada');
        }

        $empresaId = $this->user['rol_id'] == 1 ? null : $this->user['empresa_id'];

        $data = [
            'title' => 'Editar Sucursal',
            'sucursal' => $sucursal,
            'empresas' => $this->empresaModel->getActivas(),
            'empresa_id' => $empresaId
        ];

        return $this->render('admin/sucursales/form', $data);
    }

    public function actualizar($id)
    {
        $this->requireLogin();
        $this->requirePermission('CONFIGURACION', 'editar');
        $this->checkAuth();

        $sucursal = $this->sucursalModel->find($id);

        if (!$sucursal) {
            return $this->errorResponse('Sucursal no encontrada', 404);
        }

        $rules = [
            'empresa_id' => 'required|is_natural_no_zero',
            'nombre' => 'required|min_length[3]|max_length[200]',
            'departamento' => 'in_list[CAPITAL,CONCEPCIÓN,SAN PEDRO,CORDILLERA,GUAIRÁ,CAAAGUAZÚ,CAAZAPÁ,ITAPÚA,MISIONES,PARAGUARÍ,ALTO PARANÁ,CENTRAL,ÑEEMBUCÚ,AMAMBAY,CANINDEYÚ,PRESIDENTE HAYES,BOQUERÓN,ALTO PARAGUAY]'
        ];

        if (!$this->validate($rules)) {
            return $this->errorResponse('Error de validación: ' . implode(', ', $this->validator->getErrors()));
        }

        $empresaId = $this->request->getPost('empresa_id');

        // Enforce multiempresa: admin/gerente solo pueden operar dentro de su empresa
        if (($this->user['rol_id'] ?? null) != \App\Models\RolModel::SUPER_ADMIN) {
            $empresaIdUser = $this->user['empresa_id'] ?? null;
            if (!$empresaIdUser) {
                return $this->errorResponse('Usuario no tiene empresa asignada', 403);
            }
            if ((int) $empresaId !== (int) $empresaIdUser) {
                return $this->errorResponse('No tiene permisos para operar sobre otra empresa', 403);
            }
            $empresaId = $empresaIdUser;
        }
        $esMatriz = $this->request->getPost('es_matriz') ? true : false;

        // Validar que solo haya una matriz
        if ($esMatriz && !$this->sucursalModel->validarUnicaMatriz($empresaId, $id)) {
            return $this->errorResponse('Ya existe una sucursal matriz para esta empresa');
        }

        $data = [
            'empresa_id' => $empresaId,
            'nombre' => $this->request->getPost('nombre'),
            'codigo' => $this->request->getPost('codigo'),
            'direccion' => $this->request->getPost('direccion'),
            'ciudad' => $this->request->getPost('ciudad'),
            'departamento' => $this->request->getPost('departamento'),
            'telefono' => $this->request->getPost('telefono'),
            'email' => $this->request->getPost('email'),
            'latitud' => $this->request->getPost('latitud'),
            'longitud' => $this->request->getPost('longitud'),
            'horario_apertura' => $this->request->getPost('horario_apertura'),
            'horario_cierre' => $this->request->getPost('horario_cierre'),
            'es_matriz' => $esMatriz,
            'codigo_establecimiento' => $this->request->getPost('codigo_establecimiento'),
            'responsable' => $this->request->getPost('responsable')
        ];

        if ($this->sucursalModel->update($id, $data)) {
            $auditoriaModel = new \App\Models\AuditoriaModel();
            $auditoriaModel->registrar([
                'usuario_id' => $this->session->get('user_id'),
                'empresa_id' => $empresaId,
                'tabla_afectada' => 'sucursales',
                'accion' => 'UPDATE',
                'registro_id' => $id,
                'datos_anteriores' => $sucursal,
                'datos_nuevos' => $data,
                'ip_address' => $this->request->getIPAddress()
            ]);

            return $this->successResponse('Sucursal actualizada exitosamente', [
                'redirect' => base_url('admin/sucursales')
            ]);
        }

        return $this->errorResponse('Error al actualizar la sucursal');
    }

    public function ver($id)
    {
        $this->requireLogin();
        $this->requirePermission('CONFIGURACION', 'ver');
        $this->checkAuth();
        $sucursal = $this->sucursalModel->find($id);

        if (!$sucursal) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Sucursal no encontrada');
        }

        // Obtener información adicional
        $empresa = $this->empresaModel->find($sucursal['empresa_id']);

        // Obtener estadísticas de la sucursal
        $db = \Config\Database::connect();
        $ventasHoy = $db->table('ventas')
            ->where('sucursal_id', $id)
            ->where('DATE(fecha_venta)', date('Y-m-d'))
            ->countAllResults();

        $data = [
            'title' => 'Ver Sucursal',
            'sucursal' => $sucursal,
            'empresa' => $empresa,
            'ventas_hoy' => $ventasHoy
        ];

        return $this->render('admin/sucursales/ver', $data);
    }

    public function eliminar($id)
    {
        $this->requireLogin();
        $this->requirePermission('CONFIGURACION', 'eliminar');
        $this->checkAuth();
        $sucursal = $this->sucursalModel->find($id);

        if (!$sucursal) {
            return $this->errorResponse('Sucursal no encontrada', 404);
        }

        // Verificar si tiene ventas asociadas
        $ventaModel = new \App\Models\VentaModel();
        $tieneVentas = $ventaModel->where('sucursal_id', $id)->countAllResults();

        if ($tieneVentas > 0) {
            return $this->errorResponse('No se puede eliminar la sucursal porque tiene ventas asociadas');
        }

        // Verificar si tiene usuarios asignados
        $usuarioModel = new \App\Models\UsuarioModel();
        $tieneUsuarios = $usuarioModel->where('sucursal_id', $id)->countAllResults();

        if ($tieneUsuarios > 0) {
            return $this->errorResponse('No se puede eliminar la sucursal porque tiene usuarios asignados');
        }

        // Solo desactivar
        if ($this->sucursalModel->update($id, ['estado' => false])) {
            $auditoriaModel = new \App\Models\AuditoriaModel();
            $auditoriaModel->registrar([
                'usuario_id' => $this->session->get('user_id'),
                'empresa_id' => $sucursal['empresa_id'],
                'tabla_afectada' => 'sucursales',
                'accion' => 'DELETE',
                'registro_id' => $id,
                'datos_anteriores' => $sucursal,
                'ip_address' => $this->request->getIPAddress()
            ]);

            return $this->successResponse('Sucursal eliminada exitosamente');
        }

        return $this->errorResponse('Error al eliminar la sucursal');
    }

    /**
     * Obtener sucursales por empresa (para AJAX)
     */
    public function getPorEmpresa()
    {
        // Verificar autenticación sin redirigir (para AJAX)
        if (!$this->isLoggedIn()) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'No autenticado'
            ])->setStatusCode(401);
        }

        $empresaId = $this->request->getPost('empresa_id');

        if (empty($empresaId)) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'ID de empresa requerido'
            ])->setStatusCode(400);
        }

        // Validar que sea numérico
        if (!is_numeric($empresaId)) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'ID de empresa inválido'
            ])->setStatusCode(400);
        }

        $empresaId = (int) $empresaId;

        // Obtener sucursales activas de la empresa
        $sucursales = $this->sucursalModel->getPorEmpresa($empresaId, true);

        return $this->response->setJSON([
            'success' => true,
            'data' => $sucursales
        ]);
    }
}
