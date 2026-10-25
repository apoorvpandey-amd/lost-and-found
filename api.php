<?php
/**
 * Lost & Found Portal - RESTful API Endpoints
 * Enables pure HTML/JS frontend to communicate with backend seamlessly.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$pdo = getDbConnection();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get_items':
        handleGetItems($pdo);
        break;

    case 'get_item':
        handleGetSingleItem($pdo);
        break;

    case 'get_stats':
        handleGetStats($pdo);
        break;

    case 'get_categories':
        handleGetCategories();
        break;

    case 'submit_claim':
        handleSubmitClaim($pdo);
        break;

    case 'create_item':
        handleCreateItem($pdo);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Unknown action or endpoint not specified']);
        break;
}

function handleGetItems($pdo) {
    $type = trim($_GET['type'] ?? 'all');
    $category = trim($_GET['category'] ?? 'all');
    $query = trim($_GET['query'] ?? $_GET['q'] ?? '');
    $sort = trim($_GET['sort'] ?? 'newest');
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 0;

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

    if ($category !== 'all' && !empty($category)) {
        $where[] = "category = :category";
        $params[':category'] = $category;
    }

    if ($query !== '') {
        $where[] = "(title LIKE :q OR description LIKE :q OR location LIKE :q)";
        $params[':q'] = '%' . $query . '%';
    }

    $sql = "SELECT id, type, title, category, description, location, item_date, image_path, status, created_at, contact_name, contact_email, contact_phone FROM items";
    if (!empty($where)) {
        $sql .= " WHERE " . implode(' AND ', $where);
    }

    if ($sort === 'oldest') {
        $sql .= " ORDER BY item_date ASC, id ASC";
    } else {
        $sql .= " ORDER BY item_date DESC, id DESC";
    }

    if ($limit > 0) {
        $sql .= " LIMIT " . $limit;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll();

    // Format items with category metadata
    $categories = get_categories();
    foreach ($items as &$item) {
        $catKey = $item['category'];
        $item['category_label'] = $categories[$catKey]['label'] ?? ucfirst($catKey);
        $item['category_icon'] = '';
        $item['time_ago'] = time_ago($item['created_at']);
        if (!empty($item['image_path'])) {
            $item['image_url'] = UPLOAD_WEB_PATH . $item['image_path'];
        } else {
            $item['image_url'] = null;
        }
    }

    echo json_encode(['success' => true, 'count' => count($items), 'data' => $items]);
}

function handleGetSingleItem($pdo) {
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: (int)($_GET['id'] ?? 0);
    if (!$id) {
        echo json_encode(['success' => false, 'error' => 'Invalid item ID']);
        return;
    }

    $stmt = $pdo->prepare("SELECT * FROM items WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $item = $stmt->fetch();

    if (!$item) {
        echo json_encode(['success' => false, 'error' => 'Item not found']);
        return;
    }

    $categories = get_categories();
    $catKey = $item['category'];
    $item['category_label'] = $categories[$catKey]['label'] ?? ucfirst($catKey);
    $item['category_icon'] = '';
    $item['time_ago'] = time_ago($item['created_at']);
    if (!empty($item['image_path'])) {
        $item['image_url'] = UPLOAD_WEB_PATH . $item['image_path'];
    } else {
        $item['image_url'] = null;
    }

    echo json_encode(['success' => true, 'data' => $item]);
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

function handleGetCategories() {
    $categories = get_categories();
    echo json_encode(['success' => true, 'categories' => $categories]);
}

function handleSubmitClaim($pdo) {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
        return;
    }

    $itemId = filter_input(INPUT_POST, 'item_id', FILTER_VALIDATE_INT) ?: (int)($_POST['item_id'] ?? 0);
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

function handleCreateItem($pdo) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
        return;
    }

    $type = in_array($_POST['type'] ?? '', ['lost', 'found']) ? $_POST['type'] : 'lost';
    $title = sanitize($_POST['title'] ?? '');
    $category = sanitize($_POST['category'] ?? '');
    $item_date = sanitize($_POST['item_date'] ?? date('Y-m-d'));
    $location = sanitize($_POST['location'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $contact_name = sanitize($_POST['contact_name'] ?? '');
    $contact_email = sanitize($_POST['contact_email'] ?? '');
    $contact_phone = sanitize($_POST['contact_phone'] ?? '');

    $categories = get_categories();

    if (empty($title) || strlen($title) < 3) {
        echo json_encode(['success' => false, 'error' => 'Title must be at least 3 characters long.']);
        return;
    }

    if (empty($category) || !array_key_exists($category, $categories)) {
        echo json_encode(['success' => false, 'error' => 'Please select a valid category.']);
        return;
    }

    if (empty($location) || strlen($location) < 3) {
        echo json_encode(['success' => false, 'error' => 'Location must be at least 3 characters long.']);
        return;
    }

    if (empty($description) || strlen($description) < 10) {
        echo json_encode(['success' => false, 'error' => 'Description must be at least 10 characters long.']);
        return;
    }

    if (empty($contact_name)) {
        echo json_encode(['success' => false, 'error' => 'Contact name is required.']);
        return;
    }

    if (empty($contact_email) || !filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'error' => 'A valid contact email is required.']);
        return;
    }

    // Handle optional image upload
    $uploaded_image_name = null;
    if (isset($_FILES['item_image']) && $_FILES['item_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $uploadResult = handle_image_upload('item_image');
        if ($uploadResult['success']) {
            $uploaded_image_name = $uploadResult['filename'];
        } else {
            echo json_encode(['success' => false, 'error' => $uploadResult['error']]);
            return;
        }
    }

    $securitySecret = bin2hex(random_bytes(16));

    $sql = "
        INSERT INTO items (
            type, title, category, description, location, 
            item_date, image_path, contact_name, contact_email, 
            contact_phone, status, security_secret
        ) VALUES (
            :type, :title, :category, :description, :location, 
            :item_date, :image_path, :contact_name, :contact_email, 
            :contact_phone, 'active', :security_secret
        )
    ";

    $stmt = $pdo->prepare($sql);
    $success = $stmt->execute([
        ':type'            => $type,
        ':title'           => $title,
        ':category'        => $category,
        ':description'     => $description,
        ':location'        => $location,
        ':item_date'       => $item_date,
        ':image_path'      => $uploaded_image_name,
        ':contact_name'    => $contact_name,
        ':contact_email'   => $contact_email,
        ':contact_phone'   => $contact_phone,
        ':security_secret' => $securitySecret
    ]);

    if ($success) {
        $newItemId = $pdo->lastInsertId();
        echo json_encode([
            'success' => true,
            'id' => $newItemId,
            'message' => 'Item report published successfully!'
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to save item to database.']);
    }
}

