<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\User;

/**
 * Fills the remaining empty tables so every admin module shows data:
 * offers, peti_transfers, sales_hierarchy, settings, support_tickets,
 * vendor_payments, customers, audit_logs.
 * Run AFTER SampleDataSeeder (needs users/dealers/suppliers/orders).
 */
class DemoExtraSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach (['offers','peti_transfers','sales_hierarchy','settings','support_tickets','vendor_payments','customers','audit_logs'] as $t) {
            if (DB::getSchemaBuilder()->hasTable($t)) DB::table($t)->delete();
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $adminId    = User::where('role', 'super_admin')->value('id');
        $userIds    = User::pluck('id')->all();
        $salesIds   = User::where('role', 'sales')->pluck('id')->all() ?: $userIds;
        $dealerIds  = DB::table('dealers')->pluck('id')->all();
        $supplierIds= DB::table('suppliers')->pluck('id')->all();
        $orderIds   = DB::table('orders')->pluck('id')->all();
        $pick = fn($arr) => $arr[array_rand($arr)];

        // ── OFFERS (8) ─────────────────────────────────────────────────────
        $offers = [
            ['FLAT500', 'Flat ₹500 Off', 'fixed', 500, 5000],
            ['SAVE10', '10% Off Order', 'percentage', 10, 10000],
            ['BULK15', 'Bulk Buyer 15%', 'percentage', 15, 50000],
            ['NEWDLR', 'New Dealer ₹1000', 'fixed', 1000, 8000],
            ['FEST20', 'Festive 20% Off', 'percentage', 20, 20000],
            ['S1PREM', 'Premium Grade Deal', 'percentage', 8, 15000],
            ['LAPTOP5', 'Laptop ₹5000 Off', 'fixed', 5000, 40000],
            ['LOYAL12', 'Loyalty 12%', 'percentage', 12, 12000],
        ];
        foreach ($offers as $i => [$code, $title, $dtype, $dval, $minAmt]) {
            DB::table('offers')->insert([
                'title' => $title, 'code' => $code,
                'description' => $title . ' — auto-applied at checkout when eligible.',
                'discount_type' => $dtype, 'discount_value' => $dval,
                'min_order_amount' => $minAmt, 'max_discount_amount' => $dtype === 'percentage' ? 10000 : null,
                'applicable_to' => ['all','phone','laptop','accessory'][$i % 4],
                'applicable_grade' => ['all','all','S1','S2'][$i % 4],
                'customer_type' => ['all','b2b','retail'][$i % 3],
                'valid_from' => $now->copy()->subDays(10),
                'valid_to' => $now->copy()->addDays(rand(10, 60)),
                'max_usage' => 100, 'usage_count' => rand(0, 40),
                'is_active' => $i % 5 !== 0, 'created_by' => $adminId,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        // ── SETTINGS (key/value) ───────────────────────────────────────────
        $settings = [
            'company_name' => 'DXEmpire Techbuzz Private Limited',
            'company_email' => 'support@dxempire.in',
            'company_phone' => '+918787635196',
            'gst_number' => '27AABFD1234M1Z0',
            'default_gst_rate' => '18',
            'currency' => 'INR',
            'invoice_prefix' => 'INV-',
            'order_prefix' => 'ORD-',
            'low_stock_threshold' => '10',
            'support_email' => 'help@dxempire.in',
            'address' => 'Industrial Area, Agartala, Tripura - 799001',
            'razorpay_enabled' => '1',
        ];
        foreach ($settings as $k => $v) {
            DB::table('settings')->insert(['key' => $k, 'value' => $v, 'updated_at' => $now]);
        }

        // ── CUSTOMERS (retail) (12) ────────────────────────────────────────
        $names = ['Arjun Mehta','Sneha Iyer','Rahul Verma','Pooja Nair','Karan Shah','Divya Rao',
                  'Manoj Gupta','Ritu Singh','Vivek Joshi','Ananya Das','Sameer Khan','Nisha Patel'];
        $cities = [['Mumbai','Maharashtra','400001'],['Bangalore','Karnataka','560001'],['Delhi','Delhi','110001'],
                   ['Chennai','Tamil Nadu','600001'],['Pune','Maharashtra','411001'],['Hyderabad','Telangana','500001']];
        foreach ($names as $i => $n) {
            $c = $cities[$i % count($cities)];
            DB::table('customers')->insert([
                'name' => $n, 'phone' => '97' . str_pad((string) rand(10000000, 99999999), 8, '0', STR_PAD_LEFT),
                'email' => strtolower(str_replace(' ', '.', $n)) . '@gmail.com',
                'address' => (10 + $i) . ', Main Road', 'city' => $c[0], 'state' => $c[1], 'pincode' => $c[2],
                'is_active' => $i % 7 !== 0, 'created_at' => $now->copy()->subDays(rand(1, 90)), 'updated_at' => $now,
            ]);
        }

        // ── SUPPORT TICKETS (10) ───────────────────────────────────────────
        $subjects = ['Order not delivered','Wrong grade received','Invoice correction needed','Refund request',
                     'Damaged unit on arrival','Payment not reflected','Bulk order query','Warranty claim',
                     'Delivery delay','Product exchange request'];
        foreach ($subjects as $i => $sub) {
            DB::table('support_tickets')->insert([
                'order_id' => $orderIds ? $pick($orderIds) : null,
                'created_by' => $pick($userIds), 'assigned_to' => $pick($userIds),
                'subject' => $sub, 'description' => $sub . '. Customer is awaiting resolution.',
                'status' => ['open','in_progress','resolved','closed'][$i % 4],
                'priority' => ['low','medium','high'][$i % 3],
                'resolved_at' => $i % 4 >= 2 ? $now->copy()->subDays(rand(1, 10)) : null,
                'created_at' => $now->copy()->subDays(rand(1, 40)), 'updated_at' => $now,
            ]);
        }

        // ── VENDOR PAYMENTS (10) ───────────────────────────────────────────
        foreach (range(1, 10) as $i) {
            DB::table('vendor_payments')->insert([
                'supplier_id' => $pick($supplierIds), 'amount' => rand(50000, 800000),
                'method' => ['bank_transfer','upi','cheque','rtgs'][rand(0, 3)],
                'reference_number' => 'VP-' . str_pad((string) $i, 5, '0', STR_PAD_LEFT),
                'note' => 'Payment against purchase order settlement.',
                'paid_at' => $now->copy()->subDays(rand(1, 45)),
                'created_by' => $adminId, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        // ── PETI TRANSFERS (8) ─────────────────────────────────────────────
        $locations = ['Mumbai Warehouse','Delhi Hub','Bangalore Center','Pune Store','Chennai Depot'];
        foreach (range(1, 8) as $i) {
            $type = ['internal','dealer'][$i % 2];
            $units = rand(5, 40);
            DB::table('peti_transfers')->insert([
                'transfer_number' => 'PT-' . str_pad((string) $i, 5, '0', STR_PAD_LEFT),
                'type' => $type, 'from_location' => $pick($locations),
                'to_location' => $type === 'internal' ? $pick($locations) : 'Dealer Location',
                'to_dealer_id' => $type === 'dealer' && $dealerIds ? $pick($dealerIds) : null,
                'items' => json_encode([['grade' => 'S1', 'qty' => (int) ($units / 2)], ['grade' => 'S3', 'qty' => (int) ($units / 2)]]),
                'total_units' => $units, 'total_value' => $units * rand(15000, 40000),
                'status' => ['draft','approved','completed','cancelled'][$i % 4],
                'notes' => 'Stock movement between locations.',
                'created_by' => $adminId, 'approved_by' => $i % 4 >= 1 ? $adminId : null,
                'transferred_at' => $i % 4 >= 2 ? $now->copy()->subDays(rand(1, 20)) : null,
                'created_at' => $now->copy()->subDays(rand(1, 30)), 'updated_at' => $now,
            ]);
        }

        // ── SALES HIERARCHY (tree) ───────────────────────────────────────────
        // tree_id (= "Unique Code" in the admin UI) is kept IDENTICAL to the
        // matching users.unique_code (used by the mobile Sales-ID login), so
        // the two systems reference the same people with the same codes —
        // not two different numbering schemes for the same org chart.
        $usersByCode = User::whereNotNull('unique_code')->get(['id', 'phone', 'unique_code'])->keyBy('unique_code');

        $nodes = [
            // code      parent_code  name              role                state          area           district
            ['CEO001', null,     'Anil Sharma',    'ceo',              'All India',    null,           null],
            ['SM001',  'CEO001', 'Rajesh Kumar',   'state_manager',    'Maharashtra',  null,           null],
            ['AM001',  'SM001',  'Priya Singh',    'area_manager',     'Maharashtra',  'Mumbai Zone',  null],
            ['AM002',  'SM001',  'Ramesh Desai',   'area_manager',     'Maharashtra',  'Pune Zone',    null],
            ['DM001',  'AM001',  'Amit Patel',     'district_manager', 'Maharashtra',  'Mumbai Zone',  'Dadar'],
            ['DM002',  'AM002',  'Zara Khan',      'district_manager', 'Maharashtra',  'Pune Zone',    'Kothrud'],
            ['SG001',  'DM001',  'Vikram Singh',   'salesman',         'Maharashtra',  'Mumbai Zone',  'Dadar'],
            ['SG002',  'DM001',  'Suresh Patel',   'salesman',         'Maharashtra',  'Mumbai Zone',  'Dadar'],
            ['SG003',  'DM002',  'Rani Sharma',    'salesman',         'Maharashtra',  'Pune Zone',    'Kothrud'],
        ];

        $idByCode = [];
        foreach ($nodes as [$code, $parentCode, $name, $role, $state, $area, $district]) {
            $user = $usersByCode->get($code);
            $idByCode[$code] = DB::table('sales_hierarchy')->insertGetId([
                'tree_id'        => $code,
                'name'           => $name,
                'phone'          => $user?->phone ?? '9111111' . rand(100, 999),
                'email'          => strtolower(explode(' ', $name)[0]) . '@dxempire.com',
                'hierarchy_role' => $role,
                'parent_id'      => $parentCode ? ($idByCode[$parentCode] ?? null) : null,
                'state'          => $state,
                'area'           => $area,
                'district'       => $district,
                'user_id'        => $user?->id,
                'is_active'      => true,
                'created_at'     => $now, 'updated_at' => $now,
            ]);
        }

        // ── AUDIT LOGS (15) ────────────────────────────────────────────────
        $actions = ['created','updated','deleted','approved','dispatched','viewed'];
        $models  = ['Order','Invoice','Product','Dealer','User','Expense'];
        foreach (range(1, 15) as $i) {
            DB::table('audit_logs')->insert([
                'user_id' => $pick($userIds), 'action' => $pick($actions),
                'model_type' => 'App\\Models\\' . $pick($models), 'model_id' => rand(1, 40),
                'old_values' => json_encode(['status' => 'pending']),
                'new_values' => json_encode(['status' => 'approved']),
                'ip_address' => '103.' . rand(1, 255) . '.' . rand(1, 255) . '.' . rand(1, 255),
                'created_at' => $now->copy()->subDays(rand(1, 30)),
            ]);
        }

        $c = fn($t) => DB::table($t)->count();
        $this->command->info('');
        $this->command->info('✅ Extra demo data seeded:');
        foreach (['offers','settings','customers','support_tickets','vendor_payments','peti_transfers','sales_hierarchy','audit_logs'] as $t) {
            $this->command->info('   ' . str_pad($t, 18) . $c($t));
        }
    }
}
