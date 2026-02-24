-- ERP Integration Log Table
-- Single table for all ERP providers (Odoo, SAP, Dynamics)
-- Similar to ejar_integ_log structure

CREATE TABLE IF NOT EXISTS `erp_integ_log` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `entity_type` varchar(50) NOT NULL COMMENT 'invoice, client, property, contract, etc.',
  `entity_id` int(11) NOT NULL COMMENT 'einv_id, client_id, are_id, tts_id, etc.',
  `operation` varchar(50) NOT NULL COMMENT 'post, sync, create, update, delete',
  `status` varchar(20) NOT NULL COMMENT 'success, error, pending',
  `provider` varchar(20) NOT NULL DEFAULT 'odoo' COMMENT 'odoo, sap, dynamics',
  `http_code` int(11) DEFAULT 0,
  `error_message` text DEFAULT NULL,
  `request_data` longtext DEFAULT NULL COMMENT 'JSON encoded request data',
  `response_data` longtext DEFAULT NULL COMMENT 'JSON encoded response data',
  `dt_created` int(11) NOT NULL,
  PRIMARY KEY (`log_id`),
  KEY `entity_type_id` (`entity_type`, `entity_id`),
  KEY `provider` (`provider`),
  KEY `status` (`status`),
  KEY `dt_created` (`dt_created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

