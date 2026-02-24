<?php
/**
 * ═══════════════════════════════════════════════════════════════════════
 * ERP System - Autoloaded Functions
 * ═══════════════════════════════════════════════════════════════════════
 * 
 * This file is autoloaded by functions_lib/index.php
 * It loads the ERP classes and provides all erp_* wrapper functions.
 *
 * @version 2.3
 * @author Simaat Development Team
 * @date 2026-02-11
 */

// Prevent double-loading
if (defined('ERP_LIB_LOADED')) {
    return;
}
define('ERP_LIB_LOADED', true);

// Load ERP classes (autoloader + Interface + Factory)
require_once __DIR__ . '/lib/erp/autoload.php';
require_once __DIR__ . '/lib/erp/src/Interface/ERP_Interface.php';
require_once __DIR__ . '/lib/erp/src/Factory/ERP_Factory.php';

// ═══════════════════════════════════════════════════════════════════════
// Client Functions
// ═══════════════════════════════════════════════════════════════════════

function erp_sync_client($client_id, $auth_id = NULL) {
    try {
        return ERP_Factory::create()->syncClient($client_id, $auth_id);
    } catch (Exception $e) {
        error_log("ERP_SYNC_CLIENT Error: " . $e->getMessage());
        return ['status' => 'ERROR', 'info' => $e->getMessage()];
    }
}

function erp_search_client($search_term, $search_by = 'vat_number') {
    try {
        return ERP_Factory::create()->searchClient($search_term, $search_by);
    } catch (Exception $e) {
        return ['status' => 'ERROR', 'info' => $e->getMessage()];
    }
}

function erp_get_client($erp_id) {
    try {
        return ERP_Factory::create()->getClient($erp_id);
    } catch (Exception $e) {
        return ['status' => 'ERROR', 'info' => $e->getMessage()];
    }
}

// ═══════════════════════════════════════════════════════════════════════
// Invoice Functions
// ═══════════════════════════════════════════════════════════════════════

function erp_post_invoice($einv_id, $table = 'plt_einv', $auth_id = NULL) {
    try {
        $erp = ERP_Factory::create();

        if (defined('ERP_DEBUG') && ERP_DEBUG) {
            error_log("ERP_POST_INVOICE: einv_id=$einv_id table=$table system=" . $erp->getName());
        }
        
        $result = $erp->postInvoice($einv_id, $table, $auth_id);
        
        if (defined('ERP_DEBUG') && ERP_DEBUG) {
            error_log("ERP_POST_INVOICE: status=" . ($result['status'] ?? 'NULL'));
        }
        
        return $result;
    } catch (Exception $e) {
        error_log("ERP_POST_INVOICE ERROR: " . $e->getMessage());
        return ['status' => 'ERROR', 'info' => $e->getMessage()];
    }
}

function erp_cancel_invoice($einv_id, $table = 'plt_einv') {
    try {
        return ERP_Factory::create()->cancelInvoice($einv_id, $table);
    } catch (Exception $e) {
        return ['status' => 'ERROR', 'info' => $e->getMessage()];
    }
}

function erp_get_invoice($erp_id) {
    try {
        return ERP_Factory::create()->getInvoice($erp_id);
    } catch (Exception $e) {
        return ['status' => 'ERROR', 'info' => $e->getMessage()];
    }
}

// ═══════════════════════════════════════════════════════════════════════
// Entity Sync Functions
// ═══════════════════════════════════════════════════════════════════════

function erp_sync_property($property_id, $auth_id = NULL) {
    try {
        return ERP_Factory::create()->syncProperty($property_id, $auth_id);
    } catch (Exception $e) {
        return ['status' => 'ERROR', 'info' => $e->getMessage()];
    }
}

function erp_sync_unit($unit_id, $auth_id = NULL) {
    try {
        return ERP_Factory::create()->syncUnit($unit_id, $auth_id);
    } catch (Exception $e) {
        return ['status' => 'ERROR', 'info' => $e->getMessage()];
    }
}

function erp_sync_contract($contract_id, $auth_id = NULL) {
    try {
        return ERP_Factory::create()->syncContract($contract_id, $auth_id);
    } catch (Exception $e) {
        return ['status' => 'ERROR', 'info' => $e->getMessage()];
    }
}

function erp_sync_installment($installment_id, $auth_id = NULL) {
    try {
        return ERP_Factory::create()->syncInstallment($installment_id, $auth_id);
    } catch (Exception $e) {
        return ['status' => 'ERROR', 'info' => $e->getMessage()];
    }
}

function erp_sync_cost_center($cost_center_id, $auth_id = NULL) {
    try {
        return ERP_Factory::create()->syncCostCenter($cost_center_id, $auth_id);
    } catch (Exception $e) {
        return ['status' => 'ERROR', 'info' => $e->getMessage()];
    }
}

function erp_sync_account($account_id, $auth_id = NULL) {
    try {
        return ERP_Factory::create()->syncAccount($account_id, $auth_id);
    } catch (Exception $e) {
        return ['status' => 'ERROR', 'info' => $e->getMessage()];
    }
}

// ═══════════════════════════════════════════════════════════════════════
// System Management Functions
// ═══════════════════════════════════════════════════════════════════════

function erp_get_active_system() {
    return ERP_Factory::getActiveSystem();
}

function erp_get_active_config() {
    return ERP_Factory::getActiveConfig();
}

function erp_set_active_system($system) {
    return ERP_Factory::setActiveSystem($system);
}

function erp_get_available_systems($active_only = false) {
    return ERP_Factory::getAvailableSystems($active_only);
}

function erp_get_config_by_code($code) {
    return ERP_Factory::getConfigByCode($code);
}

function erp_test_connection($system = null) {
    try {
        if ($system === null) {
            return ERP_Factory::create()->testConnection();
        }
        return ERP_Factory::testSystem($system);
    } catch (Exception $e) {
        return ['status' => 'ERROR', 'info' => $e->getMessage()];
    }
}

function erp_get_system_name() {
    try {
        return ERP_Factory::create()->getName();
    } catch (Exception $e) {
        return 'Unknown';
    }
}

function erp_update_last_sync($system = null) {
    ERP_Factory::updateLastSync($system);
}

function erp_get_api_url() {
    $config = ERP_Factory::getActiveConfig();
    return $config['erp_api_url'] ?? '';
}

function erp_get_api_secret() {
    $config = ERP_Factory::getActiveConfig();
    return $config['api_secret'] ?? '';
}

// ═══════════════════════════════════════════════════════════════════════
// Logging Functions
// ═══════════════════════════════════════════════════════════════════════

/**
 * ERP logging function - writes to error_log
 * 
 * @param string $message Log message
 * @param string $provider ERP provider (odoo, sap, dynamics)
 */
function erp_log($message, $provider = null) {
    $prefix = 'ERP';
    if ($provider) {
        $prefix .= '[' . strtoupper($provider) . ']';
    }
    error_log("$prefix: $message");
}

/**
 * Save ERP integration log to database
 *
 * @param string $entity_type Entity type (invoice, client, property, etc.)
 * @param int    $entity_id   Entity ID
 * @param string $operation   Operation type (post, sync, create, update, delete)
 * @param string $status      Status (success, error, pending)
 * @param string $provider    ERP provider (odoo, sap, dynamics)
 * @param array  $request_data  Request data sent to ERP
 * @param array  $response_data Response data from ERP
 * @param string $error_message Error message if any
 * @param int    $http_code     HTTP status code
 * @return array Result with log_id
 */
function erp_save_log($entity_type, $entity_id, $operation, $status, $provider = 'odoo', $request_data = null, $response_data = null, $error_message = null, $http_code = null) {
    $dateNow = time();
    
    $request_json = null;
    $response_json = null;
    
    if (!empty($request_data)) {
        $request_json = is_string($request_data) ? $request_data : json_encode($request_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    
    if (!empty($response_data)) {
        $response_json = is_string($response_data) ? $response_data : json_encode($response_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    $entity_type_escaped  = addslashes($entity_type);
    $operation_escaped    = addslashes($operation);
    $status_escaped       = addslashes($status);
    $provider_escaped     = addslashes($provider);
    $error_msg_escaped    = !empty($error_message) ? addslashes($error_message) : '';
    $request_json_escaped = !empty($request_json) ? addslashes($request_json) : '';
    $response_json_escaped = !empty($response_json) ? addslashes($response_json) : '';
    
    $sql = "INSERT INTO `erp_integ_log` SET 
        `entity_type`    = '$entity_type_escaped',
        `entity_id`      = " . intval($entity_id) . ",
        `operation`      = '$operation_escaped',
        `status`         = '$status_escaped',
        `provider`       = '$provider_escaped',
        `http_code`      = " . intval($http_code ?? 0) . ",
        `error_message`  = " . (!empty($error_msg_escaped) ? "'$error_msg_escaped'" : 'NULL') . ",
        `request_data`   = " . (!empty($request_json_escaped) ? "'$request_json_escaped'" : 'NULL') . ",
        `response_data`  = " . (!empty($response_json_escaped) ? "'$response_json_escaped'" : 'NULL') . ",
        `dt_created`     = $dateNow";
    
    $result = @jitquery($sql, -1);
    
    $log_id = 0;
    if (is_array($result)) {
        $log_id = $result['newid'] ?? $result['insert_id'] ?? 0;
        
        if (empty($log_id)) {
            $last_id = @jitquery("SELECT LAST_INSERT_ID() as last_id", -1);
            if (is_array($last_id) && isset($last_id['data'][0]['last_id'])) {
                $log_id = intval($last_id['data'][0]['last_id']);
            }
        }
    }
    
    if (empty($log_id)) {
        error_log("ERP_SAVE_LOG: Failed to insert log");
        return ['status' => 'ERROR', 'info' => 'Failed to create log entry'];
    }
    
    return [
        'status' => 'OK',
        'log_id' => $log_id,
        'info'   => 'Log saved successfully',
    ];
}
