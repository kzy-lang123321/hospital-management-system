<?php
/**
 * Hospital Management System (HMS) - Central Inventory & Procurement
 */

Auth::requireRole(['super_admin', 'hospital_admin', 'pharmacist']);

$db = Database::getConnection();

// Overall KPIs
$statsQuery = $db->query("
    SELECT 
        COUNT(*) as total_items,
        COUNT(CASE WHEN current_stock <= reorder_level THEN 1 END) as low_stock_alerts,
        COALESCE(SUM(current_stock * unit_cost_price), 0) as total_valuation,
        (SELECT COUNT(*) FROM `purchase_orders` WHERE `status` IN ('Draft', 'Approved')) as active_pos
    FROM `inventory_items`
");
$stats = $statsQuery->fetch(PDO::FETCH_ASSOC);

// Tabs
$activeTab = clean_input($_GET['tab'] ?? 'stock');

// Categories & Suppliers
$categories = $db->query("SELECT * FROM `inventory_categories` ORDER BY category_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$suppliers = $db->query("SELECT * FROM `suppliers` WHERE `status` = 'Active' ORDER BY supplier_name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Stock items query
$items = $db->query("
    SELECT i.*, c.category_name 
    FROM `inventory_items` i
    JOIN `inventory_categories` c ON i.category_id = c.id
    ORDER BY CASE WHEN i.current_stock <= i.reorder_level THEN 1 ELSE 2 END, i.item_name ASC LIMIT 100
")->fetchAll(PDO::FETCH_ASSOC);

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
                <p class="text-sm text-slate-500 mt-1">Surgical Disposables, PPE, Reagents, Vendor Purchase Orders & Goods Receipt Note (GRN).</p>
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

        <!-- KPIs Strip -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                <div class="text-[11px] font-bold uppercase text-slate-500 tracking-wider">Stock Catalog</div>
                <div class="text-2xl font-black text-slate-800 mt-1"><?= (int)($stats['total_items'] ?? 0) ?></div>
                <div class="text-xs text-slate-400 font-medium mt-1">Unique Consumables</div>
            </div>

            <div class="bg-white p-4 rounded-xl border border-rose-200 shadow-sm">
                <div class="text-[11px] font-bold uppercase text-rose-600 tracking-wider">Reorder Alerts</div>
                <div class="text-2xl font-black text-rose-600 mt-1"><?= (int)($stats['low_stock_alerts'] ?? 0) ?></div>
                <div class="text-xs text-rose-500 font-medium mt-1">Stock &le; Reorder Level</div>
            </div>

            <div class="bg-white p-4 rounded-xl border border-emerald-200 shadow-sm">
                <div class="text-[11px] font-bold uppercase text-emerald-600 tracking-wider">Inventory Valuation</div>
                <div class="text-2xl font-black text-emerald-600 mt-1"><?= format_currency($stats['total_valuation']) ?></div>
                <div class="text-xs text-emerald-600 font-medium mt-1">Total On-Hand Assets</div>
            </div>

            <div class="bg-white p-4 rounded-xl border border-blue-200 shadow-sm">
                <div class="text-[11px] font-bold uppercase text-blue-600 tracking-wider">Active POs</div>
                <div class="text-2xl font-black text-slate-800 mt-1"><?= (int)($stats['active_pos'] ?? 0) ?></div>
                <div class="text-xs text-blue-500 font-medium mt-1">Pending Delivery</div>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <div class="flex border-b border-slate-200 space-x-4">
            <a href="index.php?page=inventory&tab=stock" class="pb-3 text-xs font-bold transition-colors <?= $activeTab === 'stock' ? 'border-b-2 border-amber-600 text-amber-600' : 'text-slate-500 hover:text-slate-800' ?>">
                <i class="fa-solid fa-boxes-stacked mr-1"></i> Stock Ledger
            </a>
            <a href="index.php?page=inventory&tab=pos" class="pb-3 text-xs font-bold transition-colors <?= $activeTab === 'pos' ? 'border-b-2 border-amber-600 text-amber-600' : 'text-slate-500 hover:text-slate-800' ?>">
                <i class="fa-solid fa-file-contract mr-1"></i> Purchase Orders & Goods Receipt (<?= count($pos) ?>)
            </a>
            <a href="index.php?page=inventory&tab=suppliers" class="pb-3 text-xs font-bold transition-colors <?= $activeTab === 'suppliers' ? 'border-b-2 border-amber-600 text-amber-600' : 'text-slate-500 hover:text-slate-800' ?>">
                <i class="fa-solid fa-truck-moving mr-1"></i> Vendors & Suppliers (<?= count($allSuppliers) ?>)
            </a>
        </div>

        <!-- TAB 1: STOCK LEDGER -->
        <?php if ($activeTab === 'stock'): ?>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead class="bg-slate-50 border-b border-slate-200 text-slate-600 uppercase font-semibold">
                        <tr>
                            <th class="py-3 px-4">Code</th>
                            <th class="py-3 px-4">Item Name</th>
                            <th class="py-3 px-4">Category</th>
                            <th class="py-3 px-4">Unit of Measure</th>
                            <th class="py-3 px-4">Current Stock</th>
                            <th class="py-3 px-4">Reorder Level</th>
                            <th class="py-3 px-4">Unit Cost</th>
                            <th class="py-3 px-4">Valuation</th>
                            <th class="py-3 px-4">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        <?php if (empty($items)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-12 text-slate-400">
                                No stock items in inventory ledger.
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($items as $it): ?>
                        <tr class="hover:bg-slate-50 transition-colors <?= $it['current_stock'] <= $it['reorder_level'] ? 'bg-amber-50/50' : '' ?>">
                            <td class="py-3 px-4 font-mono font-bold text-slate-900"><?= htmlspecialchars($it['item_code']) ?></td>
                            <td class="py-3 px-4 font-bold text-slate-900"><?= htmlspecialchars($it['item_name']) ?></td>
                            <td class="py-3 px-4">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium bg-slate-100 text-slate-700">
                                    <?= htmlspecialchars($it['category_name']) ?>
                                </span>
                            </td>
                            <td class="py-3 px-4 text-slate-500"><?= htmlspecialchars($it['unit_of_measure']) ?></td>
                            <td class="py-3 px-4 font-bold <?= $it['current_stock'] <= $it['reorder_level'] ? 'text-red-600' : 'text-slate-800' ?>">
                                <?= (int)$it['current_stock'] ?>
                            </td>
                            <td class="py-3 px-4 text-slate-500"><?= (int)$it['reorder_level'] ?></td>
                            <td class="py-3 px-4"><?= format_currency($it['unit_cost_price']) ?></td>
                            <td class="py-3 px-4 font-semibold text-slate-900"><?= format_currency($it['current_stock'] * $it['unit_cost_price']) ?></td>
                            <td class="py-3 px-4 whitespace-nowrap">
                                <?php if ($it['current_stock'] <= $it['reorder_level']): ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold bg-red-100 text-red-800">
                                        <i class="fa-solid fa-triangle-exclamation mr-1"></i> Reorder
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-100 text-emerald-800">Sufficient</span>
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
                                No Purchase Orders recorded.
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($pos as $p): ?>
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="py-3 px-4 font-mono font-bold text-slate-900"><?= htmlspecialchars($p['po_number']) ?></td>
                            <td class="py-3 px-4 font-semibold text-slate-800"><?= htmlspecialchars($p['supplier_name']) ?></td>
                            <td class="py-3 px-4 text-slate-500"><?= date('d M Y', strtotime($p['order_date'])) ?></td>
                            <td class="py-3 px-4 text-slate-500"><?= $p['expected_delivery_date'] ? date('d M Y', strtotime($p['expected_delivery_date'])) : 'Open' ?></td>
                            <td class="py-3 px-4 font-bold text-slate-900"><?= format_currency($p['total_amount']) ?></td>
                            <td class="py-3 px-4 text-slate-600"><?= htmlspecialchars($p['created_by_name']) ?></td>
                            <td class="py-3 px-4 whitespace-nowrap">
                                <?php if ($p['status'] === 'Received'): ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-100 text-emerald-800">
                                        <i class="fa-solid fa-check mr-1"></i> Received (GRN)
                                    </span>
                                <?php elseif ($p['status'] === 'Approved'): ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-blue-100 text-blue-800">Approved</span>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-amber-100 text-amber-800"><?= htmlspecialchars($p['status']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 px-4 text-right whitespace-nowrap">
                                <?php if ($p['status'] !== 'Received' && $p['status'] !== 'Cancelled'): ?>
                                <form method="POST" action="index.php?action=po_receive" class="inline" onsubmit="return confirm('Receive this PO and increment warehouse items?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="po_id" value="<?= $p['id'] ?>">
                                    <button type="submit" class="btn-primary bg-emerald-600 hover:bg-emerald-700 py-1 px-2.5 text-xs text-white font-semibold">
                                        <i class="fa-solid fa-box-open mr-1"></i> Receive Goods (GRN)
                                    </button>
                                </form>
                                <?php else: ?>
                                <span class="text-xs text-slate-400">Archived</span>
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

        <!-- TAB 3: SUPPLIERS -->
        <?php if ($activeTab === 'suppliers'): ?>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead class="bg-slate-50 border-b border-slate-200 text-slate-600 uppercase font-semibold">
                        <tr>
                            <th class="py-3 px-4">Code</th>
                            <th class="py-3 px-4">Supplier Name</th>
                            <th class="py-3 px-4">Contact Person</th>
                            <th class="py-3 px-4">Phone</th>
                            <th class="py-3 px-4">Email</th>
                            <th class="py-3 px-4">Address</th>
                            <th class="py-3 px-4">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        <?php if (empty($allSuppliers)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-12 text-slate-400">
                                No registered suppliers.
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($allSuppliers as $sup): ?>
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="py-3 px-4 font-mono font-bold text-slate-900"><?= htmlspecialchars($sup['code']) ?></td>
                            <td class="py-3 px-4 font-bold text-slate-900"><?= htmlspecialchars($sup['supplier_name']) ?></td>
                            <td class="py-3 px-4 text-slate-700"><?= htmlspecialchars($sup['contact_person'] ?: 'N/A') ?></td>
                            <td class="py-3 px-4 text-slate-600"><?= htmlspecialchars($sup['phone']) ?></td>
                            <td class="py-3 px-4 text-slate-500"><?= htmlspecialchars($sup['email'] ?: 'N/A') ?></td>
                            <td class="py-3 px-4 text-slate-500 truncate max-w-xs"><?= htmlspecialchars($sup['address'] ?: 'N/A') ?></td>
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

<!-- MODAL: ADD INVENTORY ITEM -->
<div id="modal-add-item" class="fixed inset-0 bg-slate-900/60 z-50 flex items-center justify-center hidden p-4">
    <div class="bg-white rounded-2xl max-w-lg w-full shadow-2xl border border-slate-200 overflow-hidden">
        <div class="px-6 py-4 bg-amber-600 text-white flex items-center justify-between">
            <h3 class="font-bold text-base">Add Consumable Stock Item</h3>
            <button type="button" onclick="document.getElementById('modal-add-item').classList.add('hidden')" class="text-white/80 hover:text-white text-lg">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST" action="index.php?action=inventory_item_store" class="p-6 space-y-4">
            <?= csrf_field() ?>

            <div>
                <label class="form-label text-xs">Item Name <span class="text-red-500">*</span></label>
                <input type="text" name="item_name" required placeholder="e.g. Sterile Latex Surgical Gloves (Size 7.0)" class="form-input text-xs">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label text-xs">Item Code <span class="text-red-500">*</span></label>
                    <input type="text" name="item_code" required placeholder="e.g. INV-GLV-70" class="form-input text-xs font-mono">
                </div>
                <div>
                    <label class="form-label text-xs">Category <span class="text-red-500">*</span></label>
                    <select name="category_id" required class="form-input text-xs">
                        <option value="">-- Select Category --</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['category_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="form-label text-xs">Unit of Measure</label>
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
            </div>

            <div>
                <label class="form-label text-xs">Unit Cost Price (Rs.) <span class="text-red-500">*</span></label>
                <input type="number" step="0.01" name="unit_cost_price" required placeholder="850.00" class="form-input text-xs font-semibold">
            </div>

            <div class="pt-3 border-t border-slate-200 flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('modal-add-item').classList.add('hidden')" class="btn-secondary text-xs">Cancel</button>
                <button type="submit" class="btn-primary bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold">
                    <i class="fa-solid fa-check mr-1"></i> Save Item
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

            <!-- Single or Primary Line Item -->
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
