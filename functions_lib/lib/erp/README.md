# 📦 ERP System Library

نظام موحد للتعامل مع أنظمة ERP المختلفة (Odoo, SAP, Dynamics)

## 📁 الهيكل

```
erp/
├── autoload.php          # Autoloader للكلاسات
├── init.php              # ملف التهيئة الرئيسي
├── README.md             # هذا الملف
└── src/
    ├── Interface/
    │   └── ERP_Interface.php    # الواجهة الأساسية
    ├── Factory/
    │   └── ERP_Factory.php      # مصنع الأنظمة
    ├── Systems/                 # تطبيقات الأنظمة (Odoo, SAP, etc.)
    └── Functions.php            # الوظائف الموحدة
```

## 🚀 الاستخدام

### التحميل الأساسي

```php
require_once 'functions_lib/lib/erp/init.php';
```

### أمثلة الاستخدام

#### مزامنة عميل
```php
$result = erp_sync_client($client_id);
if ($result['status'] == 'OK') {
    echo "تمت المزامنة بنجاح";
}
```

#### إرسال فاتورة
```php
$result = erp_post_invoice($einv_id, 'plt_einv');
if ($result['status'] == 'OK') {
    echo "تم إرسال الفاتورة: " . $result['erp_id'];
}
```

#### البحث عن عميل
```php
$result = erp_search_client('1234567890', 'vat_number');
```

#### الحصول على النظام النشط
```php
$active_system = erp_get_active_system();
echo "النظام النشط: " . $active_system; // odoo, sap, dynamics
```

#### اختبار الاتصال
```php
$result = erp_test_connection();
if ($result['status'] == 'OK') {
    echo "الاتصال ناجح";
}
```

## 🔧 الوظائف المتاحة

### وظائف العملاء
- `erp_sync_client($client_id, $auth_id = NULL)` - مزامنة عميل
- `erp_search_client($search_term, $search_by = 'vat_number')` - البحث عن عميل
- `erp_get_client($erp_id)` - الحصول على بيانات عميل

### وظائف الفواتير
- `erp_post_invoice($einv_id, $table = 'plt_einv', $auth_id = NULL)` - إرسال فاتورة
- `erp_cancel_invoice($einv_id, $table = 'plt_einv')` - إلغاء فاتورة
- `erp_get_invoice($erp_id)` - الحصول على فاتورة

### وظائف المزامنة
- `erp_sync_property($property_id, $auth_id = NULL)` - مزامنة عقار
- `erp_sync_unit($unit_id, $auth_id = NULL)` - مزامنة وحدة
- `erp_sync_contract($contract_id, $auth_id = NULL)` - مزامنة عقد
- `erp_sync_installment($installment_id, $auth_id = NULL)` - مزامنة قسط
- `erp_sync_cost_center($cost_center_id, $auth_id = NULL)` - مزامنة مركز تكلفة
- `erp_sync_account($account_id, $auth_id = NULL)` - مزامنة حساب

### وظائف إدارة النظام
- `erp_get_active_system()` - الحصول على النظام النشط
- `erp_get_active_config()` - الحصول على إعدادات النظام النشط
- `erp_set_active_system($system)` - تفعيل نظام معين
- `erp_get_available_systems($active_only = false)` - الحصول على الأنظمة المتاحة
- `erp_test_connection($system = null)` - اختبار الاتصال
- `erp_get_system_name()` - الحصول على اسم النظام

### وظائف السجلات
- `erp_log($message, $provider = null)` - تسجيل رسالة
- `erp_save_log(...)` - حفظ سجل في قاعدة البيانات

## 📝 ملاحظات

- النظام يدعم حالياً: Odoo, SAP, Dynamics
- يتم تحديد النظام النشط من جدول `erp_integrations`
- جميع الوظائف ترجع array مع `status` و `info`
- في حالة الخطأ، `status` يكون `'ERROR'`

## 🔄 الترقية من النسخة القديمة

إذا كنت تستخدم النسخة القديمة (`functions_lib/erp.php`):

```php
// القديم
require_once 'functions_lib/erp.php';

// الجديد
require_once 'functions_lib/lib/erp/init.php';
```

الوظائف نفسها، فقط تغيير مسار التحميل!





