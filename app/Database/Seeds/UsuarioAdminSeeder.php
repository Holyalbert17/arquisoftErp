<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class UsuarioAdminSeeder extends Seeder
{
    public function run()
    {
        $db = \Config\Database::connect();
        
        // Verificar si ya existe un usuario admin
        $existe = $db->table('usuarios')
                    ->where('username', 'admin')
                    ->countAllResults();
        
        if ($existe > 0) {
            echo "El usuario admin ya existe.\n";
            return;
        }
        
        // Obtener la primera empresa o crear una de ejemplo
        $empresa = $db->table('empresas')->get()->getRowArray();
        
        if (!$empresa) {
            // Crear empresa de ejemplo
            $empresaData = [
                'nombre' => 'Empresa Demo',
                'ruc' => '80012345-1',
                'direccion' => 'Av. República Argentina 123',
                'ciudad' => 'Asunción',
                'telefono' => '(021) 123-456',
                'email' => 'admin@empresa.com.py',
                'porcentaje_iva' => 10.00,
                'timbrado' => '1234567',
                'establecimiento' => '001',
                'punto_expedicion' => '001',
                'moneda_principal' => 'PYG',
                'estado' => true,
                'fecha_creacion' => date('Y-m-d H:i:s')
            ];
            
            $db->table('empresas')->insert($empresaData);
            $empresaId = $db->insertID();
            
            // Crear sucursal matriz
            $sucursalData = [
                'empresa_id' => $empresaId,
                'nombre' => 'Casa Central',
                'direccion' => 'Av. República Argentina 123',
                'ciudad' => 'Asunción',
                'departamento' => 'CAPITAL',
                'telefono' => '(021) 123-456',
                'es_matriz' => true,
                'codigo_establecimiento' => '001',
                'estado' => true,
                'fecha_creacion' => date('Y-m-d H:i:s')
            ];
            
            $db->table('sucursales')->insert($sucursalData);
            $sucursalId = $db->insertID();
        } else {
            $empresaId = $empresa['id'];
            
            // Obtener sucursal matriz
            $sucursal = $db->table('sucursales')
                          ->where('empresa_id', $empresaId)
                          ->where('es_matriz', true)
                          ->get()
                          ->getRowArray();
            
            if (!$sucursal) {
                // Crear sucursal matriz
                $sucursalData = [
                    'empresa_id' => $empresaId,
                    'nombre' => 'Casa Central',
                    'direccion' => $empresa['direccion'] ?? 'Sin dirección',
                    'ciudad' => $empresa['ciudad'] ?? 'Asunción',
                    'departamento' => 'CAPITAL',
                    'es_matriz' => true,
                    'codigo_establecimiento' => '001',
                    'estado' => true,
                    'fecha_creacion' => date('Y-m-d H:i:s')
                ];
                
                $db->table('sucursales')->insert($sucursalData);
                $sucursalId = $db->insertID();
            } else {
                $sucursalId = $sucursal['id'];
            }
        }
        
        // Verificar que existe el rol Administrador
        $rol = $db->table('roles')
                  ->where('nivel', 1)
                  ->get()
                  ->getRowArray();
        
        if (!$rol) {
            // Crear rol Administrador
            $rolData = [
                'nombre' => 'Administrador',
                'descripcion' => 'Acceso completo al sistema, gestiona todas las empresas',
                'nivel' => 1,
                'es_rol_sistema' => true,
                'estado' => true,
                'fecha_creacion' => date('Y-m-d H:i:s')
            ];
            
            $db->table('roles')->insert($rolData);
            $rolId = $db->insertID();
        } else {
            $rolId = $rol['id'];
        }
        
        // Crear usuario admin
        $password = password_hash('Admin123', PASSWORD_BCRYPT);
        
        $usuarioData = [
            'empresa_id' => $empresaId,
            'sucursal_id' => $sucursalId,
            'rol_id' => $rolId,
            'nombre' => 'Administrador',
            'apellido' => 'Sistema',
            'ci' => '1234567',
            'email' => 'admin@sistema.com',
            'telefono' => '(0981) 123-456',
            'username' => 'admin',
            'password' => $password,
            'estado' => true,
            'fecha_creacion' => date('Y-m-d H:i:s')
        ];
        
        $db->table('usuarios')->insert($usuarioData);
        
        echo "Usuario admin creado exitosamente!\n";
        echo "Usuario: admin\n";
        echo "Contraseña: Admin123\n";
        echo "Empresa ID: {$empresaId}\n";
        echo "Sucursal ID: {$sucursalId}\n";
    }
}
