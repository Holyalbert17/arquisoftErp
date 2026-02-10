<?php

namespace App\Controllers;

class Debug_db extends BaseController 
{
    public function index() {
        $db = \Config\Database::connect();
        $schema = $db->query("SELECT column_name, is_nullable, data_type, column_default FROM information_schema.columns WHERE table_name = 'stock'")->getResultArray();
        
        $constraints = $db->query("
            SELECT tc.constraint_name, tc.constraint_type, kcu.column_name 
            FROM information_schema.table_constraints tc 
            JOIN information_schema.key_column_usage kcu ON tc.constraint_name = kcu.constraint_name 
            WHERE tc.table_name = 'stock'
        ")->getResultArray();

        $triggers = $db->query("
            SELECT  event_object_table AS table_name, trigger_name, action_timing, event_manipulation AS event, action_statement AS definition, pg_get_triggerdef(pg_trigger.oid) as trigger_def
            FROM information_schema.triggers
            JOIN pg_trigger ON pg_trigger.tgname = triggers.trigger_name
            WHERE event_object_table = 'productos'
        ")->getResultArray();

        $functions = $db->query("
            SELECT pg_get_functiondef(p.oid) as definition
            FROM pg_proc p
            JOIN pg_namespace n ON p.pronamespace = n.oid
            WHERE n.nspname = 'public' AND p.proname = 'registrar_auditoria'
        ")->getResultArray();

        return $this->response->setJSON(['schema' => $schema, 'constraints' => $constraints, 'triggers' => $triggers, 'functions' => $functions]);
    }
}
