<?php
$page_title = "Cart Batch Details";
require_once '../includes/config.php';
require_once '../includes/header.php';

// Check if user is admin or librarian
if (!isLoggedIn() || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'librarian')) {
    redirect('../login.php');
}

// Get batch ID from URL
$batch_id = isset($_GET['batch_id']) ? trim($_GET['batch_id']) : '';

if (empty($batch_id)) {
    setMessage('error', 'Invalid batch ID');
    redirect('transactions.php');
}

// Get batch details with grouped book counts
$sql = "SELECT 
            b.book_id,
            b.title,
            b.author,
            b.isbn,
            b.cover_image,
            b.available_quantity,
            COUNT(*) as requested_quantity,
            bor.user_id,
            u.full_name,
            u.email,
            MIN(bor.borrow_date) as borrow_date,
            MIN(bor.borrowing_id) as first_borrowing_id,
            bor.cart_batch_id
        FROM borrowings bor
        JOIN books b ON bor.book_id = b.book_id
        JOIN users u ON bor.user_id = u.user_id
        WHERE bor.cart_batch_id = ? AND bor.status = 'pending'
        GROUP BY b.book_id, bor.user_id, bor.cart_batch_id
        ORDER BY b.title";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $batch_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    setMessage('error', 'Batch not found or already processed');
    redirect('transactions.php');
}

$batch_items = [];
$member_info = null;
$batch_date = null;

while ($row = $result->fetch_assoc()) {
    if (!$member_info) {
        $member_info = [
            'user_id' => $row['user_id'],
            'full_name' => $row['full_name'],
            'email' => $row['email']
        ];
        $batch_date = $row['borrow_date'];
    }
    $batch_items[] = $row;
}
$stmt->close();

// Get alert message
$message = getMessage();
?>

<!-- Alert Container -->
<div id="alertContainer" class="mb-6">
    <?php if ($message): ?>
        <div class="bg-<?php echo $message['type'] === 'success' ? 'green' : 'red'; ?>-50 border-l-4 border-<?php echo $message['type'] === 'success' ? 'green' : 'red'; ?>-500 text-<?php echo $message['type'] === 'success' ? 'green' : 'red'; ?>-700 p-4 rounded">
            <div class="flex items-center">
                <i class="fas fa-<?php echo $message['type'] === 'success' ? 'check' : 'exclamation'; ?>-circle mr-2"></i>
                <span><?php echo $message['message']; ?></span>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Page Header -->
<div class="mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">Cart Batch Request</h1>
            <p class="text-gray-600 mt-2">Batch ID: <?php echo htmlspecialchars($batch_id); ?></p>
        </div>
        <a href="transactions.php" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-300 transition font-semibold">
            <i class="fas fa-arrow-left mr-2"></i>Back
        </a>
    </div>
</div>

<!-- Member Information -->
<div class="bg-white rounded-lg shadow-md p-6 mb-6">
    <h2 class="text-xl font-bold text-gray-800 mb-4">
        <i class="fas fa-user mr-2"></i>Member Information
    </h2>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
            <p class="text-sm text-gray-600 font-semibold">Name</p>
            <p class="text-gray-800"><?php echo htmlspecialchars($member_info['full_name']); ?></p>
        </div>
        <div>
            <p class="text-sm text-gray-600 font-semibold">Email</p>
            <p class="text-gray-800"><?php echo htmlspecialchars($member_info['email']); ?></p>
        </div>
        <div>
            <p class="text-sm text-gray-600 font-semibold">Request Date</p>
            <p class="text-gray-800"><?php echo date('F j, Y g:i A', strtotime($batch_date)); ?></p>
        </div>
    </div>
</div>

<!-- Batch Items -->
<div class="bg-white rounded-lg shadow-md p-6 mb-6">
    <h2 class="text-xl font-bold text-gray-800 mb-4">
        <i class="fas fa-shopping-cart mr-2"></i>Requested Books (<?php echo count($batch_items); ?>)
    </h2>
    
    <div class="space-y-4">
        <?php foreach ($batch_items as $item): ?>
            <?php 
                $can_fulfill = $item['requested_quantity'] <= $item['available_quantity'];
                $book_image = $item['cover_image'] ? '../uploads/books/' . $item['cover_image'] : '../assets/images/default-book.png';
            ?>
            
            <div class="border rounded-lg p-4 <?php echo $can_fulfill ? 'border-gray-200' : 'border-red-300 bg-red-50'; ?>">
                <div class="flex gap-4">
                    <!-- Book Image -->
                    <img src="<?php echo $book_image; ?>" alt="<?php echo htmlspecialchars($item['title']); ?>" 
                         class="w-20 h-28 object-cover rounded">
                    
                    <!-- Book Details -->
                    <div class="flex-1">
                        <h3 class="font-bold text-gray-800 mb-1"><?php echo htmlspecialchars($item['title']); ?></h3>
                        <p class="text-sm text-gray-600 mb-2">by <?php echo htmlspecialchars($item['author']); ?></p>
                        <p class="text-sm text-gray-500 mb-2">ISBN: <?php echo htmlspecialchars($item['isbn']); ?></p>
                        
                        <div class="flex gap-4 text-sm">
                            <span class="font-semibold text-blue-600">
                                Requested: <?php echo $item['requested_quantity']; ?>
                            </span>
                            <span class="font-semibold <?php echo $can_fulfill ? 'text-green-600' : 'text-red-600'; ?>">
                                Available: <?php echo $item['available_quantity']; ?>
                            </span>
                        </div>
                        
                        <?php if (!$can_fulfill): ?>
                            <p class="text-sm text-red-600 font-semibold mt-2">
                                <i class="fas fa-exclamation-triangle mr-1"></i>
                                Insufficient stock! Only <?php echo $item['available_quantity']; ?> available.
                            </p>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Quantity Selector -->
                    <div class="flex flex-col items-end justify-center">
                        <label class="text-xs text-gray-600 font-semibold mb-2">Approve Quantity:</label>
                        <input type="number" 
                               class="approve-quantity w-24 px-3 py-2 border rounded text-center"
                               data-book-id="<?php echo $item['book_id']; ?>"
                               data-max="<?php echo min($item['requested_quantity'], $item['available_quantity']); ?>"
                               value="<?php echo min($item['requested_quantity'], $item['available_quantity']); ?>"
                               min="0"
                               max="<?php echo min($item['requested_quantity'], $item['available_quantity']); ?>">
                        <span class="text-xs text-gray-500 mt-1">Max: <?php echo min($item['requested_quantity'], $item['available_quantity']); ?></span>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Action Buttons -->
<div class="bg-white rounded-lg shadow-md p-6">
    <div class="flex justify-between items-center">
        <div>
            <p class="text-sm text-gray-600 mb-2">Set due date for all approved books:</p>
            <input type="date" id="batchDueDate" 
                   class="px-4 py-2 border rounded-lg"
                   value="<?php echo date('Y-m-d', strtotime('+14 days')); ?>"
                   min="<?php echo date('Y-m-d'); ?>">
        </div>
        
        <div class="flex gap-3">
            <button onclick="rejectAllBatch('<?php echo $batch_id; ?>')" 
                    class="bg-red-600 text-white px-6 py-3 rounded-lg hover:bg-red-700 transition font-semibold">
                <i class="fas fa-times mr-2"></i>Reject All
            </button>
            <button onclick="approvePartialBatch('<?php echo $batch_id; ?>')" 
                    class="bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 transition font-semibold">
                <i class="fas fa-check mr-2"></i>Approve Selected Quantities
            </button>
        </div>
    </div>
</div>

<script>
// Validate quantity inputs
document.querySelectorAll('.approve-quantity').forEach(input => {
    input.addEventListener('input', function() {
        const max = parseInt(this.dataset.max);
        let value = parseInt(this.value) || 0;
        
        if (value < 0) value = 0;
        if (value > max) value = max;
        
        this.value = value;
    });
});

// Approve partial batch
async function approvePartialBatch(batchId) {
    const dueDate = document.getElementById('batchDueDate').value;
    
    if (!dueDate) {
        showAlert('error', 'Please select a due date');
        return;
    }
    
    // Collect quantities for each book
    const quantities = {};
    let hasAnyApproval = false;
    
    document.querySelectorAll('.approve-quantity').forEach(input => {
        const bookId = input.dataset.bookId;
        const quantity = parseInt(input.value) || 0;
        quantities[bookId] = quantity;
        if (quantity > 0) hasAnyApproval = true;
    });
    
    if (!hasAnyApproval) {
        showAlert('error', 'Please approve at least one book');
        return;
    }
    
    try {
        const response = await fetch('approve_cart_batch.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                batch_id: batchId,
                due_date: dueDate,
                quantities: quantities
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showAlert('success', data.message);
            setTimeout(() => {
                window.location.href = 'transactions.php';
            }, 1500);
        } else {
            showAlert('error', data.message);
        }
    } catch (error) {
        console.error('Error:', error);
        showAlert('error', 'Failed to approve batch');
    }
}

// Reject all batch
async function rejectAllBatch(batchId) {
    if (!confirm('Are you sure you want to reject ALL books in this batch? This cannot be undone.')) {
        return;
    }
    
    try {
        const response = await fetch('reject_cart_batch.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `batch_id=${batchId}`
        });
        
        const data = await response.json();
        
        if (data.success) {
            showAlert('success', data.message);
            setTimeout(() => {
                window.location.href = 'transactions.php';
            }, 1500);
        } else {
            showAlert('error', data.message);
        }
    } catch (error) {
        console.error('Error:', error);
        showAlert('error', 'Failed to reject batch');
    }
}

// Show alert
function showAlert(type, message) {
    const alertClass = type === 'success' ? 'green' : 'red';
    const icon = type === 'success' ? 'check' : 'exclamation';
    
    document.getElementById('alertContainer').innerHTML = `
        <div class="bg-${alertClass}-50 border-l-4 border-${alertClass}-500 text-${alertClass}-700 p-4 rounded">
            <div class="flex items-center">
                <i class="fas fa-${icon}-circle mr-2"></i>
                <span>${message}</span>
            </div>
        </div>
    `;
    
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
</script>

<?php require_once '../includes/footer.php'; ?>