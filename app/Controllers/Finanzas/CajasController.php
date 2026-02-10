<?php

namespace App\Controllers\Finanzas;

use App\Controllers\BaseController;
use App\Models\CajaModel;
use App\Models\MovimientoCajaModel;
use App\Models\ArqueoCajaModel;
use App\Models\ArqueoCajaDetalleModel;
use App\Models\VentaModel;
use App\Models\PagoVentaModel;

class CajasController extends BaseController
{
    protected $cajaModel;
    protected $movimientoCajaModel;
    protected $arqueoCajaModel;
    protected $arqueoCajaDetalleModel;
    protected $ventaModel;
    protected $pagoVentaModel;

    public function __construct()
    {
        $this->cajaModel = new CajaModel();
        $this->movimientoCajaModel = new MovimientoCajaModel();
        $this->arqueoCajaModel = new ArqueoCajaModel();
        $this->arqueoCajaDetalleModel = new ArqueoCajaDetalleModel();
        $this->ventaModel = new VentaModel();
        $this->pagoVentaModel = new PagoVentaModel();
    }

    protected function checkAuth()
    {
        if (!$this->isLoggedIn()) {
            return redirect()->to('/auth/login');
        }

        if (!$this->user) {
            $this->loadUserData();
        }

        $this->requirePermission('CAJAS', 'ver');
    }

    /**
     * Determinar si el usuario actual es admin/gerente (nivel <= 3)
     */
    private function esAdminRol(): bool
    {
        $rolModel = new \App\Models\RolModel();
        $rol = $rolModel->find($this->user['rol_id'] ?? 0);
        return $rol && (int)($rol['nivel'] ?? 99) <= 3;
    }

    /**
     * Validar que el usuario tenga acceso a una caja específica.
     * Admins/gerentes acceden a todas las cajas de su empresa/sucursal.
     * Empleados solo acceden a cajas donde ellos son usuario_id o usuario_apertura.
     */
    private function validarAccesoCaja(array $caja): bool
    {
        if ($this->esAdminRol()) {
            return true;
        }
        $userId = $this->user['id'] ?? 0;
        return ($caja['usuario_id'] ?? null) == $userId
            || ($caja['usuario_apertura'] ?? null) == $userId;
    }

    public function index()
    {
        $this->checkAuth();
        $empresaId = $this->user['empresa_id'] ?? null;
        $sucursalId = $this->sucursal['id'] ?? null;

        if (!$empresaId) {
            return redirect()->to('/auth/login');
        }

        // Determinar nivel del rol
        $rolModel = new \App\Models\RolModel();
        $rol = $rolModel->find($this->user['rol_id'] ?? 0);
        $esAdmin = $rol && (int)($rol['nivel'] ?? 99) <= 3;

        $builder = $this->cajaModel->where('empresa_id', $empresaId);
        if ($sucursalId) {
            $builder->where('sucursal_id', $sucursalId);
        }
        // Empleados (nivel 4+) solo ven sus propias cajas
        if (!$esAdmin) {
            $builder->where('usuario_id', $this->user['id']);
        }

        $cajas = $builder->orderBy('nombre', 'ASC')->findAll();

        // Obtener información adicional
        foreach ($cajas as &$caja) {
            $caja['movimientos_hoy'] = $this->movimientoCajaModel
                ->where('caja_id', $caja['id'])
                ->where('DATE(fecha_movimiento)', date('Y-m-d'))
                ->countAllResults();
        }

        $data = [
            'title' => 'Gestión de Cajas',
            'cajas' => $cajas,
            'tiene_cajas_abiertas' => $this->cajaModel->tieneCajasAbiertas($this->user['id'], $sucursalId)
        ];

        return $this->render('finanzas/cajas/index', $data);
    }

    public function crear()
    {
        $this->checkAuth();
        $empresaId = $this->user['empresa_id'] ?? null;

        if (!$empresaId) {
            return redirect()->to('/auth/login');
        }

        $data = [
            'title' => 'Nueva Caja',
            'caja' => null
        ];

        return $this->render('finanzas/cajas/form', $data);
    }

    public function guardar()
    {
        $this->checkAuth();
        $empresaId = $this->user['empresa_id'] ?? null;

        if (!$empresaId) {
            return $this->errorResponse('Usuario no tiene empresa asignada', 403);
        }

        $rules = [
            'nombre' => 'required|min_length[3]|max_length[100]',
            'descripcion' => 'permit_empty|max_length[500]'
        ];

        if ($this->request->getPost('abrir_inmediatamente')) {
            $rules['saldo_inicial'] = 'required|numeric|greater_than_equal_to[0]';
        }

        if (!$this->validate($rules)) {
            return $this->errorResponse('Error de validación: ' . implode(', ', $this->validator->getErrors()));
        }

        $sucursalId = $this->sucursal['id'] ?? null;
        if (!$sucursalId && $this->session->has('sucursal_id')) {
            $sucursalId = $this->session->get('sucursal_id');
        }

        // No permitir abrir otra caja si ya tiene una abierta (mismo usuario/sucursal)
        if ($this->request->getPost('abrir_inmediatamente') && $this->cajaModel->tieneCajasAbiertas($this->user['id'], $sucursalId)) {
            return $this->errorResponse('Ya tiene una caja abierta. Cierre la caja actual antes de abrir otra.', 400);
        }

        // Validar nombre único por empresa y sucursal (UNIQUE en BD)
        $nombre = trim($this->request->getPost('nombre'));
        $existe = $this->cajaModel->where('empresa_id', $empresaId)
            ->where('sucursal_id', $sucursalId)
            ->where('nombre', $nombre)
            ->first();
        if ($existe) {
            return $this->errorResponse('Ya existe una caja con el mismo nombre en esta sucursal. Use otro nombre.', 400);
        }

        $db = \Config\Database::connect();
        $db->transStart();

        try {
            $cajaData = [
                'empresa_id' => $empresaId,
                'sucursal_id' => $sucursalId,
                'usuario_id' => $this->user['id'],
                'nombre' => $nombre,
                'descripcion' => $this->request->getPost('descripcion'),
                'estado' => 'CERRADA',
                'saldo_inicial' => 0,
                'saldo_actual' => 0
            ];

            $this->cajaModel->insert($cajaData);
            $newId = $this->cajaModel->getInsertID();

            // Si se seleccionó abrir inmediatamente
            if ($this->request->getPost('abrir_inmediatamente')) {
                $saldoInicial = (float) $this->request->getPost('saldo_inicial');
                $observaciones = $this->request->getPost('observaciones_apertura');

                // Actualizar estado de la caja (saldo_actual = 0, el trigger lo sumará al insertar APERTURA)
                $this->cajaModel->update($newId, [
                    'estado' => 'ABIERTA',
                    'saldo_inicial' => $saldoInicial,
                    'saldo_actual' => 0,
                    'fecha_apertura' => date('Y-m-d H:i:s'),
                    'usuario_apertura' => $this->user['id'],
                    'observaciones_apertura' => $observaciones
                ]);

                // Registrar movimiento de apertura
                $this->movimientoCajaModel->insert([
                    'caja_id' => $newId,
                    'tipo_movimiento' => 'APERTURA',
                    'monto' => $saldoInicial,
                    'moneda' => 'PYG',
                    'tipo_cambio' => 1,
                    'monto_moneda_principal' => $saldoInicial,
                    'concepto' => 'Apertura de caja (Inicial)',
                    'observaciones' => $observaciones,
                    'usuario_id' => $this->user['id']
                ]);
            }

            $db->transComplete();

            if ($db->transStatus() === false) {
                return $this->errorResponse('Error al guardar la caja');
            }

            return $this->successResponse('Caja guardada exitosamente', [
                'redirect' => base_url('finanzas/cajas/ver/' . $newId),
                'id' => $newId
            ]);

        } catch (\Exception $e) {
            $db->transRollback();
            return $this->errorResponse('Error: ' . $e->getMessage());
        }
    }

    /**
     * Página para elegir qué caja abrir (solo cajas cerradas).
     */
    public function abrirSeleccionar()
    {
        $this->checkAuth();
        $empresaId = $this->user['empresa_id'] ?? null;
        $sucursalId = $this->sucursal['id'] ?? null;

        if (!$empresaId) {
            return redirect()->to('/auth/login');
        }

        if ($this->cajaModel->tieneCajasAbiertas($this->user['id'], $sucursalId)) {
            return redirect()->to('finanzas/cajas')->with('info', 'Ya tiene una caja abierta. Cierre la actual antes de abrir otra.');
        }

        $builder = $this->cajaModel->where('empresa_id', $empresaId)->where('estado', 'CERRADA');
        if ($sucursalId) {
            $builder->where('sucursal_id', $sucursalId);
        }
        // Empleados solo ven sus propias cajas
        if (!$this->esAdminRol()) {
            $builder->where('usuario_id', $this->user['id']);
        }
        $cajasCerradas = $builder->orderBy('nombre', 'ASC')->findAll();

        $data = [
            'title' => 'Abrir Caja',
            'cajas' => $cajasCerradas
        ];

        return $this->render('finanzas/cajas/abrir_seleccionar', $data);
    }

    public function abrir($id)
    {
        $this->checkAuth();
        $empresaId = $this->user['empresa_id'] ?? null;
        $sucursalId = $this->sucursal['id'] ?? ($this->session->get('sucursal_id') ?? null);

        if (!$empresaId) {
            return redirect()->to('/auth/login');
        }

        $caja = $this->cajaModel->find($id);

        if (!$caja || $caja['empresa_id'] != $empresaId) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Caja no encontrada');
        }

        if (!$this->validarAccesoCaja($caja)) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('No tiene acceso a esta caja');
        }

        if ($sucursalId && !empty($caja['sucursal_id']) && (int) $caja['sucursal_id'] !== (int) $sucursalId) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Caja no encontrada');
        }

        if ($caja['estado'] == 'ABIERTA') {
            return redirect()->to('finanzas/cajas')->with('error', 'La caja ya está abierta');
        }

        $data = [
            'title' => 'Abrir Caja',
            'caja' => $caja
        ];

        return $this->render('finanzas/cajas/abrir', $data);
    }

    protected function normalizarNumero($valor, $default = 0, $maxValue = 10000000000000)
    {
        if ($valor === null || $valor === '') return $default;
        // Si ya es numérico, devolverlo
        if (is_numeric($valor)) {
            $num = (float) $valor;
            return max(0, min($num, (float) $maxValue));
        }
        // Eliminar puntos de miles
        $valor = str_replace('.', '', $valor);
        // Reemplazar coma decimal por punto
        $valor = str_replace(',', '.', $valor);
        $num = (float) $valor;
        return max(0, min($num, (float) $maxValue));
    }

    public function procesarApertura($id)
    {
        $this->checkAuth();
        $empresaId = $this->user['empresa_id'] ?? null;
        $sucursalId = $this->sucursal['id'] ?? ($this->session->get('sucursal_id') ?? null);

        if (!$empresaId) {
            return $this->errorResponse('Usuario no tiene empresa asignada', 403);
        }

        $caja = $this->cajaModel->find($id);

        if (!$caja || $caja['empresa_id'] != $empresaId) {
            return $this->errorResponse('Caja no encontrada', 404);
        }

        if (!$this->validarAccesoCaja($caja)) {
            return $this->errorResponse('No tiene acceso a esta caja', 403);
        }

        if ($sucursalId && !empty($caja['sucursal_id']) && (int) $caja['sucursal_id'] !== (int) $sucursalId) {
            return $this->errorResponse('No tiene acceso a esta caja', 403);
        }

        if ($caja['estado'] == 'ABIERTA') {
            return $this->errorResponse('La caja ya está abierta');
        }

        if ($this->cajaModel->tieneCajasAbiertas($this->user['id'], $sucursalId)) {
            return $this->errorResponse('Ya tiene una caja abierta. Cierre la caja actual antes de abrir otra.', 400);
        }

        // Remover validación 'numeric' estricta si el input viene formateado
        // Validamos manualmente después de normalizar
        $rules = [
            'saldo_inicial' => 'required',
            'observaciones' => 'permit_empty|max_length[500]'
        ];

        if (!$this->validate($rules)) {
            return $this->errorResponse('Error de validación: ' . implode(', ', $this->validator->getErrors()));
        }

        $saldoInicial = $this->normalizarNumero($this->request->getPost('saldo_inicial'));

        if ($saldoInicial < 0) {
            return $this->errorResponse('El saldo inicial no puede ser negativo');
        }

        $db = \Config\Database::connect();
        $db->transStart();

        try {
            // Actualizar caja (saldo_actual = 0 porque el trigger lo sumará al insertar el movimiento APERTURA)
            $this->cajaModel->update($id, [
                'estado' => 'ABIERTA',
                'saldo_inicial' => $saldoInicial,
                'saldo_actual' => 0,
                'fecha_apertura' => date('Y-m-d H:i:s'),
                'usuario_apertura' => $this->user['id'],
                'observaciones_apertura' => $this->request->getPost('observaciones')
            ]);

            // Registrar movimiento de apertura
            $this->movimientoCajaModel->insert([
                'caja_id' => $id,
                'tipo_movimiento' => 'APERTURA',
                'monto' => $saldoInicial,
                'moneda' => 'PYG',
                'tipo_cambio' => 1,
                'monto_moneda_principal' => $saldoInicial,
                'concepto' => 'Apertura de caja',
                'observaciones' => $this->request->getPost('observaciones'),
                'fecha_movimiento' => date('Y-m-d H:i:s'),
                'usuario_id' => $this->user['id']
            ]);

            $db->transComplete();

            if ($db->transStatus() === false) {
                return $this->errorResponse('Error al abrir la caja');
            }

            return $this->successResponse('Caja abierta exitosamente', [
                'redirect' => base_url('finanzas/cajas/ver/' . $id)
            ]);

        } catch (\Exception $e) {
            $db->transRollback();
            return $this->errorResponse('Error: ' . $e->getMessage());
        }
    }

    public function cerrar($id)
    {
        $this->checkAuth();
        $empresaId = $this->user['empresa_id'] ?? null;
        $sucursalId = $this->sucursal['id'] ?? ($this->session->get('sucursal_id') ?? null);

        if (!$empresaId) {
            return redirect()->to('/auth/login');
        }

        $caja = $this->cajaModel->find($id);

        if (!$caja || $caja['empresa_id'] != $empresaId) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Caja no encontrada');
        }

        if (!$this->validarAccesoCaja($caja)) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('No tiene acceso a esta caja');
        }

        if ($caja['estado'] != 'ABIERTA') {
            return redirect()->to('finanzas/cajas')->with('error', 'La caja no está abierta');
        }

        // Obtener movimientos del día
        $fechaInicio = $caja['fecha_apertura'] ?? (date('Y-m-d') . ' 00:00:00');
        $fechaFin = date('Y-m-d H:i:s'); // Hasta ahora

        $movimientos = $this->movimientoCajaModel->getMovimientosCaja($id, $fechaInicio, $fechaFin);
        $resumen = $this->movimientoCajaModel->getResumenMovimientos($id, $fechaInicio, $fechaFin);

        // Calcular saldo esperado
        // Saldo Inicial + Ingresos - Egresos
        // IMPORTANTE: Filtrar movimientos que SI afectan saldo (efectivo)
        // Por ahora asumimos que movimientos_caja solo tiene lo que afecta saldo caja.
        // Si hay movimientos de 'CHEQUE' que no tocan efectivo, deberíamos filtrarlos.
        // Pero el modelo 'MovimientoCaja' usualmente es "Caja Chica/Efectivo".
        // Si pagaron con Tarjeta, ¿entra a MovimientoCaja?
        // En VentaService:
        // if ($cajaId && $esEfectivo) ... -> Sólo efectivo entra a MovimientoCaja.
        // Entonces sumando todo debería dar el saldo.
        
        $saldoEsperado = $caja['saldo_inicial'];
        // Ajuste en lógica: usar la columna 'monto_moneda_principal' y el tipo de movimiento
        // Los movimientos ya están en la tabla. Mejor confiar en $caja['saldo_actual'] si se mantiene bien?
        // Pero recalcular es más seguro.
        
        // Recalcular saldo esperado desde movimientos
        foreach ($movimientos as $mov) {
             if ($mov['tipo_movimiento'] == 'APERTURA') continue; // Ya contamos saldo inicial
             
             switch($mov['tipo_movimiento']) {
                 case 'INGRESO':
                 case 'VENTA': // Si VentaService guarda como VENTA en movimientos
                 case 'COBRO':
                 case 'TRANSFERENCIA_ENTRADA':
                 case 'AJUSTE':
                     // AJUSTE por arqueo: puede ser positivo (sobrante) o negativo (faltante).
                     // Conservar el signo desde movimientos_caja.monto_moneda_principal.
                     $saldoEsperado += (float) ($mov['monto_moneda_principal'] ?? 0);
                     break;
                 case 'EGRESO':
                 case 'GASTO':
                 case 'DEVOLUCION':
                 case 'TRANSFERENCIA_SALIDA':
                     $saldoEsperado -= $mov['monto_moneda_principal'];
                     break;
             }
        }
        
        // Comparar con saldo_actual de caja para sanidad
        // $saldoEsperado debería ser igual a $caja['saldo_actual']
        
        // Obtener ventas pendientes dentro del periodo de la caja
        $db = \Config\Database::connect();
        $fechaInicioPend = $caja['fecha_apertura'] ?? (date('Y-m-d') . ' 00:00:00');
        $fechaFinPend = date('Y-m-d H:i:s');

        $ventasBuilder = $db->table('ventas')
            ->where('empresa_id', $empresaId)
            ->where('estado', 'PENDIENTE')
            ->where('fecha_venta >=', $fechaInicioPend)
            ->where('fecha_venta <=', $fechaFinPend);

        if ($db->fieldExists('caja_id', 'ventas')) {
            $ventasBuilder->where('caja_id', $id);
        } else {
            $ventasBuilder->where('usuario_id', $caja['usuario_apertura'] ?? $this->user['id']);
        }

        $ventasPendientes = $ventasBuilder->orderBy('fecha_venta', 'DESC')->get()->getResultArray();

        $data = [
            'title' => 'Cerrar Caja',
            'caja' => $caja,
            'movimientos' => $movimientos,
            'resumen' => $resumen,
            'saldo_esperado' => $caja['saldo_actual'], // Usar el saldo actual mantenido por el sistema
            'ventas_pendientes' => $ventasPendientes
        ];

        return $this->render('finanzas/cajas/cerrar', $data);
    }

    public function procesarCierre($id)
    {
        $this->checkAuth();
        $empresaId = $this->user['empresa_id'] ?? null;
        $sucursalId = $this->sucursal['id'] ?? ($this->session->get('sucursal_id') ?? null);

        if (!$empresaId) {
            return $this->errorResponse('Usuario no tiene empresa asignada', 403);
        }

        $caja = $this->cajaModel->find($id);

        if (!$caja || $caja['empresa_id'] != $empresaId) {
            return $this->errorResponse('Caja no encontrada', 404);
        }

        if (!$this->validarAccesoCaja($caja)) {
            return $this->errorResponse('No tiene acceso a esta caja', 403);
        }

        if ($caja['estado'] != 'ABIERTA') {
            return $this->errorResponse('La caja no está abierta');
        }

        $rules = [
            'saldo_fisico' => 'required',
            'observaciones' => 'permit_empty|max_length[500]'
        ];

        if (!$this->validate($rules)) {
            return $this->errorResponse('Error de validación: ' . implode(', ', $this->validator->getErrors()));
        }

        // Validación profesional: no permitir cierre si hay ventas pendientes dentro del periodo de la caja
        $fechaInicio = $caja['fecha_apertura'] ?? (date('Y-m-d') . ' 00:00:00');
        $fechaFin = date('Y-m-d H:i:s');

        $db = \Config\Database::connect();
        $ventasBuilder = $db->table('ventas')
            ->select('COUNT(*) as total', false)
            ->where('empresa_id', $empresaId)
            ->where('estado', 'PENDIENTE')
            ->where('fecha_venta >=', $fechaInicio)
            ->where('fecha_venta <=', $fechaFin);

        // Si ventas tiene caja_id, cerramos por caja. Si no, por usuario que abrió la caja.
        if ($db->fieldExists('caja_id', 'ventas')) {
            $ventasBuilder->where('caja_id', $id);
        } else {
            $ventasBuilder->where('usuario_id', $caja['usuario_apertura'] ?? $this->user['id']);
        }

        $ventasRow = $ventasBuilder->get()->getRowArray();
        $ventasPendientes = (int) ($ventasRow['total'] ?? 0);

        if ($ventasPendientes > 0) {
            return $this->errorResponse('No se puede cerrar la caja: tiene ventas pendientes. Finalice o anule las ventas pendientes antes de cerrar.', 400);
        }

        $db = \Config\Database::connect();
        $db->transStart();

        try {
            $saldoFisico = $this->normalizarNumero($this->request->getPost('saldo_fisico'));
            $saldoSistema = (float) $caja['saldo_actual'];
            $diferencia = $saldoFisico - $saldoSistema;

            // Actualizar caja
            $this->cajaModel->update($id, [
                'estado' => 'CERRADA',
                'saldo_esperado' => $saldoSistema,
                'diferencia' => $diferencia,
                'saldo_actual' => $saldoFisico,
                'fecha_cierre' => date('Y-m-d H:i:s'),
                'usuario_cierre' => $this->user['id'],
                'observaciones_cierre' => $this->request->getPost('observaciones')
            ]);

            // Registrar movimiento de cierre
            $this->movimientoCajaModel->insert([
                'caja_id' => $id,
                'tipo_movimiento' => 'CIERRE',
                'monto' => abs($diferencia), // Guardamos la diferencia absoluta
                'moneda' => 'PYG',
                'tipo_cambio' => 1,
                'monto_moneda_principal' => abs($diferencia),
                'concepto' => 'Cierre de caja - Diferencia: ' . number_format($diferencia, 0, ',', '.') . ' Gs.',
                'observaciones' => $this->request->getPost('observaciones'),
                'fecha_movimiento' => date('Y-m-d H:i:s'),
                'usuario_id' => $this->user['id']
            ]);

            $db->transComplete();

            if ($db->transStatus() === false) {
                return $this->errorResponse('Error al cerrar la caja');
            }

            return $this->successResponse('Caja cerrada exitosamente', [
                'redirect' => base_url('finanzas/cajas')
            ]);

        } catch (\Exception $e) {
            $db->transRollback();
            return $this->errorResponse('Error: ' . $e->getMessage());
        }
    }

    public function ver($id)
    {
        $this->checkAuth();
        $empresaId = $this->user['empresa_id'] ?? null;
        $sucursalId = $this->sucursal['id'] ?? ($this->session->get('sucursal_id') ?? null);

        if (!$empresaId) {
            return redirect()->to('/auth/login');
        }

        $caja = $this->cajaModel->find($id);

        if (!$caja || $caja['empresa_id'] != $empresaId) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Caja no encontrada');
        }

        if (!$this->validarAccesoCaja($caja)) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('No tiene acceso a esta caja');
        }

        // Obtener movimientos del periodo real de la caja (apertura -> ahora/cierre)
        $fechaInicio = $caja['fecha_apertura'] ?? (date('Y-m-d') . ' 00:00:00');
        $fechaFin = $caja['fecha_cierre'] ?? date('Y-m-d H:i:s');

        $movimientos = $this->movimientoCajaModel->getMovimientosCaja($id, $fechaInicio, $fechaFin);
        $resumen = $this->movimientoCajaModel->getResumenMovimientos($id, $fechaInicio, $fechaFin);

        // Obtener ventas asociadas a esta caja (por pagos_ventas.caja_id)
        $db = \Config\Database::connect();
        $ventas = $db->table('ventas v')
            ->select('v.*')
            ->join('pagos_ventas pv', 'pv.venta_id = v.id', 'inner')
            ->where('v.empresa_id', $empresaId)
            ->where('pv.caja_id', $id)
            ->where('v.estado', 'COMPLETADA')
            ->where('v.fecha_venta >=', $fechaInicio)
            ->where('v.fecha_venta <=', $fechaFin)
            ->groupBy('v.id')
            ->orderBy('v.fecha_venta', 'DESC')
            ->get()
            ->getResultArray();

        $data = [
            'title' => 'Detalle de Caja: ' . $caja['nombre'],
            'caja' => $caja,
            'movimientos' => $movimientos,
            'resumen' => $resumen,
            'ventas' => $ventas
        ];

        return $this->render('finanzas/cajas/ver', $data);
    }

    public function arqueo($id)
    {
        $this->checkAuth();
        $empresaId = $this->user['empresa_id'] ?? null;
        $sucursalId = $this->sucursal['id'] ?? ($this->session->get('sucursal_id') ?? null);

        if (!$empresaId) {
            return redirect()->to('/auth/login');
        }

        $caja = $this->cajaModel->find($id);

        if (!$caja || $caja['empresa_id'] != $empresaId) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Caja no encontrada');
        }

        if (!$this->validarAccesoCaja($caja)) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('No tiene acceso a esta caja');
        }

        if ($caja['estado'] != 'ABIERTA') {
            return redirect()->to('finanzas/cajas')->with('error', 'Solo se pueden hacer arqueos en cajas abiertas');
        }

        $fechaInicio = $caja['fecha_apertura'] ?? (date('Y-m-d') . ' 00:00:00');
        $fechaFin = date('Y-m-d H:i:s');
        $usuarioCaja = $caja['usuario_apertura'];

        $calculo = $this->calcularTotalesSistema($id, $empresaId, $usuarioCaja, $fechaInicio, $fechaFin, (float) ($caja['saldo_inicial'] ?? 0));
        $totalesPorMedio = $calculo['totales'];
        $totalSistema = $calculo['total'];

        $data = [
            'title' => 'Arqueo de Caja',
            'caja' => $caja,
            'totales_esperados' => $totalesPorMedio,
            'total_sistema' => $totalSistema
        ];

        return $this->render('finanzas/cajas/arqueo', $data);
    }

    public function procesarArqueo($id)
    {
        $this->checkAuth();
        $empresaId = $this->user['empresa_id'] ?? null;
        $sucursalId = $this->sucursal['id'] ?? ($this->session->get('sucursal_id') ?? null);

        if (!$empresaId) {
            return $this->errorResponse('Usuario no tiene empresa asignada', 403);
        }

        $caja = $this->cajaModel->find($id);

        if (!$caja || $caja['empresa_id'] != $empresaId) {
            return $this->errorResponse('Caja no encontrada', 404);
        }

        if (!$this->validarAccesoCaja($caja)) {
            return $this->errorResponse('No tiene acceso a esta caja', 403);
        }

        if ($caja['estado'] != 'ABIERTA') {
            return $this->errorResponse('Solo se pueden hacer arqueos en cajas abiertas');
        }

        $esFinal = (int) ($this->request->getPost('es_final') ?? 0) === 1;

        $rules = [
            'saldo_fisico' => 'required|numeric|greater_than_equal_to[0]',
            'denominaciones' => 'required'
        ];

        if (!$this->validate($rules)) {
            return $this->errorResponse('Error de validación: ' . implode(', ', $this->validator->getErrors()));
        }

        $db = \Config\Database::connect();
        $db->transStart();

        try {
            $saldoFisico = $this->normalizarNumero($this->request->getPost('saldo_fisico'));
            $denominaciones = json_decode($this->request->getPost('denominaciones'), true);

            if (!is_array($denominaciones)) {
                return $this->errorResponse('Formato de denominaciones inválido');
            }

            // Recalcular total del sistema en servidor (no confiar en el frontend)
            $fechaInicio = $caja['fecha_apertura'] ?? (date('Y-m-d') . ' 00:00:00');
            $fechaFin = date('Y-m-d H:i:s');
            $usuarioCaja = $caja['usuario_apertura'];

            if ($esFinal) {
                // Un arqueo final debe ejecutarse una sola vez por caja abierta.
                // Si ya existe un AJUSTE por arqueo en el periodo de la caja, bloquear.
                $ajustePrevio = $this->movimientoCajaModel
                    ->where('caja_id', $id)
                    ->where('tipo_movimiento', 'AJUSTE')
                    ->where('fecha_movimiento >=', $fechaInicio)
                    ->like('concepto', 'Ajuste por arqueo', 'after')
                    ->countAllResults();

                if ($ajustePrevio > 0) {
                    return $this->errorResponse('Ya existe un arqueo final (ajuste) para esta caja. Solo se permiten arqueos de control adicionales.', 400);
                }
            }

            $calculo = $this->calcularTotalesSistema($id, $empresaId, $usuarioCaja, $fechaInicio, $fechaFin, (float) ($caja['saldo_inicial'] ?? 0));
            $totalSistema = (float) $calculo['total'];

            // Validar que saldo_fisico coincida con la suma de denominaciones
            $totalFisicoDetalles = 0;
            foreach ($denominaciones as $detalle) {
                $subtotal = $this->normalizarNumero($detalle['subtotal'] ?? 0);
                if ($subtotal < 0) {
                    return $this->errorResponse('Subtotal inválido en denominaciones');
                }
                $totalFisicoDetalles += $subtotal;
            }

            // Tolerancia mínima por redondeo
            if (abs($saldoFisico - $totalFisicoDetalles) > 1) {
                return $this->errorResponse('El saldo físico no coincide con el total calculado por denominaciones');
            }

            $diferencia = $saldoFisico - $totalSistema;

            // Crear arqueo
            $arqueoData = [
                'caja_id' => $id,
                'fecha_arqueo' => date('Y-m-d'),
                'hora_arqueo' => date('H:i:s'),
                'saldo_sistema' => $totalSistema,
                'saldo_fisico' => $saldoFisico,
                'diferencia' => $diferencia,
                'observaciones' => $this->request->getPost('observaciones'),
                'usuario_id' => $this->user['id']
            ];

            $this->arqueoCajaModel->insert($arqueoData);
            $arqueoId = $this->arqueoCajaModel->getInsertID();

            // Crear detalles con tipo de medio de pago
            $orden = 1;
            foreach ($denominaciones as $detalle) {
                if ((int) ($detalle['cantidad'] ?? 0) > 0 || (float) ($detalle['subtotal'] ?? 0) > 0) {
                    $this->arqueoCajaDetalleModel->insert([
                        'arqueo_id' => $arqueoId,
                        'tipo_medio_pago' => $detalle['tipo_medio_pago'] ?? 'EFECTIVO',
                        'denominacion' => $detalle['denominacion'],
                        'cantidad' => (int) ($detalle['cantidad'] ?? 1),
                        'subtotal' => (float) $detalle['subtotal'],
                        'orden' => $orden++
                    ]);
                }
            }

            // Arqueo de control: NO ajusta saldo.
            // Arqueo final: SI ajusta saldo y registra el AJUSTE.
            if ($esFinal && abs($diferencia) > 0.01) {
                $this->movimientoCajaModel->insert([
                    'caja_id' => $id,
                    'tipo_movimiento' => 'AJUSTE',
                    'monto' => abs($diferencia),
                    'moneda' => 'PYG',
                    'tipo_cambio' => 1,
                    // Guardar el signo real para que reportes/listados reflejen si fue faltante o sobrante
                    'monto_moneda_principal' => $diferencia,
                    'concepto' => 'Ajuste por arqueo - Diferencia: ' . number_format($diferencia, 0, ',', '.') . ' Gs.',
                    'observaciones' => 'Arqueo final realizado el ' . date('d/m/Y H:i'),
                    'fecha_movimiento' => date('Y-m-d H:i:s'),
                    'usuario_id' => $this->user['id']
                ]);

                // saldo_actual se actualiza automáticamente por el trigger trg_actualizar_saldo_caja
            }

            $db->transComplete();

            if ($db->transStatus() === false) {
                return $this->errorResponse('Error al procesar el arqueo');
            }

            $mensaje = $esFinal ? 'Arqueo final registrado exitosamente' : 'Arqueo de control registrado exitosamente';
            return $this->successResponse($mensaje, [
                'redirect' => base_url('finanzas/cajas/ver/' . $id)
            ]);

        } catch (\Exception $e) {
            $db->transRollback();
            return $this->errorResponse('Error: ' . $e->getMessage());
        }
    }

    public function registrarMovimiento($id)
    {
        $this->checkAuth();
        $empresaId = $this->user['empresa_id'] ?? null;
        $sucursalId = $this->sucursal['id'] ?? ($this->session->get('sucursal_id') ?? null);

        if (!$empresaId) {
            return $this->errorResponse('Usuario no tiene empresa asignada', 403);
        }

        $caja = $this->cajaModel->find($id);

        if (!$caja || $caja['empresa_id'] != $empresaId) {
            return $this->errorResponse('Caja no encontrada', 404);
        }

        if (!$this->validarAccesoCaja($caja)) {
            return $this->errorResponse('No tiene acceso a esta caja', 403);
        }

        if ($caja['estado'] != 'ABIERTA') {
            return $this->errorResponse('La caja debe estar abierta para registrar movimientos');
        }

        // Remover validación numeric estricta
        $rules = [
            'tipo_movimiento' => 'required|in_list[EGRESO,INGRESO,TRANSFERENCIA_ENTRADA,TRANSFERENCIA_SALIDA]',
            'monto' => 'required',
            'concepto' => 'required|min_length[5]|max_length[200]'
        ];

        if (!$this->validate($rules)) {
            return $this->errorResponse('Error de validación: ' . implode(', ', $this->validator->getErrors()));
        }

        try {
            $tipoMovimiento = $this->request->getPost('tipo_movimiento');
            $monto = $this->normalizarNumero($this->request->getPost('monto'));
            $moneda = $this->request->getPost('moneda') ?? 'PYG';
            $tipoCambio = $this->normalizarNumero($this->request->getPost('tipo_cambio') ?? 1);
            $montoPrincipal = $monto * $tipoCambio;

            if ($montoPrincipal <= 0) {
                return $this->errorResponse('El monto debe ser mayor a cero');
            }

            $movimientoData = [
                'caja_id' => $id,
                'tipo_movimiento' => $tipoMovimiento,
                'monto' => $monto,
                'moneda' => $moneda,
                'tipo_cambio' => $tipoCambio,
                'monto_moneda_principal' => $montoPrincipal,
                'concepto' => $this->request->getPost('concepto'),
                'referencia' => $this->request->getPost('referencia'),
                'observaciones' => $this->request->getPost('observaciones'),
                'fecha_movimiento' => date('Y-m-d H:i:s'),
                'usuario_id' => $this->user['id']
            ];

            $this->movimientoCajaModel->insert($movimientoData);

            // saldo_actual se actualiza automáticamente por el trigger trg_actualizar_saldo_caja

            return $this->successResponse('Movimiento registrado exitosamente', [
                'redirect' => base_url('finanzas/cajas/ver/' . $id)
            ]);

        } catch (\Exception $e) {
            return $this->errorResponse('Error: ' . $e->getMessage());
        }
    }

    /**
     * Calcula los totales esperados del sistema por medio de pago para una caja.
     * Fuente de verdad: pagos_ventas + pagos_cuentas + movimientos manuales.
     */
    private function calcularTotalesSistema(int $cajaId, int $empresaId, int $usuarioCaja, string $fechaInicio, string $fechaFin, float $saldoInicial = 0): array
    {
        $db = \Config\Database::connect();

        $totalesPorMedio = [
            'EFECTIVO' => 0,
            'TARJETA_DEBITO' => 0,
            'TARJETA_CREDITO' => 0,
            'TRANSFERENCIA' => 0,
            'CHEQUE' => 0,
            'OTRO' => 0
        ];

        // 1) Saldo inicial en efectivo
        $totalesPorMedio['EFECTIVO'] += (float) $saldoInicial;

        // 2) Ventas completadas: preferir vínculo por caja_id en pagos_ventas si existe
        $tieneCajaIdEnPagos = $db->fieldExists('caja_id', 'pagos_ventas');
        if ($tieneCajaIdEnPagos) {
            $queryVentas = $db->query("
                SELECT
                    pv.forma_pago as metodo_pago,
                    SUM(pv.monto) as total
                FROM pagos_ventas pv
                INNER JOIN ventas v ON v.id = pv.venta_id
                WHERE pv.caja_id = ?
                    AND v.empresa_id = ?
                    AND v.fecha_venta >= ?
                    AND v.fecha_venta <= ?
                    AND v.estado = 'COMPLETADA'
                GROUP BY pv.forma_pago
            ", [$cajaId, $empresaId, $fechaInicio, $fechaFin]);
        } else {
            $queryVentas = $db->query("
                SELECT
                    pv.forma_pago as metodo_pago,
                    SUM(pv.monto) as total
                FROM pagos_ventas pv
                INNER JOIN ventas v ON v.id = pv.venta_id
                WHERE v.fecha_venta >= ?
                    AND v.fecha_venta <= ?
                    AND v.estado = 'COMPLETADA'
                    AND v.empresa_id = ?
                    AND v.usuario_id = ?
                GROUP BY pv.forma_pago
            ", [$fechaInicio, $fechaFin, $empresaId, $usuarioCaja]);
        }

        foreach ($queryVentas->getResultArray() as $row) {
            $metodo = strtoupper((string) $row['metodo_pago']);
            $monto = (float) ($row['total'] ?? 0);
            if (isset($totalesPorMedio[$metodo])) {
                $totalesPorMedio[$metodo] += $monto;
            } else {
                $totalesPorMedio['OTRO'] += $monto;
            }
        }

        // 3) Cobros CxC por caja (ingresos)
        $queryCobros = $db->query("
            SELECT forma_pago, SUM(monto) as total
            FROM pagos_cuentas
            WHERE caja_id = ?
            AND cuenta_cobrar_id IS NOT NULL
            GROUP BY forma_pago
        ", [$cajaId]);

        foreach ($queryCobros->getResultArray() as $row) {
            $metodo = strtoupper((string) $row['forma_pago']);
            $monto = (float) ($row['total'] ?? 0);
            if (isset($totalesPorMedio[$metodo])) {
                $totalesPorMedio[$metodo] += $monto;
            } else {
                $totalesPorMedio['OTRO'] += $monto;
            }
        }

        // 4) Pagos CxP por caja (egresos)
        $queryPagos = $db->query("
            SELECT forma_pago, SUM(monto) as total
            FROM pagos_cuentas
            WHERE caja_id = ?
            AND cuenta_pagar_id IS NOT NULL
            GROUP BY forma_pago
        ", [$cajaId]);

        foreach ($queryPagos->getResultArray() as $row) {
            $metodo = strtoupper((string) $row['forma_pago']);
            $monto = (float) ($row['total'] ?? 0);
            if (isset($totalesPorMedio[$metodo])) {
                $totalesPorMedio[$metodo] -= $monto;
            } else {
                $totalesPorMedio['OTRO'] -= $monto;
            }
        }

        // 5) Movimientos manuales de caja (se consideran efectivo)
        $queryMovimientos = $db->query("
            SELECT tipo_movimiento, SUM(monto_moneda_principal) as total
            FROM movimientos_caja
            WHERE caja_id = ?
            AND tipo_movimiento IN ('INGRESO', 'EGRESO', 'TRANSFERENCIA_ENTRADA', 'TRANSFERENCIA_SALIDA')
            GROUP BY tipo_movimiento
        ", [$cajaId]);

        foreach ($queryMovimientos->getResultArray() as $row) {
            $tipo = $row['tipo_movimiento'];
            $monto = (float) ($row['total'] ?? 0);

            if ($tipo == 'INGRESO' || $tipo == 'TRANSFERENCIA_ENTRADA') {
                $totalesPorMedio['EFECTIVO'] += $monto;
            } elseif ($tipo == 'EGRESO' || $tipo == 'TRANSFERENCIA_SALIDA') {
                $totalesPorMedio['EFECTIVO'] -= $monto;
            }
        }

        return [
            'totales' => $totalesPorMedio,
            'total' => array_sum($totalesPorMedio)
        ];
    }
}
