<?php
// -----
// A simple observer-class that monitors customer-login actions and sets a session
// variable to indicate whether/not the customer qualifies for a tax-exemption.
//
// Last updated: v2.0.2
//
class TaxExemptCustomerObserver extends base
{
    protected
        $exemptions_list = '',
        $exemptions_all = false;

    // -----
    // Class constructor.
    //
    public function __construct()
    {
        // -----
        // If a non-guest customer is currently logged-in, gather any tax-exemptions that they
        // might have, providing tax-related overrides **only if** the customer has
        // some form of tax-exemption.
        //
        if (zen_is_logged_in() && !zen_in_guest_checkout()) {
            if ($this->initializeTaxExemptions() !== false) {
                $this->attach(
                    $this,
                    [
                        //- From /includes/functions/functions_taxes.php
                        'NOTIFY_ZEN_GET_TAX_RATE_OVERRIDE',
                        'NOTIFY_ZEN_GET_TAX_DESCRIPTION_OVERRIDE',
                        'NOTIFY_ZEN_GET_MULTIPLE_TAX_RATES_OVERRIDE',
                    ]
                );
            }
        }
    }

    // -----
    // This function is invoked when one of the attached notifiers "fires" and acts as a router to provide
    // the required functionality.
    //
    // Note: The notifications are attached **only if** the customer has one or more tax-exemptions!
    //
    public function update(&$class, $eventID, $p1, &$p2, &$p3, &$p4, &$p5)
    {
        switch ($eventID) {
            // -----
            // Issued by zen_get_tax_rate, on entry:
            //
            // $p1 ... (r/o) An array containing:
            //         - class_id ..... the tax_class_id
            //         - country_id ... the country_id for the tax-rate (might be -1).
            //         - zone_id ...... the zone_id for the tax-rate (might be -1).
            // $p2 ... (r/w) A reference to the to-be-used tax-rate, overridden by this processing
            //         if the customer is tax-exempt.
            //
            case 'NOTIFY_ZEN_GET_TAX_RATE_OVERRIDE':
                // -----
                // If the customer is exempt from *all* taxes, set the tax-rate override to 0 and
                // perform a quick-return.
                //
                if ($this->exemptions_all === true) {
                    $p2 = 0;
                    return;
                }

                // -----
                // Otherwise, the customer is exempt from *some* taxes.  See whether the current tax-class
                // requested is one of them.  The value returned by getCustomersTaxRates is a database-object
                // which, if it contains data, identifies the tax(es) for which the partially-exempt customer
                // is non-exempt!
                //
                $tax_rates = $this->getCustomersTaxRatesSummed($p1['class_id'], $p1['country_id'], $p1['zone_id']);
                if ($tax_rates->EOF) {
                    $p2 = 0;
                    return;
                }

                // -----
                // If we got here, the customer is *not* exempt from some taxes.  Sum up the associated rates and
                // set the override.
                //
                $tax_multiplier = 1;
                foreach ($tax_rates as $rate) {
                    $tax_multiplier *= 1 + $rate['tax_rate_summed'] / 100;
                }
                $p2 = ($tax_multiplier - 1) / 100;
                break;

            // -----
            // Issued by zen_get_tax_description, on entry:
            //
            // $p1 ... (r/o) An array containing:
            //         - class_id ..... the tax_class_id
            //         - country_id ... the country_id for the tax-rate (might be -1).
            //         - zone_id ...... the zone_id for the tax-rate (might be -1).
            // $p2 ... (r/w) A reference to the to-be-used tax-description, overridden by this processing
            //         if the customer is tax-exempt.
            //
            case 'NOTIFY_ZEN_GET_TAX_DESCRIPTION_OVERRIDE':
                // -----
                // If the customer is exempt from *all* taxes, set the tax-description override to the 'unknown'
                // string perform a quick-return.
                //
                if ($this->exemptions_all === true) {
                    $p2 = TEXT_UNKNOWN_TAX_RATE;
                    return;
                }

                // -----
                // Otherwise, the customer is exempt from *some* taxes.  See whether the current tax-class
                // requested is one of them.  The value returned by getCustomersTaxRates is a database-object
                // which, if it contains data, identifies the tax(es) for which the partially-exempt customer
                // is non-exempt!
                //
                $tax_rates = $this->getCustomersTaxRates($p1['class_id'], $p1['country_id'], $p1['zone_id']);
                if ($tax_rates->EOF) {
                    $p2 = TEXT_UNKNOWN_TAX_RATE;
                    return;
                }

                // -----
                // If we got here, the customer is *not* exempt from some taxes.  Concatenate the
                // tax-descriptions for which the customer is responsible.
                //
                $tax_descriptions = [];
                foreach ($tax_rates as $rate) {
                    $tax_descriptions[] = $rate['tax_description'];
                }
                $p2 = implode(' + ', $tax_descriptions);
                break;

            // -----
            // Issued by zen_get_multiple_tax_rates, on entry:
            //
            // $p1 ... (r/o) An array containing:
            //         - class_id ..... the tax_class_id
            //         - country_id ... the country_id for the tax-rate (might be -1).
            //         - zone_id ...... the zone_id for the tax-rate (might be -1).
            // $p2 ... (r/w) A reference to the to-be-used tax-description/rates array, overridden by this processing
            //         if the customer is tax-exempt.
            //
            case 'NOTIFY_ZEN_GET_MULTIPLE_TAX_RATES_OVERRIDE':
                // -----
                // If the customer is exempt from *all* taxes, set the tax-description override to the 'unknown'
                // string perform a quick-return.
                //
                if ($this->exemptions_all === true) {
                    $p2 = [TEXT_UNKNOWN_TAX_RATE => 0];
                    return;
                }

                // -----
                // Otherwise, the customer is exempt from *some* taxes.  See whether the current tax-class
                // requested is one of them.  The value returned by getCustomersTaxRates is a database-object
                // which, if it contains data, identifies the tax(es) for which the partially-exempt customer
                // is non-exempt!
                //
                $tax_rates = $this->getCustomersTaxRates($p1['class_id'], $p1['country_id'], $p1['zone_id']);
                if ($tax_rates->EOF) {
                    $p2 = [TEXT_UNKNOWN_TAX_RATE => 0];
                    return;
                }

                // -----
                // If we got here, the customer is *not* exempt from some taxes.  Create (and return) an
                // array that maps each 'active' tax-description to its associated rate.
                //
                $rates_array = [];
                $tax_aggregate_rate = 1;
                $tax_rate_factor = 1;
                $tax_prior_rate = 1;
                $tax_priority = 0;
                foreach ($tax_rates as $rate) {
                    $current_tax_rate = 1 + $rate['tax_rate'] / 100;
                    if ($rate['tax_priority'] <= $tax_priority) {
                        $tax_rate_factor = $tax_prior_rate * $current_tax_rate;
                    } else {
                        $tax_priority = $rate['tax_priority'];
                        $tax_prior_rate = $tax_aggregate_rate;
                        $tax_rate_factor = $current_tax_rate * $tax_aggregate_rate;
                        $tax_aggregate_rate = 1;
                    }
                    $rates_array[$rate['tax_description']] = 100 * ($tax_rate_factor - $tax_prior_rate);
                    $tax_aggregate_rate += $tax_rate_factor - 1;
                }
                $p2 = $rates_array;
                break;

            default:
                break;
        }
    }

    // -----
    // Called on each page-load by the class constructor if a customer is currently logged in.
    //
    // Check to see whether the customer has any exemptions, saving them in a class variable for
    // use in any tax calculations for the customer.  If the customer has no exemptions, the associated
    // tax-calculation notifications won't be attached!
    //
    protected function initializeTaxExemptions()
    {
        global $db;

        $exemption_status = false;
        $check = $db->Execute(
            "SELECT customers_tax_exempt
               FROM " . TABLE_CUSTOMERS . "
              WHERE customers_id = " . (int)$_SESSION['customer_id'] . "
              LIMIT 1"
        );
        if (!$check->EOF && !empty($check->fields['customers_tax_exempt'])) {
            if (strtoupper(trim($check->fields['customers_tax_exempt'])) === 'ALL') {
                $exemption_status = 'ALL';
                $this->exemptions_all = true;
            } else {
                $exemption_status = true;
                $customers_exemptions = explode(',', $check->fields['customers_tax_exempt']);
                $exemptions = [];
                foreach ($customers_exemptions as $next_exemption) {
                    $exemptions[] = addslashes(trim($next_exemption));
                }
                $this->exemptions_list = "'" . implode("', '", $exemptions) . "'";
            }
        }
        return $exemption_status;
    }

    protected function getCustomersTaxRatesSummed($tax_class_id, $country_id, $zone_id)
    {
        global $db;

        $tax_class_id = (int)$tax_class_id;
        $country_id = (int)$country_id;
        $zone_id = (int)$zone_id;
        if ($country_id === -1 && $zone_id === -1) {
            $country_id = (int)$_SESSION['customer_country_id'];
            $zone_id = (int)$_SESSION['customer_zone_id'];
        }

        $tax_info = $db->Execute(
            "SELECT SUM(tax_rate) AS tax_rate_summed, tax_priority
              FROM " . TABLE_TAX_RATES . " tr
                    LEFT JOIN " . TABLE_ZONES_TO_GEO_ZONES . " za
                        ON tr.tax_zone_id = za.geo_zone_id
                    LEFT JOIN " . TABLE_GEO_ZONES . " tz 
                        ON tz.geo_zone_id = tr.tax_zone_id
              WHERE tr.tax_class_id = $tax_class_id
                AND tr.tax_description NOT IN ({$this->exemptions_list})
                AND (za.zone_country_id IS NULL OR za.zone_country_id = 0 OR za.zone_country_id = $country_id)
                AND (za.zone_id IS NULL OR za.zone_id = 0 OR za.zone_id = $zone_id)
              GROUP BY tr.tax_priority
              ORDER BY tr.tax_priority"
        );
        return $tax_info;
    }

    protected function getCustomersTaxRates($tax_class_id, $country_id, $zone_id)
    {
        global $db;

        $tax_class_id = (int)$tax_class_id;
        $country_id = (int)$country_id;
        $zone_id = (int)$zone_id;
        if ($country_id === -1 && $zone_id === -1) {
            $country_id = (int)$_SESSION['customer_country_id'];
            $zone_id = (int)$_SESSION['customer_zone_id'];
        }

        $tax_info = $db->Execute(
            "SELECT tax_rate, tax_description, tax_priority
              FROM " . TABLE_TAX_RATES . " tr
                    LEFT JOIN " . TABLE_ZONES_TO_GEO_ZONES . " za
                        ON tr.tax_zone_id = za.geo_zone_id
                    LEFT JOIN " . TABLE_GEO_ZONES . " tz 
                        ON tz.geo_zone_id = tr.tax_zone_id
              WHERE tr.tax_class_id = $tax_class_id
                AND tr.tax_description NOT IN ({$this->exemptions_list})
                AND (za.zone_country_id IS NULL OR za.zone_country_id = 0 OR za.zone_country_id = $country_id)
                AND (za.zone_id IS NULL OR za.zone_id = 0 OR za.zone_id = $zone_id)
              ORDER BY tr.tax_priority ASC"
        );
        return $tax_info;
    }
}
