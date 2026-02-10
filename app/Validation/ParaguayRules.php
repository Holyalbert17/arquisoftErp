<?php

namespace App\Validation;

class ParaguayRules
{
    /**
     * Valida un RUC paraguayo
     * 
     * @param string $str RUC a validar (puede incluir dígito verificador separado por guion)
     * @param string $error Variable para retornar mensaje de error (opcional)
     * @return bool
     */
    public function valid_ruc(string $str, ?string &$error = null): bool
    {
        $str = trim($str);

        // Formato básico: 1234567-1 o 1234567
        if (!preg_match('/^\d{3,9}(-\d)?$/', $str)) {
            $error = 'Formato de RUC inválido. Debe ser 1234567-1 o 1234567';
            return false;
        }

        // Si tiene guion, verificar el dígito verificador
        if (strpos($str, '-') !== false) {
            $parts = explode('-', $str);
            $rucBase = $parts[0];
            $dvIngresado = $parts[1];

            $dvCalculado = $this->calcularDigitoVerificador($rucBase);

            if ($dvIngresado != $dvCalculado) {
                $error = "Dígito verificador inválido. Se esperaba {$dvCalculado} para el RUC {$rucBase}";
                return false;
            }
        }

        return true;
    }

    /**
     * Calcula el Dígito Verificador (DV) del RUC usando Módulo 11
     * 
     * @param string|int $numero RUC sin DV
     * @return int Dígito verificador
     */
    private function calcularDigitoVerificador($numero)
    {
        $basemax = 11;
        $numero = (string) $numero;
        $total = 0;
        $k = 2;

        for ($i = strlen($numero) - 1; $i >= 0; $i--) {
            $k = ($k > $basemax) ? 2 : $k;
            $n = $numero[$i];

            // Si hay caracteres no numéricos, usar su valor ASCII
            if (!is_numeric($n)) {
                $n = ord(strtoupper($n));
            }

            $total += $n * $k;
            $k++;
        }

        $resto = $total % 11;
        $dv = ($resto > 1) ? 11 - $resto : 0;

        return $dv;
    }
}
