<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $session = \Config\Services::session();
        
        if (!$session->has('user_id')) {
            if ($request->isAJAX()) {
                return service('response')->setJSON([
                    'success' => false,
                    'message' => 'Debe iniciar sesión',
                    'redirect' => base_url('auth/login')
                ])->setStatusCode(401);
            }
            
            return redirect()->to('/auth/login');
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // No action needed
    }
}
