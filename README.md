# تكامل Odoo — مرجع رفع التعديلات

آخر تحديث: 23 فبراير 2026 | الإصدار 2.1

---

## نظرة عامة

- ربط سمات مع Odoo: مزامنة عملاء، إرسال فواتير، عقود/وحدات/أقساط.
- إنشاء عميل تلقائي عند الحاجة؛ حفظ معرف Odoo في `erp_id`.
- التحديث التلقائي: `acl_status_code` → 55630 (plt_einv) أو 55640 (scm_einv).

---

## المتطلبات

- PHP 7.4+, MySQL 5.7+, cURL, JSON.

### إعدادات Odoo
```php
// في ملف الإعدادات (config أو odoo.php)
$odoo_api_url = 'http://88.223.92.71:3050';
$odoo_username = 'your_username';
$odoo_api_key = 'your_api_key';
```

### الجداول
`erp_integrations` (إلزامي — إعدادات ERP). إن لم يوجد `mw_odoo_auth`/`odoo_auth` يُستخدم السجل من `erp_integrations` حيث `provider='odoo'` و `active=1`.  
باقي الجداول: `res_client`, `plt_einv`, `scm_einv`, `plt_prop`/`acc_property`, `plt_are`/`acc_unit`, `plt_tts`, `plt_tmt`.

---

## التثبيت (مرة واحدة)

### 1. إضافة `erp_id`

#### إضافة حقول `erp_id` (SQL)

```sql
-- ===================================================================
-- إضافة حقل erp_id لجميع الجداول المطلوبة
-- Add erp_id field to all required tables
-- ===================================================================

-- 1. جدول العملاء (Clients)
ALTER TABLE `res_client` 
ADD COLUMN `erp_id` INT(11) NULL DEFAULT NULL COMMENT 'Odoo Partner ID' 
AFTER `client_id`;

-- 2. جدول الفواتير الإلكترونية - إيجارات (E-Invoices - Rent)
ALTER TABLE `plt_einv` 
ADD COLUMN `erp_id` INT(11) NULL DEFAULT NULL COMMENT 'Odoo Invoice ID' 
AFTER `einv_id`;

-- 3. جدول الفواتير الإلكترونية - صيانة/مبيعات (E-Invoices - Maintenance/Sales)
ALTER TABLE `scm_einv` 
ADD COLUMN `erp_id` INT(11) NULL DEFAULT NULL COMMENT 'Odoo Invoice ID' 
AFTER `einv_id`;

-- 4. جدول العقارات (Properties)
ALTER TABLE `acc_property` 
ADD COLUMN `erp_id` INT(11) NULL DEFAULT NULL COMMENT 'Odoo Property ID' 
AFTER `property_id`;

-- 5. جدول الوحدات (Units)
ALTER TABLE `acc_unit` 
ADD COLUMN `erp_id` INT(11) NULL DEFAULT NULL COMMENT 'Odoo Unit ID' 
AFTER `unit_id`;

-- 6. جدول العقود (Contracts)
ALTER TABLE `plt_tmt` 
ADD COLUMN `erp_id` INT(11) NULL DEFAULT NULL COMMENT 'Odoo Contract ID' 
AFTER `tmt_id`;

-- 7. جدول الأقساط (Installments)
ALTER TABLE `plt_installments` 
ADD COLUMN `erp_id` INT(11) NULL DEFAULT NULL COMMENT 'Odoo Installment ID' 
AFTER `installment_id`;

-- 8. إضافة فهرس (Index) لتحسين الأداء
ALTER TABLE `res_client` ADD INDEX `idx_erp_id` (`erp_id`);
ALTER TABLE `plt_einv` ADD INDEX `idx_erp_id` (`erp_id`);
ALTER TABLE `scm_einv` ADD INDEX `idx_erp_id` (`erp_id`);

```

إذا كان العمود المذكور بعد `AFTER` غير موجود في الجدول، احذف جزء `AFTER` من الأمر.

#### جدول `erp_integrations`
إلزامي. إعدادات الاتصال (Odoo/SAP). يُقرأ من `ERP_Factory` و `odoo.php` عند غياب `mw_odoo_auth`/`odoo_auth`.

**إنشاء الجدول:**

```sql
CREATE TABLE `erp_integrations` (
  `erp_integration_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `erp_integration_title` varchar(60) NOT NULL COMMENT 'عنوان الربط',
  `provider` varchar(30) DEFAULT NULL COMMENT 'odoo, sap, dynamics',
  `erp_integration_code` varchar(60) NOT NULL COMMENT 'كود فريد: odoo, sap, ...',
  `erp_api_url` varchar(255) DEFAULT NULL COMMENT 'رابط API',
  `company_name` varchar(255) DEFAULT NULL,
  `erp_username` varchar(255) DEFAULT NULL,
  `erp_password` varchar(255) DEFAULT NULL,
  `api_secret` varchar(255) DEFAULT NULL COMMENT 'لـ Odoo: Token أو API Key',
  `active` tinyint(1) UNSIGNED NOT NULL DEFAULT 1 COMMENT '1 = النظام النشط',
  `created_by` int(10) UNSIGNED DEFAULT 0,
  `updated_by` int(10) UNSIGNED DEFAULT 0,
  `dt_created` int(10) UNSIGNED DEFAULT 0,
  `dt_updated` int(10) UNSIGNED DEFAULT 0,
  `dt_last_sync` int(10) UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`erp_integration_id`),
  KEY `idx_code` (`erp_integration_code`),
  KEY `idx_active` (`active`),
  KEY `idx_erp_api_url` (`erp_api_url`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**إدراج سجل Odoo (مثال — غيّر القيم حسب بيئتك):**

```sql
INSERT INTO `erp_integrations` (
  `erp_integration_title`, `provider`, `erp_integration_code`,
  `erp_api_url`, `api_secret`, `active`, `dt_created`, `dt_updated`
) VALUES (
  'ربط أودوو', 'odoo', 'odoo',
  'http://YOUR_ODOO_HOST:PORT', 'YOUR_API_SECRET_OR_TOKEN',
  1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()
);
```

لـ Odoo: `provider='odoo'`, `erp_api_url` = عنوان القاعدة، `api_secret` = Token/API Key، `active=1`.

---

#### جدول `erp_integ_log`

```sql
-- جدول سجلات التكامل مع ERP
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
```

#### مفاتيح اللغة `erp_lang_keys` (جدول `lang`: `lang_code`, `lang_ar`, `lang_en`)

```sql
-- مفاتيح اللغة لـ Odoo و ERP
INSERT INTO `lang` (`lang_code`, `lang_ar`, `lang_en`) VALUES
('odoo', 'أودو', 'Odoo'),
('odoo_no_auth', 'مصادقة Odoo غير موجودة', 'Odoo authentication not found'),
('odoo_connected', 'تم الاتصال بنجاح', 'Connected successfully'),
('odoo_connected_error', 'خطأ في الاتصال', 'Connection error'),
('odoo_client_synced', 'تم مزامنة العميل بنجاح', 'Client synced successfully'),
('odoo_property_synced', 'تم مزامنة العقار بنجاح', 'Property synced successfully'),
('odoo_unit_synced', 'تم مزامنة الوحدة بنجاح', 'Unit synced successfully'),
('odoo_contract_synced', 'تم مزامنة العقد بنجاح', 'Contract synced successfully'),
('odoo_installment_synced', 'تم مزامنة القسط بنجاح', 'Installment synced successfully'),
('odoo_invoice_synced', 'تم مزامنة الفاتورة بنجاح', 'Invoice synced successfully'),
('odoo_already_synced', 'تم المزامنة مسبقاً إلى Odoo', 'Invoice already synced to Odoo'),
('odoo_cost_centers_fetched', 'تم جلب مراكز التكلفة بنجاح', 'Cost centers fetched successfully'),
('invoice_not_found', 'الفاتورة غير موجودة', 'Invoice not found'),
('verification_unknown', 'فشل التحقق', 'Verification failed')
ON DUPLICATE KEY UPDATE 
  `lang_ar` = VALUES(`lang_ar`),
  `lang_en` = VALUES(`lang_en`);
```

**بديل مبسط لحقول erp_id** (جداول plt_*):
```sql
ALTER TABLE `res_client` ADD COLUMN `erp_id` INT(11) NULL;
ALTER TABLE `plt_einv` ADD COLUMN `erp_id` INT(11) NULL;
ALTER TABLE `scm_einv` ADD COLUMN `erp_id` INT(11) NULL;
ALTER TABLE `plt_prop` ADD COLUMN `erp_id` INT(11) NULL;
ALTER TABLE `plt_are` ADD COLUMN `erp_id` INT(11) NULL;
ALTER TABLE `plt_tts` ADD COLUMN `erp_id` INT(11) NULL;
ALTER TABLE `plt_tmt` ADD COLUMN `erp_id` INT(11) NULL;
```

---

### الخطوة 2: التحقق من الملفات المطلوبة

تأكد من وجود الملفات التالية:

```
your-project/
├── functions_lib/
│   ├── odoo.php              ← الملف الرئيسي (تكامل Odoo)
│   ├── erp.php               ← دوال ERP الموحدة (erp_log, erp_save_log)
│   ├── ERP_Odoo.php          ← كلاس Odoo لنظام Multi-ERP
│   ├── acc.php               ← يحتوي على einv_return
│   ├── erp_integ_log.sql     ← جدول سجلات التكامل
│   ├── erp_lang_keys.sql     ← مفاتيح اللغة للرسائل
│   └── lib/erp/              ← مكتبة ERP (Factory, Interface, Functions)
├── functions_libX/
│   ├── odoo.php              ← نسخة احتياطية
│   └── acc.php               ← نسخة احتياطية
├── plt_einv.php              ← صفحة الفواتير + إجراء erp_post_invoice
├── setup_erp_system.sql      ← إعداد sys_config و erp_integ_log (لـ Multi-ERP)
├── admin_erp_settings.php    ← لوحة إعدادات ERP (اختياري)
└── functions/
    ├── connect.php           ← اتصال قاعدة البيانات
    └── functions.php        ← الوظائف الأساسية
```

SQL للتنفيذ مرة واحدة: محتوى `erp_integ_log`, `erp_lang_keys`, و SQL حقول `erp_id` (موجود أعلاه في الدليل).

---

## الدوال

### مزامنة عميل — `odoo_sync_client($client_id)`
```php
$result = odoo_sync_client(80);
// ['status'=>'OK', 'odoo_id'=>67, 'operation'=>'created'|'updated']
```

### إرسال فاتورة — `erp_post_invoice($einv_id, $table)` (الموصى به)
```php
$result = erp_post_invoice(1062, 'plt_einv');
// ['status'=>'OK', 'erp_id'=>31, 'odoo_id'=>31, 'invoice_number'=>'...']
```
**When adding a new action to confirm/send invoice to ERP, use the function `erp_post_invoice`.**  
عند إضافة action جديد لتأكيد أو إرسال الفاتورة إلى ERP استخدم الدالة `erp_post_invoice`.

`odoo_post($einv_id, $table)` لا يزال يعمل (توافق قديم).

### باقي الدوال
- `odoo_sync_property($are_id)` — عقار
- `odoo_sync_unit($are_id)` — وحدة
- `odoo_sync_contract($tts_id)` — عقد
- `odoo_sync_installment($tmt_id)` — قسط
- `odoo_confirm($einv_id, 'plt_einv', 'plt_einv_line')` — يستدعي `erp_post_invoice` داخلياً

---

## سيناريوهات سريعة

**فاتورة لها عميل:** `erp_post_invoice($einv_id, 'plt_einv')` — يتحقق من `erp_id` عميل، يزامن إن لزم، يرسل، يحدّث `erp_id` و `acl_status_code`.

**فاتورة بدون عميل (customer_id=0):** النظام ينشئ عميلاً من بيانات الفاتورة ويزامنه ثم يرسل الفاتورة. مطلوب `customer_ar` على الأقل.

**مرتجع (Credit Note):** `einv_return()` لا ينسخ `erp_id` (موجود في `$reset`). فاتورة المرتجع تأخذ `erp_id` جديد عند الإرسال.

---

## أكواد الحالات

| الكود | الاستخدام |
|------|-----------|
| 44110 | مسودة |
| 44115 | جاهزة لـ ZATCA |
| 44120 | مؤكدة |
| 44140, 53840 | قديم — لا تستخدم |
| 55630 | plt_einv مرتبط Odoo |
| 55640 | scm_einv مرتبط Odoo |

عند نجاح الإرسال: يُحدَّث `erp_id` و `acl_status_code` في الجدول.

---

## استكشاف الأخطاء

- **No Fields Found:** غائب `erp_id` — نفّذ SQL الحقول وأضف في `acl_field` إن لزم.
- **Invalid entity_type:** مقبول `individual` / `company`؛ الكود يصلح charity/organization → company.
- **Invalid entity_idtype:** مقبول nid, iqama, passport, cr؛ افتراضي nid.
- **Invalid cal_type:** مقبول cal_gr, cal_hj؛ افتراضي cal_gr.
- **Partner Simat ID 0:** النظام ينشئ عميلاً من الفاتورة؛ مطلوب `customer_ar`.
- **مرتجع نفس erp_id:** `erp_id` مضاف لـ `$reset` في `einv_return()` (acc.php / acc.phpX).

---

## الملفات المعدلة

**odoo.php (و odoo.php في libX):** فحص/مزامنة عميل، إنشاء عميل عند customer_id=0، حفظ erp_id، تحديث acl_status_code (55630/55640)، معالجة استجابات API، تصحيح entity_type/entity_idtype/cal_type، استخدام erp_log بدل error_log.

**acc.php (و libX):** في `einv_return()` أضف `erp_id` إلى `$reset` حتى لا يُنسخ للمرتجع.

```php
$reset=['einv_id','einv_date','uuid','create_by','update_by','dt_created','dt_updated','zatca_pdf_link','erp_id'];
// 		if($einv['acl_status_code']!=44120)
```

### 5. plt_einv.php

**التعديلات:**
- إضافة action **`erp_post_invoice`** في بداية الصفحة: عند `$do == 'erp_post_invoice'` يتم استدعاء `erp_post_invoice($id, 'plt_einv')` بعد تحميل `functions_lib/lib/erp.php`.
- توحيد actions القديمة: عند `$do == 'odoo_sync_invoice'` أو `odoo_confirm` أو `odoo_post` يتم التوجيه إلى `erp_post_invoice($id, 'plt_einv')` بدلاً من استدعاء Odoo مباشرة.
- الإبقاء على `$do == 'odoo_test'` لاختبار الاتصال.

**مقتطف الكود:**
```php
// ERP POST INVOICE - Handle FIRST
if($do == 'erp_post_invoice') {
    require_once('functions_lib/lib/erp.php');
    if (function_exists('erp_post_invoice')) {
        $json_callback = erp_post_invoice($id, 'plt_einv');
    }
    // ...
}
```

---

## السجلات

`erp_log()` و `erp_save_log()` — السجلات في error_log وجدول `erp_integ_log`. استعلام: `SELECT * FROM erp_integ_log WHERE provider='odoo' ORDER BY dt_created DESC LIMIT 50`.

---

## تحقق بعد الرفع

- مزامنة عميل: `odoo_sync_client(80)` ثم التحقق من `res_client.erp_id`.
- إرسال فاتورة: `erp_post_invoice(1062, 'plt_einv')` ثم التحقق من `plt_einv.erp_id` و `acl_status_code` = 55630.
- اتصال: `odoo_test()` أو `odoo_request('api/simat/cost-centers/list', 'GET', [])`.

---

## ملاحظات

- الدوال الموصى بها: `erp_post_invoice()`, `erp_sync_client()`. `odoo_post()` و `odoo_sync_client()` تعمل للتوافق.
- الإعداد: من `mw_odoo_auth` أو `odoo_auth` أو `erp_integrations` (provider=odoo, active=1).








