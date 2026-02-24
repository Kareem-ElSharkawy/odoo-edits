<?php
declare(strict_types=1);

/**
 * Odoo ERP Implementation
 * @version 2.1
 * @date 2026-02-11
 */

if (!class_exists('ERP_Interface')) {
    require_once __DIR__ . '/lib/erp.php';
}
require_once __DIR__ . '/odoo.php';

class ERP_Odoo extends ERP_Interface
{
    protected function getSystemName()
    {
        return 'Odoo';
    }

    public function connect()
    {
        global $lang;

        $this->is_connected = true;

        return [
            'status' => 'OK',
            'info' => $lang['odoo_connected'] ?? 'Connected to Odoo successfully',
        ];
    }

    public function testConnection()
    {
        global $lang;

        try {
            $response = odoo_request('api/test_connection', 'GET', [], null);

            if (($response['status'] ?? null) === 'OK') {
                $this->is_connected = true;

                return [
                    'status' => 'OK',
                    'info' => $lang['odoo_test_ok'] ?? 'Odoo connection test successful',
                ];
            }

            return [
                'status' => 'ERROR',
                'info' => $response['info'] ?? ($lang['odoo_connect_failed'] ?? 'Odoo connection failed'),
                'http_code' => $response['http_code'] ?? null,
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'ERROR',
                'info' => ($lang['connection_error'] ?? 'Connection error') . ': ' . $e->getMessage(),
            ];
        }
    }

    public function syncClient($client_id, $auth_id = null)
    {
        return odoo_sync_client($client_id, $auth_id);
    }

    public function searchClient($search_term, $search_by = 'vat_number')
    {
        return odoo_request('api/search_partner', 'POST', [
            'search_term' => $search_term,
            'search_by' => $search_by,
        ], null);
    }

    public function getClient($erp_id)
    {
        return odoo_request('api/get_partner', 'POST', [
            'partner_id' => $erp_id,
        ], null);
    }

    public function postInvoice($einv_id, $table = 'plt_einv', $auth_id = null)
    {
        // DEBUG logs only if constant enabled
        if (defined('ERP_ODOO_DEBUG') && ERP_ODOO_DEBUG) {
            error_log("ERP_ODOO: postInvoice() einv_id={$einv_id} table={$table} auth_id=" . ($auth_id ?? 'NULL'));
        }

        $table_line = ($table === 'plt_einv') ? 'plt_einv_line' : 'scm_einv_line';

        $result = odoo_post($einv_id, $table, $table_line, false);

        if (defined('ERP_ODOO_DEBUG') && ERP_ODOO_DEBUG) {
            error_log("ERP_ODOO: odoo_post() result=" . json_encode($result, JSON_UNESCAPED_UNICODE));
        }

        return $result;
    }

    public function cancelInvoice($einv_id, $table = 'plt_einv')
    {
        global $lang;

        // Defensive escaping for query text (because we don't know jitquery_array binding support)
        $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $table);
        $safeId = addslashes((string) $einv_id);

        $invoice = jitquery_array(NULL, "`{$safeTable}` WHERE `einv_id`='{$safeId}'", -1);

        if (!is_array($invoice) || empty($invoice['erp_id'])) {
            return [
                'status' => 'ERROR',
                'info' => $lang['einv_not_linked'] ?? 'Invoice not found or not linked to Odoo',
            ];
        }

        return odoo_request('api/simat/invoices/cancel', 'POST', [
            'invoice_id' => $invoice['erp_id'],
        ], null);
    }

    public function getInvoice($erp_id)
    {
        return odoo_request('api/simat/invoices/get', 'POST', [
            'invoice_id' => $erp_id,
        ], null);
    }

    public function syncProperty($property_id, $auth_id = null)
    {
        return odoo_sync_property($property_id, $auth_id);
    }

    public function syncUnit($unit_id, $auth_id = null)
    {
        return odoo_sync_unit($unit_id, $auth_id);
    }

    public function syncContract($contract_id, $auth_id = null)
    {
        return odoo_sync_contract($contract_id, $auth_id);
    }

    public function syncInstallment($installment_id, $auth_id = null)
    {
        return odoo_sync_installment($installment_id, $auth_id);
    }

    public function syncCostCenter($cost_center_id, $auth_id = null)
    {
        return odoo_sync_cost_center($cost_center_id, $auth_id);
    }

    public function syncAccount($account_id, $auth_id = null)
    {
        return odoo_sync_account($account_id, $auth_id);
    }
}
