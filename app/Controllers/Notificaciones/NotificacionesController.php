<?php

namespace App\Controllers\Notificaciones;

use App\Controllers\BaseController;
use App\Models\NotificacionModel;

class NotificacionesController extends BaseController
{
    protected $notificacionModel;

    public function __construct()
    {
        $this->notificacionModel = new NotificacionModel();
    }
    
    protected function checkAuth()
    {
        if (!$this->isLoggedIn()) {
            return redirect()->to('/auth/login');
        }
    }

    public function index()
    {
        $this->checkAuth();
        $empresaId = $this->user['empresa_id'];
        $usuarioId = $this->user['id'];
        
        $notificaciones = $this->notificacionModel->where('empresa_id', $empresaId)
                                                  ->where('usuario_id', $usuarioId)
                                                  ->where('estado', true)
                                                  ->orderBy('fecha_creacion', 'DESC')
                                                  ->limit(50)
                                                  ->findAll();
        
        $data = [
            'title' => 'Notificaciones',
            'notificaciones' => $notificaciones,
            'noLeidas' => $this->notificacionModel->where('empresa_id', $empresaId)
                                                   ->where('usuario_id', $usuarioId)
                                                   ->where('leida', false)
                                                   ->where('estado', true)
                                                   ->countAllResults()
        ];

        return $this->render('notificaciones/index', $data);
    }

    public function marcarLeida($id)
    {
        $this->checkAuth();
        $empresaId = $this->user['empresa_id'];
        $usuarioId = $this->user['id'];
        
        $notificacion = $this->notificacionModel->find($id);
        
        if (!$notificacion || $notificacion['empresa_id'] != $empresaId || 
            $notificacion['usuario_id'] != $usuarioId) {
            return $this->errorResponse('Notificación no encontrada', 404);
        }

        $this->notificacionModel->update($id, [
            'leida' => true,
            'fecha_lectura' => date('Y-m-d H:i:s')
        ]);

        return $this->successResponse('Notificación marcada como leída');
    }

    public function marcarTodasLeidas()
    {
        $this->checkAuth();
        $empresaId = $this->user['empresa_id'];
        $usuarioId = $this->user['id'];
        
        $this->notificacionModel->where('empresa_id', $empresaId)
                               ->where('usuario_id', $usuarioId)
                               ->where('leida', false)
                               ->set([
                                   'leida' => true,
                                   'fecha_lectura' => date('Y-m-d H:i:s')
                               ])
                               ->update();

        return $this->successResponse('Todas las notificaciones marcadas como leídas');
    }

    public function eliminar($id)
    {
        $this->checkAuth();
        $empresaId = $this->user['empresa_id'];
        $usuarioId = $this->user['id'];
        
        $notificacion = $this->notificacionModel->find($id);
        
        if (!$notificacion || $notificacion['empresa_id'] != $empresaId || 
            $notificacion['usuario_id'] != $usuarioId) {
            return $this->errorResponse('Notificación no encontrada', 404);
        }

        $this->notificacionModel->update($id, ['estado' => false]);

        return $this->successResponse('Notificación eliminada');
    }

    public function obtenerNoLeidas()
    {
        $this->checkAuth();
        $empresaId = $this->user['empresa_id'];
        $usuarioId = $this->user['id'];
        
        $notificaciones = $this->notificacionModel->where('empresa_id', $empresaId)
                                                  ->where('usuario_id', $usuarioId)
                                                  ->where('leida', false)
                                                  ->where('estado', true)
                                                  ->orderBy('fecha_creacion', 'DESC')
                                                  ->limit(10)
                                                  ->findAll();
        
        return $this->response->setJSON([
            'success' => true,
            'notificaciones' => $notificaciones,
            'cantidad' => count($notificaciones)
        ]);
    }
}
