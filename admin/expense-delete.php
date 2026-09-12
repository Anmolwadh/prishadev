<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    flash('error', 'Invalid request method.');
    redirect('admin/expenses.php');
}

require_csrf();

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    flash('error', 'Invalid expense ID.');
    redirect('admin/expenses.php');
}

$pdo = getDB();
$stmt = $pdo->prepare('SELECT * FROM expenses WHERE id = ?');
$stmt->execute([$id]);
$expense = $stmt->fetch();

if (!$expense) {
    flash('error', 'Expense record not found.');
    redirect('admin/expenses.php');
}

// Delete attached receipt file if it exists
if (!empty($expense['receipt_file'])) {
    $receiptPath = BASE_PATH . '/uploads/receipts/' . $expense['receipt_file'];
    if (is_file($receiptPath)) {
        @unlink($receiptPath);
    }
}

$del = $pdo->prepare('DELETE FROM expenses WHERE id = ?');
$del->execute([$id]);

$month = !empty($expense['bill_date']) ? substr((string)$expense['bill_date'], 0, 7) : date('Y-m');
flash('success', 'Expense bill "' . $expense['title'] . '" deleted successfully.');
redirect('admin/expenses.php?month=' . $month);
