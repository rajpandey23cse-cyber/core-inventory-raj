<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();
$pageTitle = 'Products';
$pdo = getPDO();

$msg = ''; $msgType = 'success';

// Handle CRUD
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id       = intval($_POST['id'] ?? 0);
        $sku      = trim($_POST['sku'] ?? '');
        $name     = trim($_POST['name'] ?? '');
        $cat      = intval($_POST['category_id'] ?? 0) ?: null;
        $desc     = trim($_POST['description'] ?? '');
        $unit     = trim($_POST['unit'] ?? 'pcs');
        $price    = floatval($_POST['unit_price'] ?? 0);
        $cost     = floatval($_POST['cost_price'] ?? 0);
        $reorder  = intval($_POST['reorder_level'] ?? 10);
        $status   = $_POST['status'] === 'inactive' ? 'inactive' : 'active';

        if (!$sku || !$name) { $msg = 'SKU and Name are required.'; $msgType = 'danger'; }
        else {
            if ($id) {
                $stmt = $pdo->prepare("UPDATE products SET sku=?,name=?,category_id=?,description=?,unit=?,unit_price=?,cost_price=?,reorder_level=?,status=? WHERE id=?");
                $stmt->execute([$sku,$name,$cat,$desc,$unit,$price,$cost,$reorder,$status,$id]);
                $msg = 'Product updated successfully.';
            } else {
                $stmt = $pdo->prepare("INSERT INTO products (sku,name,category_id,description,unit,unit_price,cost_price,reorder_level,status) VALUES (?,?,?,?,?,?,?,?,?)");
                $stmt->execute([$sku,$name,$cat,$desc,$unit,$price,$cost,$reorder,$status]);
                $msg = 'Product added successfully.';
            }
        }
    }
    elseif ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        $pdo->prepare("DELETE FROM products WHERE id=?")->execute([$id]);
        $msg = 'Product deleted.';
    }
}

// Filters
$search  = trim($_GET['search'] ?? '');
$filterCat = intval($_GET['category'] ?? 0);
$filterStatus = $_GET['status'] ?? '';
$filterLow = $_GET['filter'] ?? '';
$page    = max(1, intval($_GET['p'] ?? 1));
$perPage = 15;

$where = ['1=1'];
$params = [];
if ($search) { $where[] = '(p.name LIKE ? OR p.sku LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($filterCat) { $where[] = 'p.category_id = ?'; $params[] = $filterCat; }
if ($filterStatus) { $where[] = 'p.status = ?'; $params[] = $filterStatus; }
if ($filterLow === 'low') { $where[] = 'COALESCE(total_qty,0) <= p.reorder_level'; }

$whereStr = implode(' AND ', $where);
$countSQL = "SELECT COUNT(*) FROM products p LEFT JOIN (SELECT product_id, SUM(quantity) as total_qty FROM stock GROUP BY product_id) s ON p.id=s.product_id WHERE $whereStr";
$stmt = $pdo->prepare($countSQL); $stmt->execute($params);
$total = $stmt->fetchColumn();
$pages = ceil($total / $perPage);
$offset = ($page - 1) * $perPage;

$sql = "SELECT p.*, c.name as cat_name, COALESCE(s.total_qty,0) as stock_qty
        FROM products p
        LEFT JOIN categories c ON p.category_id=c.id
        LEFT JOIN (SELECT product_id, SUM(quantity) as total_qty FROM stock GROUP BY product_id) s ON p.id=s.product_id
        WHERE $whereStr ORDER BY p.id DESC LIMIT $perPage OFFSET $offset";
        // WHERE $whereStr ORDER BY p.created_at DESC LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$products = $stmt->fetchAll();

$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType ?> alert-auto"><i class="fa fa-<?= $msgType==='success'?'check-circle':'circle-exclamation' ?>"></i> <?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<div class="page-header">
  <h2><i class="fa fa-box text-primary"></i> Products</h2>
  <button class="btn btn-primary" onclick="openModal('productModal')"><i class="fa fa-plus"></i> Add Product</button>
</div>

<!-- Filters -->
<div class="filters-bar">
  <form method="GET" style="display:contents">
    <div class="search-box">
      <i class="fa fa-search"></i>
      <input type="text" name="search" class="form-control" placeholder="Search products..." value="<?= htmlspecialchars($search) ?>">
    </div>
    <select name="category" class="form-control">
      <option value="">All Categories</option>
      <?php foreach ($categories as $c): ?>
      <option value="<?= $c['id'] ?>" <?= $filterCat==$c['id']?'selected':'' ?>><?= htmlspecialchars($c['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="status" class="form-control">
      <option value="">All Status</option>
      <option value="active" <?= $filterStatus==='active'?'selected':'' ?>>Active</option>
      <option value="inactive" <?= $filterStatus==='inactive'?'selected':'' ?>>Inactive</option>
    </select>
    <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-filter"></i> Filter</button>
    <a href="/inventory/products.php" class="btn btn-outline btn-sm">Clear</a>
  </form>
</div>

<div class="card">
  <div class="card-header">
    <span class="card-title">All Products <span class="text-muted">(<?= number_format($total) ?>)</span></span>
    <a href="/inventory/api/products.php?action=export" class="btn btn-sm btn-outline"><i class="fa fa-download"></i> Export CSV</a>
  </div>
  <div class="table-wrap">
    <table id="productsTable">
      <thead>
        <tr>
          <th>#</th>
          <th>SKU</th>
          <th>Product Name</th>
          <th>Category</th>
          <th>Unit</th>
          <th>Cost</th>
          <th>Price</th>
          <th>Stock</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($products): foreach ($products as $i => $p): ?>
        <?php $isLow = $p['stock_qty'] <= $p['reorder_level']; ?>
        <tr>
          <td><?= $offset + $i + 1 ?></td>
          <td><code><?= htmlspecialchars($p['sku']) ?></code></td>
          <td>
            <strong><?= htmlspecialchars($p['name']) ?></strong>
            <?php if ($p['description']): ?>
            <br><small class="text-muted"><?= htmlspecialchars(substr($p['description'],0,40)) ?><?= strlen($p['description'])>40?'...':''?></small>
            <?php endif; ?>
          </td>
          <td><?= htmlspecialchars($p['cat_name'] ?? '—') ?></td>
          <td><?= htmlspecialchars($p['unit']) ?></td>
          <td>$<?= number_format($p['cost_price'],2) ?></td>
          <td>$<?= number_format($p['unit_price'],2) ?></td>
          <td>
            <span class="badge <?= $isLow ? 'badge-warning' : 'badge-confirmed' ?>"><?= number_format($p['stock_qty']) ?></span>
            <?php if ($isLow): ?><span class="text-warning" title="Low stock"><i class="fa fa-triangle-exclamation"></i></span><?php endif; ?>
          </td>
          <td><span class="badge badge-<?= $p['status'] ?>"><?= ucfirst($p['status']) ?></span></td>
          <td>
            <div class="table-actions">
              <button class="btn btn-sm btn-outline" onclick="editProduct(<?= htmlspecialchars(json_encode($p)) ?>)" title="Edit"><i class="fa fa-edit"></i></button>
              <form method="POST" style="display:inline" onsubmit="return confirmDelete('Delete this product?')">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                <button type="submit" class="btn btn-sm btn-danger" title="Delete"><i class="fa fa-trash"></i></button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="10"><div class="empty-state"><i class="fa fa-box"></i><p>No products found.</p></div></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <!-- Pagination -->
  <?php if ($pages > 1): ?>
  <div class="pagination">
    <?php for ($i = 1; $i <= $pages; $i++): ?>
    <a href="?p=<?= $i ?>&search=<?= urlencode($search) ?>&category=<?= $filterCat ?>&status=<?= urlencode($filterStatus) ?>" class="page-btn <?= $i==$page?'active':'' ?>"><?= $i ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>

<!-- Product Modal -->
<div class="modal-overlay" id="productModal">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title" id="modalTitle">Add Product</h3>
      <button class="modal-close" onclick="closeModal('productModal')"><i class="fa fa-times"></i></button>
    </div>
    <form method="POST">
      <div class="modal-body">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" id="prod_id" value="0">
        <div class="form-grid">
          <div class="form-group">
            <label class="form-label">SKU *</label>
            <input type="text" name="sku" id="prod_sku" class="form-control" required placeholder="e.g. PROD-001">
          </div>
          <div class="form-group">
            <label class="form-label">Product Name *</label>
            <input type="text" name="name" id="prod_name" class="form-control" required>
          </div>
          <div class="form-group">
            <label class="form-label">Category</label>
            <select name="category_id" id="prod_cat" class="form-control">
              <option value="">-- Select Category --</option>
              <?php foreach ($categories as $c): ?>
              <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Unit</label>
            <select name="unit" id="prod_unit" class="form-control">
              <option value="pcs">Pieces (pcs)</option>
              <option value="kg">Kilogram (kg)</option>
              <option value="box">Box</option>
              <option value="liter">Liter</option>
              <option value="meter">Meter</option>
              <option value="set">Set</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Cost Price ($)</label>
            <input type="number" step="0.01" name="cost_price" id="prod_cost" class="form-control" value="0">
          </div>
          <div class="form-group">
            <label class="form-label">Selling Price ($)</label>
            <input type="number" step="0.01" name="unit_price" id="prod_price" class="form-control" value="0">
          </div>
          <div class="form-group">
            <label class="form-label">Reorder Level</label>
            <input type="number" name="reorder_level" id="prod_reorder" class="form-control" value="10">
          </div>
          <div class="form-group">
            <label class="form-label">Status</label>
            <select name="status" id="prod_status" class="form-control">
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>
          </div>
          <div class="form-group span-2">
            <label class="form-label">Description</label>
            <textarea name="description" id="prod_desc" class="form-control" rows="2" placeholder="Optional description..."></textarea>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('productModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Save Product</button>
      </div>
    </form>
  </div>
</div>

<script>
function editProduct(p) {
  document.getElementById('modalTitle').textContent = 'Edit Product';
  document.getElementById('prod_id').value = p.id;
  document.getElementById('prod_sku').value = p.sku;
  document.getElementById('prod_name').value = p.name;
  document.getElementById('prod_cat').value = p.category_id || '';
  document.getElementById('prod_unit').value = p.unit;
  document.getElementById('prod_cost').value = p.cost_price;
  document.getElementById('prod_price').value = p.unit_price;
  document.getElementById('prod_reorder').value = p.reorder_level;
  document.getElementById('prod_status').value = p.status;
  document.getElementById('prod_desc').value = p.description || '';
  openModal('productModal');
}
document.querySelector('[onclick="openModal(\'productModal\')"]')?.addEventListener('click', () => {
  document.getElementById('modalTitle').textContent = 'Add Product';
  document.getElementById('prod_id').value = '0';
  document.querySelector('#productModal form').reset();
});
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
