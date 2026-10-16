<?php
/**
 * Lost & Found Portal - RESTful API Endpoints
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db.php';

$pdo = getDbConnection();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get_items':
        handleGetItems($pdo);
        break;

    case 'get_stats':
        handleGetStats($pdo);
        break;

    case 'submit_claim':
        handleSubmitClaim($pdo);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Unknown action']);
        break;
}

function handleGetItems($pdo) {
    $type = trim($_GET['type'] ?? 'all');
    $category = trim($_GET['category'] ?? 'all');
    $query = trim($_GET['query'] ?? '');
    $sort = trim($_GET['sort'] ?? 'newest');

    $where = [];
    $params = [];

    if ($type !== 'all' && in_array($type, ['lost', 'found', 'claimed'])) {
        if ($type === 'claimed') {
            $where[] = "status IN ('claimed', 'returned')";
        } else {
            $where[] = "type = :type AND status = 'active'";
            $params[':type'] = $type;
        }
    }

    if ($category !== 'all') {
        $where[] = "category = :category";
        $params[':category'] = $category;
    }

    if ($query !== '') {
        $where[] = "(title LIKE :q OR description LIKE :q OR location LIKE :q)";
        $params[':q'] = '%' . $query . '%';
    }

    $sql = "SELECT id, type, title, category, description, location, item_date, image_path, status, created_at, contact_name, contact_email FROM items";
    if (!empty($where)) {
        $sql .= " WHERE " . implode(' AND ', $where);
    }

    if ($sort === 'oldest') {
        $sql .= " ORDER BY item_date ASC, id ASC";
    } else {
        $sql .= " ORDER BY item_date DESC, id DESC";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll();

    echo json_encode(['success' => true, 'count' => count($items), 'data' => $items]);
}

function handleGetStats($pdo) {
    $total = $pdo->query("SELECT COUNT(*) FROM items")->fetchColumn();
    $lost = $pdo->query("SELECT COUNT(*) FROM items WHERE type = 'lost' AND status = 'active'")->fetchColumn();
    $found = $pdo->query("SELECT COUNT(*) FROM items WHERE type = 'found' AND status = 'active'")->fetchColumn();
    $resolved = $pdo->query("SELECT COUNT(*) FROM items WHERE status IN ('claimed', 'returned')")->fetchColumn();

    echo json_encode([
        'success' => true,
        'stats' => [
            'total' => (int)$total,
            'lost' => (int)$lost,
            'found' => (int)$found,
            'resolved' => (int)$resolved
        ]
    ]);
}

function handleSubmitClaim($pdo) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
        return;
    }

    $itemId = filter_input(INPUT_POST, 'item_id', FILTER_VALIDATE_INT);
    $name = trim($_POST['claimant_name'] ?? '');
    $email = trim($_POST['claimant_email'] ?? '');
    $phone = trim($_POST['claimant_phone'] ?? '');
    $message = trim($_POST['claim_message'] ?? '');

    if (!$itemId || empty($name) || empty($email) || empty($message)) {
        echo json_encode(['success' => false, 'error' => 'Please provide all required fields (Name, Email, and Message/Proof).']);
        return;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'error' => 'Invalid email address provided.']);
        return;
    }

    // Verify item exists
    $stmt = $pdo->prepare("SELECT id, title, type FROM items WHERE id = :id");
    $stmt->execute([':id' => $itemId]);
    $item = $stmt->fetch();

    if (!$item) {
        echo json_encode(['success' => false, 'error' => 'The item you are claiming does not exist.']);
        return;
    }

    // Insert claim
    $stmt = $pdo->prepare("
        INSERT INTO claims (item_id, claimant_name, claimant_email, claimant_phone, claim_message, status)
        VALUES (:item_id, :claimant_name, :claimant_email, :claimant_phone, :claim_message, 'pending')
    ");
    $stmt->execute([
        ':item_id' => $itemId,
        ':claimant_name' => $name,
        ':claimant_email' => $email,
        ':claimant_phone' => $phone,
        ':claim_message' => $message
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Claim inquiry sent successfully! The reporter will review your proof of ownership and contact you.'
    ]);
}
