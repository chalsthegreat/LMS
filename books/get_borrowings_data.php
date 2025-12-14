<?php
require_once '../includes/config.php';

// Check if user is logged in and is a member
if (!isLoggedIn() || $_SESSION['role'] !== 'member') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$action = isset($_GET['action']) ? $_GET['action'] : '';
$user_id = $_SESSION['user_id'];

// Handle different actions
switch ($action) {
    case 'statistics':
        getStatistics($conn, $user_id);
        break;
    
    case 'borrowings':
        getBorrowings($conn, $user_id);
        break;
    
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

/**
 * Get borrowing statistics
 */
function getStatistics($conn, $user_id) {
    $sql = "SELECT 
                COUNT(CASE WHEN status = 'borrowed' THEN 1 END) as currently_borrowed,
                COUNT(CASE WHEN status = 'overdue' THEN 1 END) as overdue_books,
                COUNT(CASE WHEN status = 'returned' THEN 1 END) as returned_books,
                COALESCE(SUM(CASE WHEN status IN ('borrowed', 'overdue') THEN fine_amount END), 0) as total_fines
            FROM borrowings 
            WHERE user_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats = $result->fetch_assoc();
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'statistics' => [
            'currently_borrowed' => (int)$stats['currently_borrowed'],
            'overdue_books' => (int)$stats['overdue_books'],
            'returned_books' => (int)$stats['returned_books'],
            'total_fines' => number_format((float)$stats['total_fines'], 2, '.', '')
        ]
    ]);
}

/**
 * Get borrowings with pagination and filters
 */
function getBorrowings($conn, $user_id) {
    // Get parameters
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $status = isset($_GET['status']) ? $_GET['status'] : 'all';
    $sort = isset($_GET['sort']) ? $_GET['sort'] : 'borrow_date_desc';
    $limit = 10;
    $offset = ($page - 1) * $limit;
    
    // Build WHERE clause
    $where_conditions = ["b.user_id = ?"];
    $params = [$user_id];
    $param_types = "i";
    
    // Status filter
    if ($status !== 'all') {
        $where_conditions[] = "b.status = ?";
        $params[] = $status;
        $param_types .= "s";
    }
    
    // Search filter
    if ($search !== '') {
        $where_conditions[] = "(bk.title LIKE ? OR bk.author LIKE ? OR bk.isbn LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $param_types .= "sss";
    }
    
    $where_clause = implode(" AND ", $where_conditions);
    
    // Build ORDER BY clause
    $order_by = "b.borrow_date DESC";
    switch ($sort) {
        case 'borrow_date_asc':
            $order_by = "b.borrow_date ASC";
            break;
        case 'borrow_date_desc':
            $order_by = "b.borrow_date DESC";
            break;
        case 'due_date_asc':
            $order_by = "b.due_date ASC";
            break;
        case 'due_date_desc':
            $order_by = "b.due_date DESC";
            break;
        case 'title_asc':
            $order_by = "bk.title ASC";
            break;
        case 'title_desc':
            $order_by = "bk.title DESC";
            break;
        default:
            $order_by = "CASE 
                            WHEN b.status = 'overdue' THEN 1
                            WHEN b.status = 'borrowed' THEN 2
                            ELSE 3
                        END, b.borrow_date DESC";
    }
    
    // Get total count
    $count_sql = "SELECT COUNT(*) as total
                  FROM borrowings b
                  JOIN books bk ON b.book_id = bk.book_id
                  WHERE $where_clause";
    
    $count_stmt = $conn->prepare($count_sql);
    $count_stmt->bind_param($param_types, ...$params);
    $count_stmt->execute();
    $total_result = $count_stmt->get_result();
    $total_borrowings = $total_result->fetch_assoc()['total'];
    $count_stmt->close();
    
    // Get borrowings - UPDATED TO INCLUDE cart_batch_id
    $sql = "SELECT b.*, b.cart_batch_id, bk.title, bk.author, bk.isbn, bk.cover_image,
                   DATEDIFF(b.due_date, CURDATE()) as days_remaining,
                   DATEDIFF(CURDATE(), b.due_date) as days_overdue,
                   b.declined_date
            FROM borrowings b
            JOIN books bk ON b.book_id = bk.book_id
            WHERE $where_clause
            ORDER BY $order_by
            LIMIT ? OFFSET ?";
    
    $params[] = $limit;
    $params[] = $offset;
    $param_types .= "ii";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $borrowings = [];
    while ($row = $result->fetch_assoc()) {
        $borrowings[] = [
            'borrowing_id' => (int)$row['borrowing_id'],
            'book_id' => (int)$row['book_id'],
            'title' => $row['title'],
            'author' => $row['author'],
            'isbn' => $row['isbn'],
            'cover_image' => $row['cover_image'],
            'borrow_date' => $row['borrow_date'],
            'due_date' => $row['due_date'],
            'return_date' => $row['return_date'],
            'declined_date' => $row['declined_date'],
            'status' => $row['status'],
            'fine_amount' => number_format((float)$row['fine_amount'], 2, '.', ''),
            'remarks' => $row['remarks'],
            'days_remaining' => (int)$row['days_remaining'],
            'days_overdue' => (int)$row['days_overdue'],
            'cart_batch_id' => $row['cart_batch_id'] // ADDED THIS LINE
        ];
    }
    
    $stmt->close();
    
    // Calculate pagination
    $total_pages = ceil($total_borrowings / $limit);
    
    echo json_encode([
        'success' => true,
        'borrowings' => $borrowings,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $total_pages,
            'total_borrowings' => (int)$total_borrowings,
            'per_page' => $limit
        ]
    ]);
}
?>