<?php
require_once '../includes/config.php';

// Check if user is logged in and has permission
if (!isLoggedIn() || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'librarian')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'transactions':
        getTransactions($conn);
        break;
    
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

/**
 * Get transactions with pagination and filters
 */
function getTransactions($conn) {
    // Get parameters
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $status = isset($_GET['status']) ? trim($_GET['status']) : '';
    $sort = isset($_GET['sort']) ? $_GET['sort'] : 'borrow_date_desc';
    $limit = 10;
    $offset = ($page - 1) * $limit;
    
    // Build WHERE clause
    $where_conditions = [];
    $params = [];
    $param_types = "";
    
    // Search filter (user name, book title, or ISBN)
    if ($search !== '') {
        $where_conditions[] = "(u.full_name LIKE ? OR b.title LIKE ? OR b.isbn LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $param_types .= "sss";
    }
    
    // Status filter
    if ($status !== '') {
        $where_conditions[] = "bor.status = ?";
        $params[] = $status;
        $param_types .= "s";
    }
    
    $where_clause = count($where_conditions) > 0 ? "WHERE " . implode(" AND ", $where_conditions) : "";
    
    // Build ORDER BY clause
    $order_by = "bor.borrow_date DESC";
    switch ($sort) {
        case 'borrow_date_asc':
            $order_by = "bor.borrow_date ASC";
            break;
        case 'borrow_date_desc':
            $order_by = "bor.borrow_date DESC";
            break;
        case 'due_date_asc':
            $order_by = "bor.due_date ASC";
            break;
        case 'due_date_desc':
            $order_by = "bor.due_date DESC";
            break;
    }
    
    // Get total count
    $count_sql = "SELECT COUNT(*) as total
                  FROM borrowings bor
                  LEFT JOIN users u ON bor.user_id = u.user_id
                  LEFT JOIN books b ON bor.book_id = b.book_id
                  $where_clause";
    
    if (count($params) > 0) {
        $count_stmt = $conn->prepare($count_sql);
        $count_stmt->bind_param($param_types, ...$params);
        $count_stmt->execute();
        $total_result = $count_stmt->get_result();
        $total_transactions = $total_result->fetch_assoc()['total'];
        $count_stmt->close();
    } else {
        $total_result = $conn->query($count_sql);
        $total_transactions = $total_result->fetch_assoc()['total'];
    }
    
    // Get transactions
    $sql = "SELECT bor.*, u.full_name, b.title, b.author, b.isbn
            FROM borrowings bor
            LEFT JOIN users u ON bor.user_id = u.user_id
            LEFT JOIN books b ON bor.book_id = b.book_id
            $where_clause
            ORDER BY $order_by
            LIMIT ? OFFSET ?";
    
    $params[] = $limit;
    $params[] = $offset;
    $param_types .= "ii";
    
    if (count($params) > 0) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($param_types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query($sql);
    }
    
    $transactions = [];
    while ($row = $result->fetch_assoc()) {
        $transactions[] = [
            'borrowing_id' => (int)$row['borrowing_id'],
            'full_name' => $row['full_name'],
            'title' => $row['title'],
            'author' => $row['author'],
            'isbn' => $row['isbn'],
            'borrow_date' => $row['borrow_date'],
            'due_date' => $row['due_date'],
            'return_date' => $row['return_date'],
            'status' => $row['status']
        ];
    }
    
    if (isset($stmt)) {
        $stmt->close();
    }
    
    // Calculate pagination
    $total_pages = ceil($total_transactions / $limit);
    
    echo json_encode([
        'success' => true,
        'transactions' => $transactions,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $total_pages,
            'total_transactions' => (int)$total_transactions,
            'per_page' => $limit
        ]
    ]);
}
?>