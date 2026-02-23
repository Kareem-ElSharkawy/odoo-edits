# 🔗 دليل تكامل Odoo مع نظام سمات
## Odoo Integration Guide for Simaat System

> **آخر تحديث:** 23 فبراير 2026  
> **الإصدار:** 2.1  
> **الحالة:** جاهز للإنتاج ✅

---

## 📋 جدول المحتويات

1. [نظرة عامة](#نظرة-عامة)
2. [المتطلبات الأساسية](#المتطلبات-الأساسية)
3. [خطوات التثبيت](#خطوات-التثبيت)
4. [الوظائف المتاحة](#الوظائف-المتاحة)
5. [الاستخدام](#الاستخدام)
6. [أكواد الحالات](#أكواد-الحالات)
7. [استكشاف الأخطاء](#استكشاف-الأخطاء)
8. [الملفات المعدلة](#الملفات-المعدلة)

---

## 🎯 نظرة عامة

### ما هو التكامل؟
هذا التكامل يربط نظام سمات (Simaat) مع نظام Odoo ERP، مما يسمح بـ:
- ✅ مزامنة بيانات العملاء تلقائياً
- ✅ إرسال الفواتير إلى Odoo
- ✅ مزامنة العقود والوحدات والأقساط
- ✅ إنشاء عملاء جدد تلقائياً عند الحاجة
- ✅ حفظ معرفات Odoo في الحقل الموحد `erp_id`

### كيف يعمل؟
```
┌─────────────┐         ┌──────────────┐         ┌─────────────┐
│   Simaat    │ ──────► │ Odoo API     │ ──────► │    Odoo     │
│  (Invoice)  │  POST   │ Integration  │  Sync   │    ERP      │
└─────────────┘         └──────────────┘         └─────────────┘
       │                                                  │
       │ ◄────────────── erp_id ────────────────────┘
       │                (Save ID back)
       ▼
  Update Status
   (55630/55640)
```

---

## 🔧 المتطلبات الأساسية

### 1. البيئة التقنية
- ✅ PHP 7.4+
- ✅ MySQL 5.7+
- ✅ cURL enabled
- ✅ JSON extension

### 2. معلومات الاتصال بـ Odoo
```php
// في ملف الإعدادات (config أو odoo.php)
$odoo_api_url = 'http://88.223.92.71:3050';
$odoo_username = 'your_username';
$odoo_api_key = 'your_api_key';
```

### 3. الجداول المطلوبة
يجب أن تكون الجداول التالية موجودة:
- `res_client` - العملاء
- `plt_einv` - الفواتير الإلكترونية (إيجارات)
- `scm_einv` - الفواتير الإلكترونية (صيانة/مبيعات)
- `plt_prop` / `acc_property` - العقارات
- `plt_are` / `acc_unit` - الوحدات
- `plt_tts` - العقود
- `plt_tmt` - الأقساط/المستحقات

**ملاحظة:** إذا كان مشروعك يستخدم `acc_property` أو `acc_unit`، أضف `erp_id` لتلك الجداول. الكود يستخدم `plt_prop` و `plt_are` لمزامنة العقارات والوحدات.

---

## 🚀 خطوات التثبيت

### الخطوة 1️⃣: إضافة حقل `erp_id` لقاعدة البيانات

قم بتشغيل السكريبت التالي **مرة واحدة فقط**:

#### 📄 ملف: `setup_odoo.sql`

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

-- ✅ تم بنجاح!
SELECT 'Setup completed successfully!' AS Status;
```

**⚠️ ملاحظة:** إذا كانت بعض الجداول لا تحتوي على الأعمدة المذكورة بعد `AFTER`، قم بحذف جزء `AFTER` من الأمر.

#### جدول سجلات التكامل `erp_integ_log`

شغّل الـ SQL التالي لإنشاء جدول السجلات:

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

#### مفاتيح اللغة `erp_lang_keys`

شغّل الـ SQL التالي لإضافة مفاتيح اللغة (يتطلب جدول `lang` بهيكل: `lang_code`, `lang_ar`, `lang_en`):

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

**بديل مبسط لحقول erp_id** (إذا لم تستخدم setup_odoo.php):
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

### الخطوة 2️⃣: تحديث metadata للحقول

قم بتشغيل السكريبت PHP التالي **مرة واحدة فقط**:

#### 📄 ملف: `setup_odoo.php`

```php
<?php
/**
 * Setup Odoo Integration - Add erp_id field metadata
 * تشغيل مرة واحدة فقط لإضافة بيانات الحقل erp_id
 */

require_once 'functions/connect.php';
require_once 'functions/functions.php';

// قائمة الجداول والحقول
$tables = [
    'res_client',
    'plt_einv',
    'scm_einv',
    'acc_property',
    'acc_unit',
    'plt_tmt',
    'plt_installments'
];

echo "🚀 Starting Odoo Integration Setup...\n\n";

// 1. إضافة الحقول
echo "Step 1: Adding erp_id columns...\n";
foreach ($tables as $table) {
    $check = @jitquery("SHOW COLUMNS FROM `$table` LIKE 'erp_id'", -1);
    
    if (empty($check)) {
        echo "  → Adding erp_id to $table...";
        $result = @jitquery("ALTER TABLE `$table` ADD COLUMN `erp_id` INT(11) NULL DEFAULT NULL COMMENT 'Odoo ID'", -1);
        echo " ✅\n";
    } else {
        echo "  ✓ erp_id already exists in $table\n";
    }
}

echo "\nStep 2: Syncing field metadata...\n";

// 2. إضافة metadata للحقول
foreach ($tables as $table) {
    // التحقق من وجود الحقل في acl_field
    $existing = @jitquery("SELECT * FROM `acl_field` WHERE `acl_table`='$table' AND `acl_field`='erp_id'", -1);
    
    if (empty($existing)) {
        echo "  → Adding metadata for $table.erp_id...";
        
        $field_data = [
            'acl_table' => $table,
            'acl_field' => 'erp_id',
            'acl_field_label' => 'Odoo ID',
            'acl_field_label_ar' => 'معرف أودو',
            'acl_field_type' => 'int',
            'acl_field_length' => 11,
            'acl_field_default' => NULL,
            'acl_field_null' => 1,
            'acl_field_index' => 0,
            'acl_field_auto_increment' => 0,
            'acl_field_comment' => 'Odoo ERP ID',
            'acl_status_code' => '10110'
        ];
        
        $insert_result = @insert('acl_field', $field_data);
        
        if ($insert_result['status'] == 'OK') {
            echo " ✅\n";
        } else {
            echo " ⚠️ Warning: " . ($insert_result['error'] ?? 'Unknown error') . "\n";
        }
    } else {
        echo "  ✓ Metadata already exists for $table.erp_id\n";
    }
}

echo "\n✅ Setup completed successfully!\n";
echo "\nNext steps:\n";
echo "1. Test client sync: odoo_sync_client(CLIENT_ID)\n";
echo "2. Test invoice post: odoo_post(EINV_ID, TABLE_NAME)\n";
echo "\n";
?>
```

**لتشغيل السكريبت:**
```bash
php setup_odoo.php
```

أو قم بفتحه في المتصفح:
```
http://your-domain.com/setup_odoo.php
```

**⚠️ مهم:** احذف الملف بعد التشغيل الناجح لأسباب أمنية!

---

### الخطوة 3️⃣: التحقق من الملفات المطلوبة

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
├── setup_odoo.php            ← إعداد حقول erp_id و metadata
├── setup_erp_system.sql      ← إعداد sys_config و erp_integ_log (لـ Multi-ERP)
├── admin_erp_settings.php    ← لوحة إعدادات ERP (اختياري)
└── functions/
    ├── connect.php           ← اتصال قاعدة البيانات
    └── functions.php         ← الوظائف الأساسية
```

#### ملفات SQL للتنفيذ (مرة واحدة):
| الملف | الغرض |
|-------|-------|
| `functions_lib/erp_integ_log.sql` | إنشاء جدول سجلات التكامل `erp_integ_log` |
| `functions_lib/erp_lang_keys.sql` | إضافة مفاتيح اللغة لرسائل Odoo و ERP |
| `setup_erp_system.sql` | إعداد sys_config و erp_integ_log (لنظام Multi-ERP) |

---

## 📚 الوظائف المتاحة

### 1️⃣ مزامنة العملاء - `odoo_sync_client()`

```php
/**
 * مزامنة بيانات عميل مع Odoo
 * Sync client data with Odoo
 * 
 * @param int $client_id - معرف العميل
 * @param int $auth_id - معرف المستخدم (اختياري)
 * @return array - النتيجة
 */
$result = odoo_sync_client(80);

// النتيجة
[
    'status' => 'OK',
    'info' => 'Client synced successfully',
    'odoo_id' => 67,  // معرف Odoo
    'operation' => 'created' // أو 'updated'
]
```

**مثال الاستخدام:**
```php
$client_id = 80;
$result = odoo_sync_client($client_id);

if ($result['status'] == 'OK') {
    echo "تم المزامنة! Odoo ID: " . $result['odoo_id'];
} else {
    echo "خطأ: " . $result['info'];
}
```

---

### 2️⃣ إرسال الفواتير - `odoo_post()`

```php
/**
 * إرسال فاتورة إلى Odoo
 * Post invoice to Odoo
 * 
 * @param int $einv_id - معرف الفاتورة
 * @param string $table - اسم الجدول (plt_einv أو scm_einv)
 * @param string $table_line - جدول سطور الفاتورة (plt_einv_line أو scm_einv_line)
 * @return array - النتيجة
 */
$result = odoo_post(1062, 'plt_einv');

// النتيجة
[
    'status' => 'OK',
    'info' => 'Invoice posted to Odoo successfully',
    'odoo_id' => 31,  // معرف الفاتورة في Odoo
    'invoice_number' => 'INV/2026/0031'
]
```

**مثال الاستخدام:**
```php
$einv_id = 1062;
$table = 'plt_einv';  // أو 'scm_einv'

$result = odoo_post($einv_id, $table);

if ($result['status'] == 'OK') {
    echo "تم إرسال الفاتورة! رقم الفاتورة في Odoo: " . $result['invoice_number'];
} else {
    echo "خطأ: " . $result['info'];
}
```

---

### 3️⃣ مزامنة العقار - `odoo_sync_property()`

```php
// المعامل: are_id (معرف العقار في plt_prop)
$result = odoo_sync_property($are_id);
```

---

### 4️⃣ مزامنة الوحدة - `odoo_sync_unit()`

```php
// المعامل: are_id (معرف الوحدة في plt_are أو plt_prop)
$result = odoo_sync_unit($are_id);
```

---

### 5️⃣ مزامنة العقد - `odoo_sync_contract()`

```php
// المعامل: tts_id (معرف العقد في plt_tts)
$result = odoo_sync_contract($tts_id);
```

---

### 6️⃣ مزامنة القسط - `odoo_sync_installment()`

```php
// المعامل: tmt_id (معرف القسط/المستحق في plt_tmt)
$result = odoo_sync_installment($tmt_id);
```

---

### 7️⃣ تأكيد وإرسال الفاتورة - `odoo_confirm()`

```php
// بديل لـ odoo_post - يستدعيه تلقائياً
$result = odoo_confirm($einv_id, 'plt_einv', 'plt_einv_line');
```

---

## 🎯 الاستخدام

### سيناريو 1: إرسال فاتورة لعميل موجود

```php
// الفاتورة لها عميل (customer_id موجود)
$einv_id = 1062;
$result = odoo_post($einv_id, 'plt_einv');

// النظام سيفعل:
// 1. يتحقق من وجود erp_id للعميل
// 2. إذا لم يكن موجود، يسينك العميل أولاً
// 3. يرسل الفاتورة
// 4. يحفظ erp_id للفاتورة
// 5. يحدث الحالة إلى 55630 (plt_einv) أو 55640 (scm_einv)
```

---

### سيناريو 2: إرسال فاتورة بدون عميل (customer_id = 0)

```php
// الفاتورة ليس لها عميل
$einv_id = 1070;
$result = odoo_post($einv_id, 'plt_einv');

// النظام سيفعل:
// 1. يكتشف أن customer_id = 0
// 2. ينشئ عميل جديد تلقائياً من بيانات الفاتورة:
//    - الاسم من customer_ar
//    - الاسم بالإنجليزي من customer_en
//    - الرقم الضريبي من customer_vat
//    - الجوال من customer_mobile
// 3. يسينك العميل الجديد مع Odoo
// 4. يربط الفاتورة بالعميل الجديد
// 5. يرسل الفاتورة
// 6. يحفظ erp_id
```

**⚠️ ملاحظة:** الفاتورة يجب أن تحتوي على `customer_ar` (اسم العميل) على الأقل، وإلا ستفشل العملية.

---

### سيناريو 3: فاتورة مرتجع (Credit Note)

```php
// إنشاء فاتورة مرتجع
$original_einv_id = 1062;
$return_data = einv_return($original_einv_id, 'plt_einv');

// النظام سيفعل:
// 1. ينسخ بيانات الفاتورة الأصلية
// 2. لا ينسخ erp_id (يبقى NULL)
// 3. عند إرسال فاتورة المرتجع لـ Odoo، سيأخذ معرف جديد
```

---

## 📊 أكواد الحالات (Status Codes)

### حالات الفواتير

| الكود | الحالة | الوصف | الاستخدام |
|------|--------|-------|-----------|
| `44110` | مسودة | Draft | الفاتورة قيد الإنشاء |
| `44115` | جاهزة للإرسال | Ready | جاهزة لإرسالها لـ ZATCA |
| `44120` | مؤكدة | Confirmed/Posted | تم تأكيدها في النظام |
| `44140` | ❌ قديم | Synced (Old) | **لا تستخدم** |
| `53840` | ❌ قديم | Synced (Old) | **لا تستخدم** |
| `55630` | ✅ مرتبطة بـ Odoo | Synced (plt_einv) | **استخدم هذا لـ plt_einv** |
| `55640` | ✅ مرتبطة بـ Odoo | Synced (scm_einv) | **استخدم هذا لـ scm_einv** |

### كيف يتم التحديث تلقائياً؟

```php
// في دالة odoo_post():
if ($response['status'] == 'OK' && !empty($odoo_invoice_id)) {
    // تحديد كود الحالة المناسب
    $acl_status_code = ($table == 'plt_einv') ? '55630' : '55640';
    
    // التحديث
    @jitquery("
        UPDATE `$table`
        SET
            `erp_id` = '$odoo_invoice_id',
            `acl_status_code` = '$acl_status_code',
            `dt_updated` = UNIX_TIMESTAMP()
        WHERE `einv_id` = '$einv_id'
    ", -1);
}
```

---

## 🔍 استكشاف الأخطاء

### المشكلة 1: "No Fields Found in Update Function"

**السبب:** الحقل `erp_id` غير موجود في قاعدة البيانات أو في `acl_field`.

**الحل:**
```bash
# شغل السكريبتات مرة أخرى
php setup_odoo.php
```

---

### المشكلة 2: "Invalid value for entity_type"

**السبب:** قيمة `entity_type` في جدول `res_client` غير مقبولة في Odoo.

**القيم المقبولة:**
- `individual` - فرد
- `company` - شركة

**الحل:** النظام يصلح هذا تلقائياً الآن:
```php
// في odoo_sync_client()
if ($entity_type == 'charity' || $entity_type == 'organization') {
    $entity_type = 'company';
} else {
    $entity_type = 'individual';
}
```

---

### المشكلة 3: "Invalid value for entity_idtype"

**السبب:** قيمة `entity_idtype` غير مقبولة.

**القيم المقبولة:**
- `nid` - الهوية الوطنية
- `iqama` - الإقامة
- `passport` - جواز السفر
- `cr` - السجل التجاري

**الحل:** النظام يصلح هذا تلقائياً:
```php
if (!in_array($entity_idtype, ['nid', 'iqama', 'passport', 'cr'])) {
    $entity_idtype = 'nid'; // افتراضي
}
```

---

### المشكلة 4: "Invalid value for cal_type"

**السبب:** قيمة `cal_type` غير مقبولة.

**القيم المقبولة:**
- `cal_gr` - ميلادي (Gregorian)
- `cal_hj` - هجري (Hijri)

**الحل:** النظام يصلح هذا تلقائياً:
```php
$cal_type = $client['cal_type'] ?? 'cal_gr';
if (!in_array($cal_type, ['cal_gr', 'cal_hj'])) {
    $cal_type = 'cal_gr'; // افتراضي: ميلادي
}
```

---

### المشكلة 5: "Partner with Simat ID 0 not found"

**السبب:** الفاتورة ليس لها عميل (`customer_id = 0`).

**الحل:** النظام ينشئ عميل تلقائياً الآن من بيانات الفاتورة.

**المتطلب:** الفاتورة يجب أن تحتوي على:
- `customer_ar` (اسم العميل بالعربي) - **إجباري**
- `customer_en` (اسم العميل بالإنجليزي) - اختياري
- `customer_vat` (الرقم الضريبي) - اختياري
- `customer_mobile` (الجوال) - اختياري

---

### المشكلة 6: فاتورة المرتجع تأخذ نفس `erp_id`

**السبب:** كان الحقل يُنسخ في `einv_return()`.

**الحل:** تم إضافة `erp_id` لقائمة `$reset` في جميع ملفات `einv_return`:
```php
$reset = [
    'einv_id',
    'einv_date',
    'uuid',
    'create_by',
    'update_by',
    'dt_created',
    'dt_updated',
    'zatca_pdf_link',
    'erp_id',      // ← جديد
    'odoo_invoice_id'   // ← جديد
];
```

---

## 🗂️ الملفات المعدلة

### 1. `functions_lib/odoo.php`
**التعديلات:**
- ✅ إضافة فحص `erp_id` في `odoo_post()`
- ✅ مزامنة العميل تلقائياً إذا لم يكن موجود
- ✅ إنشاء عميل جديد تلقائياً إذا كان `customer_id = 0`
- ✅ حفظ `erp_id` للعميل والفاتورة
- ✅ تحديث `acl_status_code` إلى `55630` (plt_einv) أو `55640` (scm_einv)
- ✅ معالجة صيغ مختلفة من استجابات Odoo API
- ✅ Validation تلقائي لـ `entity_type`, `entity_idtype`, `cal_type`
- ✅ استخدام `erp_log()` بدلاً من `error_log` (للمراسلات المهمة فقط)

### 2. `functions_libX/odoo.php`
نفس التعديلات في الملف الاحتياطي.

### 3. `functions_lib/acc.php`

**الوظيفة المعدّلة:** `einv_return($einv_id, $table, $table_line)`

**الغرض:** إنشاء فاتورة مرتجع (Credit Note) من فاتورة أصلية.

**التعديل المطلوب:** إضافة `erp_id` و `odoo_invoice_id` إلى مصفوفة `$reset` حتى لا تُنسخ قيمتهما من الفاتورة الأصلية إلى فاتورة المرتجع. فاتورة المرتجع يجب أن تحصل على معرف جديد في Odoo.

**قبل التعديل:**
```php
$reset = ['einv_id','einv_date','uuid','create_by','update_by','dt_created','dt_updated','zatca_pdf_link'];
```

**بعد التعديل:**
```php
$reset = ['einv_id','einv_date','uuid','create_by','update_by','dt_created','dt_updated','zatca_pdf_link','erp_id','odoo_invoice_id'];
```

**الموقع في الملف:** السطر 35 تقريباً، داخل دالة `einv_return()`.

### 4. `functions_libX/acc.php`
نفس التعديل في دالة `einv_return()`.

---

## 📝 سجلات النظام (Logs)

### نظام التسجيل
يستخدم التكامل دالة **`erp_log()`** للتسجيل (من `functions_lib/erp.php`)، و**`erp_save_log()`** لحفظ السجلات في جدول `erp_integ_log`.

### كيف تفحص السجلات؟

```bash
# السجلات تظهر في error_log (erp_log يستخدم error_log)
# Linux/Mac
tail -f /path/to/error_log

# Windows (Laragon)
tail -f C:\laragon\www\logs\error.log
```

### استعلام سجلات قاعدة البيانات

```sql
-- آخر عمليات التكامل
SELECT * FROM erp_integ_log 
WHERE provider = 'odoo' 
ORDER BY dt_created DESC LIMIT 50;
```

### نموذج لسجل ناجح (erp_log):

```log
ERP[ODOO]: SYNC [client #164] create → success (odoo_id=69)
ERP[ODOO]: Client #164 synced (erp_id=69)
ERP[ODOO]: Invoice #1070 synced (erp_id=43)
```

---

## ✅ الاختبار النهائي

### 1. اختبار مزامنة عميل:

```php
// اختبار عميل موجود
$client_id = 80;
$result = odoo_sync_client($client_id);
print_r($result);

// تحقق من erp_id في قاعدة البيانات
$client = jitquery_array(NULL, "`res_client` WHERE `client_id`='$client_id'", -1);
echo "Odoo ID: " . $client['erp_id'];
```

### 2. اختبار إرسال فاتورة:

```php
// إرسال فاتورة إيجار
$einv_id = 1062;
$result = odoo_post($einv_id, 'plt_einv');
print_r($result);

// تحقق من erp_id في قاعدة البيانات
$invoice = jitquery_array(NULL, "`plt_einv` WHERE `einv_id`='$einv_id'", -1);
echo "Odoo Invoice ID: " . $invoice['erp_id'];
echo "Status Code: " . $invoice['acl_status_code']; // يجب أن يكون 55630
```

### 3. اختبار فاتورة بدون عميل:

```php
// إنشاء فاتورة بدون customer_id
// تأكد أن الفاتورة لها customer_ar على الأقل
$einv_id = 1070; // فاتورة customer_id = 0
$result = odoo_post($einv_id, 'plt_einv');

// يجب أن ينشئ عميل جديد تلقائياً
print_r($result);
```

---

## 🎓 نصائح مهمة

### ✅ افعل:
- ✔️ تأكد من تشغيل `setup_odoo.php` مرة واحدة فقط
- ✔️ تابع سجلات التكامل (`erp_integ_log` و `erp_log` في error_log)
- ✔️ اختبر على بيانات تجريبية أولاً
- ✔️ احفظ نسخة احتياطية من قاعدة البيانات قبل التعديلات

### ❌ لا تفعل:
- ✘ لا تعدل قيم `erp_id` يدوياً في قاعدة البيانات
- ✘ لا تحذف الحقل `erp_id` بعد الإعداد
- ✘ لا تستخدم أكواد الحالة القديمة (`44140`, `53840`)
- ✘ لا تنسخ `erp_id` بين السجلات

---

## 📞 الدعم

### إذا واجهت مشكلة:

1. **افحص السجلات أولاً:**
   ```bash
   tail -f error_log
   ```

2. **تحقق من قاعدة البيانات:**
   ```sql
   -- تحقق من وجود الحقل
   SHOW COLUMNS FROM res_client LIKE 'erp_id';
   
   -- تحقق من البيانات
   SELECT client_id, entity_name, erp_id FROM res_client WHERE client_id = 80;
   ```

3. **اختبر الاتصال بـ Odoo:**
   ```php
   $result = odoo_test();  // أو odoo_request('api/simat/cost-centers/list', 'GET', []);
   print_r($result);
   ```

---

## 🎉 الخلاصة

بعد إتمام هذه الخطوات، سيكون لديك:

- ✅ نظام متكامل مع Odoo ERP
- ✅ مزامنة تلقائية للعملاء والفواتير
- ✅ إنشاء تلقائي للعملاء عند الحاجة
- ✅ حفظ معرفات Odoo في حقل موحد
- ✅ تحديث تلقائي لأكواد الحالات
- ✅ معالجة قوية للأخطاء

---

**تم بحمد الله! 🚀**

> إذا كانت لديك أي أسئلة أو مشاكل، راجع قسم [استكشاف الأخطاء](#استكشاف-الأخطاء) أو افحص سجلات النظام.

---

## 📎 ملاحظات إضافية

### التوافق مع نظام Multi-ERP
يمكنك استخدام الدوال الموحدة من `erp.php` بدلاً من استدعاء Odoo مباشرة:
```php
erp_sync_client($client_id);      // بديل لـ odoo_sync_client
erp_post_invoice($einv_id, 'plt_einv');  // بديل لـ odoo_post
```

### إعداد الاتصال بـ Odoo
يتم جلب إعدادات الاتصال من جدول `mw_odoo_auth` أو `odoo_auth` (الحقول: `base_url`/`auth_host`, `token`, `api_key`).








