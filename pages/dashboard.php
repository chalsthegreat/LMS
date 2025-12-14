<?php
$page_title = "Dashboard";
require_once '../includes/config.php';
require_once '../includes/header.php';

// Get statistics based on role
$stats = [];

// Total books
$books_query = "SELECT COUNT(*) as total, SUM(available_quantity) as available FROM books";
$books_result = $conn->query($books_query);
$books_data = $books_result->fetch_assoc();
$stats['total_books'] = $books_data['total'];
$stats['available_books'] = $books_data['available'];

if ($role === 'admin' || $role === 'librarian') {
    // Total members
    $members_query = "SELECT COUNT(*) as total FROM users WHERE role = 'member'";
    $members_result = $conn->query($members_query);
    $stats['total_members'] = $members_result->fetch_assoc()['total'];
    
    // Active borrowings
    $borrowed_query = "SELECT COUNT(*) as total FROM borrowings WHERE status = 'borrowed'";
    $borrowed_result = $conn->query($borrowed_query);
    $stats['active_borrowings'] = $borrowed_result->fetch_assoc()['total'];
    
    // Overdue books
    $overdue_query = "SELECT COUNT(*) as total FROM borrowings WHERE status = 'borrowed' AND due_date < CURDATE()";
    $overdue_result = $conn->query($overdue_query);
    $stats['overdue_books'] = $overdue_result->fetch_assoc()['total'];
    
    // Recent transactions
    $recent_query = "SELECT b.borrowing_id, u.full_name, bk.title, b.borrow_date, b.due_date, b.status 
                     FROM borrowings b 
                     JOIN users u ON b.user_id = u.user_id 
                     JOIN books bk ON b.book_id = bk.book_id 
                     ORDER BY b.created_at DESC LIMIT 5";
    $recent_result = $conn->query($recent_query);
} else {
    // Member specific stats
    $my_borrowed_query = "SELECT COUNT(*) as total FROM borrowings WHERE user_id = ? AND status = 'borrowed'";
    $stmt = $conn->prepare($my_borrowed_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stats['my_borrowed'] = $stmt->get_result()->fetch_assoc()['total'];
    
    // My overdue books
    $my_overdue_query = "SELECT COUNT(*) as total FROM borrowings WHERE user_id = ? AND status = 'borrowed' AND due_date < CURDATE()";
    $stmt = $conn->prepare($my_overdue_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stats['my_overdue'] = $stmt->get_result()->fetch_assoc()['total'];
    
    // My total borrowed (history)
    $my_history_query = "SELECT COUNT(*) as total FROM borrowings WHERE user_id = ?";
    $stmt = $conn->prepare($my_history_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stats['my_history'] = $stmt->get_result()->fetch_assoc()['total'];
    
    // My current borrowings
    $my_books_query = "SELECT b.borrowing_id, bk.title, bk.author, b.borrow_date, b.due_date, b.status 
                       FROM borrowings b 
                       JOIN books bk ON b.book_id = bk.book_id 
                       WHERE b.user_id = ? AND b.status = 'borrowed' 
                       ORDER BY b.due_date ASC";
    $stmt = $conn->prepare($my_books_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $my_books_result = $stmt->get_result();
}

// Get alert message if any
$message = getMessage();
?>

<!-- Alert Messages -->
<?php if ($message): ?>
    <div class="mb-6">
        <?php if ($message['type'] === 'success'): ?>
            <div class="bg-green-50 border-l-4 border-green-500 text-green-700 p-4 rounded" role="alert">
                <div class="flex items-center">
                    <i class="fas fa-check-circle mr-2"></i>
                    <span><?php echo $message['message']; ?></span>
                </div>
            </div>
        <?php elseif ($message['type'] === 'error'): ?>
            <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded" role="alert">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    <span><?php echo $message['message']; ?></span>
                </div>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- Welcome Section -->
<div class="mb-8">
    <h1 class="text-3xl font-bold text-gray-800">Welcome, <?php echo $full_name; ?>! 👋</h1>
    <p class="text-gray-600 mt-2">Here's what's happening in your library today.</p>
</div>

<!-- Statistics Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <?php if ($role === 'admin' || $role === 'librarian'): ?>
        <!-- Total Books -->
        <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-blue-500 hover:shadow-lg transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm font-semibold uppercase">Total Books</p>
                    <p class="text-3xl font-bold text-gray-800 mt-2"><?php echo $stats['total_books']; ?></p>
                </div>
                <div class="bg-blue-100 rounded-full p-4">
                    <i class="fas fa-book text-blue-600 text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-gray-600 mt-4">
                <span class="text-green-600 font-semibold"><?php echo $stats['available_books']; ?></span> available
            </p>
        </div>

        <!-- Total Members -->
        <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-green-500 hover:shadow-lg transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm font-semibold uppercase">Total Users</p>
                    <p class="text-3xl font-bold text-gray-800 mt-2"><?php echo $stats['total_members']; ?></p>
                </div>
                <div class="bg-green-100 rounded-full p-4">
                    <i class="fas fa-users text-green-600 text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-gray-600 mt-4">Active registered users</p>
        </div>

        <!-- Active Borrowings -->
        <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-yellow-500 hover:shadow-lg transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm font-semibold uppercase">Active Loans</p>
                    <p class="text-3xl font-bold text-gray-800 mt-2"><?php echo $stats['active_borrowings']; ?></p>
                </div>
                <div class="bg-yellow-100 rounded-full p-4">
                    <i class="fas fa-exchange-alt text-yellow-600 text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-gray-600 mt-4">Currently borrowed</p>
        </div>

        <!-- Overdue Books -->
        <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-red-500 hover:shadow-lg transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm font-semibold uppercase">Overdue</p>
                    <p class="text-3xl font-bold text-gray-800 mt-2"><?php echo $stats['overdue_books']; ?></p>
                </div>
                <div class="bg-red-100 rounded-full p-4">
                    <i class="fas fa-exclamation-triangle text-red-600 text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-gray-600 mt-4">Need attention</p>
        </div>
    <?php else: ?>
        <!-- Member Statistics -->
        <!-- Total Books -->
        <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-blue-500 hover:shadow-lg transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm font-semibold uppercase">Available Books</p>
                    <p class="text-3xl font-bold text-gray-800 mt-2"><?php echo $stats['available_books']; ?></p>
                </div>
                <div class="bg-blue-100 rounded-full p-4">
                    <i class="fas fa-book text-blue-600 text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-gray-600 mt-4">Ready to borrow</p>
        </div>

        <!-- My Borrowed Books -->
        <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-green-500 hover:shadow-lg transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm font-semibold uppercase">My Books</p>
                    <p class="text-3xl font-bold text-gray-800 mt-2"><?php echo $stats['my_borrowed']; ?></p>
                </div>
                <div class="bg-green-100 rounded-full p-4">
                    <i class="fas fa-book-open text-green-600 text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-gray-600 mt-4">Currently borrowed</p>
        </div>

        <!-- My Overdue -->
        <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-red-500 hover:shadow-lg transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm font-semibold uppercase">Overdue</p>
                    <p class="text-3xl font-bold text-gray-800 mt-2"><?php echo $stats['my_overdue']; ?></p>
                </div>
                <div class="bg-red-100 rounded-full p-4">
                    <i class="fas fa-exclamation-triangle text-red-600 text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-gray-600 mt-4">Need to return</p>
        </div>

        <!-- Total History -->
        <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-purple-500 hover:shadow-lg transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm font-semibold uppercase">Total Borrowed</p>
                    <p class="text-3xl font-bold text-gray-800 mt-2"><?php echo $stats['my_history']; ?></p>
                </div>
                <div class="bg-purple-100 rounded-full p-4">
                    <i class="fas fa-history text-purple-600 text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-gray-600 mt-4">All time</p>
        </div>
    <?php endif; ?>
</div>

<!-- Quick Actions & Recent Activity -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Quick Actions -->
    <div class="lg:col-span-1">
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">
                <i class="fas fa-bolt text-yellow-500 mr-2"></i>Quick Actions
            </h2>
            <div class="space-y-3">
                <?php if ($role === 'admin' || $role === 'librarian'): ?>
                    <a href="../books/books.php?action=add" class="block w-full bg-blue-50 hover:bg-blue-100 text-blue-700 font-semibold py-3 px-4 rounded-lg transition">
                        <i class="fas fa-plus-circle mr-2"></i>Add New Book
                    </a>
                    <?php if ($role === 'admin'): // ONLY ADMIN CAN ADD MEMBERS NOW ?>
                    <a href="../members/members.php?action=add" class="block w-full bg-green-50 hover:bg-green-100 text-green-700 font-semibold py-3 px-4 rounded-lg transition">
                        <i class="fas fa-user-plus mr-2"></i>Add New User
                    </a>
                    <?php endif; ?>
                    <a href="../transactions/transactions.php?action=borrow" class="block w-full bg-yellow-50 hover:bg-yellow-100 text-yellow-700 font-semibold py-3 px-4 rounded-lg transition">
                        <i class="fas fa-exchange-alt mr-2"></i>Process Borrowing
                    </a>
                    <a href="../transactions/transactions.php?action=return" class="block w-full bg-purple-50 hover:bg-purple-100 text-purple-700 font-semibold py-3 px-4 rounded-lg transition">
                        <i class="fas fa-undo mr-2"></i>Process Return
                    </a>
                <?php else: ?>
                    <a href="../books/books.php" class="block w-full bg-blue-50 hover:bg-blue-100 text-blue-700 font-semibold py-3 px-4 rounded-lg transition">
                        <i class="fas fa-search mr-2"></i>Browse Books
                    </a>
                    <a href="../books/my_borrowings.php" class="block w-full bg-green-50 hover:bg-green-100 text-green-700 font-semibold py-3 px-4 rounded-lg transition">
                        <i class="fas fa-book-open mr-2"></i>My Borrowed Books
                    </a>
                    <a href="../profile/profile.php" class="block w-full bg-purple-50 hover:bg-purple-100 text-purple-700 font-semibold py-3 px-4 rounded-lg transition">
                        <i class="fas fa-user mr-2"></i>My Profile
                    </a>

                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Recent Activity / My Books -->
    <div class="lg:col-span-2">
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">
                <i class="fas fa-clock text-blue-500 mr-2"></i>
                <?php echo ($role === 'admin' || $role === 'librarian') ? 'Recent Transactions' : 'My Current Books'; ?>
            </h2>
            
            <?php if ($role === 'admin' || $role === 'librarian'): ?>
                <?php if ($recent_result->num_rows > 0): ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead>
                                <tr class="border-b border-gray-200">
                                    <th class="text-left py-3 px-2 text-sm font-semibold text-gray-600">Member</th>
                                    <th class="text-left py-3 px-2 text-sm font-semibold text-gray-600">Book</th>
                                    <th class="text-left py-3 px-2 text-sm font-semibold text-gray-600">Due Date</th>
                                    <th class="text-left py-3 px-2 text-sm font-semibold text-gray-600">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $recent_result->fetch_assoc()): ?>
                                    <tr class="border-b border-gray-100 hover:bg-gray-50">
                                        <td class="py-3 px-2 text-sm text-gray-700"><?php echo $row['full_name']; ?></td>
                                        <td class="py-3 px-2 text-sm text-gray-700"><?php echo $row['title']; ?></td>
                                        <td class="py-3 px-2 text-sm text-gray-700">
                                            <?php 
                                            if ($row['due_date'] && $row['due_date'] !== '0000-00-00') {
                                                echo date('M d, Y', strtotime($row['due_date']));
                                            } else {
                                                echo '<span class="text-gray-400 italic">To be set</span>';
                                            }
                                            ?>
                                        </td>
                                        <td class="py-3 px-2">
                                        <?php 
                                        $status_class = $row['status'] === 'pending' ? 'bg-blue-100 text-blue-700' : 
                                                        ($row['status'] === 'borrowed' ? 'bg-yellow-100 text-yellow-700' : 
                                                        ($row['status'] === 'declined' ? 'bg-gray-100 text-gray-700' : 'bg-green-100 text-green-700'));
                                        ?>
                                            <span class="px-2 py-1 rounded-full text-xs font-semibold <?php echo $status_class; ?>">
                                                <?php echo ucfirst($row['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-gray-500 text-center py-8">No recent transactions</p>
                <?php endif; ?>
            <?php else: ?>
                <?php if ($my_books_result->num_rows > 0): ?>
                    <div class="space-y-4">
                        <?php while ($book = $my_books_result->fetch_assoc()): ?>
                            <?php 
                            $is_overdue = strtotime($book['due_date']) < time();
                            $days_until_due = ceil((strtotime($book['due_date']) - time()) / (60 * 60 * 24));
                            ?>
                            <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition">
                                <div class="flex justify-between items-start">
                                    <div class="flex-1">
                                        <h3 class="font-semibold text-gray-800"><?php echo $book['title']; ?></h3>
                                        <p class="text-sm text-gray-600 mt-1">by <?php echo $book['author']; ?></p>
                                        <p class="text-xs text-gray-500 mt-2">
                                            Borrowed: <?php echo date('M d, Y', strtotime($book['borrow_date'])); ?>
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        <?php if ($is_overdue): ?>
                                            <span class="px-3 py-1 bg-red-100 text-red-700 rounded-full text-xs font-semibold">
                                                <i class="fas fa-exclamation-triangle mr-1"></i>Overdue
                                            </span>
                                            <p class="text-xs text-red-600 mt-2">
                                                <?php echo abs($days_until_due); ?> days overdue
                                            </p>
                                        <?php else: ?>
                                            <span class="px-3 py-1 bg-green-100 text-green-700 rounded-full text-xs font-semibold">
                                                Active
                                            </span>
                                            <p class="text-xs text-gray-600 mt-2">
                                                Due in <?php echo $days_until_due; ?> days
                                            </p>
                                        <?php endif; ?>
                                        <p class="text-xs text-gray-500 mt-1">
                                            Due: <?php echo date('M d, Y', strtotime($book['due_date'])); ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8">
                        <i class="fas fa-book-open text-gray-300 text-5xl mb-4"></i>
                        <p class="text-gray-500">You haven't borrowed any books yet</p>
                        <a href="../books/books.php" class="inline-block mt-4 bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition">
                            Browse Books
                        </a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>