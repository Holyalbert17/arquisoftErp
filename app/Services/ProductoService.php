<?php

namespace App\Services;

use App\Models\ProductoModel;
use App\Models\StockModel;
use App\Models\AuditoriaModel;
use CodeIgniter\Config\Services;
use Config\Database;

class ProductoService
{
    protected $productoModel;
    protected $stockModel;
    protected $auditoriaModel;
    protected $db;

    public function __construct()
    {
        $this->db = Database::connect();
        $this->productoModel = new ProductoModel($this->db);
        $this->stockModel = new StockModel($this->db);
        $this->auditoriaModel = new AuditoriaModel($this->db);
    }

    /**
     * Obtener todos los productos de una empresa
     */
    public function getAll($empresaId, $filtros = [])
    {
        $builder = $this->productoModel->builder();
        $builder->select('productos.*');
        $builder->select('(SELECT COALESCE(SUM(cantidad), 0) FROM stock WHERE stock.producto_id = productos.id) as stock');
        
        $builder->where('empresa_id', $empresaId);
        $builder->where('estado', true);
        
        return $builder->get()->getResultArray();
    }

    /**
     * Obtener un producto por ID y verificar empresa
     */
    public function getById($id, $empresaId)
    {
        $producto = $this->productoModel->find($id);

        if (!$producto || $producto['empresa_id'] != $empresaId) {
            return null;
        }
        return $producto;
    }

    /**
     * Crear un nuevo producto
     */
    public function create(array $data, $file = null, $usuario)
    {
        $this->db->transStart();

        try {
            // Manejo de imagen
            if ($file) {
                $uploadResult = $this->uploadImage($file);
                if ($uploadResult['success']) {
                    $data['imagen_principal'] = $uploadResult['filename'];
                } else {
                    throw new \Exception($uploadResult['message']);
                }
            }

            // Insertar Producto
            if (!$this->productoModel->insert($data)) {
                $errors = $this->productoModel->errors();
                $dbError = $this->db->error(); // Capture DB error like Unique Constraint
                $msg = 'Error al insertar el producto';
                if (!empty($errors)) {
                    $msg .= ': ' . implode(', ', $errors);
                } elseif (!empty($dbError['message'])) {
                    $msg .= ': ' . $dbError['message'];
                }
                throw new \Exception($msg);
            }

            $productoId = $this->productoModel->getInsertID();
            
            // Validate that we got a valid ID
            if (!$productoId) {
                throw new \Exception('No se pudo obtener el ID del producto insertado');
            }
            
            // Paranoid check: Verify product exists in current transaction
            // Direct query to bypass model cache or other layers
            $check = $this->db->query("SELECT id FROM productos WHERE id = ?", [$productoId])->getRow();
            if (!$check) {
                throw new \Exception("El producto ID $productoId no se encuentra en la base de datos después de la inserción.");
            }

            // Crear registro base de stock para que el producto aparezca en el módulo Stock
            if (!empty($data['controla_stock']) && !empty($data['sucursal_id'])) {
                $sucursalId = (int) $data['sucursal_id'];
                $stockInicial = (int) round((float) ($data['stock_inicial'] ?? 0));

                // Evitar duplicados: usar el registro "base" (sin lote)
                $stockExistente = $this->stockModel->where('producto_id', $productoId)
                                                 ->where('sucursal_id', $sucursalId)
                                                 ->where('lote', null)
                                                 ->first();

                if (!$stockExistente) {
                    $this->stockModel->insert([
                        'producto_id' => $productoId,
                        'sucursal_id' => $sucursalId,
                        'cantidad' => $stockInicial,
                        'cantidad_reservada' => 0,
                        'cantidad_disponible' => $stockInicial,
                        'ubicacion' => null,
                        'lote' => null,
                        'fecha_vencimiento' => null,
                        'costo_promedio' => isset($data['precio_compra']) ? (float) $data['precio_compra'] : 0,
                        'fecha_actualizacion' => date('Y-m-d H:i:s'),
                    ]);
                }
            }

            // Auditoría
            $this->registrarAuditoria($usuario['id'], $data['empresa_id'], 'INSERT', $productoId, null, $data);

            $this->db->transComplete();

            if ($this->db->transStatus() === false) {
                $dbError = $this->db->error();
                throw new \Exception('Error al completar la transacción: ' . $dbError['message']);
            }

            return ['success' => true, 'id' => $productoId, 'message' => 'Producto creado exitosamente'];

        } catch (\Exception $e) {
            $this->db->transRollback();
            log_message('error', 'ProductoService::create failed: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Actualizar un producto existente
     */
    public function update($id, array $data, $file = null, $usuario)
    {
        // Obtener datos actuales para auditoría e imagen anterior
        $productoActual = $this->getById($id, $data['empresa_id']);
        if (!$productoActual) {
            return ['success' => false, 'message' => 'Producto no encontrado'];
        }

        $this->db->transStart();

        try {
            // Manejo de imagen
            if ($file && $file->isValid() && !$file->hasMoved()) {
                $uploadResult = $this->uploadImage($file);
                if ($uploadResult['success']) {
                    $data['imagen_principal'] = $uploadResult['filename'];

                    // Eliminar imagen anterior
                    $this->deleteImageFile($productoActual['imagen_principal']);
                } else {
                    throw new \Exception($uploadResult['message']);
                }
            }

            // Actualizar
            if (!$this->productoModel->update($id, $data)) {
                $errors = $this->productoModel->errors();
                $dbError = $this->db->error();
                $errorMsg = 'Error al actualizar';

                if (!empty($errors)) {
                    $errorMsg .= ': ' . implode(', ', $errors);
                } elseif (!empty($dbError['message'])) {
                    $errorMsg .= ': ' . $dbError['message'];
                }

                throw new \Exception($errorMsg);
            }

            // Auditoría
            $this->registrarAuditoria($usuario['id'], $data['empresa_id'], 'UPDATE', $id, $productoActual, $data);

            $this->db->transComplete();

            if ($this->db->transStatus() === false) {
                throw new \Exception('Error de transacción en actualización');
            }

            return ['success' => true, 'message' => 'Producto actualizado exitosamente'];

        } catch (\Exception $e) {
            $this->db->transRollback();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Desactivar (Soft Delete) un producto
     */
    public function delete($id, $empresaId, $usuario)
    {
        $producto = $this->getById($id, $empresaId);
        if (!$producto) {
            return ['success' => false, 'message' => 'Producto no encontrado'];
        }

        $this->db->transStart();

        try {
            if (!$this->productoModel->update($id, ['estado' => false])) {
                throw new \Exception('Error al desactivar el producto');
            }

            $this->registrarAuditoria($usuario['id'], $empresaId, 'DELETE', $id, $producto, ['estado' => false]);

            $this->db->transComplete();

            return ['success' => true, 'message' => 'Producto desactivado correctamente'];

        } catch (\Exception $e) {
            $this->db->transRollback();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Subir imagen al servidor
     */
    protected function uploadImage($file)
    {
        try {
            $uploadPath = ROOTPATH . 'public/uploads/productos';

            if (!is_dir($uploadPath)) {
                mkdir($uploadPath, 0755, true);
            }

            $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
            if (!in_array($file->getMimeType(), $allowedTypes)) {
                return ['success' => false, 'message' => 'Tipo de archivo no permitido'];
            }

            if ($file->getSize() > 2097152) { // 2MB
                return ['success' => false, 'message' => 'La imagen excede 2MB'];
            }

            $newName = $file->getRandomName();
            if ($file->move($uploadPath, $newName)) {
                return ['success' => true, 'filename' => $newName];
            }

            return ['success' => false, 'message' => 'Fallo al mover el archivo subido'];

        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    protected function deleteImageFile($filename)
    {
        if ($filename) {
            $path = ROOTPATH . 'public/uploads/productos/' . $filename;
            if (file_exists($path)) {
                @unlink($path);
            }
        }
    }

    protected function registrarAuditoria($usuarioId, $empresaId, $accion, $registroId, $anterior, $nuevo)
    {
        try {
            $this->auditoriaModel->insert([
                'usuario_id' => $usuarioId,
                'empresa_id' => $empresaId,
                'tabla_afectada' => 'productos',
                'accion' => $accion,
                'registro_id' => $registroId,
                'datos_anteriores' => $anterior ? json_encode($anterior) : null,
                'datos_nuevos' => $nuevo ? json_encode($nuevo) : null,
                'ip_address' => service('request')->getIPAddress(),
                'fecha_creacion' => date('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            log_message('error', 'Fallo al registrar auditoria producto: ' . $e->getMessage());
        }
    }
}
