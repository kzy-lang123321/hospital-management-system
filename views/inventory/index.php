<?php
/**
 * Hospital Management System (HMS) - Central Inventory & Procurement
 */

Auth::requireRole(['super_admin', 'hospital_admin', 'pharmacist']);

$db = Database::getConnection();

// Overall KPIs & Narcotics / Expiry Tracking
$statsQuery = $db->query("
    SELECT 
        COUNT(*) as total_items,
        COUNT(CASE WHEN current_stock <= reorder_level THEN 1 END) as low_stock_alerts,
        COUNT(CASE WHEN is_narcotic = 1 THEN 1 END) as narcotics_count,
        COUNT(CASE WHEN expiry_date IS NOT NULL AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 90 DAY) THEN 1 END) as near_expiry_count,
        COUNT(CASE WHEN expiry_date IS NOT NULL AND expiry_date < CURDATE() THEN 1 END) as expired_count,
        COALESCE(SUM(current_stock * unit_cost_price), 0) as total_valuation,
        (SELECT COUNT(*) FROM `purchase_orders` WHERE `status` IN ('Draft', 'Approved')) as active_pos
    FROM `inventory_items`
");
$stats = $statsQuery->fetch(PDO::FETCH_ASSOC);

// Tabs & Filter
$activeTab = clean_input($_GET['tab'] ?? 'stock');
$filter = clean_input($_GET['filter'] ?? 'all');
$search = clean_input($_GET['search'] ?? '');

// Categories & Suppliers
$categories = $db->query("SELECT * FROM `inventory_categories` ORDER BY category_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$suppliers = $db->query("SELECT * FROM `suppliers` WHERE `status` = 'Active' ORDER BY supplier_name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Stock items query with active filters
$sql = "
    SELECT i.*, c.category_name 
    FROM `inventory_items` i
    JOIN `inventory_categories` c ON i.category_id = c.id
    WHERE 1=1
";
$params = [];

if ($filter === 'narcotics') {
    $sql .= " AND i.is_narcotic = 1";
} elseif ($filter === 'near_expiry') {
    $sql .= " AND i.expiry_date IS NOT NULL AND i.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 90 DAY)";
} elseif ($filter === 'expired') {
    $sql .= " AND i.expiry_date IS NOT NULL AND i.expiry_date < CURDATE()";
} elseif ($filter === 'low_stock') {
    $sql .= " AND i.current_stock <= i.reorder_level";
}

if (!empty($search)) {
    $sql .= " AND (i.item_name LIKE ? OR i.item_code LIKE ? OR i.generic_name LIKE ? OR i.batch_number LIKE ?)";
    $st = "%$search%";
    $params = array_merge($params, [$st, $st, $st, $st]);
}

$sql .= " ORDER BY CASE WHEN i.expiry_date IS NOT NULL AND i.expiry_date < CURDATE() THEN 1 WHEN i.is_narcotic = 1 THEN 2 WHEN i.current_stock <= i.reorder_level THEN 3 ELSE 4 END, i.item_name ASC LIMIT 100";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Purchase Orders query
$pos = $db->query("
    SELECT po.*, s.supplier_name, s.code as supplier_code, u.full_name as created_by_name
    FROM `purchase_orders` po
    JOIN `suppliers` s ON po.supplier_id = s.id
    JOIN `users` u ON po.created_by = u.id
    ORDER BY po.id DESC LIMIT 50
")->fetchAll(PDO::FETCH_ASSOC);

// All Suppliers
$allSuppliers = $db->query("SELECT * FROM `suppliers` ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<main class="flex-1 overflow-y-auto bg-slate-50 p-6">
    <div class="max-w-7xl mx-auto space-y-6">

        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-800 border border-amber-200">
                        <i class="fa-solid fa-boxes-packing mr-1.5 text-amber-600"></i> Supply Chain & Stores
                    </span>
                    <h1 class="text-2xl font-bold text-slate-800">Central Inventory & Procurement</h1>
                </div>
                <p class="text-sm text-slate-500 mt-1">Pharmaceuticals, Narcotics (Schedule II/IV), Surgical Disposables, Cold-Chain & Expiry Tracking.</p>
            </div>

            <div class="flex items-center gap-2">
                <button onclick="document.getElementById('modal-add-supplier').classList.remove('hidden')" class="btn-secondary text-xs flex items-center gap-1.5 font-semibold">
                    <i class="fa-solid fa-truck"></i>
                    <span>Add Supplier</span>
                </button>
                <button onclick="document.getElementById('modal-create-po').classList.remove('hidden')" class="btn-secondary text-xs flex items-center gap-1.5 font-semibold">
                    <i class="fa-solid fa-cart-shopping text-medical-600"></i>
                    <span>Generate PO</span>
                </button>
                <button onclick="document.getElementById('modal-add-item').classList.remove('hidden')" class="btn-primary bg-amber-600 hover:bg-amber-700 text-white text-xs flex items-center gap-1.5 font-semibold">
                    <i class="fa-solid fa-box-archive"></i>
                    <span>Add Stock Item</span>
                </button>
            </div>
        </div>

        <!-- Enhanced KPIs Strip -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
            <div class="bg-white p-3.5 rounded-xl border border-slate-200 shadow-xs">
                <div class="text-[10px] font-bold uppercase text-slate-500 tracking-wider">Total Catalog</div>
                <div class="text-xl font-black text-slate-800 mt-1"><?= (int)($stats['total_items'] ?? 0) ?></div>
                <div class="text-[11px] text-slate-400 font-medium mt-0.5">Medicines & Items</div>
            </div>

            <div class="bg-white p-3.5 rounded-xl border border-purple-200 shadow-xs">
                <div class="text-[10px] font-bold uppercase text-purple-700 tracking-wider">Controlled / Narcotics</div>
                <div class="text-xl font-black text-purple-700 mt-1"><?= (int)($stats['narcotics_count'] ?? 0) ?></div>
                <div class="text-[11px] text-purple-500 font-medium mt-0.5">Schedule II &amp; IV</div>
            </div>

            <div class="bg-white p-3.5 rounded-xl border border-amber-200 shadow-xs">
                <div class="text-[10px] font-bold uppercase text-amber-700 tracking-wider">Near Expiry (&lt;90d)</div>
                <div class="text-xl font-black text-amber-600 mt-1"><?= (int)($stats['near_expiry_count'] ?? 0) ?></div>
                <div class="text-[11px] text-amber-600 font-medium mt-0.5">Needs FEFO Alert</div>
            </div>

            <div class="bg-white p-3.5 rounded-xl border border-rose-200 shadow-xs">
                <div class="text-[10px] font-bold uppercase text-rose-700 tracking-wider">Expired / Quarantine</div>
                <div class="text-xl font-black text-rose-600 mt-1"><?= (int)($stats['expired_count'] ?? 0) ?></div>
                <div class="text-[11px] text-rose-500 font-medium mt-0.5">Quarantined Stock</div>
            </div>

            <div class="bg-white p-3.5 rounded-xl border border-red-200 shadow-xs">
                <div class="text-[10px] font-bold uppercase text-red-600 tracking-wider">Reorder Alerts</div>
                <div class="text-xl font-black text-red-600 mt-1"><?= (int)($stats['low_stock_alerts'] ?? 0) ?></div>
                <div class="text-[11px] text-red-500 font-medium mt-0.5">Low Stock Units</div>
            </div>

            <div class="bg-white p-3.5 rounded-xl border border-emerald-200 shadow-xs">
                <div class="text-[10px] font-bold uppercase text-emerald-700 tracking-wider">Total Valuation</div>
                <div class="text-xl font-black text-emerald-600 mt-1 truncate"><?= format_currency($stats['total_valuation']) ?></div>
                <div class="text-[11px] text-emerald-600 font-medium mt-0.5">On-Hand Assets</div>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <div class="flex border-b border-slate-200 space-x-4">
            <a href="index.php?page=inventory&tab=stock" class="pb-3 text-xs font-bold transition-colors <?= $activeTab === 'stock' ? 'border-b-2 border-amber-600 text-amber-600' : 'text-slate-500 hover:text-slate-800' ?>">
                <i class="fa-solid fa-boxes-stacked mr-1"></i> Stock Ledger &amp; Medicine Formulations
            </a>
            <a href="index.php?page=inventory&tab=pos" class="pb-3 text-xs font-bold transition-colors <?= $activeTab === 'pos' ? 'border-b-2 border-amber-600 text-amber-600' : 'text-slate-500 hover:text-slate-800' ?>">
                <i class="fa-solid fa-file-contract mr-1"></i> Purchase Orders &amp; Goods Receipt (<?= count($pos) ?>)
            </a>
            <a href="index.php?page=inventory&tab=suppliers" class="pb-3 text-xs font-bold transition-colors <?= $activeTab === 'suppliers' ? 'border-b-2 border-amber-600 text-amber-600' : 'text-slate-500 hover:text-slate-800' ?>">
                <i class="fa-solid fa-truck-moving mr-1"></i> Vendors &amp; Suppliers (<?= count($allSuppliers) ?>)
            </a>
        </div>

        <!-- TAB 1: STOCK LEDGER -->
        <?php if ($activeTab === 'stock'): ?>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            
            <!-- Filter Pills & Search Toolbar -->
            <div class="p-3.5 border-b border-slate-200 bg-slate-50/70 flex flex-col md:flex-row md:items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-2">
                    <a href="index.php?page=inventory&tab=stock&filter=all" class="px-2.5 py-1 rounded-lg text-xs font-bold transition-all <?= $filter === 'all' ? 'bg-amber-600 text-white shadow-xs' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-100' ?>">
                        All Catalog (<?= (int)$stats['total_items'] ?>)
                    </a>
                    <a href="index.php?page=inventory&tab=stock&filter=narcotics" class="px-2.5 py-1 rounded-lg text-xs font-bold transition-all <?= $filter === 'narcotics' ? 'bg-purple-700 text-white shadow-xs' : 'bg-purple-50 text-purple-700 border border-purple-200 hover:bg-purple-100' ?>">
                        <i class="fa-solid fa-skull mr-1 text-purple-400"></i> Narcotics &amp; Controlled (<?= (int)$stats['narcotics_count'] ?>)
                    </a>
                    <a href="index.php?page=inventory&tab=stock&filter=near_expiry" class="px-2.5 py-1 rounded-lg text-xs font-bold transition-all <?= $filter === 'near_expiry' ? 'bg-amber-500 text-white shadow-xs' : 'bg-amber-50 text-amber-800 border border-amber-200 hover:bg-amber-100' ?>">
                        <i class="fa-solid fa-clock-rotate-left mr-1 text-amber-500"></i> Near-Expiry &lt;90d (<?= (int)$stats['near_expiry_count'] ?>)
                    </a>
                    <a href="index.php?page=inventory&tab=stock&filter=expired" class="px-2.5 py-1 rounded-lg text-xs font-bold transition-all <?= $filter === 'expired' ? 'bg-rose-700 text-white shadow-xs' : 'bg-rose-50 text-rose-700 border border-rose-200 hover:bg-rose-100' ?>">
                        <i class="fa-solid fa-ban mr-1 text-rose-400"></i> Expired (<?= (int)$stats['expired_count'] ?>)
                    </a>
                    <a href="index.php?page=inventory&tab=stock&filter=low_stock" class="px-2.5 py-1 rounded-lg text-xs font-bold transition-all <?= $filter === 'low_stock' ? 'bg-red-600 text-white shadow-xs' : 'bg-red-50 text-red-700 border border-red-200 hover:bg-red-100' ?>">
                        <i class="fa-solid fa-triangle-exclamation mr-1 text-red-500"></i> Low Stock (<?= (int)$stats['low_stock_alerts'] ?>)
                    </a>
                </div>

                <!-- Search box -->
                <form method="GET" action="index.php" class="flex items-center gap-2">
                    <input type="hidden" name="page" value="inventory">
                    <input type="hidden" name="tab" value="stock">
                    <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
                    <div class="relative">
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search medicine, code, batch..." class="pl-8 pr-3 py-1.5 text-xs bg-white border border-slate-200 rounded-lg outline-none focus:border-amber-500 w-56 font-sans">
                        <i class="fa-solid fa-magnifying-glass absolute left-2.5 top-2.5 text-slate-400 text-[11px]"></i>
                    </div>
                    <?php if (!empty($search)): ?>
                        <a href="index.php?page=inventory&tab=stock&filter=<?= htmlspecialchars($filter) ?>" class="text-xs text-slate-400 hover:text-slate-600">Clear</a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Table -->
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead class="bg-slate-50 border-b border-slate-200 text-slate-600 uppercase font-semibold text-[11px]">
                        <tr>
                            <th class="py-3 px-4">Item Code</th>
                            <th class="py-3 px-4">Medicine / Consumable Name</th>
                            <th class="py-3 px-4">Category &amp; Storage</th>
                            <th class="py-3 px-4">Batch # &amp; Expiry</th>
                            <th class="py-3 px-4">Current Stock</th>
                            <th class="py-3 px-4">Reorder</th>
                            <th class="py-3 px-4">Unit Cost</th>
                            <th class="py-3 px-4">Sale MRP</th>
                            <th class="py-3 px-4">Valuation</th>
                            <th class="py-3 px-4">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        <?php if (empty($items)): ?>
                        <tr>
                            <td colspan="10" class="text-center py-12 text-slate-400">
                                <i class="fa-solid fa-box-open text-3xl mb-2 text-slate-300"></i>
                                <p>No matching inventory items found for this filter criteria.</p>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($items as $it): 
                            $isExpired = !empty($it['expiry_date']) && strtotime($it['expiry_date']) < time();
                            $daysLeft = !empty($it['expiry_date']) ? round((strtotime($it['expiry_date']) - time()) / 86400) : null;
                            $isNearExpiry = ($daysLeft !== null && $daysLeft >= 0 && $daysLeft <= 90);
                            $isLow = $it['current_stock'] <= $it['reorder_level'];
                        ?>
                        <tr class="hover:bg-slate-50 transition-colors <?= $isExpired ? 'bg-rose-50/40' : ($isNearExpiry ? 'bg-amber-50/30' : ($isLow ? 'bg-red-50/20' : '')) ?>">
                            <td class="py-3 px-4 font-mono font-bold text-slate-900"><?= htmlspecialchars($it['item_code']) ?></td>
                            <td class="py-3 px-4">
                                <div class="font-bold text-slate-900 flex items-center gap-1.5 flex-wrap">
                                    <span><?= htmlspecialchars($it['item_name']) ?></span>
                                    <?php if (!empty($it['is_narcotic'])): ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-purple-100 text-purple-800 border border-purple-200">
                                            <i class="fa-solid fa-skull mr-1 text-purple-600"></i> <?= htmlspecialchars($it['narcotic_schedule'] ?? 'Controlled') ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($it['generic_name'])): ?>
                                    <div class="text-[11px] text-slate-500 font-medium"><?= htmlspecialchars($it['generic_name']) ?></div>
                                <?php endif; ?>
                                <div class="text-[10px] text-slate-400 mt-0.5">
                                    Form: <span class="font-medium text-slate-600"><?= htmlspecialchars($it['dosage_form'] ?? 'Standard') ?></span>
                                </div>
                            </td>
                            <td class="py-3 px-4">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium bg-slate-100 text-slate-700">
                                    <?= htmlspecialchars($it['category_name']) ?>
                                </span>
                                <?php if (!empty($it['storage_condition'])): ?>
                                    <div class="text-[10px] text-slate-500 mt-1 flex items-center gap-1">
                                        <i class="fa-solid fa-temperature-arrow-down text-sky-500"></i>
                                        <span><?= htmlspecialchars($it['storage_condition']) ?></span>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 px-4 font-mono">
                                <div class="text-slate-800 font-semibold"><?= htmlspecialchars($it['batch_number'] ?? 'N/A') ?></div>
                                <?php if (!empty($it['expiry_date'])): ?>
                                    <div class="text-[11px] mt-0.5 <?= $isExpired ? 'text-rose-600 font-bold' : ($isNearExpiry ? 'text-amber-600 font-bold' : 'text-slate-500') ?>">
                                        <?= date('d M Y', strtotime($it['expiry_date'])) ?>
                                        <?php if ($isExpired): ?>
                                            <span class="inline-block px-1.5 py-0.2 rounded bg-rose-100 text-rose-800 text-[9px] font-extrabold uppercase ml-1">Expired</span>
                                        <?php elseif ($isNearExpiry): ?>
                                            <span class="inline-block px-1.5 py-0.2 rounded bg-amber-100 text-amber-800 text-[9px] font-bold ml-1"><?= $daysLeft ?>d left</span>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-[10px] text-slate-400">Non-perishable</div>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 px-4">
                                <div class="font-bold font-mono <?= $isLow ? 'text-red-600' : 'text-slate-800' ?>">
                                    <?= (int)$it['current_stock'] ?> <span class="text-[10px] font-normal text-slate-400"><?= htmlspecialchars($it['unit_of_measure']) ?></span>
                                </div>
                            </td>
                            <td class="py-3 px-4 text-slate-500 font-mono"><?= (int)$it['reorder_level'] ?></td>
                            <td class="py-3 px-4 text-slate-700"><?= format_currency($it['unit_cost_price']) ?></td>
                            <td class="py-3 px-4">
                                <?php if (!empty($it['unit_sale_price']) && (float)$it['unit_sale_price'] > 0): ?>
                                    <span class="font-semibold text-emerald-700 font-mono"><?= format_currency($it['unit_sale_price']) ?></span>
                                <?php else: ?>
                                    <span class="text-slate-400 text-[11px]">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 px-4 font-semibold text-slate-900"><?= format_currency($it['current_stock'] * $it['unit_cost_price']) ?></td>
                            <td class="py-3 px-4 whitespace-nowrap">
                                <?php if ($isExpired): ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-800 border border-rose-200">
                                        <i class="fa-solid fa-ban mr-1"></i> Quarantined
                                    </span>
                                <?php elseif ($isLow): ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-red-100 text-red-800 border border-red-200">
                                        <i class="fa-solid fa-triangle-exclamation mr-1"></i> Low Stock
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-100 text-emerald-800">
                                        Active
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- TAB 2: PURCHASE ORDERS & GRN -->
        <?php if ($activeTab === 'pos'): ?>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead class="bg-slate-50 border-b border-slate-200 text-slate-600 uppercase font-semibold">
                        <tr>
                            <th class="py-3 px-4">PO #</th>
                            <th class="py-3 px-4">Supplier</th>
                            <th class="py-3 px-4">Order Date</th>
                            <th class="py-3 px-4">Expected Delivery</th>
                            <th class="py-3 px-4">Total Amount</th>
                            <th class="py-3 px-4">Created By</th>
                            <th class="py-3 px-4">Status</th>
                            <th class="py-3 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        <?php if (empty($pos)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-12 text-slate-400">
                                No Purchase Orders on record.
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($pos as $po): ?>
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="py-3 px-4 font-mono font-bold text-slate-900"><?= htmlspecialchars($po['po_number']) ?></td>
                            <td class="py-3 px-4 font-semibold text-slate-900">
                                <?= htmlspecialchars($po['supplier_name']) ?>
                                <span class="text-[10px] text-slate-400 font-mono block"><?= htmlspecialchars($po['supplier_code']) ?></span>
                            </td>
                            <td class="py-3 px-4 text-slate-500"><?= date('d M Y', strtotime($po['order_date'])) ?></td>
                            <td class="py-3 px-4 text-slate-500"><?= $po['expected_delivery_date'] ? date('d M Y', strtotime($po['expected_delivery_date'])) : 'TBD' ?></td>
                            <td class="py-3 px-4 font-semibold text-slate-900"><?= format_currency($po['total_amount']) ?></td>
                            <td class="py-3 px-4 text-slate-600"><?= htmlspecialchars($po['created_by_name']) ?></td>
                            <td class="py-3 px-4">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold <?= $po['status'] === 'Received' ? 'bg-emerald-100 text-emerald-800' : ($po['status'] === 'Approved' ? 'bg-blue-100 text-blue-800' : 'bg-amber-100 text-amber-800') ?>">
                                    <?= htmlspecialchars($po['status']) ?>
                                </span>
                            </td>
                            <td class="py-3 px-4 text-right">
                                <?php if ($po['status'] !== 'Received'): ?>
                                <form method="POST" action="index.php?action=po_receive" class="inline" onsubmit="return confirm('Confirm Goods Receipt Note (GRN) for this order? Inventory stock will be updated automatically.')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="po_id" value="<?= $po['id'] ?>">
                                    <button type="submit" class="text-xs bg-emerald-50 hover:bg-emerald-100 text-emerald-700 font-bold px-2.5 py-1 rounded border border-emerald-200">
                                        <i class="fa-solid fa-check-double mr-1"></i> Receive GRN
                                    </button>
                                </form>
                                <?php else: ?>
                                <span class="text-slate-400 font-mono text-[11px]"><i class="fa-solid fa-circle-check text-emerald-600 mr-1"></i> Fulfilled</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- TAB 3: SUPPLIERS & VENDORS -->
        <?php if ($activeTab === 'suppliers'): ?>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead class="bg-slate-50 border-b border-slate-200 text-slate-600 uppercase font-semibold">
                        <tr>
                            <th class="py-3 px-4">Vendor Code</th>
                            <th class="py-3 px-4">Company Name</th>
                            <th class="py-3 px-4">Contact Person</th>
                            <th class="py-3 px-4">Phone / Contact</th>
                            <th class="py-3 px-4">Email</th>
                            <th class="py-3 px-4">Address</th>
                            <th class="py-3 px-4">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        <?php if (empty($allSuppliers)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-12 text-slate-400">
                                No vendor suppliers registered yet.
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($allSuppliers as $sup): ?>
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="py-3 px-4 font-mono font-bold text-slate-900"><?= htmlspecialchars($sup['code']) ?></td>
                            <td class="py-3 px-4 font-bold text-slate-900"><?= htmlspecialchars($sup['supplier_name']) ?></td>
                            <td class="py-3 px-4 text-slate-600"><?= htmlspecialchars($sup['contact_person'] ?? '-') ?></td>
                            <td class="py-3 px-4 font-mono text-slate-700"><?= htmlspecialchars($sup['phone']) ?></td>
                            <td class="py-3 px-4 text-slate-500"><?= htmlspecialchars($sup['email'] ?? '-') ?></td>
                            <td class="py-3 px-4 text-slate-500 max-w-xs truncate"><?= htmlspecialchars($sup['address'] ?? '-') ?></td>
                            <td class="py-3 px-4">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-100 text-emerald-800">
                                    <?= htmlspecialchars($sup['status']) ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

    </div>
</main>

<!-- MODAL: ADD INVENTORY / PHARMACEUTICAL ITEM -->
<div id="modal-add-item" class="fixed inset-0 bg-slate-900/60 z-50 flex items-center justify-center hidden p-4">
    <div class="bg-white rounded-2xl max-w-xl w-full shadow-2xl border border-slate-200 overflow-hidden max-h-[92vh] flex flex-col">
        <div class="px-6 py-4 bg-amber-600 text-white flex items-center justify-between flex-shrink-0">
            <div>
                <h3 class="font-bold text-base">Register Medicine / Stock Consumable</h3>
                <p class="text-xs text-amber-100">Supports regular drugs, controlled substances (narcotics), batches, and expiry dates.</p>
            </div>
            <button type="button" onclick="document.getElementById('modal-add-item').classList.add('hidden')" class="text-white/80 hover:text-white text-lg">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST" action="index.php?action=inventory_item_store" class="p-6 space-y-4 overflow-y-auto custom-scrollbar flex-1">
            <?= csrf_field() ?>

            <div class="grid grid-cols-2 gap-3">
                <div class="col-span-2 sm:col-span-1">
                    <label class="form-label text-xs">Item / Brand Name <span class="text-red-500">*</span></label>
                    <input type="text" name="item_name" required placeholder="e.g. Morphine Sulfate 10mg / Augmentin 625mg" class="form-input text-xs font-semibold">
                </div>
                <div class="col-span-2 sm:col-span-1">
                    <label class="form-label text-xs">Generic Formula</label>
                    <input type="text" name="generic_name" placeholder="e.g. Morphine Sulfate / Amoxicillin + Clav" class="form-input text-xs">
                </div>
            </div>

            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="form-label text-xs">Item Code <span class="text-red-500">*</span></label>
                    <input type="text" name="item_code" required placeholder="e.g. MED-MOR-10" class="form-input text-xs font-mono">
                </div>
                <div>
                    <label class="form-label text-xs">Category <span class="text-red-500">*</span></label>
                    <select name="category_id" required class="form-input text-xs">
                        <option value="">-- Category --</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['category_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label text-xs">Dosage Form</label>
                    <select name="dosage_form" class="form-input text-xs">
                        <option value="Tablets">Tablets</option>
                        <option value="Capsules">Capsules</option>
                        <option value="Injection">Injection (Ampoule/Vial)</option>
                        <option value="Infusion">IV Infusion / Fluid</option>
                        <option value="Syrup">Syrup / Suspension</option>
                        <option value="Inhaler">Inhaler / Respule</option>
                        <option value="Ointment">Ointment / Gel</option>
                        <option value="Eye_Drops">Eye / Ear Drops</option>
                        <option value="Surgical Disposable">Surgical Disposable</option>
                        <option value="Diagnostic Reagent">Diagnostic Reagent</option>
                    </select>
                </div>
            </div>

            <!-- Controlled Substance / Narcotics Section -->
            <div class="p-3.5 bg-purple-50/70 border border-purple-200 rounded-xl space-y-3">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <input type="checkbox" name="is_narcotic" value="1" id="is_narcotic_check" class="rounded text-purple-600 focus:ring-purple-500 h-4 w-4">
                        <label for="is_narcotic_check" class="text-xs font-bold text-purple-900 cursor-pointer">
                            <i class="fa-solid fa-skull mr-1 text-purple-700"></i> Controlled Substance / Narcotic Medication
                        </label>
                    </div>
                    <span class="text-[10px] text-purple-700 font-semibold bg-purple-100 px-2 py-0.5 rounded">High Security Storage</span>
                </div>

                <div class="grid grid-cols-2 gap-3 pt-1">
                    <div>
                        <label class="form-label text-xs text-purple-900">Narcotic Schedule</label>
                        <select name="narcotic_schedule" class="form-input text-xs bg-white">
                            <option value="">-- Select Schedule --</option>
                            <option value="Schedule II">Schedule II (High Abuse Potential, e.g. Morphine/Fentanyl)</option>
                            <option value="Schedule IV">Schedule IV (Low/Moderate Potential, e.g. Diazepam/Tramadol)</option>
                            <option value="Controlled Drug">Controlled Hospital Formulary</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label text-xs text-purple-900">Storage Condition</label>
                        <select name="storage_condition" class="form-input text-xs bg-white">
                            <option value="Room Temperature (15-25°C)">Room Temperature (15-25°C)</option>
                            <option value="Cold Chain (2-8°C)">Cold Chain (2-8°C Refrigerator)</option>
                            <option value="Controlled Vault / Double-Lock Safe">Controlled Double-Lock Safe</option>
                            <option value="Deep Freeze (-20°C)">Deep Freeze (-20°C)</option>
                            <option value="Protect from Light">Protect from Light</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Batch & Expiry Tracking -->
            <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl space-y-3">
                <div class="text-[11px] font-bold text-slate-700 uppercase tracking-wider">Batch Identification &amp; Shelf Life</div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="form-label text-xs">Initial Batch Number</label>
                        <input type="text" name="batch_number" placeholder="e.g. BT-2026-901" class="form-input text-xs font-mono font-bold">
                    </div>
                    <div>
                        <label class="form-label text-xs">Batch Expiry Date</label>
                        <input type="date" name="expiry_date" value="<?= date('Y-m-d', strtotime('+18 months')) ?>" class="form-input text-xs font-mono">
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-4 gap-3">
                <div>
                    <label class="form-label text-xs">Unit UOM</label>
                    <input type="text" name="unit_of_measure" value="Box / Pack" class="form-input text-xs">
                </div>
                <div>
                    <label class="form-label text-xs">Opening Stock</label>
                    <input type="number" name="current_stock" value="50" class="form-input text-xs font-bold">
                </div>
                <div>
                    <label class="form-label text-xs">Reorder Level</label>
                    <input type="number" name="reorder_level" value="20" class="form-input text-xs">
                </div>
                <div>
                    <label class="form-label text-xs">Cost Price (Rs.) <span class="text-red-500">*</span></label>
                    <input type="number" step="0.01" name="unit_cost_price" required placeholder="850.00" class="form-input text-xs font-semibold">
                </div>
            </div>

            <div>
                <label class="form-label text-xs">Unit Sale MRP (Rs.)</label>
                <input type="number" step="0.01" name="unit_sale_price" placeholder="1050.00" class="form-input text-xs font-semibold text-emerald-700">
                <p class="text-[10px] text-slate-400 mt-1">Dispensing counter retail price per unit billed to patients.</p>
            </div>

            <div class="pt-3 border-t border-slate-200 flex justify-end gap-3 flex-shrink-0">
                <button type="button" onclick="document.getElementById('modal-add-item').classList.add('hidden')" class="btn-secondary text-xs">Cancel</button>
                <button type="submit" class="btn-primary bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold">
                    <i class="fa-solid fa-check mr-1"></i> Save Item &amp; Inventory Controls
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: ADD SUPPLIER -->
<div id="modal-add-supplier" class="fixed inset-0 bg-slate-900/60 z-50 flex items-center justify-center hidden p-4">
    <div class="bg-white rounded-2xl max-w-md w-full shadow-2xl border border-slate-200 overflow-hidden">
        <div class="px-6 py-4 bg-slate-900 text-white flex items-center justify-between">
            <h3 class="font-bold text-base">Register Vendor / Supplier</h3>
            <button type="button" onclick="document.getElementById('modal-add-supplier').classList.add('hidden')" class="text-white/80 hover:text-white text-lg">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST" action="index.php?action=supplier_store" class="p-6 space-y-4">
            <?= csrf_field() ?>

            <div>
                <label class="form-label text-xs">Supplier Name <span class="text-red-500">*</span></label>
                <input type="text" name="supplier_name" required placeholder="e.g. Khyber Surgical Supplies Ltd." class="form-input text-xs">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label text-xs">Supplier Code <span class="text-red-500">*</span></label>
                    <input type="text" name="code" required placeholder="e.g. SUP-KHYBER" class="form-input text-xs font-mono">
                </div>
                <div>
                    <label class="form-label text-xs">Phone <span class="text-red-500">*</span></label>
                    <input type="text" name="phone" required placeholder="+92 91 ..." class="form-input text-xs">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label text-xs">Contact Person</label>
                    <input type="text" name="contact_person" placeholder="Manager" class="form-input text-xs">
                </div>
                <div>
                    <label class="form-label text-xs">Email</label>
                    <input type="email" name="email" placeholder="sales@vendor.com" class="form-input text-xs">
                </div>
            </div>

            <div>
                <label class="form-label text-xs">Office Address</label>
                <input type="text" name="address" placeholder="Peshawar Industrial Estate..." class="form-input text-xs">
            </div>

            <div class="pt-3 border-t border-slate-200 flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('modal-add-supplier').classList.add('hidden')" class="btn-secondary text-xs">Cancel</button>
                <button type="submit" class="btn-primary text-xs font-bold">
                    <i class="fa-solid fa-check mr-1"></i> Save Supplier
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: GENERATE PURCHASE ORDER -->
<div id="modal-create-po" class="fixed inset-0 bg-slate-900/60 z-50 flex items-center justify-center hidden p-4">
    <div class="bg-white rounded-2xl max-w-xl w-full shadow-2xl border border-slate-200 overflow-hidden">
        <div class="px-6 py-4 bg-medical-700 text-white flex items-center justify-between">
            <h3 class="font-bold text-base">Generate Vendor Purchase Order (PO)</h3>
            <button type="button" onclick="document.getElementById('modal-create-po').classList.add('hidden')" class="text-white/80 hover:text-white text-lg">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST" action="index.php?action=po_store" class="p-6 space-y-4">
            <?= csrf_field() ?>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="form-label text-xs">Select Supplier <span class="text-red-500">*</span></label>
                    <select name="supplier_id" required class="form-input text-xs font-semibold">
                        <option value="">-- Choose Vendor --</option>
                        <?php foreach ($suppliers as $s): ?>
                        <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['supplier_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label text-xs">Expected Delivery Date</label>
                    <input type="date" name="expected_delivery_date" value="<?= date('Y-m-d', strtotime('+7 days')) ?>" class="form-input text-xs">
                </div>
            </div>

            <!-- Primary Line Item -->
            <div class="p-3 bg-slate-50 rounded-lg border border-slate-200 space-y-3">
                <div class="text-[11px] font-bold text-slate-500 uppercase">Purchase Item Details</div>
                <div>
                    <label class="form-label text-xs">Select Stock Consumable Item <span class="text-red-500">*</span></label>
                    <select name="item_name" required class="form-input text-xs font-semibold">
                        <option value="">-- Choose Consumable --</option>
                        <?php foreach ($items as $it): ?>
                        <option value="<?= htmlspecialchars($it['item_name']) ?>">
                            <?= htmlspecialchars($it['item_name']) ?> (Current Stock: <?= (int)$it['current_stock'] ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="form-label text-xs">Order Quantity <span class="text-red-500">*</span></label>
                        <input type="number" name="quantity" value="50" min="1" required class="form-input text-xs font-bold">
                    </div>
                    <div>
                        <label class="form-label text-xs">Agreed Unit Price (Rs.) <span class="text-red-500">*</span></label>
                        <input type="number" step="0.01" name="unit_price" value="850.00" required class="form-input text-xs font-bold">
                    </div>
                </div>
            </div>

            <div class="pt-3 border-t border-slate-200 flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('modal-create-po').classList.add('hidden')" class="btn-secondary text-xs">Cancel</button>
                <button type="submit" class="btn-primary text-xs font-bold">
                    <i class="fa-solid fa-file-invoice mr-1"></i> Issue Purchase Order
                </button>
            </div>
        </form>
    </div>
</div>
