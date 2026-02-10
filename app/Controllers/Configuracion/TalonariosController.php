<?php

namespace App\Controllers\Configuracion;

use App\Controllers\BaseController;
use App\Models\TalonarioModel;
use App\Models\SucursalModel;

class TalonariosController extends BaseController
{
    protected $talonarioModel;
    protected $sucursalModel;

    public function __construct()
    {
        $this->talonarioModel = new TalonarioModel();
        $this->sucursalModel = new SucursalModel();
    }

    protected function checkAuth()
    {
        if (!$this->isLoggedIn()) {
            return redirect()->to('/auth/login');
        }
        $this->loadUserData();
        $rolModel = new \App\Models\RolModel();
        $rol = isset($this->user['rol_id']) ? $rolModel->find($this->user['rol_id']) : null;
        if (!$rol || (int) ($rol['nivel'] ?? 99) > 3) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('No tiene permisos');
        }
    }

    public function index()
    {
        $this->checkAuth();
        $empresaId = $this->user['empresa_id'] ?? null;

        if (!$empresaId) {
            return redirect()->to('/auth/login');
        }

        $sucursalId = $this->request->getGet('sucursal_id');
        $builder = $this->talonarioModel->where('empresa_id', $empresaId);
        if (!empty($sucursalId)) {
            $builder->where('sucursal_id', (int) $sucursalId);
        }

        $talonarios = $builder->orderBy('fecha_inicio_vigencia', 'DESC')->findAll();
        $sucursales = $this->sucursalModel->getPorEmpresa($empresaId, true);
        $sucursalesMap = [];
        foreach ($sucursales as $s) {
            $sucursalesMap[(int) $s['id']] = $s['nombre'];
        }

        $data = [
            'title' => 'Gestión de Talonarios SET',
            'talonarios' => $talonarios,
            'sucursales' => $sucursales,
            'sucursales_map' => $sucursalesMap,
            'sucursal_id' => $sucursalId
        ];

        return $this->render('configuracion/talonarios/index', $data);
    }

    public function crear()
    {
        $this->checkAuth();
        $empresaId = $this->user['empresa_id'] ?? null;

        if (!$empresaId) {
            return redirect()->to('/auth/login');
        }

        $sucursalId = $this->sucursal['id'] ?? ($this->session->get('sucursal_id') ?? null);
        $sucursales = $this->sucursalModel->getPorEmpresa($empresaId, true);

        $data = [
            'title' => 'Nuevo Talonario',
            'talonario' => null,
            'sucursal_id' => $sucursalId,
            'sucursales' => $sucursales
        ];

        return $this->render('configuracion/talonarios/form', $data);
    }

    public function guardar()
    {
        $this->checkAuth();
        $empresaId = $this->user['empresa_id'] ?? null;

        if (!$empresaId) {
            return $this->errorResponse('Usuario no tiene empresa asignada', 403);
        }

        $rules = [
            'sucursal_id' => 'required|integer|greater_than[0]',
            'tipo_comprobante' => 'required|in_list[FACTURA,FACTURA_ELECTRONICA,TICKET,NOTA_CREDITO,NOTA_DEBITO]',
            'timbrado' => 'required|min_length[5]|max_length[20]',
            'establecimiento' => 'required|exact_length[3]',
            'punto_expedicion' => 'required|exact_length[3]',
            'numero_desde' => 'required|integer|greater_than[0]',
            'numero_hasta' => 'required|integer',
            'fecha_inicio_vigencia' => 'required|valid_date',
            'fecha_fin_vigencia' => 'required|valid_date'
        ];

        if (!$this->validate($rules)) {
            return $this->errorResponse('Error de validación: ' . implode(', ', $this->validator->getErrors()));
        }

        $sucursalId = (int) $this->request->getPost('sucursal_id');
        $sucursal = $this->sucursalModel->find($sucursalId);
        if (!$sucursal || (int) ($sucursal['empresa_id'] ?? 0) !== (int) $empresaId) {
            return $this->errorResponse('Sucursal inválida', 400);
        }

        try {
            $numeroDesde = (int) $this->request->getPost('numero_desde');
            $numeroHasta = (int) $this->request->getPost('numero_hasta');

            if ($numeroDesde > $numeroHasta) {
                return $this->errorResponse('El número desde debe ser menor o igual al número hasta');
            }

            $talonarioData = [
                'empresa_id' => $empresaId,
                'sucursal_id' => $sucursalId,
                'tipo_comprobante' => $this->request->getPost('tipo_comprobante'),
                'timbrado' => $this->request->getPost('timbrado'),
                'establecimiento' => $this->request->getPost('establecimiento'),
                'punto_expedicion' => $this->request->getPost('punto_expedicion'),
                'numero_desde' => $numeroDesde,
                'numero_hasta' => $numeroHasta,
                'numero_actual' => $numeroDesde,
                'fecha_inicio_vigencia' => $this->request->getPost('fecha_inicio_vigencia'),
                'fecha_fin_vigencia' => $this->request->getPost('fecha_fin_vigencia'),
                'estado' => 'ACTIVO',
                'observaciones' => $this->request->getPost('observaciones'),
                'usuario_creacion' => $this->user['id']
            ];

            $this->talonarioModel->insert($talonarioData);

            return $this->successResponse('Talonario creado exitosamente', [
                'redirect' => base_url('configuracion/talonarios')
            ]);

        } catch (\Exception $e) {
            return $this->errorResponse('Error: ' . $e->getMessage());
        }
    }

    public function editar($id)
    {
        $this->checkAuth();
        $empresaId = $this->user['empresa_id'] ?? null;

        if (!$empresaId) {
            return redirect()->to('/auth/login');
        }

        $talonario = $this->talonarioModel->find($id);

        if (!$talonario || $talonario['empresa_id'] != $empresaId) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Talonario no encontrado');
        }

        $sucursales = $this->sucursalModel->getPorEmpresa($empresaId, true);

        $data = [
            'title' => 'Editar Talonario',
            'talonario' => $talonario,
            'sucursal_id' => $talonario['sucursal_id'] ?? null,
            'sucursales' => $sucursales
        ];

        return $this->render('configuracion/talonarios/form', $data);
    }

    public function actualizar($id)
    {
        $this->checkAuth();
        $empresaId = $this->user['empresa_id'] ?? null;

        if (!$empresaId) {
            return $this->errorResponse('Usuario no tiene empresa asignada', 403);
        }

        $talonario = $this->talonarioModel->find($id);

        if (!$talonario || $talonario['empresa_id'] != $empresaId) {
            return $this->errorResponse('Talonario no encontrado', 404);
        }

        $rules = [
            'sucursal_id' => 'required|integer|greater_than[0]',
            'tipo_comprobante' => 'required|in_list[FACTURA,FACTURA_ELECTRONICA,TICKET,NOTA_CREDITO,NOTA_DEBITO]',
            'timbrado' => 'required|min_length[5]|max_length[20]',
            'establecimiento' => 'required|exact_length[3]',
            'punto_expedicion' => 'required|exact_length[3]',
            'numero_desde' => 'required|integer|greater_than[0]',
            'numero_hasta' => 'required|integer',
            'numero_actual' => 'required|integer',
            'fecha_inicio_vigencia' => 'required|valid_date',
            'fecha_fin_vigencia' => 'required|valid_date',
            'estado' => 'required|in_list[ACTIVO,VENCIDO,AGOTADO,ANULADO]'
        ];

        if (!$this->validate($rules)) {
            return $this->errorResponse('Error de validación: ' . implode(', ', $this->validator->getErrors()));
        }

        try {
            $sucursalId = (int) $this->request->getPost('sucursal_id');
            $sucursal = $this->sucursalModel->find($sucursalId);
            if (!$sucursal || (int) ($sucursal['empresa_id'] ?? 0) !== (int) $empresaId) {
                return $this->errorResponse('Sucursal inválida', 400);
            }

            $numeroDesde = (int) $this->request->getPost('numero_desde');
            $numeroHasta = (int) $this->request->getPost('numero_hasta');
            $numeroActual = (int) $this->request->getPost('numero_actual');

            if ($numeroDesde > $numeroHasta) {
                return $this->errorResponse('El número desde debe ser menor o igual al número hasta');
            }

            if ($numeroActual < $numeroDesde || $numeroActual > $numeroHasta) {
                return $this->errorResponse('El número actual debe estar entre el número desde y hasta');
            }

            $talonarioData = [
                'sucursal_id' => $sucursalId,
                'tipo_comprobante' => $this->request->getPost('tipo_comprobante'),
                'timbrado' => $this->request->getPost('timbrado'),
                'establecimiento' => $this->request->getPost('establecimiento'),
                'punto_expedicion' => $this->request->getPost('punto_expedicion'),
                'numero_desde' => $numeroDesde,
                'numero_hasta' => $numeroHasta,
                'numero_actual' => $numeroActual,
                'fecha_inicio_vigencia' => $this->request->getPost('fecha_inicio_vigencia'),
                'fecha_fin_vigencia' => $this->request->getPost('fecha_fin_vigencia'),
                'estado' => $this->request->getPost('estado'),
                'observaciones' => $this->request->getPost('observaciones')
            ];

            $this->talonarioModel->update($id, $talonarioData);

            return $this->successResponse('Talonario actualizado exitosamente', [
                'redirect' => base_url('configuracion/talonarios')
            ]);

        } catch (\Exception $e) {
            return $this->errorResponse('Error: ' . $e->getMessage());
        }
    }

    public function obtenerSiguienteNumero()
    {
        $this->checkAuth();
        $empresaId = $this->user['empresa_id'] ?? null;
        $tipoComprobante = $this->request->getPost('tipo_comprobante');
        $sucursalId = $this->request->getPost('sucursal_id');

        if (!$empresaId || !$tipoComprobante) {
            return $this->errorResponse('Parámetros incompletos');
        }

        $builder = $this->talonarioModel->where('empresa_id', $empresaId)
            ->where('tipo_comprobante', $tipoComprobante)
            ->where('estado', 'ACTIVO');

        if ($sucursalId) {
            $builder->where('sucursal_id', $sucursalId);
        }

        $talonario = $builder->first();

        if (!$talonario) {
            return $this->errorResponse('No hay talonario activo para este tipo de comprobante');
        }

        // Verificar vigencia
        $fechaActual = date('Y-m-d');
        if ($fechaActual < $talonario['fecha_inicio_vigencia'] || $fechaActual > $talonario['fecha_fin_vigencia']) {
            return $this->errorResponse('El talonario está fuera de vigencia');
        }

        // Verificar si está agotado
        if ($talonario['numero_actual'] >= $talonario['numero_hasta']) {
            return $this->errorResponse('El talonario está agotado');
        }

        $siguienteNumero = $talonario['numero_actual'] + 1;

        return $this->successResponse('Número obtenido', [
            'numero' => $siguienteNumero,
            'timbrado' => $talonario['timbrado'],
            'establecimiento' => $talonario['establecimiento'],
            'punto_expedicion' => $talonario['punto_expedicion'],
            'talonario_id' => $talonario['id']
        ]);
    }

    public function incrementarNumero($id)
    {
        $this->checkAuth();
        $empresaId = $this->user['empresa_id'] ?? null;

        if (!$empresaId) {
            return $this->errorResponse('Usuario no tiene empresa asignada', 403);
        }

        $talonario = $this->talonarioModel->find($id);

        if (!$talonario || $talonario['empresa_id'] != $empresaId) {
            return $this->errorResponse('Talonario no encontrado', 404);
        }

        if ($talonario['numero_actual'] >= $talonario['numero_hasta']) {
            // Marcar como agotado
            $this->talonarioModel->update($id, ['estado' => 'AGOTADO']);
            return $this->errorResponse('El talonario está agotado');
        }

        $nuevoNumero = $talonario['numero_actual'] + 1;
        $this->talonarioModel->update($id, ['numero_actual' => $nuevoNumero]);

        // Si llegó al límite, marcar como agotado
        if ($nuevoNumero >= $talonario['numero_hasta']) {
            $this->talonarioModel->update($id, ['estado' => 'AGOTADO']);
        }

        return $this->successResponse('Número incrementado', [
            'numero_actual' => $nuevoNumero
        ]);
    }

    public function eliminar($id)
    {
        $this->checkAuth();
        $empresaId = $this->user['empresa_id'] ?? null;

        if (!$empresaId) {
            return $this->errorResponse('Usuario no tiene empresa asignada', 403);
        }

        $talonario = $this->talonarioModel->find($id);

        if (!$talonario || $talonario['empresa_id'] != $empresaId) {
            return $this->errorResponse('Talonario no encontrado', 404);
        }

        // Solo desactivar (marcar como anulado)
        if ($this->talonarioModel->update($id, ['estado' => 'ANULADO'])) {
            return $this->successResponse('Talonario eliminado exitosamente');
        }

        return $this->errorResponse('Error al eliminar el talonario');
    }
}
