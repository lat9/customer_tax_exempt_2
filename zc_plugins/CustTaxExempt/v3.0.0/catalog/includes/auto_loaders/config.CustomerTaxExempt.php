<?php 
// -----
// Load the CustomerTaxExempt observer.  GitHub#1.
//
$autoLoadConfig[71][] = array(
    'autoType' => 'class',
    'loadFile' => 'observers/TaxExemptCustomerObserver.php'
);
$autoLoadConfig[71][] = array(
    'autoType' => 'classInstantiate',
    'className' => 'TaxExemptCustomerObserver',
    'objectName' => 'TaxExempt'
);
