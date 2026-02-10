<?php

if (!function_exists('formatGs')) {
    /**
     * Formatea un valor numérico en guaraníes (Gs.)
     * Redondea a enteros y formatea con separador de miles
     * 
     * @param mixed $valor Valor numérico a formatear
     * @return string Valor formateado con "Gs." al final
     */
    function formatGs($valor): string
    {
        if ($valor === null || $valor === '' || $valor === false) {
            return '0 Gs.';
        }

        // Si es numérico (float o int) o string numérico estándar (ej: "1250.00"), usarlo directamente
        if (is_numeric($valor)) {
            $valor = (float) $valor;
        }
        // Si es string con formato (ej: "1.250"), limpiar
        elseif (is_string($valor)) {
            $valor = trim($valor);
            // Si tiene puntos y comas, asumir formato latino/paraguayo: 1.250,00
            if (strpos($valor, '.') !== false && strpos($valor, ',') !== false) {
                $valor = str_replace('.', '', $valor);
                $valor = str_replace(',', '.', $valor);
            }
            // Si solo tiene puntos, asumir separador de miles si hay más de uno o si la parte decimal no es lógica
            elseif (strpos($valor, '.') !== false) {
                // Riesgoso: "1.250" vs "1.25" (SQL). 
                // Asumimos que si viene de DB es SQL (punto = decimal), si viene de input es Gs (punto = mil)
                // Para seguridad, limpiamos todo excepto dígitos y signo menos
                $valor = preg_replace('/[^0-9-]/', '', $valor);
            }
        }

        $valor = (float) $valor;

        if ($valor <= 0 || is_nan($valor)) {
            return '0 Gs.';
        }

        // Redondear a entero para guaraníes (no tienen decimales)
        $valor = round($valor);

        // Formatear con separador de miles (punto) y sin decimales
        return number_format($valor, 0, ',', '.') . ' Gs.';
    }
}

if (!function_exists('formatGsNumber')) {
    /**
     * Formatea un número sin el símbolo "Gs." (solo el número formateado)
     * Útil para cuando se necesita el número formateado sin la moneda
     * 
     * @param mixed $valor Valor numérico a formatear
     * @return string Número formateado sin símbolo de moneda
     */
    function formatGsNumber($valor): string
    {
        if ($valor === null || $valor === '' || $valor === false) {
            return '0';
        }

        // Si es string, limpiar y convertir
        if (is_string($valor)) {
            $valor = trim($valor);
            // Remover puntos (separadores de miles) y comas antes de convertir
            $valor = str_replace('.', '', $valor);
            $valor = str_replace(',', '.', $valor);
            $valor = preg_replace('/[^0-9.-]/', '', $valor);
        }

        $valor = (float) $valor;

        if ($valor <= 0 || is_nan($valor)) {
            return '0';
        }

        // Redondear a entero para guaraníes
        $valor = round($valor);

        // Asegurar que sea un entero válido
        if ($valor <= 0) {
            return '0';
        }

        // Formatear con separador de miles (punto) y sin decimales
        return number_format($valor, 0, ',', '.');
    }
}
