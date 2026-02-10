<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Business extends BaseConfig
{
    public $taxRate10 = 10;
    public $taxRate5 = 5;

    public $currencyCode = 'PYG';
    public $currencySymbol = 'Gs';

    // Default values
    public $defaultTaxType = 'GRAVADO_10';
}
