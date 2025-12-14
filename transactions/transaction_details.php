<?php
$page_title = "Transaction Details";
require_once '../includes/config.php';
require_once '../includes/header.php';

// Check if user is logged in and has permission
if (!isLoggedIn() || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'librarian')) {
    redirect('../login.php');
}

// Get transaction ID
$transaction_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($transaction_id <= 0) {
    setMessage('error', 'Invalid transaction ID');
    redirect('transactions.php');
}

// Get transaction details
$sql = "SELECT bor.*, 
        u.user_id, u.full_name, u.email, u.phone,
        b.book_id, b.title, b.author, b.isbn, b.publisher, b.publish_year, b.cover_image,
        c.category_name
        FROM borrowings bor
        LEFT JOIN users u ON bor.user_id = u.user_id
        LEFT JOIN books b ON bor.book_id = b.book_id
        LEFT JOIN categories c ON b.category_id = c.category_id
        WHERE bor.borrowing_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $transaction_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    setMessage('error', 'Transaction not found');
    redirect('transactions.php');
}

$transaction = $result->fetch_assoc();
$stmt->close();

// Calculate days borrowed or overdue
$borrow_date = new DateTime($transaction['borrow_date']);
$due_date = ($transaction['due_date'] && $transaction['due_date'] !== '0000-00-00') ? new DateTime($transaction['due_date']) : null;
$current_date = new DateTime();
$return_date = $transaction['return_date'] ? new DateTime($transaction['return_date']) : null;

if ($return_date && $due_date) {
    $days_borrowed = $borrow_date->diff($return_date)->days;
    $days_late = $return_date > $due_date ? $due_date->diff($return_date)->days : 0;
} elseif ($due_date) {
    $days_borrowed = $borrow_date->diff($current_date)->days;
    $days_late = $current_date > $due_date ? $due_date->diff($current_date)->days : 0;
} else {
    // Pending - no due date set yet
    $days_borrowed = 0;
    $days_late = 0;
}

// Status styling
$status_classes = [
    'pending' => 'bg-blue-100 text-blue-800',
    'borrowed' => 'bg-yellow-100 text-yellow-800',
    'overdue' => 'bg-red-100 text-red-800',
    'returned' => 'bg-green-100 text-green-800'
];
$status_class = $status_classes[$transaction['status']] ?? 'bg-gray-100 text-gray-800';

// Get alert message if any
$message = getMessage();
?>

<!-- Alert Messages -->
<div id="alertContainer" class="mb-6">
    <?php if ($message): ?>
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
    <?php endif; ?>
</div>

<!-- Page Header -->
<div class="mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">Transaction Details</h1>
            <p class="text-gray-600 mt-2">Transaction ID: #<?php echo $transaction_id; ?></p>
        </div>
        <a href="transactions.php" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-300 transition font-semibold">
            <i class="fas fa-arrow-left mr-2"></i>Back to Transactions
        </a>
    </div>
</div>

<!-- Transaction Status Card -->
<div class="bg-white rounded-lg shadow-md p-6 mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-xl font-bold text-gray-800 mb-2">Transaction Status</h2>
            <span class="px-4 py-2 rounded-full text-sm font-semibold <?php echo $status_class; ?>">
                <?php echo ucfirst($transaction['status']); ?>
            </span>
        </div>
        <?php if ($transaction['status'] === 'pending'): ?>
            <div class="flex gap-2">
                <button onclick="approveRequest(<?php echo $transaction_id; ?>)" 
                        class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700 transition font-semibold">
                    <i class="fas fa-check mr-2"></i>Approve
                </button>
                <button onclick="rejectRequest(<?php echo $transaction_id; ?>)" 
                        class="bg-red-600 text-white px-6 py-2 rounded-lg hover:bg-red-700 transition font-semibold">
                    <i class="fas fa-times mr-2"></i>Reject
                </button>
            </div>
        <?php elseif ($transaction['status'] === 'borrowed' || $transaction['status'] === 'overdue'): ?>
            <button onclick="markAsReturned(<?php echo $transaction_id; ?>)" 
                    class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition font-semibold">
                <i class="fas fa-undo mr-2"></i>Mark as Returned
            </button>
        <?php endif; ?>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <!-- Left Column: Book & User Details -->
    <div class="lg:col-span-2 space-y-8">
        <!-- Book Information -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">
                <i class="fas fa-book mr-2"></i>Book Information
            </h2>
            <div class="flex gap-6">
                <?php if ($transaction['cover_image'] && $transaction['cover_image'] !== '' && $transaction['cover_image'] !== 'null'): ?>
                    <img src="../uploads/books/<?php echo htmlspecialchars($transaction['cover_image']); ?>" 
                         alt="<?php echo htmlspecialchars($transaction['title']); ?>"
                         class="w-32 h-48 object-cover rounded-lg shadow"
                         onerror="this.src='../assets/images/default-book.png'">
                <?php else: ?>
                    <div class="w-32 h-48 bg-gray-200 rounded-lg flex items-center justify-center">
                        <i class="fas fa-book text-4xl text-gray-400"></i>
                    </div>
                <?php endif; ?>
                <div class="flex-1">
                    <h3 class="text-lg font-bold text-gray-800 mb-2"><?php echo htmlspecialchars($transaction['title']); ?></h3>
                    <div class="space-y-2 text-sm text-gray-600">
                        <p><strong>Author:</strong> <?php echo htmlspecialchars($transaction['author']); ?></p>
                        <p><strong>ISBN:</strong> <?php echo htmlspecialchars($transaction['isbn']); ?></p>
                        <p><strong>Publisher:</strong> <?php echo htmlspecialchars($transaction['publisher']); ?></p>
                        <p><strong>Publication Year:</strong> <?php echo htmlspecialchars($transaction['publish_year']); ?></p>
                        <p><strong>Category:</strong> <?php echo htmlspecialchars($transaction['category_name']); ?></p>
                    </div>
                    <div class="mt-4">
                        <a href="../books/book_details.php?id=<?php echo $transaction['book_id']; ?>" 
                           class="text-blue-600 hover:text-blue-800 text-sm font-semibold">
                            <i class="fas fa-external-link-alt mr-1"></i>View Book Details
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- User Information -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">
                <i class="fas fa-user mr-2"></i>User Information
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm text-gray-600">
                <div>
                    <p class="mb-2"><strong>Name:</strong> <?php echo htmlspecialchars($transaction['full_name']); ?></p>
                    <p class="mb-2"><strong>Email:</strong> <?php echo htmlspecialchars($transaction['email']); ?></p>
                </div>
                <div>
                    <p class="mb-2"><strong>Phone:</strong> <?php echo htmlspecialchars($transaction['phone'] ?? 'N/A'); ?></p>
                    <p class="mb-2"><strong>User ID:</strong> #<?php echo $transaction['user_id']; ?></p>
                </div>
            </div>
            <div class="mt-4">
                <a href="../profile/profile.php?id=<?php echo $transaction['user_id']; ?>" 
                   class="text-blue-600 hover:text-blue-800 text-sm font-semibold">
                    <i class="fas fa-external-link-alt mr-1"></i>View User Profile
                </a>
            </div>
        </div>
    </div>

    <!-- Right Column: Transaction Timeline -->
    <div class="lg:col-span-1">
        <!-- Transaction Timeline -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">
                <i class="fas fa-calendar-alt mr-2"></i>Timeline
            </h2>
            <div class="space-y-4">
                <div class="flex items-start">
                    <div class="flex-shrink-0 w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-calendar-plus text-blue-600"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-semibold text-gray-800">Borrow Date</p>
                        <p class="text-sm text-gray-600"><?php echo date('F j, Y', strtotime($transaction['borrow_date'])); ?></p>
                        <p class="text-xs text-gray-500"><?php echo date('g:i A', strtotime($transaction['borrow_date'])); ?></p>
                    </div>
                </div>

<div class="flex items-start">
    <div class="flex-shrink-0 w-10 h-10 bg-yellow-100 rounded-full flex items-center justify-center">
        <i class="fas fa-calendar-check text-yellow-600"></i>
    </div>
    <div class="ml-4">
        <p class="text-sm font-semibold text-gray-800">Due Date</p>
        <p class="text-sm text-gray-600">
            <?php echo $due_date ? date('F j, Y', strtotime($transaction['due_date'])) : 'To be set upon approval'; ?>
        </p>
        <?php if ($days_late > 0 && !$return_date && $due_date): ?>
            <p class="text-xs text-red-600 font-semibold"><?php echo $days_late; ?> day(s) overdue</p>
        <?php endif; ?>
    </div>
</div>

                <?php if ($return_date): ?>
                    <div class="flex items-start">
                        <div class="flex-shrink-0 w-10 h-10 bg-green-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-calendar-times text-green-600"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-semibold text-gray-800">Return Date</p>
                            <p class="text-sm text-gray-600"><?php echo date('F j, Y', strtotime($transaction['return_date'])); ?></p>
                            <p class="text-xs text-gray-500"><?php echo date('g:i A', strtotime($transaction['return_date'])); ?></p>
                            <?php if ($days_late > 0): ?>
                                <p class="text-xs text-red-600 font-semibold">Returned <?php echo $days_late; ?> day(s) late</p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Statistics Card -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">
                <i class="fas fa-chart-bar mr-2"></i>Statistics
            </h2>
            <div class="space-y-3">
                <div class="flex justify-between items-center py-2 border-b">
                    <span class="text-sm text-gray-600">Days Borrowed</span>
                    <span class="text-sm font-semibold text-gray-800"><?php echo $days_borrowed; ?> days</span>
                </div>
                <?php if ($days_late > 0): ?>
                    <div class="flex justify-between items-center py-2 border-b">
                        <span class="text-sm text-gray-600">Days Overdue</span>
                        <span class="text-sm font-semibold text-red-600"><?php echo $days_late; ?> days</span>
                    </div>
                <?php endif; ?>
                <div class="flex justify-between items-center py-2">
                    <span class="text-sm text-gray-600">Transaction ID</span>
                    <span class="text-sm font-semibold text-gray-800">#<?php echo $transaction_id; ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Confirmation Modal -->
<div id="confirmModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4 transform transition-all">
        <div class="p-6">
            <div class="flex items-center mb-4">
                <div id="modalIcon" class="w-12 h-12 rounded-full flex items-center justify-center mr-4">
                    <i class="fas fa-question-circle text-2xl"></i>
                </div>
                <h3 id="modalTitle" class="text-xl font-bold text-gray-800"></h3>
            </div>
            <p id="modalMessage" class="text-gray-600 mb-6"></p>
            <div class="flex gap-3 justify-end">
                <button id="modalCancelBtn" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition font-semibold">
                    Cancel
                </button>
                <button id="modalConfirmBtn" class="px-4 py-2 rounded-lg hover:opacity-90 transition font-semibold">
                    Confirm
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Modal functionality
function showConfirmModal(title, message, confirmText, confirmClass, onConfirm) {
    const modal = document.getElementById('confirmModal');
    const modalTitle = document.getElementById('modalTitle');
    const modalMessage = document.getElementById('modalMessage');
    const modalIcon = document.getElementById('modalIcon');
    const confirmBtn = document.getElementById('modalConfirmBtn');
    const cancelBtn = document.getElementById('modalCancelBtn');
    
    modalTitle.textContent = title;
    modalMessage.textContent = message;
    confirmBtn.textContent = confirmText;
    confirmBtn.className = `px-4 py-2 ${confirmClass} text-white rounded-lg hover:opacity-90 transition font-semibold`;
    
    if (confirmClass.includes('green')) {
        modalIcon.className = 'w-12 h-12 rounded-full bg-green-100 flex items-center justify-center mr-4';
        modalIcon.querySelector('i').className = 'fas fa-check-circle text-2xl text-green-600';
    } else if (confirmClass.includes('blue')) {
        modalIcon.className = 'w-12 h-12 rounded-full bg-blue-100 flex items-center justify-center mr-4';
        modalIcon.querySelector('i').className = 'fas fa-undo text-2xl text-blue-600';
    } else {
        modalIcon.className = 'w-12 h-12 rounded-full bg-red-100 flex items-center justify-center mr-4';
        modalIcon.querySelector('i').className = 'fas fa-exclamation-circle text-2xl text-red-600';
    }
    
    modal.classList.remove('hidden');
    
    const handleConfirm = () => {
        onConfirm();
        closeModal();
        confirmBtn.removeEventListener('click', handleConfirm);
        cancelBtn.removeEventListener('click', closeModal);
    };
    
    const closeModal = () => {
        modal.classList.add('hidden');
        confirmBtn.removeEventListener('click', handleConfirm);
        cancelBtn.removeEventListener('click', closeModal);
    };
    
    confirmBtn.addEventListener('click', handleConfirm);
    cancelBtn.addEventListener('click', closeModal);
    
    modal.addEventListener('click', (e) => {
        if (e.target === modal) closeModal();
    });
}

// Approve request
function approveRequest(borrowingId) {
    // Get borrow date from the PHP variable
    const borrowDate = '<?php echo $transaction["borrow_date"]; ?>';
    
    const borrow = new Date(borrowDate);
    const today = new Date();
    
    const minDate = borrow > today ? borrow : today;
    const minDateStr = minDate.toISOString().split('T')[0];
    
    const defaultDue = new Date(borrow);
    defaultDue.setDate(defaultDue.getDate() + 14);
    const defaultDueDate = defaultDue.toISOString().split('T')[0];
    
    const modal = document.getElementById('confirmModal');
    const modalTitle = document.getElementById('modalTitle');
    const modalMessage = document.getElementById('modalMessage');
    const modalIcon = document.getElementById('modalIcon');
    const confirmBtn = document.getElementById('modalConfirmBtn');
    const cancelBtn = document.getElementById('modalCancelBtn');
    
    modalTitle.textContent = 'Set Due Date';
    modalMessage.innerHTML = `
        <div class="space-y-4">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Borrow Date (Set by Member)</label>
                <input type="date" value="${borrowDate}" disabled
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-100 cursor-not-allowed">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Due Date (Return Date)</label>
                <input type="date" id="dueDate" value="${defaultDueDate}" min="${minDateStr}" 
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <p class="text-sm text-gray-500">Default: 14 days from borrow date</p>
        </div>
    `;
    
    confirmBtn.textContent = 'Approve & Set Due Date';
    confirmBtn.className = 'px-4 py-2 bg-green-600 text-white rounded-lg hover:opacity-90 transition font-semibold';
    
    modalIcon.className = 'w-12 h-12 rounded-full bg-green-100 flex items-center justify-center mr-4';
    modalIcon.querySelector('i').className = 'fas fa-calendar-check text-2xl text-green-600';
    
    modal.classList.remove('hidden');
    
    const handleConfirm = async () => {
        const dueDate = document.getElementById('dueDate').value;
        
        if (!dueDate) {
            showAlert('error', 'Please select a due date');
            return;
        }
        
        if (new Date(dueDate) <= new Date(borrowDate)) {
            showAlert('error', 'Due date must be after borrow date');
            return;
        }
        
        try {
            const response = await fetch('approve_borrow.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `borrowing_id=${borrowingId}&due_date=${dueDate}`
            });
            const data = await response.json();
            
            if (data.success) {
                window.location.reload();
            } else {
                showAlert('error', data.message);
            }
        } catch (error) {
            showAlert('error', 'Failed to approve request');
        }
        
        closeModal();
    };
    
    const closeModal = () => {
        modal.classList.add('hidden');
        confirmBtn.removeEventListener('click', handleConfirm);
        cancelBtn.removeEventListener('click', closeModal);
    };
    
    confirmBtn.addEventListener('click', handleConfirm);
    cancelBtn.addEventListener('click', closeModal);
    
    modal.addEventListener('click', (e) => {
        if (e.target === modal) closeModal();
    });
}

// Reject request
function rejectRequest(borrowingId) {
    showConfirmModal(
        'Reject Request',
        'Are you sure you want to reject this borrow request? This action cannot be undone.',
        'Reject',
        'bg-red-600',
        async () => {
            try {
                const response = await fetch('reject_borrow.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `borrowing_id=${borrowingId}`
                });
                const data = await response.json();
                
                if (data.success) {
                    window.location.href = 'transactions.php';
                } else {
                    showAlert('error', data.message);
                }
            } catch (error) {
                showAlert('error', 'Failed to reject request');
            }
        }
    );
}

// Mark as returned
function markAsReturned(borrowingId) {
    showConfirmModal(
        'Mark as Returned',
        'Confirm that this book has been returned?',
        'Mark Returned',
        'bg-blue-600',
        async () => {
            try {
                const response = await fetch('return_book.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `borrowing_id=${borrowingId}`
                });
                const data = await response.json();
                
                if (data.success) {
                    window.location.reload();
                } else {
                    showAlert('error', data.message);
                }
            } catch (error) {
                showAlert('error', 'Failed to mark as returned');
            }
        }
    );
}

// Show alert
function showAlert(type, message) {
    const alertClass = type === 'success' ? 'bg-green-50 border-green-500 text-green-700' : 'bg-red-50 border-red-500 text-red-700';
    const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
    
    document.getElementById('alertContainer').innerHTML = `
        <div class="${alertClass} border-l-4 p-4 rounded" role="alert">
            <div class="flex items-center">
                <i class="fas ${icon} mr-2"></i>
                <span>${message}</span>
            </div>
        </div>
    `;
    
    setTimeout(() => {
        document.getElementById('alertContainer').innerHTML = '';
    }, 5000);
}
</script>

<?php require_once '../includes/footer.php'; ?>