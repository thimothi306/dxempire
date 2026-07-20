<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class SampleDataSeeder extends Seeder
{
    public function run(): void
    {
        // ── Clear business tables (keep it idempotent) ─────────────────────────
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach ([
            'bin_movements', 'qc_records', 'order_items', 'payments', 'invoices',
            'products', 'purchase_orders', 'orders', 'dealers', 'leads', 'expenses',
            'attendance', 'payroll_items', 'payroll_runs', 'employees', 'bins', 'suppliers',
        ] as $t) {
            if (DB::getSchemaBuilder()->hasTable($t)) {
                DB::table($t)->delete();
            }
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $now = now();

        // ══════════════════════════════════════════════════════════════════════
        // USERS — Sales hierarchy + staff  (idempotent)
        // ══════════════════════════════════════════════════════════════════════
        $users = [
            ['Anil Sharma',    'anil@dxempire.com',    '9111111101', 'super_admin',      'CEO001', null],
            ['Rajesh Kumar',   'rajesh@dxempire.com',  '9111111102', 'sales',            'SM001',  'CEO001'],
            ['Priya Singh',    'priya@dxempire.com',   '9111111103', 'area_manager',     'AM001',  'SM001'],
            ['Ramesh Desai',   'ramesh@dxempire.com',  '9111111104', 'area_manager',     'AM002',  'SM001'],
            ['Amit Patel',     'amit@dxempire.com',    '9111111105', 'district_manager', 'DM001',  'AM001'],
            ['Zara Khan',      'zara@dxempire.com',    '9111111106', 'district_manager', 'DM002',  'AM002'],
            ['Vikram Singh',   'vikram@dxempire.com',  '9111111107', 'sales',            'SG001',  'DM001'],
            ['Suresh Patel',   'suresh@dxempire.com',  '9111111108', 'sales',            'SG002',  'DM001'],
            ['Rani Sharma',    'rani@dxempire.com',    '9111111109', 'sales',            'SG003',  'DM002'],
            ['Mohan Kumar',    'mohan@dxempire.com',   '9111111110', 'warehouse_staff',  null,     null],
            ['Deepak Verma',   'deepak@dxempire.com',  '9111111111', 'qc_engineer',      null,     null],
            ['Neha Gupta',     'neha@dxempire.com',    '9111111112', 'accounts',         null,     null],
            ['Kiran Rao',      'kiran@dxempire.com',   '9111111113', 'hr_manager',       null,     null],
        ];

        $ids = [];
        foreach ($users as [$name, $email, $phone, $role, $code, $parent]) {
            $u = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $name, 'phone' => $phone,
                    'password' => Hash::make('password123'),
                    'role' => $role, 'unique_code' => $code,
                    'parent_unique_code' => $parent, 'is_active' => true,
                ]
            );
            if (!$u->hasRole($role)) $u->assignRole($role);
            $ids[$role][] = $u->id;
            $ids['by_code'][$code] = $u->id;
        }

        $warehouseId = $ids['warehouse_staff'][0];
        $qcId        = $ids['qc_engineer'][0];
        $accountsId  = $ids['accounts'][0];
        $salesIds    = $ids['sales'];   // SM001, SG001, SG002, SG003
        $adminId     = $ids['super_admin'][0];

        // ══════════════════════════════════════════════════════════════════════
        // SUPPLIERS  (10)
        // ══════════════════════════════════════════════════════════════════════
        $supplierNames = [
            'Global Imports Co', 'Apple Distributors India', 'Samsung Wholesale',
            'TechSource Traders', 'Mobile Bay Imports', 'ElectroHub Suppliers',
            'Prime Gadgets Ltd', 'NextGen Trading', 'Digital Imports Pvt', 'SmartTech Distributors',
        ];
        $supplierIds = [];
        foreach ($supplierNames as $i => $name) {
            $supplierIds[] = DB::table('suppliers')->insertGetId([
                'name' => $name,
                'phone' => '9822' . str_pad($i, 6, '0', STR_PAD_LEFT),
                'email' => 'contact@' . strtolower(str_replace(' ', '', $name)) . '.com',
                'gst_number' => '27AABCU' . rand(1000, 9999) . 'M1Z' . $i,
                'address' => 'Industrial Area, Phase ' . ($i + 1) . ', Mumbai',
                'type' => ['dealer', 'importer', 'buyback_partner'][$i % 3],
                'outstanding_balance' => rand(0, 500000),
                'is_active' => true,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        // ══════════════════════════════════════════════════════════════════════
        // BINS  (10)
        // ══════════════════════════════════════════════════════════════════════
        $binIds = [];
        foreach (range(1, 10) as $i) {
            $binIds[] = DB::table('bins')->insertGetId([
                'code' => 'BIN-' . str_pad($i, 3, '0', STR_PAD_LEFT),
                'zone' => 'Zone ' . chr(64 + (($i - 1) % 4 + 1)),
                'row' => 'R' . $i,
                'shelf' => 'S' . rand(1, 5),
                'capacity' => 50,
                'current_count' => rand(10, 45),
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        // ══════════════════════════════════════════════════════════════════════
        // PURCHASE ORDERS  (10)
        // ══════════════════════════════════════════════════════════════════════
        $poIds = [];
        foreach (range(1, 10) as $i) {
            $poIds[] = DB::table('purchase_orders')->insertGetId([
                'supplier_id' => $supplierIds[array_rand($supplierIds)],
                'total_amount' => rand(200000, 2000000),
                'expected_count' => rand(20, 100),
                'received_count' => rand(20, 100),
                'status' => ['draft', 'placed', 'received'][rand(0, 2)],
                'received_at' => $now->copy()->subDays(rand(5, 40)),
                'created_by' => $warehouseId,
                'created_at' => $now->copy()->subDays(rand(5, 40)), 'updated_at' => $now,
            ]);
        }

        // ══════════════════════════════════════════════════════════════════════
        // PRODUCTS  (40 — mixed statuses so QC / Inventory / Refurb all show data)
        // ══════════════════════════════════════════════════════════════════════
        $catalog = [
            ['phone',     'Apple',   'iPhone 13'],
            ['phone',     'Apple',   'iPhone 14 Pro'],
            ['phone',     'Samsung', 'Galaxy S22'],
            ['phone',     'Samsung', 'Galaxy S23 Ultra'],
            ['phone',     'OnePlus', 'OnePlus 11'],
            ['phone',     'Xiaomi',  'Redmi Note 12'],
            ['laptop',    'Apple',   'MacBook Air M2'],
            ['laptop',    'Dell',    'XPS 13'],
            ['laptop',    'HP',      'Pavilion 15'],
            ['accessory', 'Apple',   'AirPods Pro'],
        ];
        // status mix: 18 in_stock, 8 received(pending QC), 5 refurbishment, 9 sold
        $statusPlan = array_merge(
            array_fill(0, 18, 'in_stock'),
            array_fill(0, 8, 'received'),
            array_fill(0, 5, 'refurbishment'),
            array_fill(0, 9, 'sold'),
        );
        shuffle($statusPlan);

        $productIds = [];      // all
        $inStockProductIds = [];
        $soldProductIds = [];
        foreach ($statusPlan as $idx => $status) {
            [$cat, $brand, $model] = $catalog[array_rand($catalog)];
            $purchase = rand(8000, 90000);
            $selling  = $purchase + rand(2000, 25000);
            $grade    = 'S' . rand(1, 5);
            $pid = DB::table('products')->insertGetId([
                'imei' => $cat === 'phone' ? str_pad((string) rand(1, 999999999), 15, '0', STR_PAD_LEFT) . $idx : null,
                'serial_number' => strtoupper($brand[0]) . 'N' . rand(100000, 999999),
                'category' => $cat,
                'brand' => $brand,
                'model' => $model,
                'grade' => $grade,
                'status' => $status,
                'return_count' => 0,
                'bin_id' => $status === 'in_stock' ? $binIds[array_rand($binIds)] : null,
                'purchase_price' => $purchase,
                'selling_price' => $selling,
                'supplier_id' => $supplierIds[array_rand($supplierIds)],
                'purchase_order_id' => $poIds[array_rand($poIds)],
                'qc_passed_at' => in_array($status, ['in_stock', 'sold']) ? $now->copy()->subDays(rand(1, 20)) : null,
                'sold_at' => $status === 'sold' ? $now->copy()->subDays(rand(1, 15)) : null,
                'created_at' => $now->copy()->subDays(rand(5, 40)), 'updated_at' => $now,
            ]);
            $productIds[] = $pid;
            if ($status === 'in_stock') $inStockProductIds[] = $pid;
            if ($status === 'sold') $soldProductIds[] = $pid;
        }

        // ══════════════════════════════════════════════════════════════════════
        // DEALERS / BUSINESS PARTNERS  (10)  — each needs a b2b_partner user
        // ══════════════════════════════════════════════════════════════════════
        $dealerCompanies = [
            ['Sharma Electronics',   'Maharashtra',   '400001'],
            ['Patel Mobile Store',   'Gujarat',       '380001'],
            ['Singh Tech Solutions', 'Delhi',         '110001'],
            ['Khan Gadgets',         'Maharashtra',   '411001'],
            ['Tech Hub Pvt Ltd',     'Tamil Nadu',    '600001'],
            ['Digital World',        'Telangana',     '500001'],
            ['Smart Devices Store',  'Madhya Pradesh','452001'],
            ['Mobile World India',   'Karnataka',     '560001'],
            ['Electronics Plus',     'Uttar Pradesh', '226001'],
            ['Future Tech Store',    'Rajasthan',     '302001'],
        ];
        $dealerIds = [];
        foreach ($dealerCompanies as $i => [$biz, $state, $pin]) {
            $du = User::firstOrCreate(
                ['email' => 'partner' . ($i + 1) . '@dxempire.com'],
                [
                    'name' => $biz . ' (Owner)',
                    'phone' => '9933' . str_pad($i, 6, '0', STR_PAD_LEFT),
                    'password' => Hash::make('password123'),
                    'role' => 'b2b_partner', 'is_active' => true,
                ]
            );
            if (!$du->hasRole('b2b_partner')) $du->assignRole('b2b_partner');

            $limit = rand(3, 10) * 100000;
            $dealerIds[] = DB::table('dealers')->insertGetId([
                'user_id' => $du->id,
                'business_name' => $biz,
                'gst_number' => '27AAB' . strtoupper(substr(md5($biz), 0, 6)) . 'Z' . $i,
                'kyc_status' => ['verified', 'verified', 'verified', 'pending'][rand(0, 3)],
                'credit_limit' => $limit,
                'credit_used' => rand(0, (int) $limit),
                'price_tier' => ['T1', 'T2', 'T3'][rand(0, 2)],
                'state' => $state,
                'pincode' => $pin,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        // ══════════════════════════════════════════════════════════════════════
        // ORDERS + ORDER ITEMS + PAYMENTS + INVOICES
        //   status mix engineered so dashboard tiles all light up:
        //   - several 'delivered' spread across today/week/month (revenue)
        //   - 'approved'/'picking'/'dispatched' (active orders)
        //   - 'packing' (pending dispatch)
        // ══════════════════════════════════════════════════════════════════════
        // build an assignable pool of products for line items (in_stock + sold)
        $lineProducts = array_merge($inStockProductIds, $soldProductIds, $productIds);

        $orderPlan = [
            // [status, created_at, delivered?]
            ['delivered', $now->copy()->setTime(10, 30)],                    // today
            ['delivered', $now->copy()->setTime(14, 15)],                    // today
            ['delivered', $now->copy()->subDays(2)],                         // this week
            ['delivered', $now->copy()->subDays(4)],                         // this week
            ['delivered', $now->copy()->subDays(9)],                         // this month
            ['delivered', $now->copy()->subDays(15)],                        // this month
            ['delivered', $now->copy()->subDays(21)],                        // this month
            ['dispatched', $now->copy()->subDays(1)],
            ['picking',    $now->copy()->subDays(1)],
            ['approved',   $now->copy()->subHours(6)],
            ['packing',    $now->copy()->subHours(8)],
            ['packing',    $now->copy()->subDays(2)],
            ['pending',    $now->copy()->subHours(3)],
            ['cancelled',  $now->copy()->subDays(6)],
        ];

        $orderNo = 1;
        foreach ($orderPlan as [$status, $created]) {
            $dealerId = $dealerIds[array_rand($dealerIds)];
            $itemCount = rand(1, 3);
            $subtotal = 0; $gstTotal = 0;
            $lineRows = [];
            for ($k = 0; $k < $itemCount; $k++) {
                $pid = $lineProducts[array_rand($lineProducts)];
                $qty = rand(1, 4);
                $unit = rand(12000, 85000);
                $line = $unit * $qty;
                $gst = round($line * 0.18, 2);
                $subtotal += $line;
                $gstTotal += $gst;
                $lineRows[] = compact('pid', 'qty', 'unit', 'gst', 'line');
            }
            $total = $subtotal + $gstTotal;

            $orderId = DB::table('orders')->insertGetId([
                'order_number' => 'ORD-' . str_pad($orderNo, 5, '0', STR_PAD_LEFT),
                'dealer_id' => $dealerId,
                'customer_id' => null,
                'status' => $status,
                'payment_status' => $status === 'delivered' ? 'paid' : ($status === 'cancelled' ? 'refunded' : 'unpaid'),
                'subtotal' => $subtotal,
                'gst_amount' => $gstTotal,
                'total_amount' => $total,
                'credit_used' => 0,
                'awb_number' => in_array($status, ['dispatched', 'delivered']) ? 'AWB' . rand(10000000, 99999999) : null,
                'logistics_provider' => in_array($status, ['dispatched', 'delivered']) ? ['Shiprocket', 'DTDC', 'Delhivery'][rand(0, 2)] : null,
                'dispatched_at' => in_array($status, ['dispatched', 'delivered']) ? $created->copy()->addDay() : null,
                'delivered_at' => $status === 'delivered' ? $created->copy()->addDays(2) : null,
                'notes' => 'Bulk order of refurbished devices',
                'created_at' => $created, 'updated_at' => $created,
            ]);

            foreach ($lineRows as $r) {
                DB::table('order_items')->insert([
                    'order_id' => $orderId,
                    'product_id' => $r['pid'],
                    'quantity' => $r['qty'],
                    'unit_price' => $r['unit'],
                    'gst_rate' => 18.00,
                    'gst_amount' => $r['gst'],
                    'line_total' => $r['line'] + $r['gst'],
                    'created_at' => $created, 'updated_at' => $created,
                ]);
            }

            // Payment + invoice for paid/delivered orders
            if ($status === 'delivered') {
                DB::table('payments')->insert([
                    'order_id' => $orderId,
                    'razorpay_order_id' => 'order_' . strtoupper(substr(md5($orderId . 'o'), 0, 14)),
                    'razorpay_payment_id' => 'pay_' . strtoupper(substr(md5($orderId . 'p'), 0, 14)),
                    'amount' => $total,
                    'status' => 'captured',
                    'method' => ['upi', 'card', 'netbanking'][rand(0, 2)],
                    'paid_at' => $created->copy()->addDay(),
                    'created_at' => $created, 'updated_at' => $created,
                ]);

                DB::table('invoices')->insert([
                    'order_id' => $orderId,
                    'invoice_number' => 'INV-' . str_pad($orderNo, 5, '0', STR_PAD_LEFT),
                    'dealer_id' => $dealerId,
                    'subtotal' => $subtotal,
                    'gst_amount' => $gstTotal,
                    'total' => $total,
                    'issued_at' => $created->copy()->addDay(),
                    'created_at' => $created, 'updated_at' => $created,
                ]);
            }
            $orderNo++;
        }

        // ══════════════════════════════════════════════════════════════════════
        // LEADS  (10)
        // ══════════════════════════════════════════════════════════════════════
        $leadContacts = [
            'Rohan Gupta', 'Priya Verma', 'Abhishek Singh', 'Neha Sharma', 'Varun Patel',
            'Sanjay Mehta', 'Divya Nair', 'Karan Malhotra', 'Anjali Reddy', 'Manish Joshi',
        ];
        foreach ($leadContacts as $i => $contact) {
            DB::table('leads')->insert([
                'source' => ['b2b_inquiry', 'website', 'referral', 'walk_in', 'marketplace'][rand(0, 4)],
                'contact_name' => $contact,
                'phone' => '98' . str_pad((string) rand(10000000, 99999999), 8, '0', STR_PAD_LEFT),
                'business_name' => ['Prospect', 'Enterprise', 'Retail', 'Wholesale'][rand(0, 3)] . ' Traders ' . ($i + 1),
                'stage' => ['new', 'contacted', 'quoted', 'negotiating', 'won', 'lost'][rand(0, 5)],
                'assigned_to' => $salesIds[array_rand($salesIds)],
                'last_contact_at' => $now->copy()->subDays(rand(1, 20)),
                'notes' => 'Interested in bulk purchase of refurbished electronics.',
                'created_at' => $now->copy()->subDays(rand(1, 60)), 'updated_at' => $now,
            ]);
        }

        // ══════════════════════════════════════════════════════════════════════
        // EXPENSES  (10)
        // ══════════════════════════════════════════════════════════════════════
        $expenseCats = ['Travel', 'Meals', 'Office Supplies', 'Marketing', 'Utilities', 'Logistics', 'Rent'];
        foreach (range(1, 10) as $i) {
            DB::table('expenses')->insert([
                'category' => $expenseCats[array_rand($expenseCats)],
                'amount' => rand(500, 45000),
                'vendor' => ['Indian Oil', 'Zomato', 'Amazon Business', 'Google Ads', 'MSEB', 'Blue Dart'][rand(0, 5)],
                'description' => 'Business operational expense #' . $i,
                'incurred_at' => $now->copy()->subDays(rand(1, 30))->toDateString(),
                'created_by' => $accountsId,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        // ══════════════════════════════════════════════════════════════════════
        // QC RECORDS  (12)
        // ══════════════════════════════════════════════════════════════════════
        $qcSample = array_slice($productIds, 0, min(12, count($productIds)));
        foreach ($qcSample as $pid) {
            $outcome = ['pass', 'pass', 'pass', 'repair', 'reject'][rand(0, 4)];
            DB::table('qc_records')->insert([
                'product_id' => $pid,
                'engineer_id' => $qcId,
                'grade' => 'S' . rand(1, 5),
                'condition_notes' => ['Excellent condition', 'Minor scratches on rear', 'Battery health 89%', 'Screen replaced', 'Needs new casing'][rand(0, 4)],
                'outcome' => $outcome,
                'graded_at' => $now->copy()->subDays(rand(1, 25)),
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        // ══════════════════════════════════════════════════════════════════════
        // BIN MOVEMENTS  (12)
        // ══════════════════════════════════════════════════════════════════════
        foreach (array_slice($productIds, 0, 12) as $pid) {
            DB::table('bin_movements')->insert([
                'product_id' => $pid,
                'from_bin_id' => rand(0, 1) ? $binIds[array_rand($binIds)] : null,
                'to_bin_id' => $binIds[array_rand($binIds)],
                'moved_by' => $warehouseId,
                'moved_at' => $now->copy()->subDays(rand(1, 20)),
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        // ══════════════════════════════════════════════════════════════════════
        // EMPLOYEES + ATTENDANCE + PAYROLL
        // ══════════════════════════════════════════════════════════════════════
        $empUserMap = [
            ['email' => 'rajesh@dxempire.com', 'dept' => 'Sales',      'desig' => 'State Manager',     'salary' => 120000],
            ['email' => 'priya@dxempire.com',  'dept' => 'Sales',      'desig' => 'Area Manager',      'salary' => 80000],
            ['email' => 'ramesh@dxempire.com', 'dept' => 'Sales',      'desig' => 'Area Manager',      'salary' => 80000],
            ['email' => 'amit@dxempire.com',   'dept' => 'Sales',      'desig' => 'District Manager',  'salary' => 60000],
            ['email' => 'zara@dxempire.com',   'dept' => 'Sales',      'desig' => 'District Manager',  'salary' => 60000],
            ['email' => 'vikram@dxempire.com', 'dept' => 'Sales',      'desig' => 'Sales Executive',   'salary' => 30000],
            ['email' => 'suresh@dxempire.com', 'dept' => 'Sales',      'desig' => 'Sales Executive',   'salary' => 30000],
            ['email' => 'rani@dxempire.com',   'dept' => 'Sales',      'desig' => 'Sales Executive',   'salary' => 30000],
            ['email' => 'mohan@dxempire.com',  'dept' => 'Warehouse',  'desig' => 'Warehouse Manager', 'salary' => 35000],
            ['email' => 'deepak@dxempire.com', 'dept' => 'QC',         'desig' => 'QC Engineer',       'salary' => 40000],
        ];

        $employeeIds = [];
        foreach ($empUserMap as $em) {
            $uid = User::where('email', $em['email'])->value('id');
            $eid = DB::table('employees')->insertGetId([
                'user_id' => $uid,
                'department' => $em['dept'],
                'designation' => $em['desig'],
                'shift' => ['morning', 'evening'][rand(0, 1)],
                'basic_salary' => $em['salary'],
                'join_date' => $now->copy()->subYear()->toDateString(),
                'is_active' => true,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $employeeIds[$eid] = $em['salary'];

            // Attendance — last 20 working days
            for ($d = 0; $d < 28; $d++) {
                $date = $now->copy()->subDays($d);
                if (in_array($date->dayOfWeek, [0, 6])) continue;
                $st = rand(1, 20) === 1 ? 'absent' : (rand(1, 15) === 1 ? 'half_day' : 'present');
                DB::table('attendance')->insert([
                    'employee_id' => $eid,
                    'date' => $date->toDateString(),
                    'status' => $st,
                    'check_in' => $st === 'absent' ? null : $date->copy()->setTime(9, rand(0, 59)),
                    'check_out' => $st === 'absent' ? null : $date->copy()->setTime(18, rand(0, 59)),
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }

        // Payroll run for last month
        $runId = DB::table('payroll_runs')->insertGetId([
            'month' => $now->copy()->subMonth()->month,
            'year' => $now->copy()->subMonth()->year,
            'status' => 'processed',
            'processed_at' => $now,
            'total_payout' => array_sum($employeeIds),
            'created_at' => $now, 'updated_at' => $now,
        ]);
        foreach ($employeeIds as $eid => $salary) {
            $deductions = round($salary * 0.08, 2);
            DB::table('payroll_items')->insert([
                'payroll_run_id' => $runId,
                'employee_id' => $eid,
                'days_worked' => rand(20, 24),
                'basic' => $salary,
                'deductions' => $deductions,
                'net_salary' => $salary - $deductions,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        // ── Bust the cached dashboard so new numbers show immediately ──────────
        Cache::forget('analytics:dashboard');

        $this->report();
    }

    private function report(): void
    {
        $c = fn($t) => DB::getSchemaBuilder()->hasTable($t) ? DB::table($t)->count() : 0;
        $this->command->info('');
        $this->command->info('═══════════════════════════════════════════════════════════');
        $this->command->info('✅ FULL DEMO DATA SEEDED — every module now has records');
        $this->command->info('═══════════════════════════════════════════════════════════');
        $this->command->info('  Users        : ' . $c('users'));
        $this->command->info('  Suppliers    : ' . $c('suppliers'));
        $this->command->info('  Bins         : ' . $c('bins'));
        $this->command->info('  Purchase POs : ' . $c('purchase_orders'));
        $this->command->info('  Products     : ' . $c('products'));
        $this->command->info('  Dealers      : ' . $c('dealers'));
        $this->command->info('  Orders       : ' . $c('orders'));
        $this->command->info('  Order Items  : ' . $c('order_items'));
        $this->command->info('  Payments     : ' . $c('payments'));
        $this->command->info('  Invoices     : ' . $c('invoices'));
        $this->command->info('  Leads        : ' . $c('leads'));
        $this->command->info('  Expenses     : ' . $c('expenses'));
        $this->command->info('  QC Records   : ' . $c('qc_records'));
        $this->command->info('  Bin Moves    : ' . $c('bin_movements'));
        $this->command->info('  Employees    : ' . $c('employees'));
        $this->command->info('  Attendance   : ' . $c('attendance'));
        $this->command->info('  Payroll Items: ' . $c('payroll_items'));
        $this->command->info('═══════════════════════════════════════════════════════════');
        $this->command->info('  Web login : anil@dxempire.com / password123');
        $this->command->info('  Mobile IDs: SM001 · AM001 · DM001 · SG001');
        $this->command->info('═══════════════════════════════════════════════════════════');
        $this->command->info('');
    }
}
