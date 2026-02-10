<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateInternalChatTables extends Migration
{
    public function up()
    {
        // --------------------------------------------------------
        // TABLA: chats
        // --------------------------------------------------------
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'empresa_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => false, // Based on user schema provided (integer, NO UNSIGNED)
            ],
            'tipo' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'default'    => 'PRIVATE',
            ],
            'nombre' => [
                'type'       => 'VARCHAR',
                'constraint' => '100',
                'null'       => true,
            ],
            'foto' => [
                'type'       => 'VARCHAR',
                'constraint' => '255',
                'null'       => true,
            ],
            'creador_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => false, // Based on users.id which is integer
                'null'       => true,
            ],
            'ultimo_mensaje_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'estado' => [
                'type'    => 'BOOLEAN',
                'default' => true,
            ],
            'fecha_creacion' => [
                'type' => 'DATETIME',
                'default' => new \CodeIgniter\Database\RawSql('CURRENT_TIMESTAMP'), 
            ],
            'fecha_modificacion' => [
                'type' => 'DATETIME',
                'default' => new \CodeIgniter\Database\RawSql('CURRENT_TIMESTAMP'),
            ],
        ]);
        
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('empresa_id', 'empresas', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('creador_id', 'usuarios', 'id', 'SET NULL', 'SET NULL');
        
        $this->forge->createTable('chats');

        // --------------------------------------------------------
        // TABLA: chat_participantes
        // --------------------------------------------------------
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'chat_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'usuario_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => false,
            ],
            'es_admin' => [
                'type'    => 'BOOLEAN',
                'default' => false,
            ],
            'unread_count' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 0,
            ],
            'fecha_unido' => [
                'type' => 'DATETIME',
                'default' => new \CodeIgniter\Database\RawSql('CURRENT_TIMESTAMP'),
            ],
            'activo' => [
                'type'    => 'BOOLEAN',
                'default' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('chat_id', 'chats', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('usuario_id', 'usuarios', 'id', 'CASCADE', 'CASCADE');
        // Unique constraint: A user can only be in a chat once
        $this->forge->addUniqueKey(['chat_id', 'usuario_id']);
        
        $this->forge->createTable('chat_participantes');

        // --------------------------------------------------------
        // TABLA: mensajes
        // --------------------------------------------------------
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'chat_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'usuario_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => false,
            ],
            'mensaje' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'tipo' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'default'    => 'TEXT',
            ],
            'adjunto_url' => [
                'type'       => 'VARCHAR',
                'constraint' => '500',
                'null'       => true,
            ],
            'leido_por_todos' => [
                'type'    => 'BOOLEAN',
                'default' => false,
            ],
            'fecha_envio' => [
                'type' => 'DATETIME',
                'default' => new \CodeIgniter\Database\RawSql('CURRENT_TIMESTAMP'),
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('chat_id', 'chats', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('usuario_id', 'usuarios', 'id', 'SET NULL', 'SET NULL');
        
        // IMPORTANT: Ensure utf8mb4 for emojis if MySQL
        $attributes = [
            'ENGINE' => 'InnoDB',
        ];
        
        // Only verify utf8mb4 on MySQL, but since we use CodeIgniter database abstraction, 
        // we generally rely on database config. However, for MySQL it's critical.
        // Assuming PostgreSQL based on previous context ("public"."chats" schema dump looks PostgreSQL-ish with "public"),
        // PostgreSQL handles utf8 natively usually.
        
        $this->forge->createTable('mensajes', true, $attributes);
        
        // Add index for performance on fetching messages
        // $this->db->query('CREATE INDEX idx_mensajes_chat_fecha ON mensajes(chat_id, fecha_envio)');
    }

    public function down()
    {
        $this->forge->dropTable('mensajes');
        $this->forge->dropTable('chat_participantes');
        $this->forge->dropTable('chats');
    }
}
