<?php
/**
 * ═══════════════════════════════════════════════════════════════════════
 * ERP_Interface - Base Interface for ERP Systems
 * ═══════════════════════════════════════════════════════════════════════
 * 
 * This abstract class defines the interface that all ERP implementations
 * must follow (Odoo, SAP, Dynamics, etc.)
 * 
 * @version 2.0
 * @author Simaat Development Team
 * @date 2026-01-31
 */

abstract class ERP_Interface
{
    protected $system_name;
    protected $config;
    protected $is_connected = false;
    
    public function __construct($config = [])
    {
        $this->config = $config;
        $this->system_name = $this->getSystemName();
    }
    
    // Abstract Methods - must be implemented in each Class
    abstract protected function getSystemName();
    abstract public function connect();
    abstract public function testConnection();
    abstract public function syncClient($client_id, $auth_id = null);
    abstract public function searchClient($search_term, $search_by = 'vat_number');
    abstract public function getClient($erp_id);
    abstract public function postInvoice($einv_id, $table = 'plt_einv', $auth_id = null);
    abstract public function cancelInvoice($einv_id, $table = 'plt_einv');
    abstract public function getInvoice($erp_id);
    abstract public function syncProperty($property_id, $auth_id = null);
    abstract public function syncUnit($unit_id, $auth_id = null);
    abstract public function syncContract($contract_id, $auth_id = null);
    abstract public function syncInstallment($installment_id, $auth_id = null);
    abstract public function syncCostCenter($cost_center_id, $auth_id = null);
    abstract public function syncAccount($account_id, $auth_id = null);
    
    // Utility Methods
    public function getName() { 
        return $this->system_name; 
    }
    
    public function isConnected() { 
        return $this->is_connected; 
    }
    
    protected function logSync($entity_type, $entity_id, $operation, $request_data, $response_data, $status, $erp_id = null)
    {
        $log_data = [
            'timestamp' => date('Y-m-d H:i:s'),
            'system' => $this->system_name,
            'entity_type' => $entity_type,
            'entity_id' => $entity_id,
            'erp_id' => $erp_id,
            'operation' => $operation,
            'status' => $status
        ];
        error_log("ERP_SYNC [{$this->system_name}]: " . json_encode($log_data, JSON_UNESCAPED_UNICODE));
    }
    
    protected function saveERPId($table, $id_field, $entity_id, $erp_id)
    {
        if (empty($erp_id)) return false;
        $result = @jitquery("UPDATE `$table` SET `erp_id` = '$erp_id', `dt_updated` = UNIX_TIMESTAMP() WHERE `$id_field` = '$entity_id'", -1);
        return ($result['status'] ?? '') == 'OK';
    }
    
    protected function updateEntityStatus($table, $id_field, $entity_id, $erp_id, $status_code)
    {
        $result = @jitquery("UPDATE `$table` SET `erp_id` = '$erp_id', `acl_status_code` = '$status_code', `dt_updated` = UNIX_TIMESTAMP() WHERE `$id_field` = '$entity_id'", -1);
        return ($result['status'] ?? '') == 'OK';
    }
    
    protected function parseERPId($response, $id_field = 'id')
    {
        $paths = [
            ['data', 'result', $id_field], ['data', 'result', 'id'],
            ['data', $id_field], ['data', 'id'],
            ['result', $id_field], ['result', 'id'],
            [$id_field], ['id']
        ];
        foreach ($paths as $path) {
            $value = $response;
            foreach ($path as $key) {
                if (!isset($value[$key])) break;
                $value = $value[$key];
            }
            if (is_numeric($value) && $value > 0) return intval($value);
        }
        return null;
    }
}





