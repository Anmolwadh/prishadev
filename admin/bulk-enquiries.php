<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$pdo = getDB();

if (isset($_GET['status'], $_GET['id'])) {
    $id = (int)$_GET['id'];
    $status = (string)$_GET['status'];
    if (in_array($status, ['New', 'Contacted', 'Closed'], true)) {
        $pdo->prepare('UPDATE bulk_enquiries SET status = ? WHERE id = ?')->execute([$status, $id]);
        flash('success', 'Enquiry updated.');
    }
    redirect('admin/bulk-enquiries.php');
}

$rows = $pdo->query('SELECT * FROM bulk_enquiries ORDER BY created_at DESC')->fetchAll();
$pageTitle = 'Bulk Enquiries';
include __DIR__ . '/includes/header.php';
?>
<div class="admin-card">
  <div class="table-responsive">
    <table class="table align-middle">
      <thead><tr><th>Date</th><th>Name</th><th>Business</th><th>Phone</th><th>Product</th><th>Qty</th><th>Status</th><th>Message</th><th>Action</th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?= e(date('d M Y', strtotime($r['created_at']))) ?></td>
            <td><?= e($r['name']) ?><div class="small text-muted"><?= e((string)$r['email']) ?></div></td>
            <td><?= e((string)$r['business_name']) ?></td>
            <td><?= e($r['phone']) ?></td>
            <td><?= e((string)$r['product']) ?></td>
            <td><?= e((string)$r['quantity']) ?></td>
            <td><?= e($r['status']) ?></td>
            <td style="max-width:220px"><?= e((string)$r['message']) ?></td>
            <td class="text-nowrap">
              <a class="btn btn-sm btn-outline-success" href="?id=<?= (int)$r['id'] ?>&status=Contacted">Contacted</a>
              <a class="btn btn-sm btn-outline-secondary" href="?id=<?= (int)$r['id'] ?>&status=Closed">Close</a>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="9" class="text-center text-muted">No enquiries yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
