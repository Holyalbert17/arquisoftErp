<?php

namespace App\Controllers\Chat;

use App\Controllers\BaseController;
use App\Services\ChatService;

class ChatController extends BaseController
{
    protected $chatService;

    public function __construct()
    {
        $this->chatService = new ChatService();
    }

    public function index()
    {
        $this->checkAuth();
        
        // Verifica permiso
        // if (!$this->permisoModel->hasPermission($this->user['rol_id'], 'CHAT_INTERNO', 'ver')) {
        //    return redirect()->to('/dashboard')->with('error', 'No tiene permisos para acceder al chat.');
        // }
        
        $data = [
            'title' => 'Chat Interno',
            'user' => $this->user
        ];

        return $this->render('chat/index', $data);
    }

    // AJAX: Obtener lista de chats y contactos
    public function getInitData()
    {
        $this->checkAuth();
        
        try {
            $chats = $this->chatService->getChatsUsuario($this->user['id'], $this->user['empresa_id']);
            $contactos = $this->chatService->getContactosDisponibles($this->user['empresa_id'], $this->user['id']);
            
            return $this->response->setJSON([
                'status' => 'success',
                'chats' => $chats,
                'contactos' => $contactos,
                'current_user_id' => $this->user['id']
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    // AJAX: Obtener mensajes de un chat
    public function getMensajes($chatId)
    {
        $this->checkAuth();
        
        // Verificar pertenencia al chat
        if (!$this->chatService->esParticipante($chatId, $this->user['id'])) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'No autorizado']);
        }

        $mensajes = $this->chatService->getMensajes($chatId);
        
        // Marcar como leídos
        $this->chatService->marcarLeidos($chatId, $this->user['id']);

        return $this->response->setJSON(['status' => 'success', 'mensajes' => $mensajes]);
    }

    // AJAX: Enviar mensaje
    public function enviar()
    {
        $this->checkAuth();
        
        $chatId = $this->request->getPost('chat_id');
        $mensaje = $this->request->getPost('mensaje');
        $tipo = $this->request->getPost('tipo') ?? 'TEXT';
        
        // TODO: Manejar archivos adjuntos
        
        if (empty($mensaje) && $tipo === 'TEXT') {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Mensaje vacío']);
        }

        try {
            $nuevoMensaje = $this->chatService->enviarMensaje($chatId, $this->user['id'], $mensaje, $tipo);
            return $this->response->setJSON(['status' => 'success', 'mensaje' => $nuevoMensaje]);
        } catch (\Exception $e) {
            return $this->response->setJSON(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    // AJAX: Crear Nuevo Chat (Privado)
    public function crearPrivado() 
    {
        $this->checkAuth();
        $targetUserId = $this->request->getPost('usuario_id');
        
        try {
            $chatId = $this->chatService->getOrCreatePrivateChat($this->user['empresa_id'], $this->user['id'], $targetUserId);
            return $this->response->setJSON(['status' => 'success', 'chat_id' => $chatId]);
        } catch (\Exception $e) {
            return $this->response->setJSON(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }
    
    // AJAX: Crear Grupo
    public function crearGrupo()
    {
        $this->checkAuth();
        // Verificar permiso de crear grupos if needed

        $nombre = $this->request->getPost('nombre');
        $participantes = $this->request->getPost('participantes'); // Array de IDs
        
        if (empty($nombre) || empty($participantes)) {
             return $this->response->setJSON(['status' => 'error', 'message' => 'Datos incompletos']);
        }
        
        // Agregar al creador
        $participantes[] = $this->user['id'];
        
        try {
            $chatId = $this->chatService->crearGrupo($this->user['empresa_id'], $this->user['id'], $nombre, $participantes);
             return $this->response->setJSON(['status' => 'success', 'chat_id' => $chatId]);
        } catch (\Exception $e) {
             return $this->response->setJSON(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }


    // AJAX: Polling para nuevos mensajes
    public function sync()
    {
        $this->checkAuth();
        $lastUpdate = $this->request->getGet('last_update');
        
        $updates = $this->chatService->getUpdates($this->user['id'], $lastUpdate);
        
        return $this->response->setJSON([
            'status' => 'success', 
            'updates' => $updates,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }

    // AJAX: Count Global Unread (For Header Badge)
    public function getUnreadCount()
    {
        $this->checkAuth();
        try {
            $count = $this->chatService->getTotalUnreadCount($this->user['id']);
            return $this->response->setJSON(['status' => 'success', 'count' => $count]);
        } catch (\Exception $e) {
            return $this->response->setJSON(['status' => 'error', 'count' => 0]);
        }
    }
}
