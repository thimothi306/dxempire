<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PushTokenController;
use App\Http\Controllers\Procurement\PurchaseOrderController;
use App\Http\Controllers\Procurement\ReceivingController;
use App\Http\Controllers\Procurement\SupplierController;
use App\Http\Controllers\Analytics\AnalyticsController;
use App\Http\Controllers\CRM\DealerController;
use App\Http\Controllers\CRM\LeadController;
use App\Http\Controllers\CRM\SupportTicketController;
use App\Http\Controllers\Inventory\BinController;
use App\Http\Controllers\Inventory\InventoryController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Finance\ExpenseController;
use App\Http\Controllers\HR\AttendanceController;
use App\Http\Controllers\Integrations\LogisticsController;
use App\Http\Controllers\HR\EmployeeController;
use App\Http\Controllers\HR\PayrollController;
use App\Http\Controllers\Finance\FinanceController;
use App\Http\Controllers\Finance\InvoiceController;
use App\Http\Controllers\Finance\VendorPaymentController;
use App\Http\Controllers\Orders\OrderController;
use App\Http\Controllers\Orders\RazorpayWebhookController;
use App\Http\Controllers\QC\QcController;
use App\Http\Controllers\Sales\SalesHierarchyController;
use App\Http\Controllers\Sales\OfferController;
use App\Http\Controllers\Inventory\PetiTransferController;
use App\Http\Controllers\Retail\CustomerAuthController;
use App\Http\Controllers\Retail\RetailController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Mobile\AuthController as MobileAuthController;
use App\Http\Controllers\Mobile\HierarchyController as MobileHierarchyController;
use App\Http\Controllers\Mobile\DashboardController as MobileDashboardController;
use App\Http\Controllers\Partner\PartnerAuthController;
use App\Http\Controllers\Partner\PartnerPortalController;
use App\Http\Controllers\Partner\PartnerCatalogController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // ── Public routes ─────────────────────────────────────────────────────
    Route::middleware('throttle:10,1')->group(function () {
        Route::post('/auth/send-otp',    [AuthController::class, 'sendOtp']);
        Route::post('/auth/verify-otp',  [AuthController::class, 'verifyOtp']);
        Route::post('/auth/admin/login', [AuthController::class, 'adminLogin']);
    });

    // ── Authenticated routes ───────────────────────────────────────────────
    Route::middleware(['auth:sanctum', 'throttle:120,1'])->group(function () {

        // Auth
        Route::get('/auth/me',       [AuthController::class, 'me']);
        Route::post('/auth/refresh', [AuthController::class, 'refresh']);
        Route::post('/auth/logout',  [AuthController::class, 'logout']);

        // Push tokens
        Route::post('/users/push-token',   [PushTokenController::class, 'register']);
        Route::delete('/users/push-token', [PushTokenController::class, 'unregister']);

        // In-app notification inbox
        Route::get('/notifications',               [NotificationController::class, 'index']);
        Route::patch('/notifications/read-all',    [NotificationController::class, 'markAllRead']);
        Route::get('/notifications/unread-count',  [NotificationController::class, 'unreadCount']);
        Route::patch('/notifications/{notification}', [NotificationController::class, 'markRead']);

        // ── Super admin only ───────────────────────────────────────────────
        Route::middleware('role:super_admin')->prefix('admin')->group(function () {

            // User management
            Route::get('roles',                       [UserController::class, 'roles']);
            Route::get('users',                       [UserController::class, 'index']);
            Route::post('users',                      [UserController::class, 'store']);
            Route::get('users/{user}',                [UserController::class, 'show']);
            Route::put('users/{user}',                [UserController::class, 'update']);
            Route::put('users/{user}/role',           [UserController::class, 'assignRole']);
            Route::post('users/{user}/deactivate',    [UserController::class, 'deactivate']);
            Route::post('users/{user}/activate',      [UserController::class, 'activate']);

            // Audit log
            Route::get('audit-logs',                  [AuditLogController::class, 'index']);

            // Settings management
            Route::get('settings',                    [SettingsController::class, 'index']);
            Route::put('settings',                    [SettingsController::class, 'bulkUpdate']);
            Route::get('settings/{key}',              [SettingsController::class, 'show']);
            Route::put('settings/{key}',              [SettingsController::class, 'update']);
        });

        // ── Procurement ───────────────────────────────────────────────────
        Route::middleware('role:super_admin,warehouse_staff')->group(function () {
            Route::get('suppliers',                       [SupplierController::class, 'index']);
            Route::post('suppliers',                      [SupplierController::class, 'store']);
            Route::get('suppliers/{supplier}',            [SupplierController::class, 'show']);
            Route::put('suppliers/{supplier}',            [SupplierController::class, 'update']);
            Route::delete('suppliers/{supplier}',         [SupplierController::class, 'destroy']);

            Route::get('purchase-orders',                          [PurchaseOrderController::class, 'index']);
            Route::post('purchase-orders',                         [PurchaseOrderController::class, 'store']);
            Route::get('purchase-orders/{purchaseOrder}',          [PurchaseOrderController::class, 'show']);
            Route::put('purchase-orders/{purchaseOrder}',          [PurchaseOrderController::class, 'update']);
            Route::post('purchase-orders/{purchaseOrder}/receive', [ReceivingController::class, 'storeForPo']);

            Route::prefix('procurement')->group(function () {
                Route::post('receive',                    [ReceivingController::class, 'store']);
                Route::get('history',                     [ReceivingController::class, 'history']);
            });
        });

        // ── Inventory ─────────────────────────────────────────────────────
        Route::prefix('inventory')->group(function () {
            Route::get('/',               [InventoryController::class, 'index']);
            Route::get('availability',    [InventoryController::class, 'availability']);
            Route::get('imei/{imei}',     [InventoryController::class, 'lookupByImei']);
            Route::get('export',          [InventoryController::class, 'export'])
                ->middleware('role:super_admin,warehouse_staff');
            Route::get('{product}',       [InventoryController::class, 'show']);
        });

        Route::middleware('role:super_admin,warehouse_staff')->prefix('bins')->group(function () {
            Route::get('/',               [BinController::class, 'index']);
            Route::post('/',              [BinController::class, 'store']);
            Route::post('move',           [BinController::class, 'move']);
            Route::get('{bin}/products',  [BinController::class, 'products']);
        });

        // ── QC ────────────────────────────────────────────────────────────
        Route::middleware('role:super_admin,qc_engineer,warehouse_staff')->prefix('qc')->group(function () {
            Route::get('pending',                       [QcController::class, 'pending']);
            Route::post('grade',                        [QcController::class, 'grade']);
            Route::get('records',                       [QcController::class, 'records']);
            Route::get('stats',                         [QcController::class, 'stats']);
            Route::get('refurbishment',                 [QcController::class, 'refurbishment']);
            Route::post('refurbishment',                [QcController::class, 'sendToRefurbishment']);
            Route::put('refurbishment/{product}',       [QcController::class, 'completeRefurbishment']);
        });

        // ── CRM & Sales ───────────────────────────────────────────────────
        Route::middleware('role:super_admin,sales')->group(function () {
            Route::get('leads',                      [LeadController::class, 'index']);
            Route::post('leads',                     [LeadController::class, 'store']);
            Route::get('leads/{lead}',               [LeadController::class, 'show']);
            Route::put('leads/{lead}',               [LeadController::class, 'update']);
            Route::put('leads/{lead}/stage',         [LeadController::class, 'updateStage']);
            Route::post('leads/{lead}/convert',      [LeadController::class, 'convert']);

            Route::get('dealers',                    [DealerController::class, 'index']);
            Route::get('dealers/{dealer}',           [DealerController::class, 'show']);
            Route::get('dealers/{dealer}/ledger',    [DealerController::class, 'ledger']);
        });

        Route::middleware('role:super_admin')->group(function () {
            Route::post('dealers',                   [DealerController::class, 'store']);
            Route::put('dealers/{dealer}/kyc',       [DealerController::class, 'updateKyc']);
            Route::put('dealers/{dealer}/credit',    [DealerController::class, 'updateCredit']);
        });

        // Support tickets — any authenticated user can create, sales/admin can manage
        Route::post('support/tickets',               [SupportTicketController::class, 'store']);
        Route::middleware('role:super_admin,sales')->group(function () {
            Route::get('support/tickets',            [SupportTicketController::class, 'index']);
            Route::put('support/tickets/{supportTicket}', [SupportTicketController::class, 'update']);
        });

        // ── Orders ────────────────────────────────────────────────────────
        Route::prefix('orders')->group(function () {
            // Any authenticated user can view orders they are associated with
            Route::get('/',                              [OrderController::class, 'index']);
            Route::get('/{order}',                       [OrderController::class, 'show']);
            Route::get('/{order}/payments',              [OrderController::class, 'payments']);
            Route::get('/{order}/invoice/download',      [OrderController::class, 'downloadInvoice']);

            // Dealers / sales can create orders
            Route::middleware('role:super_admin,sales,b2b_partner')->group(function () {
                Route::post('/',                         [OrderController::class, 'store']);
            });

            // Warehouse staff manage fulfillment lifecycle
            Route::middleware('role:super_admin,warehouse_staff')->group(function () {
                Route::post('/{order}/picking',          [OrderController::class, 'startPicking']);
                Route::post('/{order}/packing-complete', [OrderController::class, 'completePacking']);
                Route::post('/{order}/shipment',         [OrderController::class, 'createShipment']);
                Route::post('/{order}/dispatch',         [OrderController::class, 'dispatchOrder']);
                Route::post('/{order}/deliver',          [OrderController::class, 'deliver']);
                Route::post('/{order}/return',           [OrderController::class, 'processReturn']);
            });

            // Only super_admin can approve or cancel
            Route::middleware('role:super_admin')->group(function () {
                Route::post('/{order}/approve',          [OrderController::class, 'approve']);
                Route::post('/{order}/cancel',           [OrderController::class, 'cancel']);
            });
        });

        // ── Finance ───────────────────────────────────────────────────────
        Route::middleware('role:super_admin,accounts')->group(function () {

            // Invoices
            Route::prefix('finance/invoices')->group(function () {
                Route::get('/',                              [InvoiceController::class, 'index']);
                Route::get('/{invoice}',                     [InvoiceController::class, 'show']);
                Route::get('/{invoice}/download',            [InvoiceController::class, 'download']);
                Route::post('/orders/{order}/generate',      [InvoiceController::class, 'generate']);
                Route::post('/{invoice}/payment',            [InvoiceController::class, 'recordPayment']);
            });

            // Expenses
            Route::prefix('finance/expenses')->group(function () {
                Route::get('/',                [ExpenseController::class, 'index']);
                Route::post('/',               [ExpenseController::class, 'store']);
                Route::get('/categories',      [ExpenseController::class, 'categories']);
                Route::get('/{expense}',       [ExpenseController::class, 'show']);
                Route::post('/{expense}',      [ExpenseController::class, 'update']);
                Route::delete('/{expense}',    [ExpenseController::class, 'destroy']);
            });

            // Reports & vendor payments
            Route::prefix('finance')->group(function () {
                Route::get('profit-loss',               [FinanceController::class, 'profitLoss']);
                Route::get('gst-summary',               [FinanceController::class, 'gstSummary']);
                Route::get('gst-export',                [FinanceController::class, 'gstExport']);
                Route::get('receivables',               [FinanceController::class, 'receivables']);
                Route::get('dealers/{dealer}/ledger',   [FinanceController::class, 'ledger']);
                Route::get('vendor-payments',           [VendorPaymentController::class, 'index']);
                Route::post('vendor-payments',          [VendorPaymentController::class, 'store']);
            });
        });

        // ── HR ────────────────────────────────────────────────────────────
        Route::middleware('role:super_admin,hr_manager')->prefix('hr')->group(function () {

            // Employees
            Route::get('employees/departments',          [EmployeeController::class, 'departments']);
            Route::get('employees',                      [EmployeeController::class, 'index']);
            Route::post('employees',                     [EmployeeController::class, 'store']);
            Route::get('employees/{employee}',           [EmployeeController::class, 'show']);
            Route::put('employees/{employee}',           [EmployeeController::class, 'update']);
            Route::delete('employees/{employee}',        [EmployeeController::class, 'destroy']);

            // Attendance
            Route::get('attendance',                     [AttendanceController::class, 'index']);
            Route::post('attendance/bulk',               [AttendanceController::class, 'bulkMark']);
            Route::get('attendance/today',               [AttendanceController::class, 'today']);
            Route::post('attendance/check-in',           [AttendanceController::class, 'checkIn']);
            Route::post('attendance/check-out',          [AttendanceController::class, 'checkOut']);
            Route::get('attendance/{employee}/summary',  [AttendanceController::class, 'summary']);

            // Payroll
            Route::get('payroll',                               [PayrollController::class, 'index']);
            Route::post('payroll',                              [PayrollController::class, 'create']);
            Route::post('payroll/process',                      [PayrollController::class, 'createAndProcess']);
            Route::get('payroll/{payrollRun}',                  [PayrollController::class, 'show']);
            Route::get('payroll/{payrollRun}/items',            [PayrollController::class, 'items']);
            Route::post('payroll/{payrollRun}/process',         [PayrollController::class, 'process']);
            Route::post('payroll/{payrollRun}/mark-paid',       [PayrollController::class, 'markPaid']);
            Route::post('payroll/{payrollRun}/generate-slips',  [PayrollController::class, 'generateAllSlips']);
            Route::get('payroll/{payrollRun}/slips/{payrollItem}', [PayrollController::class, 'downloadSlip']);
        });

        // ── Logistics ─────────────────────────────────────────────────────
        Route::middleware('role:super_admin,warehouse_staff')->prefix('logistics')->group(function () {
            Route::post('orders/{order}/shipment',  [LogisticsController::class, 'createShipment']);
            Route::get('track/{awb}',               [LogisticsController::class, 'track']);
            Route::delete('shipment/{awb}',         [LogisticsController::class, 'cancel']);
        });

        // ── Analytics ─────────────────────────────────────────────────────
        Route::middleware('role:super_admin,sales,accounts')->prefix('analytics')->group(function () {
            Route::get('dashboard',        [AnalyticsController::class, 'dashboard']);
            Route::get('revenue',          [AnalyticsController::class, 'revenue']);
            Route::get('sales',            [AnalyticsController::class, 'sales']);
            Route::get('inventory',        [AnalyticsController::class, 'inventory']);
            Route::get('stock-movements',  [AnalyticsController::class, 'stockMovements']);
            Route::get('partners',         [AnalyticsController::class, 'partnerPerformance']);
            Route::get('forecast',         [AnalyticsController::class, 'forecast']);
        });

        // ── Sales Hierarchy ───────────────────────────────────────────────
        Route::middleware('role:super_admin,sales')->prefix('sales')->group(function () {
            Route::get('hierarchy',                               [SalesHierarchyController::class, 'index']);
            Route::get('hierarchy/tree',                          [SalesHierarchyController::class, 'tree']);
            Route::post('hierarchy',                              [SalesHierarchyController::class, 'store']);
            Route::get('hierarchy/{salesHierarchy}',              [SalesHierarchyController::class, 'show']);
            Route::put('hierarchy/{salesHierarchy}',              [SalesHierarchyController::class, 'update']);
            Route::delete('hierarchy/{salesHierarchy}',           [SalesHierarchyController::class, 'destroy']);
            Route::get('hierarchy/{salesHierarchy}/downline',     [SalesHierarchyController::class, 'downline']);
            Route::get('hierarchy/{salesHierarchy}/performance',  [SalesHierarchyController::class, 'performance']);
            Route::post('hierarchy/{salesHierarchy}/assign-dealer', [SalesHierarchyController::class, 'assignDealer']);
        });

        // ── Offer Engine ──────────────────────────────────────────────────
        Route::middleware('role:super_admin,sales')->prefix('offers')->group(function () {
            Route::get('/',                 [OfferController::class, 'index']);
            Route::post('/',                [OfferController::class, 'store']);
            Route::get('active',            [OfferController::class, 'active']);
            Route::post('validate',         [OfferController::class, 'validateCode']);
            Route::get('{offer}',           [OfferController::class, 'show']);
            Route::put('{offer}',           [OfferController::class, 'update']);
            Route::delete('{offer}',        [OfferController::class, 'destroy']);
        });

        // ── Peti to Peti ──────────────────────────────────────────────────
        Route::middleware('role:super_admin,warehouse_staff')->prefix('peti-transfers')->group(function () {
            Route::get('/',                              [PetiTransferController::class, 'index']);
            Route::post('/',                             [PetiTransferController::class, 'store']);
            Route::get('{petiTransfer}',                 [PetiTransferController::class, 'show']);
            Route::post('{petiTransfer}/approve',        [PetiTransferController::class, 'approve']);
            Route::post('{petiTransfer}/complete',       [PetiTransferController::class, 'complete']);
            Route::post('{petiTransfer}/cancel',         [PetiTransferController::class, 'cancel']);
        });
    });

    // ── Mobile App Routes ──────────────────────────────────────────────────
    Route::prefix('mobile')->group(function () {
        // Public mobile auth (login with Sales ID only)
        Route::middleware('throttle:10,1')->group(function () {
            Route::post('auth/login', [MobileAuthController::class, 'login']);
        });

        // Authenticated mobile routes
        Route::middleware(['auth:sanctum', 'throttle:120,1'])->group(function () {
            // Auth
            Route::get('auth/me',      [MobileAuthController::class, 'me']);
            Route::post('auth/logout', [MobileAuthController::class, 'logout']);

            // Dashboard
            Route::get('dashboard', [MobileDashboardController::class, 'index']);

            // Hierarchy & Team
            Route::prefix('hierarchy')->group(function () {
                Route::get('subordinates', [MobileHierarchyController::class, 'subordinates']);
                Route::get('tree',         [MobileHierarchyController::class, 'tree']);
                Route::get('team-stats',   [MobileHierarchyController::class, 'teamStats']);
                Route::get('colleagues',   [MobileHierarchyController::class, 'colleagues']);
            });
        });
    });

    // ── Partner Web Portal (view-only; separate site) ──────────────────────
    Route::prefix('partner')->group(function () {
        // Public: partner login (email/phone + password)
        Route::middleware('throttle:10,1')->group(function () {
            Route::post('auth/login', [PartnerAuthController::class, 'login']);
        });

        // Authenticated partner routes (b2b_partner token)
        Route::middleware(['auth:sanctum', 'throttle:120,1'])->group(function () {
            Route::get('auth/me',      [PartnerAuthController::class, 'me']);
            Route::post('auth/logout', [PartnerAuthController::class, 'logout']);

            Route::get('dashboard',       [PartnerPortalController::class, 'dashboard']);
            Route::get('orders',          [PartnerPortalController::class, 'orders']);
            Route::get('orders/{order}',  [PartnerPortalController::class, 'orderShow']);
            Route::get('invoices',        [PartnerPortalController::class, 'invoices']);
            Route::get('dues',            [PartnerPortalController::class, 'dues']);

            // Catalog — browse in-stock products by brand / grade
            Route::get('catalog/brands',  [PartnerCatalogController::class, 'brands']);
            Route::get('catalog/grades',  [PartnerCatalogController::class, 'grades']);
            Route::get('catalog',         [PartnerCatalogController::class, 'index']);
        });
    });

    // ── Webhooks (public, signature verified in controller) ───────────────
    Route::post('/webhooks/razorpay', [RazorpayWebhookController::class, 'handle']);

    // ── Retail / B2C ─────────────────────────────────────────────────────────
    // Public retail auth
    Route::prefix('retail')->middleware('throttle:10,1')->group(function () {
        Route::post('auth/send-otp',   [CustomerAuthController::class, 'sendOtp']);
        Route::post('auth/verify-otp', [CustomerAuthController::class, 'verifyOtp']);
    });

    // Public retail catalog (no auth needed to browse)
    Route::prefix('retail')->group(function () {
        Route::get('catalog',            [RetailController::class, 'catalog']);
        Route::get('catalog/{product}',  [RetailController::class, 'productDetail']);
    });

    // Authenticated retail routes
    Route::prefix('retail')->middleware('retail.auth')->group(function () {
        Route::post('auth/logout',       [CustomerAuthController::class, 'logout']);
        Route::get('auth/me',            [CustomerAuthController::class, 'me']);
        Route::put('auth/profile',       [CustomerAuthController::class, 'updateProfile']);

        Route::get('cart',               [RetailController::class, 'cartView']);
        Route::post('cart',              [RetailController::class, 'cartAdd']);
        Route::delete('cart/{productId}',[RetailController::class, 'cartRemove']);
        Route::delete('cart',            [RetailController::class, 'cartClear']);

        Route::get('orders',             [RetailController::class, 'ordersList']);
        Route::post('orders',            [RetailController::class, 'orderPlace']);
        Route::get('orders/{order}',     [RetailController::class, 'orderShow']);
    });

    // ── Admin: Retail Customers ───────────────────────────────────────────────
    Route::middleware(['auth:sanctum', 'role:super_admin,sales,accounts'])->group(function () {
        Route::get('customers',           [CustomerController::class, 'index']);
        Route::get('customers/{customer}',[CustomerController::class, 'show']);
        Route::put('customers/{customer}',[CustomerController::class, 'update']);
    });
});
