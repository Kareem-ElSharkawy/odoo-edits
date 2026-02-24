-- ============================================================
-- ERP Integration Language Keys
-- ============================================================
-- Add these keys to the `lang` table in your database
-- Table structure: `lang` (lang_code, lang_ar, lang_en)
-- ============================================================

-- Odoo ERP Keys
INSERT INTO `lang` (`lang_code`, `lang_ar`, `lang_en`) VALUES
('odoo', 'أودو', 'Odoo'),
('odoo_no_auth', 'مصادقة Odoo غير موجودة', 'Odoo authentication not found'),
('odoo_connected', 'تم الاتصال بنجاح', 'Connected successfully'),
('odoo_connected_error', 'خطأ في الاتصال', 'Connection error'),
('odoo_test_ok', 'اختبار الاتصال بـ Odoo ناجح', 'Odoo connection test successful'),
('odoo_connect_failed', 'فشل الاتصال بـ Odoo', 'Odoo connection failed'),
('odoo_client_synced', 'تم مزامنة العميل بنجاح', 'Client synced successfully'),
('odoo_property_synced', 'تم مزامنة العقار بنجاح', 'Property synced successfully'),
('odoo_unit_synced', 'تم مزامنة الوحدة بنجاح', 'Unit synced successfully'),
('odoo_contract_synced', 'تم مزامنة العقد بنجاح', 'Contract synced successfully'),
('odoo_installment_synced', 'تم مزامنة القسط بنجاح', 'Installment synced successfully'),
('odoo_invoice_synced', 'تم مزامنة الفاتورة بنجاح', 'Invoice synced successfully'),
('odoo_invoice_updated', 'تم تحديث الفاتورة في Odoo', 'Invoice updated in Odoo'),
('odoo_already_synced', 'تم المزامنة مسبقاً إلى Odoo', 'Invoice already synced to Odoo'),
('odoo_no_client_data', 'لا يمكن مزامنة الفاتورة إلى Odoo: لم يتم العثور على بيانات العميل', 'Cannot sync invoice to Odoo: No client data found.'),
('odoo_client_create_failed', 'فشل إنشاء العميل في قاعدة البيانات', 'Failed to create client in database'),
('odoo_client_sync_failed', 'فشل مزامنة العميل الجديد إلى Odoo', 'Failed to sync new client to Odoo'),
('odoo_cost_center_synced', 'تم مزامنة مركز التكلفة بنجاح', 'Cost center synced successfully'),
('odoo_account_synced', 'تم مزامنة الحساب بنجاح', 'Account synced successfully'),
('odoo_installments_fetched', 'تم جلب الأقساط بنجاح', 'Installments fetched successfully'),
('odoo_cost_centers_fetched', 'تم جلب مراكز التكلفة بنجاح', 'Cost centers fetched successfully'),

-- SAP ERP Keys
('sap_connected', 'تم الاتصال بـ SAP Business One بنجاح', 'Connected to SAP Business One successfully'),
('sap_connect_failed', 'فشل الاتصال بـ SAP', 'SAP connection failed'),
('sap_disconnected', 'تم تسجيل الخروج من SAP', 'Logged out from SAP'),
('sap_test_ok', 'اختبار الاتصال بـ SAP Business One ناجح', 'SAP Business One connection test successful'),
('sap_test_failed', 'فشل اختبار الاتصال بـ SAP', 'SAP connection test failed'),

-- Common ERP Keys
('connection_error', 'خطأ في الاتصال', 'Connection error'),
('unknown_error', 'خطأ غير معروف', 'Unknown error'),
('not_connected', 'غير متصل', 'Not connected'),
('client_not_found', 'العميل غير موجود', 'Client not found'),
('client_synced', 'تم مزامنة العميل بنجاح', 'Client synced successfully'),
('client_sync_failed', 'فشل مزامنة العميل', 'Client sync failed'),
('client_not_in_erp', 'العميل غير موجود في ERP. يرجى مزامنة العميل أولاً', 'Client not found in ERP. Please sync client first'),
('einv_not_found', 'الفاتورة غير موجودة', 'Invoice not found'),
('einv_not_linked', 'الفاتورة غير موجودة أو غير مربوطة بـ ERP', 'Invoice not found or not linked to ERP'),
('einv_already_synced', 'الفاتورة مرسلة مسبقاً', 'Invoice already sent'),
('einv_synced', 'تم إرسال الفاتورة بنجاح', 'Invoice posted successfully'),
('einv_post_failed', 'فشل إرسال الفاتورة', 'Invoice posting failed'),
('einv_cancelled', 'تم إلغاء الفاتورة', 'Invoice cancelled'),
('einvoice', 'فاتورة', 'Invoice'),
('sync_not_available', 'المزامنة غير متاحة حالياً', 'Sync not available yet'),
('cost_center_not_found', 'مركز التكلفة غير موجود', 'Cost center not found'),
('cost_center_synced', 'تم مزامنة مركز التكلفة بنجاح', 'Cost center synced successfully'),
('account_not_found', 'الحساب غير موجود', 'Account not found'),
('account_synced', 'تم مزامنة الحساب بنجاح', 'Account synced successfully'),
('product_not_found', 'المنتج غير موجود', 'Product not found'),
('product_synced', 'تم مزامنة المنتج بنجاح', 'Product synced successfully'),
('invoice_not_found', 'الفاتورة غير موجودة', 'Invoice not found'),
('verification_unknown', 'فشل التحقق', 'Verification failed'),

-- ERP Factory Keys
('erp_not_supported', 'نظام ERP غير مدعوم', 'ERP system not supported'),
('erp_settings_not_found', 'لم يتم العثور على إعدادات النظام', 'System settings not found'),
('erp_activated', 'تم تفعيل نظام ERP', 'ERP system activated'),
('erp_activation_failed', 'فشل في تفعيل النظام', 'Failed to activate system'),
('test_failed', 'فشل الاختبار', 'Test failed')

ON DUPLICATE KEY UPDATE 
    `lang_ar` = VALUES(`lang_ar`),
    `lang_en` = VALUES(`lang_en`);






