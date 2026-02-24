<?php
/**
 * ═══════════════════════════════════════════════════════════════════════
 * ERP_Factory - ERP Systems Factory
 * ═══════════════════════════════════════════════════════════════════════
 * 
 * Factory class to create and manage ERP system instances
 * 
 * @version 2.1
 * @author Simaat Development Team
 * @date 2026-02-11
 */

// Debug flag – set to true only when troubleshooting
if (!defined('ERP_DEBUG')) {
    define('ERP_DEBUG', false);
}

class ERP_Factory
{
    // Supported ERP systems definition
    const SYSTEMS = [
        'odoo'     => ['name' => 'Odoo ERP',                  'name_ar' => 'أودو',                'class' => 'ERP_Odoo',     'file' => 'ERP_Odoo.php'],
        'sap'      => ['name' => 'SAP Business One',          'name_ar' => 'ساب',                 'class' => 'ERP_SAP',      'file' => 'ERP_SAP.php'],
        'dynamics' => ['name' => 'Microsoft Dynamics 365',     'name_ar' => 'مايكروسوفت دايناميك', 'class' => 'ERP_Dynamics', 'file' => 'ERP_Dynamics.php'],
    ];
    
    private static $active_system = null;
    private static $active_config = null;
    private static $instance = null;
    
    /** Helper: log only when ERP_DEBUG is on */
    private static function debug($msg)
    {
        if (ERP_DEBUG) {
            error_log("ERP_FACTORY: $msg");
        }
    }
    
    /**
     * Get the functions_lib directory path (where ERP_Odoo.php etc. live)
     */
    private static function getFunctionsLibDir()
    {
        // Factory lives at: functions_lib/lib/erp/src/Factory/
        // functions_lib/ is 4 levels up
        return dirname(__DIR__, 4);
    }
    
    /**
     * Get active ERP system from erp_integrations table
     */
    public static function getActiveSystem()
    {
        if (self::$active_system !== null) {
            return self::$active_system;
        }
        
        self::debug("Querying erp_integrations table...");
        
        // Query active integration (single query is sufficient)
        $integration = @jitquery_array(NULL, "`erp_integrations` WHERE `active` = '1' ORDER BY `erp_integration_id` ASC LIMIT 1", -1);
        
        // Fallback: direct SELECT query
        if (!is_array($integration) || empty($integration)) {
            $query_result = @jitquery("SELECT * FROM `erp_integrations` WHERE `active` = 1 ORDER BY `erp_integration_id` ASC LIMIT 1", -1);
            if (is_array($query_result) && isset($query_result['data'][0])) {
                $integration = $query_result['data'][0];
            }
        }
        
        if (is_array($integration) && !empty($integration['provider'])) {
            self::$active_system = strtolower($integration['provider']);
            self::$active_config = $integration;
            self::debug("Active system = " . self::$active_system);
        } else {
            self::$active_system = 'odoo';
            self::$active_config = null;
            self::debug("No active system found, defaulting to 'odoo'");
        }
        
        return self::$active_system;
    }
    
    /**
     * Get active ERP system settings
     */
    public static function getActiveConfig()
    {
        if (self::$active_config === null) {
            self::getActiveSystem();
        }
        return self::$active_config;
    }
    
    /**
     * Get settings for a specific system by code
     */
    public static function getConfigByCode($code)
    {
        $safe_code = addslashes($code);
        $integration = @jitquery_array(NULL, "`erp_integrations` WHERE `erp_integration_code` = '$safe_code'", -1);
        return is_array($integration) ? $integration : null;
    }
    
    /**
     * Activate a specific ERP system
     */
    public static function setActiveSystem($system)
    {
        global $lang;
        $system = strtolower($system);
        
        if (!isset(self::SYSTEMS[$system])) {
            return ['status' => 'ERROR', 'info' => ($lang['erp_not_supported'] ?? 'ERP system not supported') . ": $system"];
        }
        
        $safe_system = addslashes($system);
        
        // Check system exists in table
        $check = @jitquery_array(NULL, "`erp_integrations` WHERE `provider` = '$safe_system'", -1);
        
        if (!is_array($check) || empty($check)) {
            return ['status' => 'ERROR', 'info' => ($lang['erp_settings_not_found'] ?? 'System settings not found') . ": " . self::SYSTEMS[$system]['name']];
        }
        
        // Deactivate all systems
        @jitquery("UPDATE `erp_integrations` SET `active` = 0, `dt_updated` = UNIX_TIMESTAMP()", -1);
        
        // Activate selected system
        $result = @jitquery("UPDATE `erp_integrations` SET `active` = 1, `dt_updated` = UNIX_TIMESTAMP() WHERE `provider` = '$safe_system'", -1);
        
        if (($result['status'] ?? '') == 'OK') {
            self::$active_system = $system;
            self::$active_config = null;
            self::$instance = null;
            return ['status' => 'OK', 'info' => ($lang['erp_activated'] ?? 'ERP system activated') . ": " . self::SYSTEMS[$system]['name']];
        }
        
        return ['status' => 'ERROR', 'info' => $lang['erp_activation_failed'] ?? 'Failed to activate system'];
    }
    
    /**
     * Create instance of active ERP system
     */
    public static function create($force_new = false)
    {
        if (self::$instance !== null && !$force_new) {
            return self::$instance;
        }
        
        $active = self::getActiveSystem();
        
        // Check system support – fall back to Odoo
        if (!isset(self::SYSTEMS[$active])) {
            self::debug("System '$active' not supported, falling back to 'odoo'");
            $active = 'odoo';
        }
        
        $class_name = self::SYSTEMS[$active]['class'];
        
        // Load class file – check multiple locations
        if (!class_exists($class_name)) {
            self::loadClassFile($active);
        }
        
        // Fallback to Odoo if requested class not found
        if (!class_exists($class_name) && $active !== 'odoo') {
            self::debug("Class '$class_name' not found, falling back to Odoo");
            $active = 'odoo';
            $class_name = 'ERP_Odoo';
            if (!class_exists($class_name)) {
                self::loadClassFile('odoo');
            }
        }
        
        if (!class_exists($class_name)) {
            throw new Exception("ERP class not found: $class_name");
        }
        
        $config = self::loadConfig($active);
        self::$instance = new $class_name($config);
        self::debug("Instance of $class_name created");
        
        return self::$instance;
    }
    
    /**
     * Load the class file for a given ERP system key
     */
    private static function loadClassFile($system)
    {
        $fileName = self::SYSTEMS[$system]['file'] ?? null;
        if (!$fileName) return;
        
        $functionsLibDir = self::getFunctionsLibDir();
        
        // 1) functions_lib/ directory (primary location)
        $file = $functionsLibDir . '/' . $fileName;
        if (file_exists($file)) {
            require_once $file;
            return;
        }
        
        // 2) lib/erp/src/Systems/ directory (alternative)
        $file = dirname(__DIR__) . '/Systems/' . $fileName;
        if (file_exists($file)) {
            require_once $file;
        }
    }
    
    /**
     * Load ERP system settings from erp_integrations
     */
    private static function loadConfig($system)
    {
        // Use cached config if it matches the requested system
        $integration = null;
        if (self::$active_config && strtolower(self::$active_config['provider'] ?? '') === $system) {
            $integration = self::$active_config;
        }
        
        if (!is_array($integration) || empty($integration)) {
            $safe_system = addslashes($system);
            $integration = @jitquery_array(NULL, "`erp_integrations` WHERE `provider` = '$safe_system' AND `active` = '1' LIMIT 1", -1);
        }
        
        if (!is_array($integration) || empty($integration)) {
            $safe_system = addslashes($system);
            $integration = @jitquery_array(NULL, "`erp_integrations` WHERE `provider` = '$safe_system' ORDER BY `erp_integration_id` ASC LIMIT 1", -1);
        }
        
        $config = [];
        
        if (is_array($integration)) {
            $config = [
                'integration_id' => $integration['erp_integration_id'] ?? null,
                'title'          => $integration['erp_integration_title'] ?? '',
                'provider'       => $integration['provider'] ?? $system,
                'api_url'        => $integration['erp_api_url'] ?? '',
                'company_name'   => $integration['company_name'] ?? '',
                'username'       => $integration['erp_username'] ?? '',
                'password'       => $integration['erp_password'] ?? '',
                'api_secret'     => $integration['api_secret'] ?? '',
                'active'         => $integration['active'] ?? 0,
                'dt_last_sync'   => $integration['dt_last_sync'] ?? null,
            ];
        }
        
        return $config;
    }
    
    /**
     * Get all available ERP systems from table
     */
    public static function getAvailableSystems($active_only = false)
    {
        $where = $active_only ? "WHERE `active` = '1'" : "";
        $result = @jitquery("SELECT * FROM `erp_integrations` $where ORDER BY `erp_integration_id` ASC", -1);
        
        $systems = [];
        if (is_array($result) && isset($result['data'])) {
            foreach ($result['data'] as $row) {
                $provider = strtolower($row['provider']);
                $systems[$provider] = [
                    'id' => $row['erp_integration_id'],
                    'title' => $row['erp_integration_title'],
                    'code' => $row['erp_integration_code'],
                    'provider' => $provider,
                    'name' => self::SYSTEMS[$provider]['name'] ?? $row['erp_integration_title'],
                    'name_ar' => self::SYSTEMS[$provider]['name_ar'] ?? $row['erp_integration_title'],
                    'api_url' => $row['erp_api_url'],
                    'active' => $row['active'] == 1,
                    'enabled' => isset(self::SYSTEMS[$provider])
                ];
            }
        }
        
        return $systems;
    }
    
    /**
     * Test connection for a specific system
     */
    public static function testSystem($system)
    {
        global $lang;
        $system = strtolower($system);
        
        if (!isset(self::SYSTEMS[$system])) {
            return ['status' => 'ERROR', 'info' => ($lang['erp_not_supported'] ?? 'ERP system not supported') . ": $system"];
        }
        
        $safe_system = addslashes($system);
        $integration = @jitquery_array(NULL, "`erp_integrations` WHERE `provider` = '$safe_system'", -1);
        
        if (!is_array($integration) || empty($integration)) {
            return ['status' => 'ERROR', 'info' => $lang['erp_settings_not_found'] ?? 'System settings not found'];
        }
        
        try {
            $class_name = self::SYSTEMS[$system]['class'];
            if (!class_exists($class_name)) {
                self::loadClassFile($system);
            }
            
            if (!class_exists($class_name)) {
                return ['status' => 'ERROR', 'info' => "Class not implemented: $class_name"];
            }
            
            $config = self::loadConfig($system);
            $instance = new $class_name($config);
            return $instance->testConnection();
        } catch (Exception $e) {
            return ['status' => 'ERROR', 'info' => ($lang['test_failed'] ?? 'Test failed') . ': ' . $e->getMessage()];
        }
    }
    
    /**
     * Update last sync time
     */
    public static function updateLastSync($system = null)
    {
        if ($system === null) {
            $system = self::getActiveSystem();
        }
        
        $safe_system = addslashes($system);
        @jitquery("UPDATE `erp_integrations` SET `dt_last_sync` = UNIX_TIMESTAMP() WHERE `provider` = '$safe_system'", -1);
    }
}

