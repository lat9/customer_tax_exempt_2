<?php
// -----
// Part of the "Customer Tax-Exempt, v2" plugin by lat9
// Copyright (c) 2019-2024, Vinos de Frutas Tropicales
//
// Last updated: v2.0.2
//
if (!defined('IS_ADMIN_FLAG') || IS_ADMIN_FLAG !== true) {
    die('Illegal Access');
}

class CustomerTaxExemptAdminObserver extends base
{
    public function __construct() 
    {
        $this->attach(
            $this,
            [
                /* From admin/customers.php */
                'ADMIN_CUSTOMER_UPDATE',
                'NOTIFY_ADMIN_CUSTOMERS_CUSTOMER_EDIT',
            ]
        );
    }

    public function update(&$class, $eventID, $p1, &$p2, &$p3, &$p4, &$p5, &$p6)
    {
        switch ($eventID) {
            // -----
            // Issued by admin/customers.php on an 'update' action, just prior to the redirect.  Gives
            // us the opportunity to update the customer's tax-exempt status, too.
            //
            // On entry:
            //
            // $p1 ... (r/o) The customers_id being updated.
            //
            case 'ADMIN_CUSTOMER_UPDATE':
                $sql_data_array = [
                    'customers_tax_exempt' => zen_db_prepare_input(trim($_POST['customers_tax_exempt'])),
                ];
                $customers_id = (int)$p1;
                zen_db_perform(TABLE_CUSTOMERS, $sql_data_array, 'update', "customers_id = $customers_id");
                break;

            // -----
            // Issued by admin/customers.php when creating the form-entry for the customer's update.  We'll
            // add the form-input for the customer's tax-exempt status.
            //
            // On entry:
            //
            // $p1 ... (r/o) A copy of the $cInfo object that contains the customer's current settings.
            // $p2 ... (r/w) An array (empty initially) that is updated with the additional tax-exempt field.
            //
            case 'NOTIFY_ADMIN_CUSTOMERS_CUSTOMER_EDIT':
                global $error, $db;

                if (!empty($error)) {
                    $p2[] = [
                        'label' => ENTRY_TAX_EXEMPT,
                        'fieldname' => 'customers_tax_exempt',
                        'input' => $p1->customers_tax_exempt . zen_draw_hidden_field('customers_tax_exempt', $p1->customers_tax_exempt)
                    ];
                } else {
                    $current_value = $db->Execute(
                        "SELECT customers_tax_exempt
                           FROM " . TABLE_CUSTOMERS . "
                          WHERE customers_id = {$p1->customers_id}
                          LIMIT 1"
                    );
                    $customers_tax_exempt = ($current_value->EOF) ? '' : $current_value->fields['customers_tax_exempt'];
                    $field_value = htmlspecialchars((string)$customers_tax_exempt, ENT_COMPAT, CHARSET, true);
                    $input_field = zen_draw_textarea_field('customers_tax_exempt', 'soft', '100%', '3', $field_value, 'class="noEditor form-control"');

                    $tax_descriptions = $db->Execute("SELECT tax_description FROM " . TABLE_TAX_RATES);
                    $examples = [];
                    foreach ($tax_descriptions as $description) {
                        $examples[] = $description['tax_description'];
                    }
                    $examples = implode(', ', $examples);
                    $example = '<br><span class="help-block">' . NOTES_TAX_EXEMPT . '<br><br>' . $examples . '</span>';

                    $p2[] = [
                        'label' => ENTRY_TAX_EXEMPT,
                        'fieldname' => 'customers_tax_exempt',
                        'input' => $input_field . $example
                    ];
                }
                break;

            default:
                break;
        }
    }
}
