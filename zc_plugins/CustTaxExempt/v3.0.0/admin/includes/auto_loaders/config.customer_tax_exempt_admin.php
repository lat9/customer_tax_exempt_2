<?php
// -----
// Part of the "Customer Tax-Exempt, v2" plugin by lat9
// Copyright (c) 2019, Vinos de Frutas Tropicales
//
$autoLoadConfig[200][] = array(
  'autoType' => 'init_script',
  'loadFile' => 'init_customer_tax_exempt_admin.php'
);

$autoLoadConfig[200][] = array(
    'autoType' => 'class',
    'loadFile' => 'observers/CustomerTaxExemptAdminObserver.php',
    'classPath' => DIR_WS_CLASSES
);
$autoLoadConfig[200][] = array(
    'autoType' => 'classInstantiate',
    'className' => 'CustomerTaxExemptAdminObserver',
    'objectName' => 'cte'
);
