<?php
$page_title = "Book Details";
require_once '../includes/config.php';
require_once '../includes/header.php';

// Get book ID
$book_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($book_id <= 0) {
    setMessage('error', 'Invalid book ID');
    redirect('books.php');
}

// Get book information
$sql = "SELECT b.*, c.category_name 
        FROM books b 
        LEFT JOIN categories c ON b.category_id = c.category_id 
        WHERE b.book_id = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $book_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    setMessage('error', 'Book not found');
    redirect('books.php');
}

$book = $result->fetch_assoc();
$book_image = $book['cover_image'] ? '../uploads/books/' . $book['cover_image'] : '../assets/images/default-book.png';
$is_available = $book['available_quantity'] > 0;

// Get borrowing history for this book
$history_sql = "SELECT b.*, u.full_name, u.username 
                FROM borrowings b 
                JOIN users u ON b.user_id = u.user_id 
                WHERE b.book_id = ? 
                ORDER BY b.borrow_date DESC 
                LIMIT 10";
$history_stmt = $conn->prepare($history_sql);
$history_stmt->bind_param("i", $book_id);
$history_stmt->execute();
$history_result = $history_stmt->get_result();

// Check if current user has a transaction (borrowed or pending) for this book
$has_transaction = false;
$borrowing_status = null;
$borrowing_id = null;
$pending_count = 0; // Initialize pending count
if ($role === 'member') {
    // Check for any borrowed or pending status
    $check_sql = "SELECT borrowing_id, status FROM borrowings WHERE user_id = ? AND book_id = ? AND status IN ('borrowed', 'pending') LIMIT 1";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ii", $user_id, $book_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    if ($row = $check_result->fetch_assoc()) {
        $has_transaction = true;
        $borrowing_status = $row['status'];
        $borrowing_id = $row['borrowing_id'];
    }
    $check_stmt->close();
    
    // Get total pending count for this book (for quantity display in cancel modal)
    $pending_sql = "SELECT COUNT(*) as pending_count 
                    FROM borrowings 
                    WHERE user_id = ? AND book_id = ? AND status = 'pending'";
    $pending_stmt = $conn->prepare($pending_sql);
    $pending_stmt->bind_param("ii", $user_id, $book_id);
    $pending_stmt->execute();
    $pending_result = $pending_stmt->get_result();
    $pending_count = $pending_result->fetch_assoc()['pending_count'];
    $pending_stmt->close();
}

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

<!-- Back Button -->
<div class="mb-6">
    <a href="books.php" class="inline-flex items-center text-blue-600 hover:text-blue-800 font-semibold">
        <i class="fas fa-arrow-left mr-2"></i>Back to Books
    </a>
</div>

<!-- Book Details -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Left Column - Book Cover and Actions -->
    <div class="lg:col-span-1">
        <div class="bg-white rounded-lg shadow-md p-6 sticky top-24">
            <!-- Book Cover -->
            <div class="relative mb-6">
                <img src="<?php echo $book_image; ?>" alt="<?php echo $book['title']; ?>" 
                     class="w-full rounded-lg shadow-lg">
                <?php if (!$is_available): ?>
                    <div class="absolute top-4 right-4 bg-red-500 text-white px-4 py-2 rounded-full text-sm font-semibold">
                        Not Available
                    </div>
                <?php else: ?>
                    <div class="absolute top-4 right-4 bg-green-500 text-white px-4 py-2 rounded-full text-sm font-semibold">
                        Available
                    </div>
                <?php endif; ?>
            </div>

            <!-- Availability Info -->
            <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                <div class="flex justify-between items-center mb-2">
                    <span class="text-gray-600 font-semibold">Total Copies:</span>
                    <span class="text-gray-800 font-bold"><?php echo $book['quantity']; ?></span>
                </div>
                <div class="flex justify-between items-center mb-2">
                    <span class="text-gray-600 font-semibold">Available:</span>
                    <span class="text-green-600 font-bold"><?php echo $book['available_quantity']; ?></span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-gray-600 font-semibold">Borrowed:</span>
                    <span class="text-red-600 font-bold"><?php echo $book['quantity'] - $book['available_quantity']; ?></span>
                </div>
            </div>

<!-- Action Buttons -->
<div id="actionButtonsContainer" class="space-y-3">
    <?php if ($role === 'member'): ?>
        <?php if ($has_transaction): ?>
            <?php if ($borrowing_status === 'pending'): ?>
                <button onclick="openCancelModal(<?php echo $book_id; ?>, '<?php echo addslashes(htmlspecialchars($book['title'])); ?>', <?php echo $pending_count; ?>)" 
                        class="w-full bg-red-600 text-white py-3 rounded-lg hover:bg-red-700 transition font-semibold">
                    <i class="fas fa-times mr-2"></i>Cancel Request
                </button>
            <?php else: ?>
                <button disabled class="w-full bg-gray-400 text-white py-3 rounded-lg cursor-not-allowed font-semibold">
                    <i class="fas fa-book-reader mr-2"></i>Already Borrowed
                </button>
            <?php endif; ?>
        <?php else: ?>
            <?php if ($is_available): ?>
                <!-- NEW: Add to Cart Button -->
                <button onclick="openAddToCartModal()" 
                        class="w-full bg-green-600 text-white py-3 rounded-lg hover:bg-green-700 transition font-semibold">
                    <i class="fas fa-cart-plus mr-2"></i>Add to Cart
                </button>
                <!-- Keep Borrow Now as Quick Option -->
                <button onclick="openBorrowModal()" 
                        class="w-full bg-blue-600 text-white py-3 rounded-lg hover:bg-blue-700 transition font-semibold">
                    <i class="fas fa-book-reader mr-2"></i>Borrow Now (Single)
                </button>
            <?php else: ?>
                <button onclick="openReserveModal()" 
                        class="w-full bg-yellow-600 text-white py-3 rounded-lg hover:bg-yellow-700 transition font-semibold">
                    <i class="fas fa-bookmark mr-2"></i>Reserve Book
                </button>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>

                <?php if ($role === 'admin' || $role === 'librarian'): ?>
                    <a href="edit_book.php?id=<?php echo $book_id; ?>" 
                       class="block w-full bg-green-600 text-white text-center py-3 rounded-lg hover:bg-green-700 transition font-semibold">
                        <i class="fas fa-edit mr-2"></i>Edit Book
                    </a>
                    <button onclick="openDeleteModal()" 
                            class="w-full bg-red-600 text-white py-3 rounded-lg hover:bg-red-700 transition font-semibold">
                        <i class="fas fa-trash mr-2"></i>Delete Book
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right Column - Book Information -->
    <div class="lg:col-span-2 space-y-6">
        <!-- Book Title and Info -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h1 class="text-3xl font-bold text-gray-800 mb-2"><?php echo $book['title']; ?></h1>
            <p class="text-xl text-gray-600 mb-4">by <?php echo $book['author']; ?></p>
            
            <?php if ($book['category_name']): ?>
                <span class="inline-block bg-blue-100 text-blue-700 px-4 py-1 rounded-full text-sm font-semibold mb-4">
                    <i class="fas fa-tag mr-1"></i><?php echo $book['category_name']; ?>
                </span>
            <?php endif; ?>

            <!-- Book Details Grid -->
            <div class="grid grid-cols-2 gap-4 mt-6">
                <?php if ($book['isbn']): ?>
                    <div class="p-4 bg-gray-50 rounded-lg">
                        <p class="text-sm text-gray-600 font-semibold mb-1">ISBN</p>
                        <p class="text-gray-800"><?php echo $book['isbn']; ?></p>
                    </div>
                <?php endif; ?>

                <?php if ($book['publisher']): ?>
                    <div class="p-4 bg-gray-50 rounded-lg">
                        <p class="text-sm text-gray-600 font-semibold mb-1">Publisher</p>
                        <p class="text-gray-800"><?php echo $book['publisher']; ?></p>
                    </div>
                <?php endif; ?>

                <?php if ($book['publish_year']): ?>
                    <div class="p-4 bg-gray-50 rounded-lg">
                        <p class="text-sm text-gray-600 font-semibold mb-1">Publish Year</p>
                        <p class="text-gray-800"><?php echo $book['publish_year']; ?></p>
                    </div>
                <?php endif; ?>

                <?php if ($book['shelf_location']): ?>
                    <div class="p-4 bg-gray-50 rounded-lg">
                        <p class="text-sm text-gray-600 font-semibold mb-1">Shelf Location</p>
                        <p class="text-gray-800"><?php echo $book['shelf_location']; ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Description -->
        <?php if ($book['description']): ?>
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4">
                    <i class="fas fa-align-left text-purple-600 mr-2"></i>Description
                </h2>
                <p class="text-gray-700 leading-relaxed"><?php echo nl2br($book['description']); ?></p>
            </div>
        <?php endif; ?>

        <!-- Borrowing History -->
        <?php if ($role === 'admin' || $role === 'librarian'): ?>
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4">
                    <i class="fas fa-history text-blue-600 mr-2"></i>Borrowing History
                </h2>
                
                <?php if ($history_result->num_rows > 0): ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead>
                                <tr class="border-b border-gray-200">
                                    <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Member</th>
                                    <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Borrow Date</th>
                                    <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Due Date</th>
                                    <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Return Date</th>
                                    <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($history = $history_result->fetch_assoc()): ?>
                                    <tr class="border-b border-gray-100 hover:bg-gray-50">
                                        <td class="py-3 px-4 text-sm text-gray-700"><?php echo $history['full_name']; ?></td>
                                    <td class="py-3 px-4 text-sm text-gray-700"><?php echo date('M d, Y', strtotime($history['borrow_date'])); ?></td>
                                    <td class="py-3 px-4 text-sm text-gray-700">
                                        <?php 
                                        if ($history['status'] === 'pending') {
                                            echo '<span class="text-blue-600 italic">To be set upon approval</span>';
                                        } elseif ($history['due_date']) {
                                            echo date('M d, Y', strtotime($history['due_date']));
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </td>
                                        <td class="py-3 px-4 text-sm text-gray-700">
                                            <?php echo $history['return_date'] ? date('M d, Y', strtotime($history['return_date'])) : '-'; ?>
                                        </td>
                                        <td class="py-3 px-4">
                                            <?php 
                                                $status_colors = [
                                                    'pending' => 'bg-blue-100 text-blue-700',
                                                    'borrowed' => 'bg-yellow-100 text-yellow-700',
                                                    'returned' => 'bg-green-100 text-green-700',
                                                    'overdue' => 'bg-red-100 text-red-700',
                                                    'declined' => 'bg-red-100 text-red-700'
                                                ];
                                            $status_class = $status_colors[$history['status']] ?? 'bg-gray-100 text-gray-700';
                                            ?>
                                            <span class="px-3 py-1 rounded-full text-xs font-semibold <?php echo $status_class; ?>">
                                                <?php echo ucfirst($history['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-gray-500 text-center py-8">No borrowing history yet</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4 transform transition-all">
        <div class="p-6">
            <div class="flex items-center justify-center w-12 h-12 mx-auto bg-red-100 rounded-full mb-4">
                <i class="fas fa-exclamation-triangle text-red-600 text-2xl"></i>
            </div>
            <h3 class="text-xl font-bold text-gray-900 text-center mb-2">Delete Book</h3>
            <p class="text-gray-600 text-center mb-6">
                Are you sure you want to delete "<strong><?php echo htmlspecialchars($book['title']); ?></strong>"? 
                This action cannot be undone and will also delete all related borrowing records, reviews, and reservations.
            </p>
            <div class="flex gap-3">
                <button onclick="closeDeleteModal()" 
                        class="flex-1 bg-gray-200 text-gray-700 px-4 py-3 rounded-lg hover:bg-gray-300 transition font-semibold">
                    Cancel
                </button>
                <form action="delete_book.php" method="POST" class="flex-1">
                    <input type="hidden" name="book_id" value="<?php echo $book['book_id']; ?>">
                    <button type="submit" 
                            class="w-full bg-red-600 text-white px-4 py-3 rounded-lg hover:bg-red-700 transition font-semibold">
                        Delete
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Borrow Book Modal -->
<div id="borrowModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4 transform transition-all">
        <div class="p-6">
            <div class="flex items-center justify-center w-12 h-12 mx-auto bg-blue-100 rounded-full mb-4">
                <i class="fas fa-book-reader text-blue-600 text-2xl"></i>
            </div>
            <h3 class="text-xl font-bold text-gray-900 text-center mb-2">Borrow Book</h3>
            <p class="text-gray-600 text-center mb-4">
                Do you want to borrow "<strong><?php echo htmlspecialchars($book['title']); ?></strong>"?
            </p>
            <div class="bg-blue-50 rounded-lg p-4 mb-6">
                <div class="flex items-start">
                    <i class="fas fa-info-circle text-blue-600 mt-1 mr-3"></i>
                    <div class="text-sm text-gray-700">
                        <p class="font-semibold mb-2">Borrowing Terms:</p>
                        <ul class="space-y-1 text-gray-600">
                            <li>• Standard loan period: 14 days</li>
                            <li>• Late return may incur fines</li>
                            <li>• Take care of the book</li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="flex gap-3">
                <button onclick="closeBorrowModal()" 
                        class="flex-1 bg-gray-200 text-gray-700 px-4 py-3 rounded-lg hover:bg-gray-300 transition font-semibold">
                    Cancel
                </button>
                <a href="borrow_book.php?id=<?php echo $book_id; ?>" 
                   class="flex-1 bg-blue-600 text-white text-center px-4 py-3 rounded-lg hover:bg-blue-700 transition font-semibold">
                    Confirm Borrow
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Reserve Book Modal -->
<div id="reserveModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4 transform transition-all">
        <div class="p-6">
            <div class="flex items-center justify-center w-12 h-12 mx-auto bg-yellow-100 rounded-full mb-4">
                <i class="fas fa-bookmark text-yellow-600 text-2xl"></i>
            </div>
            <h3 class="text-xl font-bold text-gray-900 text-center mb-2">Reserve Book</h3>
            <p class="text-gray-600 text-center mb-4">
                Do you want to reserve "<strong><?php echo htmlspecialchars($book['title']); ?></strong>"?
            </p>
            <div class="bg-yellow-50 rounded-lg p-4 mb-6">
                <div class="flex items-start">
                    <i class="fas fa-info-circle text-yellow-600 mt-1 mr-3"></i>
                    <div class="text-sm text-gray-700">
                        <p class="font-semibold mb-2">Reservation Info:</p>
                        <ul class="space-y-1 text-gray-600">
                            <li>• You'll be notified when available</li>
                            <li>• Reservation valid for 3 days</li>
                            <li>• First come, first served</li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="flex gap-3">
                <button onclick="closeReserveModal()" 
                        class="flex-1 bg-gray-200 text-gray-700 px-4 py-3 rounded-lg hover:bg-gray-300 transition font-semibold">
                    Cancel
                </button>
                <a href="reserve_book.php?id=<?php echo $book_id; ?>" 
                   class="flex-1 bg-yellow-600 text-white text-center px-4 py-3 rounded-lg hover:bg-yellow-700 transition font-semibold">
                    Confirm Reserve
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Cancel Request Modal -->
<div id="cancelModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4 transform transition-all">
        <div class="p-6">
            <div class="flex items-center justify-center w-12 h-12 mx-auto bg-red-100 rounded-full mb-4">
                <i class="fas fa-times text-red-600 text-2xl"></i>
            </div>
            <h3 class="text-xl font-bold text-gray-900 text-center mb-2">Cancel Borrow Request</h3>
            
            <!-- ADD THIS DIV WITH ID -->
            <div id="cancelModalBody">
                <p class="text-gray-600 text-center mb-4">
                    Do you want to cancel your borrow request for "<strong id="cancelBookTitle"></strong>"?
                </p>
            </div>
            
            <div class="bg-red-50 rounded-lg p-4 mb-6">
                <div class="flex items-start">
                    <i class="fas fa-info-circle text-red-600 mt-1 mr-3"></i>
                    <div class="text-sm text-gray-700">
                        <p class="font-semibold mb-2">Cancellation Info:</p>
                        <ul class="space-y-1 text-gray-600">
                            <li>• This will remove your pending request</li>
                            <li>• You can request again if needed</li>
                            <li>• Action cannot be undone</li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="flex gap-3">
                <button onclick="closeCancelModal()" 
                        class="flex-1 bg-gray-200 text-gray-700 px-4 py-3 rounded-lg hover:bg-gray-300 transition font-semibold">
                    No, Keep Request
                </button>
                <button id="confirmCancelButton" 
                        class="flex-1 bg-red-600 text-white px-4 py-3 rounded-lg hover:bg-red-700 transition font-semibold">
                    <span id="confirmCancelText">Yes, Cancel</span>
                    <span id="loadingSpinner" class="hidden animate-spin h-5 w-5 border-2 border-white border-t-transparent rounded-full inline-block"></span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Add to Cart Modal -->
<div id="addToCartModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4 transform transition-all">
        <div class="p-6">
            <div class="flex items-center justify-center w-12 h-12 mx-auto bg-green-100 rounded-full mb-4">
                <i class="fas fa-cart-plus text-green-600 text-2xl"></i>
            </div>
            <h3 class="text-xl font-bold text-gray-900 text-center mb-2">Add to Cart</h3>
            <p class="text-gray-600 text-center mb-4">
                How many copies of "<strong><?php echo htmlspecialchars($book['title']); ?></strong>" do you want to borrow?
            </p>
            
            <div class="mb-6">
                <label class="block text-sm font-semibold text-gray-700 mb-2 text-center">Quantity</label>
                <div class="flex items-center justify-center space-x-4">
                    <button onclick="decrementQuantity()" 
                            class="bg-gray-200 text-gray-700 w-12 h-12 rounded-lg hover:bg-gray-300 transition font-bold text-xl">
                        <i class="fas fa-minus"></i>
                    </button>
                    <input type="number" id="cartQuantity" value="1" min="1" max="<?php echo $book['available_quantity']; ?>" 
                           class="w-24 text-center text-3xl font-bold border-2 border-gray-300 rounded-lg py-3 focus:border-green-500 focus:outline-none">
                    <button onclick="incrementQuantity()" 
                            class="bg-gray-200 text-gray-700 w-12 h-12 rounded-lg hover:bg-gray-300 transition font-bold text-xl">
                        <i class="fas fa-plus"></i>
                    </button>
                </div>
                <p class="text-sm text-gray-500 text-center mt-3">
                    <i class="fas fa-info-circle mr-1"></i>
                    Available: <strong><?php echo $book['available_quantity']; ?></strong> copies
                </p>
            </div>
            
            <div class="bg-green-50 rounded-lg p-4 mb-6">
                <div class="flex items-start">
                    <i class="fas fa-lightbulb text-green-600 mt-1 mr-3"></i>
                    <div class="text-sm text-gray-700">
                        <p class="font-semibold mb-1">Tip:</p>
                        <p>Add multiple books to your cart and borrow them all at once!</p>
                    </div>
                </div>
            </div>
            
            <div class="flex gap-3">
                <button onclick="closeAddToCartModal()" 
                        class="flex-1 bg-gray-200 text-gray-700 px-4 py-3 rounded-lg hover:bg-gray-300 transition font-semibold">
                    Cancel
                </button>
                <button id="addToCartBtn" onclick="addToCart()" 
                        class="flex-1 bg-green-600 text-white px-4 py-3 rounded-lg hover:bg-green-700 transition font-semibold">
                    <i class="fas fa-cart-plus mr-2"></i>Add to Cart
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Delete Modal Functions
function openDeleteModal() {
    document.getElementById('deleteModal').classList.remove('hidden');
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.add('hidden');
}

// Borrow Modal Functions
function openBorrowModal() {
    document.getElementById('borrowModal').classList.remove('hidden');
}

function closeBorrowModal() {
    document.getElementById('borrowModal').classList.add('hidden');
}

// Reserve Modal Functions
function openReserveModal() {
    document.getElementById('reserveModal').classList.remove('hidden');
}

function closeReserveModal() {
    document.getElementById('reserveModal').classList.add('hidden');
}

// Add to Cart modal close on outside click
document.getElementById('addToCartModal').addEventListener('click', function(e) {
    if (e.target === this) closeAddToCartModal();
});

// Cancel Modal Functions with quantity support
function openCancelModal(bookId, bookTitle, quantity) {
    document.getElementById('cancelBookTitle').textContent = bookTitle;
    const confirmButton = document.getElementById('confirmCancelButton');
    const modalBody = document.getElementById('cancelModalBody');
    
    confirmButton.dataset.bookId = bookId;
    confirmButton.dataset.totalQuantity = quantity;
    
    // Build modal content based on quantity
    if (quantity > 1) {
        modalBody.innerHTML = `
            <p class="mb-4 text-gray-700">You have <strong>${quantity} copies</strong> of "${bookTitle}" pending.</p>
            <div class="space-y-3 mb-4">
                <label class="flex items-center p-3 border rounded-lg cursor-pointer hover:bg-gray-50 transition">
                    <input type="radio" name="cancelOption" value="all" checked class="mr-3 w-4 h-4">
                    <span class="text-gray-700">Cancel all ${quantity} copies</span>
                </label>
                <label class="flex items-center p-3 border rounded-lg cursor-pointer hover:bg-gray-50 transition">
                    <input type="radio" name="cancelOption" value="partial" class="mr-3 w-4 h-4">
                    <span class="text-gray-700">Cancel specific quantity</span>
                </label>
                <div id="quantitySelector" class="ml-8 hidden">
                    <label class="block text-sm text-gray-600 mb-2">Number of copies to cancel:</label>
                    <div class="flex items-center gap-2">
                        <button type="button" onclick="decrementCancelQuantity()" 
                                class="w-8 h-8 bg-gray-200 rounded hover:bg-gray-300">-</button>
                        <input type="number" id="cancelQuantity" min="1" max="${quantity}" 
                               value="1" class="w-20 px-2 py-1 border rounded text-center">
                        <button type="button" onclick="incrementCancelQuantity(${quantity})" 
                                class="w-8 h-8 bg-gray-200 rounded hover:bg-gray-300">+</button>
                    </div>
                </div>
            </div>
        `;
        
        // Add event listeners for radio buttons
        document.querySelectorAll('input[name="cancelOption"]').forEach(radio => {
            radio.addEventListener('change', function(e) {
                const quantitySelector = document.getElementById('quantitySelector');
                if (e.target.value === 'partial') {
                    quantitySelector.classList.remove('hidden');
                } else {
                    quantitySelector.classList.add('hidden');
                }
            });
        });
    } else {
        modalBody.innerHTML = `
            <p class="text-gray-700">Are you sure you want to cancel your request for "${bookTitle}"?</p>
        `;
    }
    
    // Reset button state
    confirmButton.disabled = false;
    document.getElementById('confirmCancelText').textContent = 'Yes, Cancel';
    document.getElementById('loadingSpinner').classList.add('hidden');
    document.getElementById('cancelModal').classList.remove('hidden');
}

function closeCancelModal() {
    document.getElementById('cancelModal').classList.add('hidden');
    // Reset button state
    const confirmButton = document.getElementById('confirmCancelButton');
    confirmButton.disabled = false;
    document.getElementById('confirmCancelText').textContent = 'Yes, Cancel';
    document.getElementById('loadingSpinner').classList.add('hidden');
}

// Quantity control functions for cancel modal
function incrementCancelQuantity(max) {
    const input = document.getElementById('cancelQuantity');
    const current = parseInt(input.value) || 1;
    if (current < max) {
        input.value = current + 1;
    }
}

function decrementCancelQuantity() {
    const input = document.getElementById('cancelQuantity');
    const current = parseInt(input.value) || 1;
    if (current > 1) {
        input.value = current - 1;
    }
}

// Handle AJAX cancellation with quantity support
document.getElementById('confirmCancelButton').addEventListener('click', function() {
    const confirmButton = this;
    const bookId = this.dataset.bookId;
    const totalQuantity = parseInt(this.dataset.totalQuantity);

    let cancelQuantity = 0; // 0 means cancel all
    
    // Check if quantity selection exists (multiple copies)
    const cancelOption = document.querySelector('input[name="cancelOption"]:checked');
    if (cancelOption && cancelOption.value === 'partial') {
        cancelQuantity = parseInt(document.getElementById('cancelQuantity').value) || 1;
        
        // Validate quantity
        if (cancelQuantity < 1 || cancelQuantity > totalQuantity) {
            showAlertMessage('error', 'Invalid quantity selected');
            return;
        }
    }

    // Show loading state
    confirmButton.disabled = true;
    document.getElementById('confirmCancelText').textContent = 'Cancelling...';
    document.getElementById('loadingSpinner').classList.remove('hidden');

    // Build URL with quantity parameter if needed
    let url = `cancel_borrow.php?book_id=${bookId}`;
    if (cancelQuantity > 0) {
        url += `&quantity=${cancelQuantity}`;
    }

    fetch(url, {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json'
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        // Reset button state
        confirmButton.disabled = false;
        document.getElementById('confirmCancelText').textContent = 'Yes, Cancel';
        document.getElementById('loadingSpinner').classList.add('hidden');

        const alertContainer = document.getElementById('alertContainer');
        if (data.success === false) {
            // Show error message
            showAlertMessage('error', data.message);
        } else {
            // Check if all copies were cancelled or partial
            const allCancelled = data.remaining_count === 0;
            
            if (allCancelled) {
                // Update action buttons to show borrow/reserve options
                const actionButtonsContainer = document.getElementById('actionButtonsContainer');
                const isAvailable = <?php echo $is_available ? 'true' : 'false'; ?>;
                if (isAvailable) {
                    actionButtonsContainer.innerHTML = `
                        <button onclick="openBorrowModal()" 
                                class="w-full bg-blue-600 text-white py-3 rounded-lg hover:bg-blue-700 transition font-semibold">
                            <i class="fas fa-book-reader mr-2"></i>Borrow This Book
                        </button>
                    `;
                } else {
                    actionButtonsContainer.innerHTML = `
                        <button onclick="openReserveModal()" 
                                class="w-full bg-yellow-600 text-white py-3 rounded-lg hover:bg-yellow-700 transition font-semibold">
                            <i class="fas fa-bookmark mr-2"></i>Reserve Book
                        </button>
                    `;
                }
            }

            // Show success message
            showAlertMessage('success', data.message);

            // Close the cancel modal
            closeCancelModal();
            
            // If partial cancellation, refresh the page to show updated quantity
            if (!allCancelled) {
                setTimeout(() => {
                    location.reload();
                }, 1500);
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        // Reset button state
        confirmButton.disabled = false;
        document.getElementById('confirmCancelText').textContent = 'Yes, Cancel';
        document.getElementById('loadingSpinner').classList.add('hidden');

        // Show error message
        showAlertMessage('error', 'An error occurred while cancelling the request');
    });
});

// Close modals when clicking outside
document.getElementById('deleteModal').addEventListener('click', function(e) {
    if (e.target === this) closeDeleteModal();
});

document.getElementById('borrowModal').addEventListener('click', function(e) {
    if (e.target === this) closeBorrowModal();
});

document.getElementById('reserveModal').addEventListener('click', function(e) {
    if (e.target === this) closeReserveModal();
});

document.getElementById('cancelModal').addEventListener('click', function(e) {
    if (e.target === this) closeCancelModal();
});

// Close modals with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeDeleteModal();
        closeBorrowModal();
        closeReserveModal();
        closeCancelModal();
        closeAddToCartModal();
    }
});

// Add to Cart Modal Functions
const maxQuantity = <?php echo $book['available_quantity']; ?>;
const bookId = <?php echo $book_id; ?>;

function openAddToCartModal() {
    document.getElementById('addToCartModal').classList.remove('hidden');
    document.getElementById('cartQuantity').value = 1;
}

function closeAddToCartModal() {
    document.getElementById('addToCartModal').classList.add('hidden');
}

function incrementQuantity() {
    const input = document.getElementById('cartQuantity');
    const current = parseInt(input.value) || 1;
    if (current < maxQuantity) {
        input.value = current + 1;
    }
}

function decrementQuantity() {
    const input = document.getElementById('cartQuantity');
    const current = parseInt(input.value) || 1;
    if (current > 1) {
        input.value = current - 1;
    }
}

// Prevent manual input of invalid quantities
document.addEventListener('DOMContentLoaded', function() {
    const quantityInput = document.getElementById('cartQuantity');
    if (quantityInput) {
        quantityInput.addEventListener('input', function() {
            let value = parseInt(this.value) || 1;
            if (value < 1) value = 1;
            if (value > maxQuantity) value = maxQuantity;
            this.value = value;
        });
    }
});

async function addToCart() {
    const quantity = parseInt(document.getElementById('cartQuantity').value);
    const addBtn = document.getElementById('addToCartBtn');
    
    if (quantity < 1 || quantity > maxQuantity) {
        showAlertMessage('error', 'Invalid quantity');
        return;
    }
    
    // Disable button and show loading
    addBtn.disabled = true;
    addBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Adding...';
    
    try {
        const response = await fetch('../cart/add_to_cart.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `book_id=${bookId}&quantity=${quantity}`
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Update cart count in header
            if (window.updateCartCount) {
                window.updateCartCount();
            }
            
            // Show success message
            showAlertMessage('success', data.message);
            
            // Close modal
            closeAddToCartModal();
            
            // Optional: Show link to view cart
            setTimeout(() => {
                const alertContainer = document.getElementById('alertContainer');
                alertContainer.innerHTML += `
                    <div class="mt-2">
                        <a href="../cart/cart.php" class="inline-block bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition text-sm font-semibold">
                            <i class="fas fa-shopping-cart mr-2"></i>View Cart
                        </a>
                    </div>
                `;
            }, 100);
        } else {
            showAlertMessage('error', data.message);
        }
    } catch (error) {
        console.error('Error:', error);
        showAlertMessage('error', 'Failed to add to cart. Please try again.');
    } finally {
        // Re-enable button
        addBtn.disabled = false;
        addBtn.innerHTML = '<i class="fas fa-cart-plus mr-2"></i>Add to Cart';
    }
}

// Helper function to show alert messages
function showAlertMessage(type, message) {
    const alertClass = type === 'success' ? 'green' : 'red';
    const icon = type === 'success' ? 'check-circle' : 'exclamation-circle';
    
    const alertContainer = document.getElementById('alertContainer');
    alertContainer.innerHTML = `
        <div class="bg-${alertClass}-50 border-l-4 border-${alertClass}-500 text-${alertClass}-700 p-4 rounded" role="alert">
            <div class="flex items-center">
                <i class="fas fa-${icon} mr-2"></i>
                <span>${message}</span>
            </div>
        </div>
    `;
    
    // Scroll to top to show alert
    window.scrollTo({ top: 0, behavior: 'smooth' });
    
    // Auto-hide after 5 seconds
    setTimeout(() => {
        alertContainer.innerHTML = '';
    }, 5000);
}
</script>

<?php require_once '../includes/footer.php'; ?>