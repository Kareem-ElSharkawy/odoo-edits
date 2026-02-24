<?php
/**
 * Odoo Integration Functions
 * Similar to ZATCA integration pattern
 * 
 * @category    Integration
 * @author      Etmam Team
 * @copyright   2025
 * @version     1.0
 * 
 * Endpoints supported (Simat API):
 * - POST /api/create_partner - Create client/partner
 * - POST /api/simat/properties/create - Create property
 * - POST /api/simat/units/create - Create unit
 * - POST /api/simat/contracts/create - Create contract
 * - POST /api/simat/installments/create - Create installment
 * - POST /api/fetch_installments - Fetch installments
 * - POST /api/simat/cost-centers/create - Create cost center
 * - GET /api/simat/cost-centers/list - List cost centers
 * - POST /api/odoo/v1/accounts/create - Create account
 */

// Enable verbose PHP error logging (set to false in production)
if (!defined('ODOO_DEBUG')) {
    define('ODOO_DEBUG', false);
}

/**
 * Odoo logger - uses erp_log (important messages only)
 */
function odoo_log($message, $log_file = null) {
    if (function_exists('erp_log')) {
        erp_log($message, 'odoo');
    }
}

/**
 * Odoo error handler - returns error as array instead of dying
 */
function odoo_error_handler($errno, $errstr, $errfile, $errline) {
    if (ODOO_DEBUG && function_exists('erp_log')) {
        erp_log("PHP Error [$errno]: $errstr in $errfile:$errline", 'odoo');
    }
    return false; // Let PHP handle the error normally too
}

/**
 * Safe wrapper for Odoo functions - catches all errors
 */
function odoo_safe_call($callback, ...$args) {
    try {
        // Set custom error handler
        $old_handler = set_error_handler('odoo_error_handler');
        
        $result = call_user_func_array($callback, $args);
        
        // Restore old handler
        if ($old_handler) {
            set_error_handler($old_handler);
        } else {
            restore_error_handler();
        }
        
        return $result;
    } catch (Exception $e) {
        return [
            'status' => 'ERROR',
            'info' => 'Exception: ' . $e->getMessage(),
            'title' => 'Odoo',
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ];
    } catch (Error $e) {
        return [
            'status' => 'ERROR',
            'info' => 'PHP Error: ' . $e->getMessage(),
            'title' => 'Odoo',
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ];
    }
}

/*
 * - POST /api/simat/invoices/create - Create invoice
 */

/**
 * Test Odoo connection
 * @param int $auth_id Authentication ID
 * @return array Status response
 */
function odoo_test($auth_id = NULL)
{
    global $lang;
    
    // Try mw_odoo_auth first, then odoo_auth
    $auth = null;
    $table_name = 'mw_odoo_auth';
    $id_column = 'mw_odoo_auth_id';
    
    // Get first record from mw_odoo_auth
    $auth = @jitquery_array(NULL, "`mw_odoo_auth` LIMIT 1", -1);
    
    if (!is_array($auth) || empty($auth)) {
        $table_name = 'odoo_auth';
        $id_column = 'auth_id';
        if (!empty($auth_id)) {
            $auth = @jitquery_array(NULL, "`odoo_auth` WHERE `auth_id`='$auth_id'", -1);
        } else {
            $auth = @jitquery_array(NULL, "`odoo_auth` WHERE `active`='1' LIMIT 1", -1);
        }
    }

    if (!is_array($auth) || empty($auth)) {
        $info = $lang['odoo_no_auth'] ?? 'Odoo authentication not found';
        return ['status' => 'ERROR', 'info' => $info, 'title' => $lang['odoo'] ?? 'Odoo'];
    }
    
    // Get values with fallbacks for different column names
    $auth_host = $auth['base_url'] ?? $auth['auth_host'] ?? '';
    $token = $auth['token'] ?? '';
    $api_key = $auth['api_key'] ?? '';
    $record_id = $auth[$id_column] ?? $auth_id;
    
    if (empty($auth_host)) {
        return ['status' => 'ERROR', 'info' => 'Odoo host URL not configured', 'title' => 'Odoo'];
    }
    
    // Test connection with a simple request
    $url = rtrim($auth_host, '/') . '/api/simat/cost-centers/list';
    
    $headers = [
        'Content-Type: application/json',
        'Accept: application/json'
    ];
    
    if (!empty($token)) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    if (!empty($api_key)) {
        $headers[] = 'X-API-Key: ' . $api_key;
    }
    
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ));

    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($curl);
    curl_close($curl);
    
    if ($curl_error) {
        $info = $lang['odoo_connected_error'] ?? 'Connection error';
        return ['status' => 'ERROR', 'info' => $info . ': ' . $curl_error, 'title' => $lang['odoo'] ?? 'Odoo'];
    }

    $responseData = json_decode($response, true);
    
    if ($http_code >= 200 && $http_code < 300) {
        $info = $lang['odoo_connected'] ?? 'Connected successfully';
        // Update dt_updated in the table
        @update($table_name, ['dt_updated' => time()], $id_column, $record_id);
        return ['status' => 'OK', 'info' => $info, 'title' => $lang['odoo'] ?? 'Odoo'];
    } else {
        $info = $lang['odoo_connected_error'] ?? 'Connection error';
        $error_msg = isset($responseData['message']) ? $responseData['message'] : "HTTP $http_code";
        return ['status' => 'ERROR', 'info' => $info . ': ' . $error_msg, 'title' => $lang['odoo'] ?? 'Odoo'];
    }
}

/**
 * Get Odoo authentication settings
 * @param int $auth_id Authentication ID
 * @param int $force Force refresh cache
 * @return array Authentication data
 */
function odoo_auth($auth_id = NULL, $force = 1)
{
    $get_cache = get_cache(array('odoo_auth', $auth_id), 'odoo_auth');
    $result = $get_cache['result'];
    if ($result && !$force) {
        $result['cached'] = 'OK';
        return $result;
    }
    
    if ($auth_id) {
        $where = " `auth_id`='$auth_id' AND";
    } else {
        $where = "";
    }
    
    $auth = jitquery_array(NULL, "`odoo_auth` WHERE $where `active`='1'", -1);
    put_cache($get_cache['querykey'], $auth);
    return $auth;
}

/**
 * Check Odoo authentication and get valid token
 * @param int $auth_id Authentication ID
 * @return array Token and URL data
 */
function odoo_check($auth_id = NULL)
{
    global $lang;

    $auth = @jitquery_array(NULL, "`erp_integrations` WHERE `provider` = 'odoo' AND `active` = '1'", -1);

    if (is_array($auth) && !empty($auth['erp_api_url'])) {
        $auth_host = $auth['erp_api_url'];

        // Decode HTML entities from API secret
        $api_key = html_entity_decode($auth['api_secret'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $auth['api_secret'] = $api_key;

        return [
            'status' => 'OK',
            'token' => $api_key,
            'url' => rtrim($auth_host, '/'),
            'auth_id' => $auth['erp_integration_id'] ?? null,
            'api_key' => $api_key,
            'auth' => $auth,
            'source' => 'erp_integrations'
        ];
    }

    if (function_exists('erp_log')) erp_log('No active Odoo configuration found', 'odoo');
    return ['status' => 'ERROR', 'info' => $lang['odoo_no_auth'] ?? 'Odoo authentication not found', 'title' => $lang['odoo'] ?? 'Odoo'];
}

/**
 * Build HTTP headers for Odoo API requests
 * @param array $auth Authentication data
 * @return array Headers
 */
function odoo_build_headers($auth)
{
    $headers = [
        'Content-Type: application/json',
        'Accept: application/json'
    ];
    
    // Get API key from different possible field names
    $api_key = $auth['api_key'] ?? $auth['api_secret'] ?? $auth['token'] ?? '';
    $api_key = html_entity_decode($api_key, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    
    if (!empty($api_key)) {
        $headers[] = 'X-API-Key: ' . $api_key;
        $headers[] = 'Authorization: Bearer ' . $api_key;
    }
    
    // Bearer token (if different from api_key)
    if (!empty($auth['token']) && $auth['token'] !== $api_key) {
        $headers[] = 'Authorization: Bearer ' . $auth['token'];
    }
    
    // Basic authentication
    if (!empty($auth['auth_user']) && !empty($auth['auth_pass'])) {
        $headers[] = 'Authorization: Basic ' . base64_encode($auth['auth_user'] . ':' . $auth['auth_pass']);
    }
    
    if (!empty($auth['erp_username']) && !empty($auth['erp_password'])) {
        $headers[] = 'Authorization: Basic ' . base64_encode($auth['erp_username'] . ':' . $auth['erp_password']);
    }
    
    return $headers;
}

/**
 * Make API request to Odoo
 * @param string $endpoint API endpoint
 * @param string $method HTTP method (GET, POST, PUT, DELETE)
 * @param array $data Request data
 * @param int $auth_id Authentication ID
 * @return array Response
 */
function odoo_request($endpoint, $method = 'POST', $data = [], $auth_id = NULL)
{
    global $lang;
    
    $odoo_auth = odoo_check($auth_id);
    
    if ($odoo_auth['status'] != 'OK') {
        return $odoo_auth;
    }
    
    $url = $odoo_auth['url'] . '/' . ltrim($endpoint, '/');
    
    $curl = curl_init();
    $curl_options = array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_HTTPHEADER => odoo_build_headers($odoo_auth['auth']),
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    );
    
    if (!empty($data) && in_array(strtoupper($method), ['POST', 'PUT', 'PATCH'])) {
        $curl_options[CURLOPT_POSTFIELDS] = json_encode($data, JSON_UNESCAPED_UNICODE);
    }
    
    curl_setopt_array($curl, $curl_options);
    
    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($curl);
    curl_close($curl);
    
    if ($curl_error) {
        if (function_exists('erp_log')) erp_log("Connection error ($endpoint): $curl_error", 'odoo');
        if (function_exists('erp_save_log')) {
            erp_save_log('api_request', 0, $endpoint, 'error', 'odoo', $data, null, 'Connection error: ' . $curl_error, 0);
        }
        return [
            'status' => 'ERROR',
            'info' => 'Connection error: ' . $curl_error,
            'http_code' => 0
        ];
    }
    
    $responseData = json_decode($response, true);
    
    if ($http_code >= 200 && $http_code < 300) {
        return [
            'status' => 'OK',
            'data' => $responseData,
            'http_code' => $http_code
        ];
    } else {
        $error_msg = $responseData['message'] ?? $responseData['error'] ?? "HTTP Error $http_code";
        if (is_array($error_msg)) $error_msg = json_encode($error_msg);
        if (function_exists('erp_log')) erp_log("HTTP $http_code ($endpoint): $error_msg", 'odoo');
        if (function_exists('erp_save_log')) {
            erp_save_log('api_request', 0, $endpoint, 'error', 'odoo', $data, $responseData, "HTTP $http_code: $error_msg", intval($http_code));
        }
        
        return [
            'status' => 'ERROR',
            'info' => $error_msg,
            'http_code' => $http_code,
            'data' => $responseData
        ];
    }
}

/**
 * Log sync operation (optional - logs to file if table doesn't exist)
 * @param string $entity_type Entity type (client, property, contract, etc.)
 * @param int $entity_id Entity ID in Simaat
 * @param string $operation Operation type (create, update, delete)
 * @param array $request_data Request data
 * @param array $response_data Response data
 * @param string $status Status (pending, success, failed)
 * @param int $odoo_id Odoo record ID
 * @return array Result
 */
function odoo_log_sync($entity_type, $entity_id, $operation, $request_data, $response_data, $status = 'pending', $odoo_id = NULL)
{
    // Determine HTTP code from response
    $http_code = $response_data['http_code'] ?? $response_data['code'] ?? 0;
    
    // Determine error message
    $error_message = null;
    if ($status == 'failed' || $status == 'error') {
        $error_message = $response_data['info'] ?? $response_data['error'] ?? null;
        if (is_array($error_message)) {
            $error_message = json_encode($error_message, JSON_UNESCAPED_UNICODE);
        }
    }
    
    // Save to database via erp_save_log
    if (function_exists('erp_save_log')) {
        erp_save_log(
            $entity_type,
            intval($entity_id),
            $operation,
            $status,
            'odoo',
            $request_data,
            $response_data,
            $error_message,
            intval($http_code)
        );
    }
    
    if (function_exists('erp_log')) erp_log("SYNC [$entity_type #$entity_id] $operation → $status" . ($odoo_id ? " (odoo_id=$odoo_id)" : ""), 'odoo');
    
    return ['status' => 'OK', 'logged' => true];
}

/**
 * Sync client/partner to Odoo
 * @param int $client_id Client ID
 * @param int $auth_id Odoo auth ID
 * @return array Response
 */
function odoo_sync_client($client_id, $auth_id = NULL)
{
    global $lang;
    
    $client = jitquery_array(NULL, "`res_client` WHERE `client_id`='$client_id'", -1);
    
    if (!is_array($client)) {
        erp_save_log('client', intval($client_id), 'sync', 'error', 'odoo', ['client_id' => $client_id], null, 'Client not found in res_client', 0);
        return ['status' => 'ERROR', 'info' => 'Client not found'];
    }
    
    // Validate and fix entity_type
    $entity_type = $client['entity_type'] ?? '';
    if (!in_array($entity_type, ['individual', 'company'])) {
        $entity_type = ($entity_type == 'charity' || $entity_type == 'organization') ? 'company' : 'individual';
    }
    
    // Validate and fix entity_idtype - must match entity_type!
    $entity_idtype = $client['entity_idtype'] ?? '';
    if (!in_array($entity_idtype, ['nid', 'iqama', 'passport', 'cr'])) {
        // Default based on entity_type: companies use CR, individuals use NID
        $entity_idtype = ($entity_type == 'company') ? 'cr' : 'nid';
    }
    // Force CR for companies even if nid was set
    if ($entity_type == 'company' && $entity_idtype == 'nid') {
        $entity_idtype = 'cr';
    }
    
    // Validate and fix cal_type
    $cal_type = $client['cal_type'] ?? 'cal_gr';
    if (!in_array($cal_type, ['cal_gr', 'cal_hj'])) {
        $cal_type = 'cal_gr'; // Default to Gregorian
    }
    
    // Map Simaat client fields to Odoo partner fields (matching Postman format)
    // Odoo REQUIRES 'name' field - use entity_name as primary
    $partner_name = $client['entity_name'] ?? $client['entity_name_en'] ?? $client['companyname'] ?? '';
    
    $odoo_data = [
        // Use client_id as simat_client_id (Simat's primary key)
        'simat_client_id' => strval($client['client_id']),
        'simat_uuid' => $client['uuid'] ?? strval($client['client_id']),
        // Odoo requires 'name' field for res.partner
        'name' => $partner_name,
        'entity_type' => $entity_type,
        'entity_idtype' => $entity_idtype,
        'entity_number' => !empty($client['entity_number']) ? strval($client['entity_number']) : '0000000000',
        'entity_name_ar' => $client['entity_name'] ?? '',
        'entity_name_en' => $client['entity_name_en'] ?? $client['entity_name'] ?? '',
        'entity_dob' => !empty($client['entity_dob']) ? date('Y-m-d', is_numeric($client['entity_dob']) ? $client['entity_dob'] : strtotime($client['entity_dob'])) : '',
        'companyname' => $client['companyname'] ?? '',
        'contact_name_ar' => $client['contact_name_ar'] ?? $client['entity_name'] ?? '',
        'contact_name_en' => $client['contact_name_en'] ?? $client['entity_name_en'] ?? '',
        'contact_email' => !empty($client['contact_email']) ? $client['contact_email'] : 'noemail@example.com',
        'contact_mobile' => !empty($client['contact_mobile']) ? $client['contact_mobile'] : '0500000000',
        'contact_phone' => $client['contact_phone'] ?? '',
        'client_address' => $client['client_address'] ?? '',
        'client_city' => !empty($client['client_city']) ? $client['client_city'] : 'Riyadh',
        'client_region' => $client['client_region'] ?? '',
        'client_postal_code' => $client['client_postal_code'] ?? '',
        'client_country' => !empty($client['client_country']) ? $client['client_country'] : 'Saudi Arabia',
        'client_acc_id' => strval($client['client_acc_id'] ?? ''),
        'client_acc_code' => strval($client['client_acc_code'] ?? ''),
        'client_tax_id' => strval($client['client_tax_id'] ?? ''),
        'client_tax_code' => strval($client['client_tax_code'] ?? ''),
        'client_cost' => strval($client['client_cost'] ?? ''),
        'client_credit' => floatval($client['client_credit'] ?? 0),
        'client_balance' => floatval($client['client_balance'] ?? 0),
        'vat_number' => strval($client['vat_number'] ?? ''),
        'taxexempt' => (bool)($client['taxexempt'] ?? false),
        'inc_tax' => (bool)($client['inc_tax'] ?? true),
        'tax_onbehalf' => (bool)($client['tax_onbehalf'] ?? false),
        'overideduenotices' => (bool)($client['overideduenotices'] ?? false),
        'separateinvoices' => (bool)($client['separateinvoices'] ?? true),
        'disableautocc' => (bool)($client['disableautocc'] ?? false),
        'client_class' => !empty($client['client_class']) ? $client['client_class'] : 'tenant',
        'client_group' => $client['client_group'] ?? '',
        'segment' => $client['segment'] ?? '',
        'gender' => !empty($client['gender']) && in_array($client['gender'], ['male', 'female']) ? $client['gender'] : 'male',
        'religion' => $client['religion'] ?? '',
        'nationality' => !empty($client['nationality']) ? $client['nationality'] : 'Saudi',
        'cal_type' => $cal_type,
        'job_title' => $client['job_title'] ?? '',
        'job_address' => $client['job_address'] ?? '',
        'job_address_prev' => $client['job_address_prev'] ?? '',
        'monthly_income' => floatval($client['monthly_income'] ?? 0),
        'dependents' => intval($client['dependents'] ?? 0),
        'rep_himself' => (bool)($client['rep_himself'] ?? true),
        'rep_entity_idtype' => $client['rep_entity_idtype'] ?? '',
        'rep_entity_number' => $client['rep_entity_number'] ?? '',
        'rep_number' => $client['rep_number'] ?? '',
        // Convert timestamps to datetime format "YYYY-MM-DD HH:MM:SS"
        'rep_issue_date' => !empty($client['rep_issue_date']) 
            ? (is_numeric($client['rep_issue_date']) ? date('Y-m-d H:i:s', $client['rep_issue_date']) : $client['rep_issue_date']) 
            : '',
        'rep_expiry_date' => !empty($client['rep_expiry_date']) 
            ? (is_numeric($client['rep_expiry_date']) ? date('Y-m-d H:i:s', $client['rep_expiry_date']) : $client['rep_expiry_date']) 
            : '',
        'bank_name' => $client['bank_name'] ?? '',
        'bank_client_name' => $client['bank_client_name'] ?? '',
        'bank_iban' => $client['bank_iban'] ?? '',
        'bank_swift' => $client['bank_swift'] ?? '',
        'bank_address' => $client['bank_address'] ?? '',
        'rmt_einv' => (bool)($client['rmt_einv'] ?? false),
        'rmt_server' => $client['rmt_server'] ?? '',
        'rmt_token' => $client['rmt_token'] ?? '',
        'client_notes' => $client['client_notes'] ?? 'Created via Simat API'
    ];
    
    // Validate required fields BEFORE sending
    if (empty($partner_name)) {
        if (function_exists('erp_log')) erp_log("Client #$client_id - missing name", 'odoo');
        return [
            'status' => 'ERROR',
            'info' => 'Client name (entity_name) is required but empty',
            'title' => $lang['odoo'] ?? 'Odoo'
        ];
    }
    
    // Remove empty/null values (but keep booleans and zero numbers)
    $odoo_data = array_filter($odoo_data, function($v) { 
        if ($v === null || $v === '') return false;
        if (is_string($v) && trim($v) === '') return false;
        return true;
    });
    
    // ── Attempt 1: Send full data ──
    $response = odoo_request('api/create_partner', 'POST', $odoo_data, $auth_id);
    
    // ── Check for error in result.error ──
    $has_result_error = isset($response['data']['result']['error']);
    $has_rpc_error = isset($response['data']['error']);
    $has_http_error = ($response['status'] ?? '') != 'OK';
    
    // If first attempt failed, retry with MINIMAL data
    if ($has_result_error || $has_rpc_error || $has_http_error) {
        $first_error = '';
        if ($has_result_error) {
            $first_error = $response['data']['result']['error'];
        } elseif ($has_rpc_error) {
            $first_error = $response['data']['error']['message'] ?? 'JSON-RPC error';
        } else {
            $first_error = $response['info'] ?? 'HTTP error';
        }
        
        // Save first attempt to DB
        if (function_exists('erp_save_log')) {
            erp_save_log(
                'client', intval($client_id), 'create_attempt1', 'error', 'odoo',
                $odoo_data, $response,
                'Attempt 1 failed: ' . $first_error,
                intval($response['http_code'] ?? 0)
            );
        }
        
        // ── Attempt 2: Minimal data - ONLY required fields ──
        $minimal_data = [
            'simat_client_id' => strval($client['client_id']),
            'name' => $partner_name,
            'entity_name_ar' => $client['entity_name'] ?? $partner_name,
            'entity_name_en' => $client['entity_name_en'] ?? $partner_name,
            'entity_type' => $entity_type,
            'entity_idtype' => $entity_idtype,
            'entity_number' => !empty($client['entity_number']) ? strval($client['entity_number']) : '0000000000',
            'contact_email' => !empty($client['contact_email']) ? $client['contact_email'] : 'noemail@example.com',
            'contact_mobile' => !empty($client['contact_mobile']) ? $client['contact_mobile'] : '0500000000',
            'vat_number' => strval($client['vat_number'] ?? ''),
        ];
        $minimal_data = array_filter($minimal_data, function($v) { return $v !== null && $v !== ''; });
        
        $response = odoo_request('api/create_partner', 'POST', $minimal_data, $auth_id);
        
        // Re-check after retry
        $has_result_error = isset($response['data']['result']['error']);
        $has_rpc_error = isset($response['data']['error']);
        $has_http_error = ($response['status'] ?? '') != 'OK';
    }
    
    // ── Handle errors (after retry) ──
    if ($has_result_error) {
        $error_msg = $response['data']['result']['error'];
        $result = $response['data']['result'] ?? [];
        $details = $result['details'] ?? $result['message'] ?? $result['traceback'] ?? '';
        if (is_array($details)) $details = json_encode($details, JSON_UNESCAPED_UNICODE);
        
        $full_error = $error_msg;
        if (!empty($details) && $details !== $error_msg) {
            $full_error .= ' | ' . $details;
        }
        
        // Full Odoo response for debugging in the info field (so user can see it in UI)
        $odoo_raw = json_encode($response['data'] ?? $response, JSON_UNESCAPED_UNICODE);
        
        if (function_exists('erp_log')) erp_log("Client #$client_id sync failed: $full_error", 'odoo');
        
        // Save to DB with PROPER error message
        if (function_exists('erp_save_log')) {
            erp_save_log(
                'client', intval($client_id), 'create', 'error', 'odoo',
                $odoo_data, $response['data'] ?? $response,
                $full_error,
                intval($response['http_code'] ?? 200)
            );
        }
        
        return [
            'status' => 'ERROR',
            'info' => 'Client sync failed: ' . $full_error . ' | Odoo response: ' . substr($odoo_raw, 0, 500),
            'title' => $lang['odoo'] ?? 'Odoo'
        ];
    }
    
    if ($has_rpc_error) {
        $rpc_error = $response['data']['error'];
        $error_msg = $rpc_error['data']['message'] ?? $rpc_error['message'] ?? 'Unknown error';
        $odoo_raw = json_encode($rpc_error, JSON_UNESCAPED_UNICODE);
        
        if (function_exists('erp_log')) erp_log("Client #$client_id JSON-RPC error: $error_msg", 'odoo');
        
        if (function_exists('erp_save_log')) {
            erp_save_log(
                'client', intval($client_id), 'create', 'error', 'odoo',
                $odoo_data, $rpc_error,
                'JSON-RPC: ' . $error_msg,
                intval($response['http_code'] ?? 200)
            );
        }
        
        return [
            'status' => 'ERROR',
            'info' => 'JSON-RPC Error: ' . $error_msg . ' | ' . substr($odoo_raw, 0, 500),
            'title' => $lang['odoo'] ?? 'Odoo'
        ];
    }
    
    if ($has_http_error) {
        $error_msg = $response['info'] ?? 'Unknown error from Odoo API';
        $odoo_raw = json_encode($response, JSON_UNESCAPED_UNICODE);
        
        if (function_exists('erp_log')) erp_log("Client #$client_id HTTP error: $error_msg", 'odoo');
        
        if (function_exists('erp_save_log')) {
            erp_save_log(
                'client', intval($client_id), 'create', 'error', 'odoo',
                $odoo_data, $response,
                'HTTP: ' . $error_msg,
                intval($response['http_code'] ?? 0)
            );
        }
        
        return [
            'status' => 'ERROR',
            'info' => 'HTTP Error: ' . $error_msg . ' | ' . substr($odoo_raw, 0, 500),
            'title' => $lang['odoo'] ?? 'Odoo'
        ];
    }
    
    if ($response['status'] == 'OK') {
        // Parse Odoo ID from different response formats
        $odoo_partner_id = null;
        $parse_source = 'NOT_FOUND';
        
        // Check all possible response locations for partner/odoo ID
        if (isset($response['data']['result']['partner_id'])) {
            $odoo_partner_id = $response['data']['result']['partner_id'];
            $parse_source = 'data.result.partner_id';
        } elseif (isset($response['data']['result']['odoo_id'])) {
            $odoo_partner_id = $response['data']['result']['odoo_id'];
            $parse_source = 'data.result.odoo_id';
        } elseif (isset($response['data']['result']['id'])) {
            $odoo_partner_id = $response['data']['result']['id'];
            $parse_source = 'data.result.id';
        } elseif (isset($response['data']['partner_id'])) {
            $odoo_partner_id = $response['data']['partner_id'];
            $parse_source = 'data.partner_id';
        } elseif (isset($response['data']['odoo_id'])) {
            $odoo_partner_id = $response['data']['odoo_id'];
            $parse_source = 'data.odoo_id';
        } elseif (isset($response['data']['id'])) {
            $odoo_partner_id = $response['data']['id'];
            $parse_source = 'data.id';
        } elseif (isset($response['partner_id'])) {
            $odoo_partner_id = $response['partner_id'];
            $parse_source = 'partner_id';
        } elseif (isset($response['odoo_id'])) {
            $odoo_partner_id = $response['odoo_id'];
            $parse_source = 'odoo_id';
        } elseif (isset($response['id'])) {
            $odoo_partner_id = $response['id'];
            $parse_source = 'id';
        }
        
        // Convert to integer if numeric
        if (is_numeric($odoo_partner_id) && $odoo_partner_id > 0) {
            $odoo_partner_id = intval($odoo_partner_id);
        }
        
        odoo_log_sync('client', $client_id, 'create', $odoo_data, $response, $odoo_partner_id ? 'success' : 'failed', $odoo_partner_id);
        
        if ($odoo_partner_id) {
            // Save erp_id
            @jitquery("UPDATE `res_client` SET `erp_id` = '$odoo_partner_id' WHERE `client_id` = '$client_id'", -1);
            
            if (function_exists('erp_log')) erp_log("Client #$client_id synced (erp_id=$odoo_partner_id)", 'odoo');
            
            return [
                'status' => 'OK',
                'info' => $lang['odoo_client_synced'] ?? 'Client synced successfully',
                'title' => $lang['odoo'] ?? 'Odoo',
                'odoo_id' => $odoo_partner_id
            ];
        } else {
            if (function_exists('erp_log')) erp_log("Client #$client_id - No partner ID in response", 'odoo');
            
            erp_save_log(
                'client',
                intval($client_id),
                'create',
                'error',
                'odoo',
                $odoo_data,
                $response,
                'Response OK but no partner ID found in response',
                intval($response['http_code'] ?? 200)
            );
            
            return [
                'status' => 'ERROR',
                'info' => 'Response OK but no partner ID found in response. Full response: ' . json_encode($response['data'] ?? [], JSON_UNESCAPED_UNICODE),
                'title' => $lang['odoo'] ?? 'Odoo'
            ];
        }
    }
    
    odoo_log_sync('client', $client_id, 'create', $odoo_data, $response, 'failed');
    if (function_exists('erp_log')) erp_log("Client #$client_id sync failed: " . ($response['info'] ?? 'Unknown error'), 'odoo');
    
    return [
        'status' => 'ERROR',
        'info' => $response['info'] ?? 'Unknown error',
        'title' => $lang['odoo'] ?? 'Odoo'
    ];
}

/**
 * Sync property to Odoo
 * @param int $are_id Property ID
 * @param int $auth_id Odoo auth ID
 * @return array Response
 */
function odoo_sync_property($are_id, $auth_id = NULL)
{
    global $lang;
    
    $property = jitquery_array(NULL, "`plt_prop` WHERE `are_id`='$are_id'", -1);
    
    if (!is_array($property)) {
        erp_save_log('property', intval($are_id), 'sync', 'error', 'odoo', ['are_id' => $are_id], null, 'Property not found in plt_prop', 0);
        return ['status' => 'ERROR', 'info' => 'Property not found'];
    }
    
    // Map Simaat property fields to Odoo fields
    $odoo_data = [
        'prop_id' => $property['are_id'],
        'are_id' => $property['are_id'],
        'are_code' => $property['are_code'] ?? '',
        'res_uid' => $property['res_uid'] ?? '',
        'uuid' => $property['uuid'] ?? '',
        'are_are_id' => $property['are_are_id'] ?? '',
        'parent_code' => $property['parent_code'] ?? '',
        'parent_desc_ar' => $property['parent_desc_ar'] ?? '',
        'parent_desc_en' => $property['parent_desc_en'] ?? '',
        'prop_level' => $property['prop_level'] ?? '1',
        'prop_sub' => $property['prop_sub'] ?? '0',
        'are_desc_fo' => $property['are_desc_fo'] ?? '',
        'are_desc_en' => $property['are_desc_en'] ?? '',
        'are_desc_full' => $property['are_desc_full'] ?? '',
        'are_en_full' => $property['are_en_full'] ?? '',
        'prop_note' => $property['prop_note'] ?? '',
        'are_owner' => $property['are_owner'] ?? '',
        'are_intermediate' => $property['are_intermediate'] ?? '',
        'are_agent' => $property['are_agent'] ?? '',
        'atr_id' => $property['atr_id'] ?? '',
        'ioe_code' => $property['ioe_code'] ?? '',
        'collector' => $property['collector'] ?? '',
        'unit_no' => $property['unit_no'] ?? '',
        'floor_no' => $property['floor_no'] ?? '',
        'rooms' => $property['rooms'] ?? '',
        'lease_area' => $property['lease_area'] ?? '',
        'build_area' => $property['build_area'] ?? '',
        'land_area' => $property['land_area'] ?? '',
        'prop_floors' => $property['prop_floors'] ?? '',
        'prop_units' => $property['prop_units'] ?? '',
        'prop_address' => $property['prop_address'] ?? '',
        'prop_district' => $property['prop_district'] ?? '',
        'prop_city' => $property['prop_city'] ?? '',
        'prop_region' => $property['prop_region'] ?? '',
        'country' => $property['country'] ?? 'Saudi Arabia',
        'street_name' => $property['street_name'] ?? '',
        'building_number' => $property['building_number'] ?? '',
        'building_postal_code' => $property['building_postal_code'] ?? '',
        'prop_lat' => $property['prop_lat'] ?? '',
        'prop_lng' => $property['prop_lng'] ?? '',
        'is_vacancy' => $property['is_vacancy'] ?? '',
        'is_rentable' => $property['is_rentable'] ?? '',
        'amt_tot' => $property['amt_tot'] ?? '',
        'monthly' => $property['monthly'] ?? '',
        'contract_type' => $property['contract_type'] ?? '',
        'deed_no' => $property['deed_no'] ?? '',
        'deed_type' => $property['deed_type'] ?? '',
        'elec_meter' => $property['elec_meter'] ?? '',
        'water_meter' => $property['water_meter'] ?? '',
        'gas_meter' => $property['gas_meter'] ?? '',
        'ree_code' => $property['ree_code'] ?? '',
        'ree_desc_fo' => $property['ree_desc_fo'] ?? '',
        'prop_usage' => $property['prop_usage'] ?? '',
        'is_asset' => $property['is_asset'] ?? '0',
        'acl_status_code' => $property['acl_status_code'] ?? 'ACTIVE',
        'draft' => $property['draft'] ?? '0'
    ];
    
    $odoo_data = array_filter($odoo_data, function($v) { return $v !== null && $v !== ''; });
    
    $response = odoo_request('api/simat/properties/create', 'POST', $odoo_data, $auth_id);
    
    odoo_log_sync('property', $are_id, 'create', $odoo_data, $response, $response['status'] == 'OK' ? 'success' : 'failed');
    
    if ($response['status'] == 'OK') {
        if (isset($response['data']['id'])) {
            update('plt_prop', ['odoo_analytic_id' => $response['data']['id']], 'are_id', $are_id);
        }
        return [
            'status' => 'OK',
            'info' => $lang['odoo_property_synced'] ?? 'Property synced successfully',
            'title' => $lang['odoo'] ?? 'Odoo',
            'odoo_id' => $response['data']['id'] ?? null
        ];
    }
    
    return [
        'status' => 'ERROR',
        'info' => $response['info'],
        'title' => $lang['odoo'] ?? 'Odoo'
    ];
}

/**
 * Sync unit to Odoo
 * @param int $are_id Unit ID
 * @param int $auth_id Odoo auth ID
 * @return array Response
 */
function odoo_sync_unit($are_id, $auth_id = NULL)
{
    global $lang;
    
    // Try plt_are first (primary units table), then plt_prop as fallback
    $unit = @jitquery_array(NULL, "`plt_are` WHERE `are_id`='$are_id'", -1);
    $unit_table = 'plt_are';
    
    if (!is_array($unit) || empty($unit)) {
        // Fallback: check plt_prop (units might be stored as sub-properties)
        $unit = @jitquery_array(NULL, "`plt_prop` WHERE `are_id`='$are_id'", -1);
        $unit_table = 'plt_prop';
        
        if (!is_array($unit) || empty($unit)) {
            erp_save_log('unit', intval($are_id), 'sync', 'error', 'odoo', 
                ['are_id' => $are_id, 'searched' => 'plt_are, plt_prop'], 
                null, 'Unit not found in plt_are or plt_prop', 0);
            return ['status' => 'ERROR', 'info' => 'Unit #' . $are_id . ' not found in plt_are or plt_prop'];
        }
    }
    
    // Get parent property's Odoo ID (if parent exists)
    $parent_odoo_id = null;
    $are_are_id = $unit['are_are_id'] ?? null;
    if (!empty($are_are_id)) {
        $parent_prop = jitquery_array(NULL, "`plt_prop` WHERE `are_id`='$are_are_id'", -1);
        if (is_array($parent_prop)) {
            $parent_odoo_id = $parent_prop['odoo_analytic_id'] ?? null;
        }
    }
    
    $odoo_data = [
        'prop_id' => $unit['are_id'],
        'are_id' => $unit['are_id'],
        'are_code' => $unit['are_code'] ?? '',
        'unit_no' => $unit['unit_no'] ?? '',
        'floor_no' => $unit['floor_no'] ?? '',
        'are_desc_fo' => $unit['are_desc_fo'] ?? '',
        'are_desc_en' => $unit['are_desc_en'] ?? '',
        'are_desc_full' => $unit['are_desc_full'] ?? '',
        'lease_area' => $unit['lease_area'] ?? '',
        'are_are_id' => $are_are_id ?? '',
        'parent_code' => $unit['parent_code'] ?? '',
        'parent_desc_ar' => $unit['parent_desc_ar'] ?? '',
        'parent_desc_en' => $unit['parent_desc_en'] ?? '',
        'parent_odoo_id' => $parent_odoo_id,
        'property_odoo_id' => $parent_odoo_id,
        'is_vacancy' => $unit['is_vacancy'] ?? '',
        'atr_id' => $unit['atr_id'] ?? '',
        'acl_status_code' => $unit['acl_status_code'] ?? 'active',
        'contact_name' => $unit['contact_name'] ?? '',
        'contact_mobile' => $unit['contact_mobile'] ?? '',
        'tts_start_date_dgr' => !empty($unit['tts_start_date_dgr']) ? date('Y-m-d', $unit['tts_start_date_dgr']) : null,
        'tts_end_date_dgr' => !empty($unit['tts_end_date_dgr']) ? date('Y-m-d', $unit['tts_end_date_dgr']) : null,
        'contract_type' => $unit['contract_type'] ?? '',
        'amt_tot' => $unit['amt_tot'] ?? '',
        'amt_due' => $unit['amt_due'] ?? '',
        'amt_collect' => $unit['amt_collect'] ?? '',
        'fin_situation' => $unit['fin_situation'] ?? '',
        'ioe_code' => $unit['ioe_code'] ?? ''
    ];
    
    $odoo_data = array_filter($odoo_data, function($v) { return $v !== null && $v !== ''; });
    
    $response = odoo_request('api/simat/units/create', 'POST', $odoo_data, $auth_id);
    
    // Check for errors in result (same pattern as client sync)
    if (isset($response['data']['result']['error'])) {
        $error_msg = $response['data']['result']['error'];
        $result = $response['data']['result'] ?? [];
        $details = $result['details'] ?? $result['message'] ?? '';
        if (is_array($details)) $details = json_encode($details, JSON_UNESCAPED_UNICODE);
        
        $full_error = $error_msg . (!empty($details) && $details !== $error_msg ? ' | ' . $details : '');
        
        if (function_exists('erp_log')) erp_log("Unit #$are_id sync failed: $full_error", 'odoo');
        
        if (function_exists('erp_save_log')) {
            erp_save_log('unit', intval($are_id), 'create', 'error', 'odoo', $odoo_data, $response['data'] ?? $response, $full_error, intval($response['http_code'] ?? 200));
        }
        
        return [
            'status' => 'ERROR',
            'info' => 'Unit sync failed: ' . $full_error,
            'title' => $lang['odoo'] ?? 'Odoo'
        ];
    }
    
    if (isset($response['data']['error'])) {
        $rpc_error = $response['data']['error'];
        $error_msg = $rpc_error['data']['message'] ?? $rpc_error['message'] ?? 'Unknown error';
        
        if (function_exists('erp_log')) erp_log("Unit #$are_id JSON-RPC error: $error_msg", 'odoo');
        
        if (function_exists('erp_save_log')) {
            erp_save_log('unit', intval($are_id), 'create', 'error', 'odoo', $odoo_data, $rpc_error, 'JSON-RPC: ' . $error_msg, intval($response['http_code'] ?? 200));
        }
        
        return [
            'status' => 'ERROR',
            'info' => 'Unit sync JSON-RPC error: ' . $error_msg,
            'title' => $lang['odoo'] ?? 'Odoo'
        ];
    }
    
    if (($response['status'] ?? '') != 'OK') {
        $error_msg = $response['info'] ?? 'Unknown error';
        if (function_exists('erp_log')) erp_log("Unit #$are_id HTTP error: $error_msg", 'odoo');
        
        odoo_log_sync('unit', $are_id, 'create', $odoo_data, $response, 'failed');
        
        return [
            'status' => 'ERROR',
            'info' => 'Unit sync failed: ' . $error_msg,
            'title' => $lang['odoo'] ?? 'Odoo'
        ];
    }
    
    // Parse Odoo ID from multiple possible response paths
    $odoo_unit_id = null;
    $parse_source = 'NOT_FOUND';
    
    if (isset($response['data']['result']['id'])) {
        $odoo_unit_id = $response['data']['result']['id'];
        $parse_source = 'data.result.id';
    } elseif (isset($response['data']['result']['unit_id'])) {
        $odoo_unit_id = $response['data']['result']['unit_id'];
        $parse_source = 'data.result.unit_id';
    } elseif (isset($response['data']['result']['odoo_id'])) {
        $odoo_unit_id = $response['data']['result']['odoo_id'];
        $parse_source = 'data.result.odoo_id';
    } elseif (isset($response['data']['id'])) {
        $odoo_unit_id = $response['data']['id'];
        $parse_source = 'data.id';
    } elseif (isset($response['data']['unit_id'])) {
        $odoo_unit_id = $response['data']['unit_id'];
        $parse_source = 'data.unit_id';
    } elseif (isset($response['data']['odoo_id'])) {
        $odoo_unit_id = $response['data']['odoo_id'];
        $parse_source = 'data.odoo_id';
    }
    
    if (is_numeric($odoo_unit_id) && $odoo_unit_id > 0) {
        $odoo_unit_id = intval($odoo_unit_id);
    }
    
    odoo_log_sync('unit', $are_id, 'create', $odoo_data, $response, $odoo_unit_id ? 'success' : 'failed', $odoo_unit_id);
    
    if ($odoo_unit_id) {
        @update($unit_table, ['odoo_unit_id' => $odoo_unit_id], 'are_id', $are_id);
        
        if (function_exists('erp_log')) erp_log("Unit #$are_id synced (erp_id=$odoo_unit_id)", 'odoo');
        
        return [
            'status' => 'OK',
            'info' => $lang['odoo_unit_synced'] ?? 'Unit synced successfully',
            'title' => $lang['odoo'] ?? 'Odoo',
            'odoo_id' => $odoo_unit_id
        ];
    }
    
    // Response OK but no ID found
    $odoo_raw = json_encode($response['data'] ?? $response, JSON_UNESCAPED_UNICODE);
    if (function_exists('erp_log')) erp_log("Unit #$are_id - Response OK but no unit ID found", 'odoo');
    
    if (function_exists('erp_save_log')) {
        erp_save_log('unit', intval($are_id), 'create', 'error', 'odoo', $odoo_data, $response, 'Response OK but no unit ID found', intval($response['http_code'] ?? 200));
    }
    
    return [
        'status' => 'ERROR',
        'info' => 'Unit sync: Response OK but no ID. Response: ' . substr($odoo_raw, 0, 500),
        'title' => $lang['odoo'] ?? 'Odoo'
    ];
}

/**
 * Sync contract to Odoo
 * @param int $tts_id Contract ID
 * @param int $auth_id Odoo auth ID
 * @return array Response
 */
function odoo_sync_contract($tts_id, $auth_id = NULL)
{
    global $lang;
    
    $contract = jitquery_array(NULL, "`plt_tts` WHERE `tts_id`='$tts_id'", -1);
    
    if (!is_array($contract)) {
        erp_save_log('contract', intval($tts_id), 'sync', 'error', 'odoo', ['tts_id' => $tts_id], null, 'Contract not found in plt_tts', 0);
        return ['status' => 'ERROR', 'info' => 'Contract not found'];
    }
    
    // Get client info
    $client = jitquery_array(NULL, "`res_client` WHERE `client_id`='{$contract['atr_id']}'", -1);
    
    $odoo_data = [
        'tts_id' => $contract['tts_id'],
        'tts_tts_id' => $contract['tts_tts_id'] ?? '',
        'tts_code' => $contract['tts_code'] ?? '',
        'res_uid' => $contract['res_uid'] ?? '',
        'uuid' => $contract['uuid'] ?? '',
        'contract_id' => $contract['contract_id'] ?? '',
        'tts_date_dgr' => !empty($contract['tts_date_dgr']) ? date('Y-m-d H:i:s', $contract['tts_date_dgr']) : null,
        'tts_start_date_dgr' => !empty($contract['tts_start_date_dgr']) ? date('Y-m-d H:i:s', $contract['tts_start_date_dgr']) : null,
        'tts_end_date_dgr' => !empty($contract['tts_end_date_dgr']) ? date('Y-m-d H:i:s', $contract['tts_end_date_dgr']) : null,
        'tts_date_hj' => $contract['tts_date_hj'] ?? '',
        'tts_start_date_hj' => $contract['tts_start_date_hj'] ?? '',
        'tts_end_date_hj' => $contract['tts_end_date_hj'] ?? '',
        'tts_days' => intval($contract['tts_days'] ?? 0),
        'tts_duration' => intval($contract['tts_duration'] ?? 0),
        'tts_period' => $contract['tts_period'] ?? '',
        'free_period' => $contract['free_period'] ?? '0',
        'cal_type' => $contract['cal_type'] ?? 'Gregorian',
        'payment_term' => $contract['payment_term'] ?? '',
        'pay_cycle' => $contract['pay_cycle'] ?? '',
        'tts_num_of_installments' => intval($contract['tts_num_of_installments'] ?? 0),
        'price_amt' => floatval($contract['price_amt'] ?? 0),
        'price_annually' => floatval($contract['price_annually'] ?? 0),
        'tts_amt' => floatval($contract['tts_amt'] ?? 0),
        'amt_tot' => floatval($contract['amt_tot'] ?? 0),
        'amt_collect' => floatval($contract['amt_collect'] ?? 0),
        'amt_due' => floatval($contract['amt_due'] ?? 0),
        'amt_balance' => floatval($contract['amt_balance'] ?? 0),
        'amt_payable' => floatval($contract['amt_payable'] ?? 0),
        'pct_tax' => floatval($contract['pct_tax'] ?? 15),
        'tts_insurance' => floatval($contract['tts_insurance'] ?? 0),
        'are_id' => $contract['are_id'] ?? '',
        'are_are_id' => $contract['are_are_id'] ?? '',
        'are_code' => $contract['are_code'] ?? '',
        'are_desc_fo' => $contract['are_desc_fo'] ?? '',
        'are_desc_lo' => $contract['are_desc_lo'] ?? '',
        'are_owner' => $contract['are_owner'] ?? '',
        'lease_area' => floatval($contract['lease_area'] ?? 0),
        'atr_id' => $contract['atr_id'] ?? '',
        'atr_code' => $client['client_code'] ?? '',
        'atr_official_name_fo' => $client['entity_name'] ?? '',
        'atr_official_id' => $client['entity_number'] ?? '',
        'atr_email' => $client['contact_email'] ?? '',
        'atr_mobile_num' => $client['contact_mobile'] ?? '',
        'contact_phone' => $client['contact_phone'] ?? '',
        'customer_code' => $client['client_code'] ?? '',
        'client_acc_id' => $client['client_acc_id'] ?? '',
        'client_acc_code' => $client['client_acc_code'] ?? '',
        'fcu_code' => 'SAR',
        'fcu_desc_lo' => 'Saudi Riyal',
        'fcu_desc_fo' => 'ريال سعودي',
        'doc_status' => $contract['doc_status'] ?? 'posted',
        'tts_validity' => $contract['tts_validity'] ?? 'active',
        'acl_status_code' => $contract['acl_status_code'] ?? 'Active',
        'draft' => (bool)($contract['draft'] ?? false),
        'archived' => (bool)($contract['archived'] ?? false),
        'contract_type' => $contract['contract_type'] ?? '',
        'contract_type_view' => $contract['contract_type_view'] ?? '',
        'entry_type' => $contract['entry_type'] ?? 'New',
        'tts_contract_place' => $contract['tts_contract_place'] ?? '',
        'tts_renew' => $contract['tts_renew'] ?? 'No',
        'tts_note' => $contract['tts_note'] ?? '',
        'tts_ref' => $contract['tts_ref'] ?? '',
        'tts_ref_no' => $contract['tts_ref_no'] ?? ''
    ];
    
    $odoo_data = array_filter($odoo_data, function($v) { return $v !== null && $v !== ''; });
    
    $response = odoo_request('api/simat/contracts/create', 'POST', $odoo_data, $auth_id);
    
    odoo_log_sync('contract', $tts_id, 'create', $odoo_data, $response, $response['status'] == 'OK' ? 'success' : 'failed');
    
    if ($response['status'] == 'OK') {
        if (isset($response['data']['id'])) {
            update('plt_tts', ['odoo_subscription_id' => $response['data']['id']], 'tts_id', $tts_id);
        }
        return [
            'status' => 'OK',
            'info' => $lang['odoo_contract_synced'] ?? 'Contract synced successfully',
            'title' => $lang['odoo'] ?? 'Odoo',
            'odoo_id' => $response['data']['id'] ?? null
        ];
    }
    
    return [
        'status' => 'ERROR',
        'info' => $response['info'],
        'title' => $lang['odoo'] ?? 'Odoo'
    ];
}

/**
 * Sync installment to Odoo
 * @param int $tmt_id Installment ID
 * @param int $auth_id Odoo auth ID
 * @return array Response
 */
function odoo_sync_installment($tmt_id, $auth_id = NULL)
{
    global $lang;
    
    $installment = jitquery_array(NULL, "`plt_tmt` WHERE `tmt_id`='$tmt_id'", -1);
    
    if (!is_array($installment)) {
        erp_save_log('installment', intval($tmt_id), 'sync', 'error', 'odoo', ['tmt_id' => $tmt_id], null, 'Installment not found in plt_tmt', 0);
        return ['status' => 'ERROR', 'info' => 'Installment not found'];
    }
    
    $odoo_data = [
        'tmt_uid' => $installment['tmt_uid'] ?? 'INST-' . $tmt_id,
        'contract_tts_id' => $installment['tts_id'] ?? '',
        'tmt_id' => $installment['tmt_id'],
        'tmt_seq' => $installment['tmt_seq'] ?? '1',
        'yr_seq' => $installment['yr_seq'] ?? date('Y'),
        'srv_cat' => $installment['srv_cat'] ?? 'Rent',
        'srv_id' => $installment['srv_id'] ?? '',
        'dt_from' => !empty($installment['dt_from']) ? date('Y-m-d H:i:s', $installment['dt_from']) : null,
        'dt_to' => !empty($installment['dt_to']) ? date('Y-m-d H:i:s', $installment['dt_to']) : null,
        'day_tot' => floatval($installment['day_tot'] ?? 0),
        'day_cost' => floatval($installment['day_cost'] ?? 0),
        'dt_due' => !empty($installment['dt_due']) ? date('Y-m-d H:i:s', $installment['dt_due']) : null,
        'dt_due_day' => !empty($installment['dt_due']) ? date('Y-m-d', $installment['dt_due']) : null,
        'dt_due_hj' => $installment['dt_due_hj'] ?? '',
        'view_due' => $installment['view_due'] ?? '',
        'dt_issue' => !empty($installment['dt_issue']) ? date('Y-m-d H:i:s', $installment['dt_issue']) : date('Y-m-d H:i:s'),
        'is_due' => (bool)($installment['is_due'] ?? false),
        'days_due' => floatval($installment['days_due'] ?? 0),
        'amt_st' => floatval($installment['amt_st'] ?? 0),
        'amt_disc' => floatval($installment['amt_disc'] ?? 0),
        'amt_untax' => floatval($installment['amt_untax'] ?? 0),
        'amt_tax' => floatval($installment['amt_tax'] ?? 0),
        'amt_tot' => floatval($installment['amt_tot'] ?? 0),
        'amt_collect' => floatval($installment['amt_collect'] ?? 0),
        'amt_due' => floatval($installment['amt_due'] ?? 0),
        'amt_due_later' => floatval($installment['amt_due_later'] ?? 0),
        'amt_balance' => floatval($installment['amt_balance'] ?? 0),
        'amt_payable' => floatval($installment['amt_payable'] ?? 0),
        'pct_tax' => floatval($installment['pct_tax'] ?? 15),
        'inc_vat' => (bool)($installment['inc_vat'] ?? true),
        'inc_comm' => (bool)($installment['inc_comm'] ?? false),
        'due_comm' => $installment['due_comm'] ?? 'nocomm',
        'pct_comm' => floatval($installment['pct_comm'] ?? 0),
        'amt_comm' => floatval($installment['amt_comm'] ?? 0),
        'pct_comm_tax' => floatval($installment['pct_comm_tax'] ?? 0),
        'amt_comm_tax' => floatval($installment['amt_comm_tax'] ?? 0),
        'amt_comm_tot' => floatval($installment['amt_comm_tot'] ?? 0),
        'skip_calc' => (bool)($installment['skip_calc'] ?? false),
        'ign_einv' => (bool)($installment['ign_einv'] ?? false),
        'ign_due' => (bool)($installment['ign_due'] ?? false),
        'entry_type' => $installment['entry_type'] ?? 'auto',
        'cal_type' => $installment['cal_type'] ?? 'cal_gr',
        'create_by' => $installment['create_by'] ?? 'System',
        'dt_created' => !empty($installment['dt_created']) ? date('Y-m-d H:i:s', $installment['dt_created']) : date('Y-m-d H:i:s')
    ];
    
    $odoo_data = array_filter($odoo_data, function($v) { return $v !== null && $v !== ''; });
    
    $response = odoo_request('api/simat/installments/create', 'POST', $odoo_data, $auth_id);
    
    odoo_log_sync('installment', $tmt_id, 'create', $odoo_data, $response, $response['status'] == 'OK' ? 'success' : 'failed');
    
    if ($response['status'] == 'OK') {
        if (isset($response['data']['id'])) {
            update('plt_tmt', ['odoo_invoice_id' => $response['data']['id']], 'tmt_id', $tmt_id);
        }
        return [
            'status' => 'OK',
            'info' => $lang['odoo_installment_synced'] ?? 'Installment synced successfully',
            'title' => $lang['odoo'] ?? 'Odoo',
            'odoo_id' => $response['data']['id'] ?? null
        ];
    }
    
    return [
        'status' => 'ERROR',
        'info' => $response['info'],
        'title' => $lang['odoo'] ?? 'Odoo'
    ];
}

/**
 * Sync invoice to Odoo
 * @param int $einv_id Invoice ID
 * @param int $auth_id Odoo auth ID
 * @return array Response
 */
function odoo_sync_invoice($einv_id, $auth_id = NULL)
{
    global $lang;
    
    // Validate input
    if (empty($einv_id)) {
        return ['status' => 'ERROR', 'info' => 'Invoice ID is required', 'title' => 'Odoo'];
    }
    
    // Check Odoo connection first
    $odoo_auth = odoo_check($auth_id);
    if (!isset($odoo_auth['status']) || $odoo_auth['status'] != 'OK') {
        return $odoo_auth ?: ['status' => 'ERROR', 'info' => 'Odoo connection check failed', 'title' => 'Odoo'];
    }
    
    // Get invoice
    $invoice = @jitquery_array(NULL, "`plt_einv` WHERE `einv_id`='$einv_id'", -1);
    
    if (!is_array($invoice) || empty($invoice)) {
        return ['status' => 'ERROR', 'info' => 'Invoice not found: ' . $einv_id, 'title' => 'Odoo'];
    }
    
    // Get invoice lines
    $lines = [];
    try {
        $lines_query = @jitquery("SELECT * FROM `plt_einv_line` WHERE `einv_id`='$einv_id'");
        
        if (!empty($lines_query['data'])) {
            foreach ($lines_query['data'] as $line) {
                $lines[] = [
                    'srv_ar' => $line['srv_ar'] ?? $line['desc_ar'] ?? '',
                    'tmt_id' => $line['tmt_id'] ?? '',
                    'desc_ar' => $line['desc_ar'] ?? '',
                    'amt_st' => floatval($line['amt_st'] ?? 0),
                    'qty' => floatval($line['qty'] ?? 1),
                    'pct_tax' => floatval($line['pct_tax'] ?? 0),
                    'amt_tax' => floatval($line['amt_tax'] ?? 0),
                    'amt_tot' => floatval($line['amt_tot'] ?? 0)
                ];
            }
        }
    } catch (Exception $e) {
        // Continue without lines if error
    }
    
    // Prepare data for Odoo
    $odoo_data = [
        'einv_id' => $invoice['einv_id'],
        'einv_number' => $invoice['einv_number'] ?? '',
        'einv_date' => $invoice['einv_date'] ?? time(),
        'uuid' => $invoice['uuid'] ?? '',
        'customer_id' => $invoice['customer_id'] ?? '',
        'amt_untax' => floatval($invoice['amt_untax'] ?? 0),
        'amt_tax' => floatval($invoice['amt_tax'] ?? 0),
        'amt_tot' => floatval($invoice['amt_tot'] ?? 0),
        'lines' => $lines
    ];
    
    // Send to Odoo
    $response = odoo_request('api/simat/invoices/create', 'POST', $odoo_data, $auth_id);
    
    // Parse JSON-RPC response: {"jsonrpc": "2.0", "id": null, "result": {"status": "updated", "id": 29}}
    $result = $response['data']['result'] ?? null;
    $odoo_id = $result['id'] ?? null;
    $result_status = $result['status'] ?? null; // "created" or "updated"
    
    // Log the sync attempt
    odoo_log_sync('invoice', $einv_id, 'create', $odoo_data, $response, 
        (isset($response['status']) && $response['status'] == 'OK' && $odoo_id) ? 'success' : 'failed', $odoo_id);
    
    // Debug: Log the full response
    $response_json = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
    // Handle response - check if HTTP request succeeded AND we got a result with ID
    if (isset($response['status']) && $response['status'] == 'OK' && $odoo_id) {
        // Update local record with Odoo ID using jitquery
        @jitquery("
            UPDATE `plt_einv`
            SET `erp_id` = '$odoo_id'
            WHERE `einv_id` = '$einv_id'
        ", -1);
        
        $msg = ($result_status == 'updated') 
            ? ($lang['odoo_invoice_updated'] ?? 'Invoice updated in Odoo')
            : ($lang['odoo_invoice_synced'] ?? 'Invoice synced successfully');
        
        return [
            'status' => 'OK',
            'info' => $msg . ' (ID: ' . $odoo_id . ') | Response: ' . json_encode($response['data'], JSON_UNESCAPED_UNICODE),
            'title' => $lang['odoo'] ?? 'Odoo',
            'odoo_id' => $odoo_id,
            'result_status' => $result_status
        ];
    }
    
    // Handle error
    $error_info = $response['info'] ?? '';
    if (empty($error_info) && isset($response['data']['error'])) {
        $error_info = is_array($response['data']['error']) 
            ? ($response['data']['error']['message'] ?? json_encode($response['data']['error']))
            : $response['data']['error'];
    }
    if (empty($error_info)) {
        $error_info = 'Unknown error occurred';
    }
    
    return [
        'status' => 'ERROR',
        'info' => $error_info . ' | Full Response: ' . json_encode($response, JSON_UNESCAPED_UNICODE),
        'title' => $lang['odoo'] ?? 'Odoo',
        'debug' => $response
    ];
}

/**
 * Sync cost center to Odoo
 * @param array $cost_center Cost center data
 * @param int $auth_id Odoo auth ID
 * @return array Response
 */
function odoo_sync_cost_center($cost_center, $auth_id = NULL)
{
    global $lang;
    
    $odoo_data = [
        'acc_id' => $cost_center['acc_id'] ?? '',
        'acc_code' => $cost_center['acc_code'] ?? '',
        'acc_name' => $cost_center['acc_name'] ?? '',
        'acc_name_en' => $cost_center['acc_name_en'] ?? '',
        'acc_level' => intval($cost_center['acc_level'] ?? 0),
        'acc_parent' => $cost_center['acc_parent'] ?? '',
        'acc_payable' => $cost_center['acc_payable'] ?? '1',
        'acc_nature' => $cost_center['acc_nature'] ?? 'debit',
        'pct_rel' => intval($cost_center['pct_rel'] ?? 100),
        'set_id' => intval($cost_center['set_id'] ?? 0),
        'res_table' => $cost_center['res_table'] ?? null,
        'res_id' => $cost_center['res_id'] ?? null,
        'client_id' => $cost_center['client_id'] ?? null,
        'acl_status_code' => $cost_center['acl_status_code'] ?? 'ACTIVE'
    ];
    
    $response = odoo_request('api/simat/cost-centers/create', 'POST', $odoo_data, $auth_id);
    
    $cost_center_id = $cost_center['acc_id'] ?? 0;
    odoo_log_sync('cost_center', $cost_center_id, 'create', $odoo_data, $response, $response['status'] == 'OK' ? 'success' : 'failed');
    
    if ($response['status'] == 'OK') {
        return [
            'status' => 'OK',
            'info' => $lang['odoo_cost_center_synced'] ?? 'Cost center synced successfully',
            'title' => $lang['odoo'] ?? 'Odoo',
            'odoo_id' => $response['data']['id'] ?? null
        ];
    }
    
    return [
        'status' => 'ERROR',
        'info' => $response['info'],
        'title' => $lang['odoo'] ?? 'Odoo'
    ];
}

/**
 * Sync account to Odoo
 * @param array $account Account data
 * @param int $auth_id Odoo auth ID
 * @return array Response
 */
function odoo_sync_account($account, $auth_id = NULL)
{
    global $lang;
    
    $odoo_data = [
        'acc_id' => $account['acc_id'] ?? '',
        'acc_code' => $account['acc_code'] ?? '',
        'acc_name' => $account['acc_name'] ?? '',
        'acc_name_en' => $account['acc_name_en'] ?? '',
        'acc_parent' => $account['acc_parent'] ?? '',
        'acc_level' => $account['acc_level'] ?? '1',
        'acc_sub' => intval($account['acc_sub'] ?? 0),
        'sequence' => intval($account['sequence'] ?? 1),
        'acc_nature' => $account['acc_nature'] ?? 'debit',
        'acc_cat' => $account['acc_cat'] ?? 'assets',
        'acc_payable' => $account['acc_payable'] ?? '0'
    ];
    
    $response = odoo_request('api/odoo/v1/accounts/create', 'POST', $odoo_data, $auth_id);
    
    $account_id = $account['acc_id'] ?? 0;
    odoo_log_sync('account', $account_id, 'create', $odoo_data, $response, $response['status'] == 'OK' ? 'success' : 'failed');
    
    if ($response['status'] == 'OK') {
        return [
            'status' => 'OK',
            'info' => $lang['odoo_account_synced'] ?? 'Account synced successfully',
            'title' => $lang['odoo'] ?? 'Odoo',
            'odoo_id' => $response['data']['id'] ?? null
        ];
    }
    
    return [
        'status' => 'ERROR',
        'info' => $response['info'],
        'title' => $lang['odoo'] ?? 'Odoo'
    ];
}

/**
 * Fetch installments from Odoo
 * @param string $contract_id Contract ID
 * @param int $auth_id Odoo auth ID
 * @return array Response
 */
function odoo_fetch_installments($contract_id, $auth_id = NULL)
{
    global $lang;
    
    $request_data = [
        'jsonrpc' => '2.0',
        'method' => 'call',
        'params' => [
            'contract_id' => $contract_id
        ]
    ];
    
    $response = odoo_request('api/fetch_installments', 'POST', $request_data, $auth_id);
    
    if ($response['status'] == 'OK') {
        return [
            'status' => 'OK',
            'info' => $lang['odoo_installments_fetched'] ?? 'Installments fetched successfully',
            'title' => $lang['odoo'] ?? 'Odoo',
            'data' => $response['data']
        ];
    }
    
    return [
        'status' => 'ERROR',
        'info' => $response['info'],
        'title' => $lang['odoo'] ?? 'Odoo'
    ];
}

/**
 * List cost centers from Odoo
 * @param int $auth_id Odoo auth ID
 * @return array Response
 */
function odoo_list_cost_centers($auth_id = NULL)
{
    global $lang;
    
    $response = odoo_request('api/simat/cost-centers/list', 'GET', [], $auth_id);
    
    if ($response['status'] == 'OK') {
        return [
            'status' => 'OK',
            'info' => $lang['odoo_cost_centers_fetched'] ?? 'Cost centers fetched successfully',
            'title' => $lang['odoo'] ?? 'Odoo',
            'data' => $response['data']
        ];
    }
    
    return [
        'status' => 'ERROR',
        'info' => $response['info'],
        'title' => $lang['odoo'] ?? 'Odoo'
    ];
}

/**
 * Confirm and sync to Odoo (same pattern as zatca_confirm)
 * @param int $einv_id Invoice ID
 * @param string $table Table name (plt_einv or scm_einv)
 * @param string $table_line Table line name
 * @return array Response
 */
function odoo_confirm($einv_id, $table = 'plt_einv', $table_line = 'plt_einv_line')
{
    global $lang;
    
    // Use odoo_post directly (contains all logging)
    return odoo_post($einv_id, $table, $table_line);
}

function odoo_confirm_old($einv_id, $table = 'plt_einv', $table_line = 'plt_einv_line')
{
    global $lang;
    
    $invoice = jitquery_array(NULL, "`$table` WHERE `einv_id`='$einv_id'", -1);
    
    if (!is_array($invoice)) {
        return ['status' => 'ERROR', 'info' => $lang['invoice_not_found'] ?? 'Invoice not found', 'title' => $lang['odoo'] ?? 'Odoo'];
    }
    
    // Check if already synced to Odoo
    $existing_odoo_id = $invoice['erp_id'] ?? $invoice['odoo_invoice_id'] ?? null;
    if (!empty($existing_odoo_id)) {
        $info = $lang['odoo_already_synced'] ?? 'Invoice already synced to Odoo';
        return ['status' => 'OK', 'info' => $info, 'title' => $lang['odoo'] ?? 'Odoo', 'odoo_id' => $existing_odoo_id];
    }
    
    // Check Odoo connection
    $odoo_auth = odoo_check();
    
    if (empty($odoo_auth)) {
        $info = $lang['verification_unknown'] ?? 'Verification failed';
        return ['status' => 'ERROR', 'info' => $info, 'title' => $lang['odoo'] ?? 'Odoo'];
    }
    
    if (isset($odoo_auth['status']) && $odoo_auth['status'] == 'ERROR') {
        return ['status' => 'ERROR', 'info' => $odoo_auth['info'], 'title' => $lang['odoo'] ?? 'Odoo'];
    }
    
    // Sync based on invoice type
    if ($invoice['mov_type'] == 'einv' || $invoice['mov_type'] == 'invoice') {
        return odoo_post($einv_id, $table, $table_line);
    } else {
        // Credit note or other types
        return odoo_post($einv_id, $table, $table_line);
    }
}

/**
 * Post invoice to Odoo (same pattern as zatca_post)
 * @param int $einv_id Invoice ID
 * @param string $table Table name
 * @param string $table_line Table line name
 * @param bool $is_retry Whether this is a retry attempt (to prevent infinite loops)
 * @return array Response
 */
function odoo_post($einv_id, $table = 'plt_einv', $table_line = 'plt_einv_line', $is_retry = false)
{
    global $lang;
    
    // Guard: ensure table_line is NEVER null/empty
    if (empty($table_line)) {
        $table_line = ($table == 'plt_einv') ? 'plt_einv_line' : 'scm_einv_line';
    }
    
    // Concise logging - one line per major step
    
    // Step 1: Load invoice
    $invoice = jitquery_array(NULL, "`$table` WHERE `einv_id`='$einv_id'", -1);
    
    if (!is_array($invoice)) {
        if (function_exists('erp_log')) erp_log("Invoice #$einv_id not found", 'odoo');
        erp_save_log('invoice', intval($einv_id), 'post', 'error', 'odoo', ['einv_id' => $einv_id, 'table' => $table], null, 'Invoice not found', 0);
        return ['status' => 'ERROR', 'info' => 'Invoice not found', 'title' => $lang['odoo'] ?? 'Odoo'];
    }
    
    // Step 2: Check if already synced
    $existing_odoo_id = $invoice['erp_id'] ?? $invoice['odoo_invoice_id'] ?? null;
    
    if (!empty($existing_odoo_id)) {
        $output['acl_status_code'] = ($table == 'plt_einv') ? '44120' : '53820';
        update($table, $output, 'einv_id', $einv_id);
        return ['status' => 'OK', 'info' => $lang['odoo_already_synced'] ?? 'Already synced to Odoo', 'title' => $lang['odoo'] ?? 'Odoo', 'odoo_id' => $existing_odoo_id];
    }
    
    // Step 3: Get Odoo authentication
    $odoo_auth = odoo_check();
    
    if (empty($odoo_auth) || (isset($odoo_auth['status']) && $odoo_auth['status'] == 'ERROR')) {
        $info = $odoo_auth['info'] ?? ($lang['odoo_no_auth'] ?? 'Odoo authentication not found');
        if (function_exists('erp_log')) erp_log("Auth failed: $info", 'odoo');
        erp_save_log('invoice', intval($einv_id), 'post', 'error', 'odoo', ['einv_id' => $einv_id], null, 'Auth failed: ' . $info, 0);
        return ['status' => 'ERROR', 'info' => $info, 'title' => $lang['odoo'] ?? 'Odoo'];
    }
    
    // Step 4: Get client info
    $customer_id = $invoice['customer_id'] ?? '';
    $client = null;
    
    if (!empty($customer_id)) {
        $client = jitquery_array(NULL, "`res_client` WHERE `client_id`='$customer_id'", -1);
    }
    
    $erp_id = null;
    $actual_client_id = null;
    
    if (empty($client)) {
        // No client found - try to create one from invoice data
        
        // Check if we have minimum required data to create a client
        $customer_name = $invoice['customer_ar'] ?? '';
        $customer_vat = $invoice['customer_vat'] ?? '';
        
        if (empty($customer_name)) {
            if (function_exists('erp_log')) erp_log("Invoice #$einv_id: No customer name", 'odoo');
            erp_save_log('invoice', intval($einv_id), 'post', 'error', 'odoo', ['einv_id' => $einv_id, 'customer_id' => $customer_id], null, 'No client data found in invoice', 0);
            return [
                'status' => 'ERROR',
                'info' => $lang['odoo_no_client_data'] ?? 'Cannot sync invoice to Odoo: No client data found.',
                'title' => $lang['odoo'] ?? 'Odoo'
            ];
        }
        
        // Create new client in res_client with ALL required fields for Odoo
        $generated_uuid = uniqid('CLI-', true);
        $new_client_data = [
            'entity_name' => $customer_name,
            'entity_name_en' => $invoice['customer_en'] ?? $customer_name,
            'vat_number' => $customer_vat,
            'contact_mobile' => !empty($invoice['customer_mobile']) ? $invoice['customer_mobile'] : '0500000000',
            'contact_email' => !empty($invoice['customer_email']) ? $invoice['customer_email'] : 'noemail@example.com',
            'entity_type' => !empty($customer_vat) ? 'company' : 'individual',
            'entity_idtype' => !empty($invoice['customer_idtype']) ? $invoice['customer_idtype'] : 'nid',
            'entity_number' => !empty($invoice['customer_idnum']) ? $invoice['customer_idnum'] : '0000000000',
            'acl_status_code' => '10110',
            'uuid' => $generated_uuid,
            'client_code' => 'AUTO-' . time(),
            'client_city' => 'Riyadh',
            'client_country' => 'Saudi Arabia'
        ];
        
        // Try using insert() function first
        $insert_result = @insert('res_client', $new_client_data);
        $new_client_id = $insert_result['newid'] ?? $insert_result['insert_id'] ?? 0;
        
        // Fallback to direct jitquery
        if (empty($new_client_id)) {
            $insert_result = @jitquery("
                INSERT INTO `res_client` SET 
                    `entity_name` = '" . addslashes($new_client_data['entity_name']) . "',
                    `entity_name_en` = '" . addslashes($new_client_data['entity_name_en']) . "',
                    `vat_number` = '" . addslashes($new_client_data['vat_number']) . "',
                    `contact_mobile` = '" . addslashes($new_client_data['contact_mobile']) . "',
                    `contact_email` = '" . addslashes($new_client_data['contact_email']) . "',
                    `entity_type` = '" . $new_client_data['entity_type'] . "',
                    `entity_idtype` = '" . $new_client_data['entity_idtype'] . "',
                    `entity_number` = '" . addslashes($new_client_data['entity_number']) . "',
                    `acl_status_code` = '" . $new_client_data['acl_status_code'] . "',
                    `uuid` = '" . $new_client_data['uuid'] . "',
                    `client_code` = '" . $new_client_data['client_code'] . "',
                    `client_city` = '" . addslashes($new_client_data['client_city']) . "',
                    `client_country` = '" . addslashes($new_client_data['client_country']) . "',
                    `dt_created` = UNIX_TIMESTAMP()
            ", -1);
            $new_client_id = $insert_result['newid'] ?? $insert_result['insert_id'] ?? 0;
        }
        
        if (empty($new_client_id)) {
            if (function_exists('erp_log')) erp_log("Failed to create client from invoice #$einv_id", 'odoo');
            erp_save_log('invoice', intval($einv_id), 'post', 'error', 'odoo', ['einv_id' => $einv_id, 'customer_name' => $customer_name], null, 'Failed to create client in database', 0);
            return [
                'status' => 'ERROR',
                'info' => $lang['odoo_client_create_failed'] ?? "Failed to create client in database",
                'title' => $lang['odoo'] ?? 'Odoo'
            ];
        }
        
        
        // Update invoice with new customer_id
        @jitquery("UPDATE `$table` SET `customer_id` = '$new_client_id' WHERE `einv_id` = '$einv_id'", -1);
        
        // Sync new client to Odoo
        $client_sync = odoo_sync_client($new_client_id);
        
        if ($client_sync['status'] != 'OK' || empty($client_sync['odoo_id'])) {
            $sync_error = $client_sync['info'] ?? 'Unknown error';
            if (function_exists('erp_log')) erp_log("Failed to sync new client: " . $sync_error, 'odoo');
            erp_save_log('invoice', intval($einv_id), 'post', 'error', 'odoo', ['einv_id' => $einv_id, 'new_client_id' => $new_client_id], $client_sync, 'Failed to sync new client: ' . $sync_error, 0);
            return [
                'status' => 'ERROR',
                'info' => $lang['odoo_client_sync_failed'] ?? 'Failed to sync new client to Odoo: ' . $sync_error,
                'title' => $lang['odoo'] ?? 'Odoo'
            ];
        }
        
        $erp_id = $client_sync['odoo_id'];
        $actual_client_id = $new_client_id;
        $client = jitquery_array(NULL, "`res_client` WHERE `client_id`='$new_client_id'", -1);
        
    } else {
        $actual_client_id = $client['client_id'] ?? $customer_id;
        $erp_id = $client['erp_id'] ?? $client['odoo_partner_id'] ?? null;
        
        // If no erp_id, sync client to Odoo first
        if (empty($erp_id) && !empty($customer_id)) {
            
            $client_sync = odoo_sync_client($customer_id);
            
            if ($client_sync['status'] == 'OK' && !empty($client_sync['odoo_id'])) {
                $erp_id = $client_sync['odoo_id'];
            } else {
                $error_info = $client_sync['info'] ?? 'Failed to sync client';
                if (function_exists('erp_log')) erp_log("Client #$customer_id sync failed: $error_info", 'odoo');
                erp_save_log('invoice', intval($einv_id), 'post', 'error', 'odoo', ['einv_id' => $einv_id, 'customer_id' => $customer_id], $client_sync, 'Client sync failed: ' . $error_info, 0);
                return [
                    'status' => 'ERROR',
                    'info' => 'Failed to sync client: ' . $error_info,
                    'title' => $lang['odoo'] ?? 'Odoo'
                ];
            }
        }
    }
    
    // Step 4b: Auto-sync related entities (Property → Unit → Contract → Installments)
    // This ensures all dependencies exist in Odoo before sending the invoice
    $sync_log = [];
    $synced_units = [];  // Track synced units to avoid duplicates
    
    // 4b-1: Sync Property
    $are_are_id = $invoice['are_are_id'] ?? null;
    if (!empty($are_are_id)) {
        $property = jitquery_array(NULL, "`plt_prop` WHERE `are_id`='$are_are_id'", -1);
        if (is_array($property)) {
            $prop_odoo_id = $property['odoo_analytic_id'] ?? null;
            if (empty($prop_odoo_id)) {
                $prop_sync = odoo_sync_property($are_are_id);
                $sync_log['property'] = ['are_id' => $are_are_id, 'status' => $prop_sync['status'], 'odoo_id' => $prop_sync['odoo_id'] ?? null];
            } else {
                $sync_log['property'] = ['are_id' => $are_are_id, 'status' => 'already_synced', 'odoo_id' => $prop_odoo_id];
                erp_save_log('property', intval($are_are_id), 'create', 'already_synced', 'odoo', ['are_id' => $are_are_id, 'odoo_analytic_id' => $prop_odoo_id], null, null, 0);
            }
        }
    }
    
    // 4b-1b: Sync Unit directly from invoice header
    // Try multiple possible unit ID sources from invoice
    $possible_unit_ids = array_filter([
        $invoice['are_id'] ?? null,
        $invoice['unit_id'] ?? null,
        $invoice['unit_are_id'] ?? null,
    ], function($v) { return !empty($v) && $v != '0'; });
    $possible_unit_ids = array_unique($possible_unit_ids);
    
    $unit_synced = false;
    
    foreach ($possible_unit_ids as $try_unit_id) {
        // Skip if same as property (are_are_id) - that's not a unit
        if ($try_unit_id == ($invoice['are_are_id'] ?? null)) {
            continue;
        }
        
        // Try 1: Look in plt_are (units table)
        $unit = @jitquery_array(NULL, "`plt_are` WHERE `are_id`='$try_unit_id'", -1);
        $found_in = null;
        
        if (is_array($unit) && !empty($unit)) {
            $found_in = 'plt_are';
        } else {
            // Try 2: Look in plt_prop as sub-property (prop_level > 1)
            $unit_prop = @jitquery_array(NULL, "`plt_prop` WHERE `are_id`='$try_unit_id'", -1);
            if (is_array($unit_prop) && !empty($unit_prop)) {
                $found_in = 'plt_prop';
                $unit = $unit_prop;
            }
        }
        
        if (!$found_in) {
            erp_save_log('unit', intval($try_unit_id), 'lookup', 'error', 'odoo', 
                ['are_id' => $try_unit_id, 'searched_tables' => 'plt_are, plt_prop'], 
                null, "Unit #$try_unit_id not found in plt_are or plt_prop", 0);
            continue;
        }
        
        // Found the unit - sync it
        $unit_odoo_id = $unit['odoo_unit_id'] ?? $unit['erp_id'] ?? null;
        if (empty($unit_odoo_id)) {
            $unit_sync = odoo_sync_unit($try_unit_id);
            $sync_log['unit_from_invoice'] = [
                'are_id' => $try_unit_id, 
                'found_in' => $found_in,
                'status' => $unit_sync['status'], 
                'odoo_id' => $unit_sync['odoo_id'] ?? null, 
                'source' => 'invoice_header'
            ];
        } else {
            $sync_log['unit_from_invoice'] = [
                'are_id' => $try_unit_id, 
                'found_in' => $found_in,
                'status' => 'already_synced', 
                'odoo_id' => $unit_odoo_id, 
                'source' => 'invoice_header'
            ];
        }
        $synced_units[] = $try_unit_id;
        $unit_synced = true;
        break;
    }
    
    if (!$unit_synced) {
        // No unit ID found in invoice at all - try to find units under the property
        if (!empty($are_are_id)) {
            
            // Check plt_are for units with are_are_id = property
            $child_unit = @jitquery_array(NULL, "`plt_are` WHERE `are_are_id`='$are_are_id' LIMIT 1", -1);
            if (is_array($child_unit) && !empty($child_unit)) {
                $child_are_id = $child_unit['are_id'];
                
                $child_odoo_id = $child_unit['odoo_unit_id'] ?? $child_unit['erp_id'] ?? null;
                if (empty($child_odoo_id)) {
                    $unit_sync = odoo_sync_unit($child_are_id);
                    $sync_log['unit_from_property'] = ['are_id' => $child_are_id, 'parent' => $are_are_id, 'status' => $unit_sync['status'], 'odoo_id' => $unit_sync['odoo_id'] ?? null];
                } else {
                    $sync_log['unit_from_property'] = ['are_id' => $child_are_id, 'parent' => $are_are_id, 'status' => 'already_synced', 'odoo_id' => $child_odoo_id];
                }
                $synced_units[] = $child_are_id;
            } else {
                // Also check plt_prop for sub-properties
                $child_prop = @jitquery_array(NULL, "`plt_prop` WHERE `are_are_id`='$are_are_id' AND `prop_level` > '1' LIMIT 1", -1);
                if (is_array($child_prop) && !empty($child_prop)) {
                    $sync_log['unit_from_property'] = ['status' => 'found_in_plt_prop_sub', 'prop_level' => $child_prop['prop_level'] ?? '?'];
                } else {
                    $sync_log['unit_from_invoice'] = ['status' => 'no_unit_found', 'checked_ids' => array_values($possible_unit_ids), 'property_id' => $are_are_id];
                }
            }
        } else {
            $sync_log['unit_from_invoice'] = ['status' => 'skipped', 'reason' => 'no unit IDs in invoice and no property'];
        }
    }
    
    // 4b-2: Get unique tmt_ids from invoice lines to find related contracts and units
    // First get ALL tmt_ids (including 0/empty) for debugging
    $all_tmt_query = @jitquery("SELECT `einv_line_id`, `tmt_id`, `srv_ar`, `amt_tot` FROM `$table_line` WHERE `einv_id`='$einv_id'", ['ttl' => -1]);
    $sync_log['all_lines_tmt'] = [];
    if (!empty($all_tmt_query['data']) && is_array($all_tmt_query['data'])) {
        foreach ($all_tmt_query['data'] as $row) {
            $sync_log['all_lines_tmt'][] = [
                'einv_line_id' => $row['einv_line_id'] ?? '?',
                'tmt_id' => $row['tmt_id'] ?? 'NULL',
                'srv_ar' => $row['srv_ar'] ?? '',
                'amt_tot' => $row['amt_tot'] ?? 0
            ];
        }
    }
    $sync_log['total_lines_found'] = count($sync_log['all_lines_tmt']);
    
    // Now get valid tmt_ids only
    $tmt_ids_query = @jitquery("SELECT DISTINCT `tmt_id` FROM `$table_line` WHERE `einv_id`='$einv_id' AND `tmt_id` IS NOT NULL AND `tmt_id` != '' AND `tmt_id` != '0'", ['ttl' => -1]);
    $synced_tts = [];
    // $synced_units already initialized above (before unit_from_invoice sync)
    $valid_tmt_ids = [];
    
    if (!empty($tmt_ids_query['data']) && is_array($tmt_ids_query['data'])) {
        foreach ($tmt_ids_query['data'] as $r) { $valid_tmt_ids[] = $r['tmt_id']; }
    }
    $sync_log['valid_tmt_ids'] = $valid_tmt_ids;
    $sync_log['valid_tmt_count'] = count($valid_tmt_ids);
    
    if (!empty($valid_tmt_ids)) {
        foreach ($tmt_ids_query['data'] as $tmt_row) {
            $tmt_id = $tmt_row['tmt_id'] ?? null;
            if (empty($tmt_id)) continue;
            
            // Get installment data
            $installment = jitquery_array(NULL, "`plt_tmt` WHERE `tmt_id`='$tmt_id'", -1);
            if (!is_array($installment)) {
                $sync_log['installments_not_found'][] = $tmt_id;
                continue;
            }
            
            $tts_id = $installment['tts_id'] ?? null;
            
            // 4b-3: Sync Contract and its Unit - only once per contract
            if (!empty($tts_id) && !in_array($tts_id, $synced_tts)) {
                $contract = jitquery_array(NULL, "`plt_tts` WHERE `tts_id`='$tts_id'", -1);
                
                if (is_array($contract)) {
                    // 4b-3a: Sync Unit from contract's are_id
                    $unit_are_id = $contract['are_id'] ?? null;
                    
                    if (!empty($unit_are_id) && !in_array($unit_are_id, $synced_units)) {
                        $unit = jitquery_array(NULL, "`plt_are` WHERE `are_id`='$unit_are_id'", -1);
                        if (is_array($unit)) {
                            $unit_odoo_id = $unit['odoo_unit_id'] ?? null;
                            if (empty($unit_odoo_id)) {
                                $unit_sync = odoo_sync_unit($unit_are_id);
                                $sync_log['units'][] = ['are_id' => $unit_are_id, 'status' => $unit_sync['status'], 'odoo_id' => $unit_sync['odoo_id'] ?? null];
                            } else {
                                $sync_log['units'][] = ['are_id' => $unit_are_id, 'status' => 'already_synced', 'odoo_id' => $unit_odoo_id];
                                erp_save_log('unit', intval($unit_are_id), 'create', 'already_synced', 'odoo', ['are_id' => $unit_are_id, 'odoo_unit_id' => $unit_odoo_id], null, null, 0);
                            }
                        } else {
                            $sync_log['units'][] = ['are_id' => $unit_are_id, 'status' => 'not_found_in_plt_are'];
                            erp_save_log('unit', intval($unit_are_id), 'create', 'error', 'odoo', ['are_id' => $unit_are_id], null, 'Unit not found in plt_are', 0);
                        }
                        $synced_units[] = $unit_are_id;
                    } elseif (empty($unit_are_id)) {
                        $sync_log['units'][] = ['tts_id' => $tts_id, 'status' => 'skipped_no_are_id'];
                        erp_save_log('unit', 0, 'create', 'skipped', 'odoo', ['tts_id' => $tts_id, 'reason' => 'contract has no are_id'], null, 'Contract has no are_id', 0);
                    }
                    
                    // 4b-3b: Sync Contract
                    $contract_odoo_id = $contract['odoo_subscription_id'] ?? null;
                    if (empty($contract_odoo_id)) {
                        $contract_sync = odoo_sync_contract($tts_id);
                        $sync_log['contracts'][] = ['tts_id' => $tts_id, 'status' => $contract_sync['status'], 'odoo_id' => $contract_sync['odoo_id'] ?? null];
                    } else {
                        $sync_log['contracts'][] = ['tts_id' => $tts_id, 'status' => 'already_synced', 'odoo_id' => $contract_odoo_id];
                        erp_save_log('contract', intval($tts_id), 'create', 'already_synced', 'odoo', ['tts_id' => $tts_id, 'odoo_subscription_id' => $contract_odoo_id], null, null, 0);
                    }
                    $synced_tts[] = $tts_id;
                } else {
                    $sync_log['contracts'][] = ['tts_id' => $tts_id, 'status' => 'not_found_in_plt_tts'];
                    erp_save_log('contract', intval($tts_id), 'create', 'error', 'odoo', ['tts_id' => $tts_id], null, 'Contract not found in plt_tts', 0);
                    $synced_tts[] = $tts_id;
                }
            }
            
            // 4b-4: Sync Installment
            $inst_odoo_id = $installment['odoo_invoice_id'] ?? null;
            if (empty($inst_odoo_id)) {
                $inst_sync = odoo_sync_installment($tmt_id);
                $sync_log['installments'][] = ['tmt_id' => $tmt_id, 'status' => $inst_sync['status'], 'odoo_id' => $inst_sync['odoo_id'] ?? null];
            } else {
                $sync_log['installments'][] = ['tmt_id' => $tmt_id, 'status' => 'already_synced', 'odoo_id' => $inst_odoo_id];
                erp_save_log('installment', intval($tmt_id), 'create', 'already_synced', 'odoo', ['tmt_id' => $tmt_id, 'odoo_invoice_id' => $inst_odoo_id], null, null, 0);
            }
        }
    }
    
    // Log the full cascade sync summary
    if (!empty($sync_log)) {
        erp_save_log(
            'cascade_sync',
            intval($einv_id),
            'auto_sync',
            'completed',
            'odoo',
            $sync_log,
            null,
            null,
            0
        );
    }
    
    // Step 5: Get invoice lines
    $all_lines = [];
    $lines_data = [];
    $lines_debug = [
        'einv_id' => $einv_id,
        'einv_id_type' => gettype($einv_id),
        'table_line' => $table_line,
        'method_used' => 'none',
    ];
    
    // Method 1: Same exact query as zatca.php (proven working)
    $lines = jitquery("select * from $table_line WHERE `einv_id`= $einv_id ");
    $lines_debug['m1_status'] = $lines['status'] ?? 'N/A';
    $lines_debug['m1_result_count'] = $lines['ResultCount'] ?? 'N/A';
    $lines_debug['m1_data_type'] = gettype($lines['data'] ?? null);
    $lines_debug['m1_data_count'] = isset($lines['data']) && is_array($lines['data']) ? count($lines['data']) : 0;
    $lines_debug['m1_error'] = $lines['error'] ?? null;
    
    if (!empty($lines['data']) && is_array($lines['data']) && count($lines['data']) > 0) {
        $lines_data = $lines['data'];
        $lines_debug['method_used'] = 'method1_jitquery';
    } else {
        // Method 2: Try with cache bypass (ttl=-1)
        $lines2 = jitquery("select * from `$table_line` WHERE `einv_id`= $einv_id ", ['ttl' => -1]);
        $lines_debug['m2_status'] = $lines2['status'] ?? 'N/A';
        $lines_debug['m2_result_count'] = $lines2['ResultCount'] ?? 'N/A';
        $lines_debug['m2_data_count'] = isset($lines2['data']) && is_array($lines2['data']) ? count($lines2['data']) : 0;
        $lines_debug['m2_error'] = $lines2['error'] ?? null;
        
        if (!empty($lines2['data']) && is_array($lines2['data']) && count($lines2['data']) > 0) {
            $lines_data = $lines2['data'];
            $lines_debug['method_used'] = 'method2_no_cache';
        } else {
            // Method 3: Direct database fallback
            $lines_debug['m3_attempted'] = true;
            try {
                global $dbname;
                $db_link_direct = CallDB();
                $lines_debug['m3_db_link'] = $db_link_direct ? 'OK' : 'FAILED';
                $lines_debug['m3_dbname'] = $dbname ?? 'NULL';
                
                if ($db_link_direct) {
                    @mysqli_query($db_link_direct, "USE `$dbname`");
                    $einv_id_safe = intval($einv_id);
                    $direct_sql = "SELECT * FROM `$table_line` WHERE `einv_id`=$einv_id_safe";
                    $lines_debug['m3_sql'] = $direct_sql;
                    $direct_result = @mysqli_query($db_link_direct, $direct_sql);
                    
                    if ($direct_result) {
                        $lines_data = mysqli_fetch_all($direct_result, MYSQLI_ASSOC);
                        $lines_debug['m3_data_count'] = count($lines_data);
                        $lines_debug['method_used'] = 'method3_direct_db';
                        mysqli_free_result($direct_result);
                    } else {
                        $lines_debug['m3_error'] = mysqli_error($db_link_direct);
                    }
                }
            } catch (Exception $e) {
                $lines_debug['m3_exception'] = $e->getMessage();
            }
        }
    }
    
    // Get unit name for invoice lines
    $invoice_unit_name = '';
    $inv_unit_are_id = $invoice['are_id'] ?? null;
    if (!empty($inv_unit_are_id)) {
        $inv_unit = @jitquery_array(NULL, "`plt_are` WHERE `are_id`='$inv_unit_are_id'", -1);
        if (is_array($inv_unit)) {
            $invoice_unit_name = $inv_unit['are_desc_fo'] ?? $inv_unit['are_desc_en'] ?? $inv_unit['are_desc_full'] ?? '';
        }
        // Fallback to plt_prop
        if (empty($invoice_unit_name)) {
            $inv_unit_prop = @jitquery_array(NULL, "`plt_prop` WHERE `are_id`='$inv_unit_are_id'", -1);
            if (is_array($inv_unit_prop)) {
                $invoice_unit_name = $inv_unit_prop['are_desc_fo'] ?? $inv_unit_prop['are_desc_en'] ?? $inv_unit_prop['are_desc_full'] ?? '';
            }
        }
    }
    
    // Process lines data into the format Odoo expects
    if (!empty($lines_data) && is_array($lines_data)) {
        // Save first line sample for debugging
        $lines_debug['first_line_keys'] = array_keys($lines_data[0]);
        $lines_debug['first_line_einv_id'] = $lines_data[0]['einv_id'] ?? 'N/A';
        
        foreach ($lines_data as $line) {
            // Get unit_name per line: try from line's tmt_id → installment → contract → unit
            $line_unit_name = $invoice_unit_name; // default to invoice-level unit
            $line_tmt_id = $line['tmt_id'] ?? null;
            if (!empty($line_tmt_id) && empty($line_unit_name)) {
                $line_inst = @jitquery_array(NULL, "`plt_tmt` WHERE `tmt_id`='$line_tmt_id'", -1);
                if (is_array($line_inst) && !empty($line_inst['tts_id'])) {
                    $line_contract = @jitquery_array(NULL, "`plt_tts` WHERE `tts_id`='" . $line_inst['tts_id'] . "'", -1);
                    if (is_array($line_contract) && !empty($line_contract['are_id'])) {
                        $line_unit = @jitquery_array(NULL, "`plt_are` WHERE `are_id`='" . $line_contract['are_id'] . "'", -1);
                        if (is_array($line_unit)) {
                            $line_unit_name = $line_unit['are_desc_fo'] ?? $line_unit['are_desc_en'] ?? '';
                        }
                    }
                }
            }
            
            $all_lines[] = [
                'srv_ar' => $line['srv_ar'] ?? $line['desc_ar'] ?? '',
                'tmt_id' => $line['tmt_id'] ?? '',
                'desc_ar' => $line['desc_ar'] ?? '',
                'desc_en' => $line['desc_en'] ?? $line['desc_ar'] ?? '',
                'unit_name' => $line_unit_name,
                'amt_st' => floatval($line['amt_st'] ?? 0),
                'qty' => floatval($line['qty'] ?? 1),
                'pct_tax' => floatval($line['pct_tax'] ?? 0),
                'amt_tax' => floatval($line['amt_tax'] ?? 0),
                'amt_tot' => floatval($line['amt_tot'] ?? 0)
            ];
        }
    }
    
    $lines_debug['final_lines_count'] = count($all_lines);
    
    // Save lines debug info to database so user can see it
    if (function_exists('erp_save_log')) {
        erp_save_log(
            'lines_debug',
            intval($einv_id),
            'fetch_lines',
            count($all_lines) > 0 ? 'found' : 'empty',
            'odoo',
            $lines_debug,
            null,
            count($all_lines) == 0 ? 'No lines found after all 3 methods' : null,
            0
        );
    }
    
    // Step 6: Prepare data for Odoo API
    // Use client_id as simat_client_id (Simat's primary key)
    $simat_client_id = strval($actual_client_id);
    
    // Get Ejar contract number for Customer Reference
    $ejar_contract_no = '';
    $tts_id = $invoice['tts_id'] ?? $invoice['contract_id'] ?? null;
    if (!empty($tts_id)) {
        $inv_contract = @jitquery_array(NULL, "`plt_tts` WHERE `tts_id`='$tts_id'", -1);
        if (is_array($inv_contract)) {
            // رقم العقد في إيجار - check each field for non-empty value (not just non-null)
            foreach (['tts_ref_no', 'tts_ref', 'tts_code'] as $_field) {
                if (!empty($inv_contract[$_field])) {
                    $ejar_contract_no = $inv_contract[$_field];
                    break;
                }
            }
        }
    }
    // Fallback: try from invoice fields directly
    if (empty($ejar_contract_no)) {
        foreach (['tts_ref_no', 'tts_ref', 'contract_no', 'ejar_no', 'tts_code'] as $_field) {
            if (!empty($invoice[$_field])) {
                $ejar_contract_no = $invoice[$_field];
                break;
            }
        }
    }
    
    
    $send_data = [
        'einv_id' => $invoice['einv_id'],
        // einv_number = رقم العقد في إيجار (لو موجود) أو رقم الفاتورة
        'einv_number' => !empty($ejar_contract_no) ? $ejar_contract_no : ($invoice['einv_number'] ?? ''),
        // رقم الفاتورة الأصلي من سمات
        'simat_invoice_number' => $invoice['einv_number'] ?? '',
        // رقم العقد في إيجار
        'eijar_contract_number' => $ejar_contract_no,
        'einv_date' => $invoice['einv_date'] ?? time(),
        'uuid' => $invoice['uuid'] ?? '',
        'simat_client_id' => $simat_client_id,
        'customer_id' => $simat_client_id,
        'partner_id' => intval($erp_id),
        'odoo_partner_id' => intval($erp_id),
        'erp_id' => intval($erp_id),
        'customer_name' => $client['entity_name'] ?? '',
        'customer_name_en' => $client['entity_name_en'] ?? '',
        'customer_vat' => $client['vat_number'] ?? '',
        'customer_mobile' => $client['contact_mobile'] ?? '',
        'customer_email' => $client['contact_email'] ?? '',
        // Customer Reference = رقم العقد في إيجار
        'ref' => $ejar_contract_no,
        'customer_reference' => $ejar_contract_no,
        // Payment Reference = رقم الفاتورة
        'payment_reference' => $invoice['einv_number'] ?? '',
        'amt_untax' => floatval($invoice['amt_untax'] ?? 0),
        'amt_tax' => floatval($invoice['amt_tax'] ?? 0),
        'amt_tot' => floatval($invoice['amt_tot'] ?? 0),
        'lines' => $all_lines
    ];
    
    // Step 7: Validate lines structure before sending (lenient validation - only log warnings)
    if (empty($all_lines) || count($all_lines) == 0) {
        if (function_exists('erp_log')) erp_log("Invoice #$einv_id has no lines - sending without items", 'odoo');
    } else {
        // Validate each line - but don't filter out, just log warnings
        $warnings = [];
        foreach ($all_lines as $line_index => $line) {
            $line_warnings = [];
            
            // Check for at least description or service name
            if (empty($line['srv_ar']) && empty($line['desc_ar']) && empty($line['desc_en'])) {
                $line_warnings[] = 'no description';
            }
            
            // Check for quantity (allow 0 but log warning)
            if (!isset($line['qty']) || $line['qty'] < 0) {
                $line_warnings[] = 'invalid qty';
            }
            
            // Check for amount (allow 0 but log warning)
            if (!isset($line['amt_tot']) && !isset($line['amt_st'])) {
                $line_warnings[] = 'no amount';
            }
            
            if (!empty($line_warnings)) {
                $warnings[] = "Line #" . ($line_index + 1) . ": " . implode(', ', $line_warnings);
            }
        }
        
        if (!empty($warnings) && function_exists('erp_log')) {
            erp_log("Invoice #$einv_id: " . count($warnings) . " lines have issues", 'odoo');
        }
    }
    
    // Step 8: Send to Odoo
    $url = $odoo_auth['url'] . '/api/simat/invoices/create';
    
    // Try file_get_contents first
    $response = null;
    $http_code = 0;
    $error_msg = '';
    
    $json_payload = json_encode($send_data, JSON_UNESCAPED_UNICODE);
    
    $context_options = [
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\n" .
                        "Accept: application/json\r\n" .
                        (!empty($odoo_auth['token']) ? "Authorization: Bearer " . $odoo_auth['token'] . "\r\n" : "") .
                        (!empty($odoo_auth['api_key']) ? "X-API-Key: " . $odoo_auth['api_key'] . "\r\n" : ""),
            'content' => $json_payload,
            'timeout' => 60,
            'ignore_errors' => true
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false
        ]
    ];
    
    $context = stream_context_create($context_options);
    $response = @file_get_contents($url, false, $context);
    
    if ($response !== false) {
        $http_code = 200;
    } else {
        // Fallback to cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json_payload);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Simaat-Odoo-Integration/1.0');
        
        $headers = ['Content-Type: application/json', 'Accept: application/json'];
        if (!empty($odoo_auth['token'])) $headers[] = 'Authorization: Bearer ' . $odoo_auth['token'];
        if (!empty($odoo_auth['api_key'])) $headers[] = 'X-API-Key: ' . $odoo_auth['api_key'];
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        $response = curl_exec($ch);
        $curl_error = curl_error($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($curl_error) $error_msg = $curl_error;
    }
    
    if (empty($response) && !empty($error_msg)) {
        if (function_exists('erp_log')) erp_log("Invoice #$einv_id connection error: $error_msg", 'odoo');
        
        // Save error log to database
        erp_save_log(
            'invoice',
            $einv_id,
            'post',
            'error',
            'odoo',
            $send_data,
            null,
            'Connection error: ' . $error_msg,
            0
        );
        
        return ['status' => 'ERROR', 'info' => 'Connection error: ' . $error_msg, 'title' => $lang['odoo'] ?? 'Odoo'];
    }
    
    $responseData = json_decode($response, true);
    
    if ($responseData === null && function_exists('erp_log')) {
        erp_log("Invoice #$einv_id: Failed to decode Odoo JSON response", 'odoo');
    }
    
    // Step 10: Check for HTTP errors
    if ($http_code >= 400 || (isset($responseData['errors']) && count($responseData['errors']) > 0)) {
        $error_msg = $responseData['errors'][0]['reason'] ?? $responseData['message'] ?? 'HTTP Error ' . $http_code;
        if (function_exists('erp_log')) erp_log("Invoice #$einv_id HTTP error: $error_msg", 'odoo');
        erp_save_log('invoice', intval($einv_id), 'post', 'error', 'odoo', $send_data, $responseData, 'HTTP Error: ' . $error_msg, intval($http_code));
        return ['status' => 'ERROR', 'info' => $error_msg, 'title' => $lang['odoo'] ?? 'Odoo'];
    }
    
    // Step 11: Check for "Partner not found" error in result - AUTO RE-SYNC & RETRY
    if (isset($responseData['result']['error'])) {
        $result_error = $responseData['result']['error'];
        
        // If Partner not found AND this is NOT a retry - auto re-sync and retry
        if (!$is_retry && strpos($result_error, 'Partner') !== false && strpos($result_error, 'not found') !== false) {
            if (function_exists('erp_log')) erp_log("Invoice #$einv_id: Partner not found - re-syncing client and retrying", 'odoo');
            
            // Clear client's erp_id and re-sync
            @jitquery("UPDATE `res_client` SET `erp_id` = NULL WHERE `client_id` = '$actual_client_id'", -1);
            
            $resync = odoo_sync_client($actual_client_id);
    
            if ($resync['status'] == 'OK' && !empty($resync['odoo_id'])) {
                // Retry the invoice post (with is_retry=true to prevent infinite loops)
                return odoo_post($einv_id, $table, $table_line, true);
            } else {
                if (function_exists('erp_log')) erp_log("Invoice #$einv_id: Re-sync failed - " . ($resync['info'] ?? 'Unknown'), 'odoo');
            }
    }
    
        // Save error log to database
        erp_save_log(
            'invoice',
            $einv_id,
            'post',
            'error',
            'odoo',
            $send_data,
            $responseData,
            $result_error,
            $http_code
        );
        
        return ['status' => 'ERROR', 'info' => $result_error, 'title' => $lang['odoo'] ?? 'Odoo'];
    }
    
    // Step 12: Parse invoice ID from response
    $odoo_invoice_id = null;
    
    // Check various response formats
    if (isset($responseData['result']['odoo_id'])) {
        $odoo_invoice_id = $responseData['result']['odoo_id'];
    } elseif (isset($responseData['result']['id'])) {
        $odoo_invoice_id = $responseData['result']['id'];
    } elseif (isset($responseData['result']['invoice_id'])) {
        $odoo_invoice_id = $responseData['result']['invoice_id'];
    } elseif (isset($responseData['odoo_id'])) {
        $odoo_invoice_id = $responseData['odoo_id'];
    } elseif (isset($responseData['id'])) {
        $odoo_invoice_id = $responseData['id'];
    } elseif (isset($responseData['data']['id'])) {
        $odoo_invoice_id = $responseData['data']['id'];
    }
    
    // Convert to integer
    if (is_numeric($odoo_invoice_id) && $odoo_invoice_id > 0) {
        $odoo_invoice_id = intval($odoo_invoice_id);
    }
    
    // Validate
    if (empty($odoo_invoice_id) || $odoo_invoice_id == 0) {
        if (function_exists('erp_log')) erp_log("Invoice #$einv_id: No valid invoice ID in Odoo response", 'odoo');
        erp_save_log('invoice', intval($einv_id), 'post', 'error', 'odoo', $send_data, $responseData, 'No valid invoice ID in Odoo response', intval($http_code));
        return ['status' => 'ERROR', 'info' => 'No valid invoice ID in Odoo response', 'title' => $lang['odoo'] ?? 'Odoo'];
    }
    
    // Step 13: Save to database
    $acl_status_code = ($table == 'plt_einv') ? '55630' : '55640';
    $sql = "UPDATE `$table` SET `erp_id` = '$odoo_invoice_id', `acl_status_code` = '$acl_status_code', `dt_updated` = UNIX_TIMESTAMP() WHERE `einv_id` = '$einv_id'";
    @jitquery($sql, -1);
    
    // Save log to database
    erp_save_log(
        'invoice',
        $einv_id,
        'post',
        'success',
        'odoo',
        $send_data,
        $responseData,
        null,
        $http_code
    );
    
    if (function_exists('erp_log')) erp_log("Invoice #$einv_id synced (erp_id=$odoo_invoice_id)", 'odoo');
    
    return ['status' => 'OK', 'info' => $lang['odoo_connected'] ?? 'Synced to Odoo successfully', 'title' => $lang['odoo'] ?? 'Odoo', 'odoo_id' => $odoo_invoice_id];
}


