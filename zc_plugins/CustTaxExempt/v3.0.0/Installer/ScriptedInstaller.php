<?php
use Zencart\PluginSupport\ScriptedInstaller as ScriptedInstallBase;

class ScriptedInstaller extends ScriptedInstallBase
{
    protected function executeInstall()
    {
        if ($this->purgeOldFiles() === false) {
            return false;
        }

        // -----
        // Remove the hidden version number set by a previous unencapsulated
        // version of the plugin.
        //
        $this->executeInstallerSql(
            "DELETE FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = 'CUSTOMER_TAX_EXEMPT_VERSION' LIMIT 1"
        );

        // -----
        // Check for the presence of the customers::customers_tax_exempt field in the database.
        //
        // If the field doesn't exist, add it as a TEXT field.  
        //
        // Otherwise, check to see whether the field is *not* a TEXT field.  v1 of the plugin
        // defined the field as a varchar(32) one, which is inadequate to hold multiple tax-exemptions.
        //
        global $sniffer;
        if (!$sniffer->field_exists(TABLE_CUSTOMERS, 'customers_tax_exempt')) {
            $this->executeInstallerSql(
                "ALTER TABLE " . TABLE_CUSTOMERS . " ADD customers_tax_exempt TEXT"
            );
        } elseif (!$sniffer->field_type(TABLE_CUSTOMERS, 'customers_tax_exempt', 'text')) {
            $this->executeInstallerSql((
                "ALTER TABLE " . TABLE_CUSTOMERS . " MODIFY customers_tax_exempt TEXT"
            );
        }

        parent::executeInstall();

        return true;
    }

    protected function executeUpgrade($oldVersion)
    {
        parent::executeUpgrade($oldVersion);
    }

    protected function executeUninstall()
    {
        parent::executeUninstall();
    }

    protected function purgeOldFiles(): bool
    {
        // -----
        // First, look for and remove the non-encapsulated versions' admin-directory
        // file.
        //
        $files_to_check = [
            'includes/auto_loaders/' => [
                'config.customer_tax_exempt_admin.php',
            ],
            'includes/classes/observers/' => [
                'CustomerTaxExemptAdminObserver.php',
            ],
            'includes/init_includes' => [
                'init_customer_tax_exempt_admin.php',
            ],
            'includes/languages/english/extra_definitions/' => [
                'customer_tax_exempt.php',
            ],
        ];

        $errorOccurred = false;
        foreach ($files_to_check as $dir => $files) {
            $current_dir = DIR_FS_ADMIN . $dir;
            foreach ($files as $next_file) {
                $current_file = $current_dir . $next_file;
                if (file_exists($current_file)) {
                    unlink($current_file);
                    if (file_exists($current_file)) {
                        $errorOccurred = true;
                        $this->errorContainer->addError(
                            0,
                            sprintf(ERROR_UNABLE_TO_DELETE_FILE, $current_file),
                            false,
                            // this str_replace has to do DIR_FS_ADMIN before CATALOG because catalog is contained within admin, so results are wrong.
                            // also, '[admin_directory]' is used to obfuscate the admin dir name, in case the user copy/pastes output to a public forum for help.
                            sprintf(ERROR_UNABLE_TO_DELETE_FILE, str_replace([DIR_FS_ADMIN, DIR_FS_CATALOG], ['[admin_directory]/', ''], $current_file))
                        );
                    }
                }
            }
        }

        // -----
        // Next, locate and attempt to remove the storefront files.
        //
        $files_to_check = [
            'includes/auto_loaders/' => [
                'config.CustomerTaxExempt.php',
            ],
            'includes/classes/observers/' => [
                'TaxExemptCustomerObserver.php',
            ],
        ];
        foreach ($files_to_check as $dir => $files) {
            $current_dir = DIR_FS_CATALOG . $dir;
            foreach ($files as $next_file) {
                $current_file = $current_dir . $next_file;
                if (file_exists($current_file)) {
                    unlink($current_file);
                    if (file_exists($current_file)) {
                        $errorOccurred = true;
                        $this->errorContainer->addError(
                            0,
                            sprintf(ERROR_UNABLE_TO_DELETE_FILE, $current_file),
                            false,
                            // this str_replace has to do DIR_FS_ADMIN before CATALOG because catalog is contained within admin, so results are wrong.
                            // also, '[admin_directory]' is used to obfuscate the admin dir name, in case the user copy/pastes output to a public forum for help.
                            sprintf(ERROR_UNABLE_TO_DELETE_FILE, str_replace([DIR_FS_ADMIN, DIR_FS_CATALOG], ['[admin_directory]/', ''], $current_file))
                        );
                    }
                }
            }
        }

        return !$errorOccurred;
    }
}
