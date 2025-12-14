<?php
require_once '../includes/config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$action = isset($_GET['action']) ? $_GET['action'] : '';

// Handle different actions
switch ($action) {
    case 'statistics':
        getStatistics($conn);
        break;
    
    case 'categories':
        getCategories($conn);
        break;
    
    case 'books':
        getBooks($conn);
        break;
    
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

/**
 * Get book statistics
 */
function getStatistics($conn) {
    $user_id = $_SESSION['user_id'];
    $role = $_SESSION['role'];
    
    // For admin/librarian - show all books statistics
    if ($role === 'admin' || $role === 'librarian') {
        // Get total books, total copies, and available copies
        $sql = "SELECT 
                    COUNT(DISTINCT book_id) as total_books,
                    COALESCE(SUM(quantity), 0) as total_copies,
                    COALESCE(SUM(available_quantity), 0) as available_copies
                FROM books";
        
        $result = $conn->query($sql);
        $stats = $result->fetch_assoc();
        
        // Get total borrowed copies from borrowings table
        $borrowed_sql = "SELECT COUNT(*) as borrowed_copies
                         FROM borrowings 
                         WHERE status IN ('borrowed', 'overdue')";
        $borrowed_result = $conn->query($borrowed_sql);
        $borrowed_stats = $borrowed_result->fetch_assoc();
        
        echo json_encode([
            'success' => true,
            'statistics' => [
                'total_books' => (int)$stats['total_books'],
                'total_copies' => (int)$stats['total_copies'],
                'available_copies' => (int)$stats['available_copies'],
                'borrowed_copies' => (int)$borrowed_stats['borrowed_copies']
            ]
        ]);
    } 
    // For members - show only their borrowing statistics
    else {
        // Get member's borrowing stats
        $member_sql = "SELECT 
                        COUNT(CASE WHEN status = 'borrowed' THEN 1 END) as currently_borrowed,
                        COUNT(CASE WHEN status = 'overdue' THEN 1 END) as overdue_books,
                        COUNT(CASE WHEN status = 'returned' THEN 1 END) as total_returned,
                        COALESCE(SUM(CASE WHEN status IN ('borrowed', 'overdue') THEN fine_amount END), 0) as total_fines
                    FROM borrowings 
                    WHERE user_id = ?";
        
        $stmt = $conn->prepare($member_sql);
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
                'total_returned' => (int)$stats['total_returned'],
                'total_fines' => number_format((float)$stats['total_fines'], 2, '.', '')
            ]
        ]);
    }
}

/**
 * Get all categories
 */
function getCategories($conn) {
    $sql = "SELECT category_id, category_name FROM categories ORDER BY category_name ASC";
    $result = $conn->query($sql);
    
    $categories = [];
    while ($row = $result->fetch_assoc()) {
        $categories[] = [
            'category_id' => (int)$row['category_id'],
            'category_name' => $row['category_name']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'categories' => $categories
    ]);
}

/**
 * Get books with pagination and filters
 */
function getBooks($conn) {
    // Get parameters
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $category = isset($_GET['category']) ? (int)$_GET['category'] : 0;
    $sort = isset($_GET['sort']) ? $_GET['sort'] : 'title_asc';
    $limit = 12;
    $offset = ($page - 1) * $limit;
    
    // Build WHERE clause
    $where_conditions = [];
    $params = [];
    $param_types = "";
    
    // Search filter
    if ($search !== '') {
        $where_conditions[] = "(b.title LIKE ? OR b.author LIKE ? OR b.isbn LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $param_types .= "sss";
    }
    
    // Category filter
    if ($category > 0) {
        $where_conditions[] = "b.category_id = ?";
        $params[] = $category;
        $param_types .= "i";
    }
    
    $where_clause = count($where_conditions) > 0 ? "WHERE " . implode(" AND ", $where_conditions) : "";
    
    // Build ORDER BY clause
    $order_by = "b.title ASC";
    switch ($sort) {
        case 'title_asc':
            $order_by = "b.title ASC";
            break;
        case 'title_desc':
            $order_by = "b.title DESC";
            break;
        case 'author_asc':
            $order_by = "b.author ASC";
            break;
        case 'author_desc':
            $order_by = "b.author DESC";
            break;
        case 'newest':
            $order_by = "b.created_at DESC";
            break;
        case 'oldest':
            $order_by = "b.created_at ASC";
            break;
    }
    
    // Get total count
    $count_sql = "SELECT COUNT(*) as total
                  FROM books b
                  LEFT JOIN categories c ON b.category_id = c.category_id
                  $where_clause";
    
    if (count($params) > 0) {
        $count_stmt = $conn->prepare($count_sql);
        $count_stmt->bind_param($param_types, ...$params);
        $count_stmt->execute();
        $total_result = $count_stmt->get_result();
        $total_books = $total_result->fetch_assoc()['total'];
        $count_stmt->close();
    } else {
        $total_result = $conn->query($count_sql);
        $total_books = $total_result->fetch_assoc()['total'];
    }
    
    // Get books
    $sql = "SELECT b.*, c.category_name
            FROM books b
            LEFT JOIN categories c ON b.category_id = c.category_id
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
    
    $books = [];
    while ($row = $result->fetch_assoc()) {
        $books[] = [
            'book_id' => (int)$row['book_id'],
            'isbn' => $row['isbn'],
            'title' => $row['title'],
            'author' => $row['author'],
            'publisher' => $row['publisher'],
            'publish_year' => $row['publish_year'],
            'category_id' => (int)$row['category_id'],
            'category_name' => $row['category_name'],
            'quantity' => (int)$row['quantity'],
            'available_quantity' => (int)$row['available_quantity'],
            'shelf_location' => $row['shelf_location'],
            'description' => $row['description'],
            'cover_image' => $row['cover_image']
        ];
    }
    
    if (isset($stmt)) {
        $stmt->close();
    }
    
    // Calculate pagination
    $total_pages = ceil($total_books / $limit);
    
    echo json_encode([
        'success' => true,
        'books' => $books,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $total_pages,
            'total_books' => (int)$total_books,
            'per_page' => $limit
        ]
    ]);
}
?>