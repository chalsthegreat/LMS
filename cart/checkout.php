<?php
$page_title = "Checkout";
require_once '../includes/config.php';
require_once '../includes/header.php';

// Check if user is logged in and is a member
if (!isLoggedIn() || $_SESSION['role'] !== 'member') {
    setMessage('error', 'Access denied. Only members can checkout.');
    redirect('../pages/dashboard.php');
}

// Check if cart is empty
$cart_check_sql = "SELECT COUNT(*) as count FROM cart WHERE user_id = ?";
$cart_check_stmt = $conn->prepare($cart_check_sql);
$cart_check_stmt->bind_param("i", $_SESSION['user_id']);
$cart_check_stmt->execute();
$cart_check_result = $cart_check_stmt->get_result();
$cart_count = $cart_check_result->fetch_assoc()['count'];
$cart_check_stmt->close();

if ($cart_count == 0) {
    setMessage('error', 'Your cart is empty. Add books before checkout.');
    redirect('cart.php');
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

<!-- Breadcrumb -->
<div class="mb-6">
    <nav class="flex" aria-label="Breadcrumb">
        <ol class="inline-flex items-center space-x-1 md:space-x-3">
            <li class="inline-flex items-center">
                <a href="../books/books.php" class="text-gray-700 hover:text-blue-600">
                    <i class="fas fa-book mr-2"></i>Books
                </a>
            </li>
            <li>
                <div class="flex items-center">
                    <i class="fas fa-chevron-right text-gray-400 mx-2"></i>
                    <a href="cart.php" class="text-gray-700 hover:text-blue-600">Cart</a>
                </div>
            </li>
            <li aria-current="page">
                <div class="flex items-center">
                    <i class="fas fa-chevron-right text-gray-400 mx-2"></i>
                    <span class="text-gray-500">Checkout</span>
                </div>
            </li>
        </ol>
    </nav>
</div>

<!-- Page Header -->
<div class="mb-8">
    <h1 class="text-3xl font-bold text-gray-800">
        <i class="fas fa-check-circle text-green-600 mr-3"></i>Checkout
    </h1>
    <p class="text-gray-600 mt-2">Review your order and submit borrow request</p>
</div>

<!-- Loading Spinner -->
<div id="loadingSpinner" class="text-center py-12">
    <i class="fas fa-spinner fa-spin text-green-600 text-4xl"></i>
    <p class="text-gray-600 mt-4">Loading checkout...</p>
</div>

<!-- Checkout Container -->
<div id="checkoutContainer" class="hidden">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Order Review (Left Column) -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Cart Items -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4">
                    <i class="fas fa-list mr-2"></i>Books to Borrow
                </h2>
                <div id="checkoutItemsContainer" class="space-y-4">
                    <!-- Items will be loaded here -->
                </div>
            </div>

            <!-- Borrowing Terms -->
            <div class="bg-blue-50 border-l-4 border-blue-500 rounded-lg p-6">
                <h3 class="text-lg font-bold text-blue-900 mb-3">
                    <i class="fas fa-info-circle mr-2"></i>Borrowing Terms & Conditions
                </h3>
                <ul class="space-y-2 text-blue-800">
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-blue-600 mt-1 mr-3"></i>
                        <span>Standard loan period is <strong>14 days</strong> from approval date</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-blue-600 mt-1 mr-3"></i>
                        <span>Your request requires <strong>librarian approval</strong></span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-blue-600 mt-1 mr-3"></i>
                        <span>Late returns may incur <strong>fines</strong></span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-blue-600 mt-1 mr-3"></i>
                        <span>You are responsible for <strong>book condition</strong></span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-blue-600 mt-1 mr-3"></i>
                        <span>Maximum of <strong>5 books</strong> can be borrowed at once</span>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Order Summary (Right Column) -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-lg shadow-md p-6 sticky top-24">
                <h2 class="text-xl font-bold text-gray-800 mb-6">
                    <i class="fas fa-clipboard-check mr-2"></i>Order Summary
                </h2>
                
                <div class="space-y-4 mb-6">
                    <div class="flex justify-between items-center pb-3 border-b">
                        <span class="text-gray-600">Unique Books:</span>
                        <span class="text-xl font-bold text-gray-800" id="summaryUniqueBooksCount">0</span>
                    </div>
                    <div class="flex justify-between items-center pb-3 border-b">
                        <span class="text-gray-600">Total Copies:</span>
                        <span class="text-xl font-bold text-green-600" id="summaryTotalCopiesCount">0</span>
                    </div>
                    <div class="flex justify-between items-center pb-3 border-b">
                        <span class="text-gray-600">Loan Period:</span>
                        <span class="text-lg font-semibold text-gray-800">14 Days</span>
                    </div>
                    <div class="flex justify-between items-center pb-3 border-b">
                        <span class="text-gray-600">Status:</span>
                        <span class="px-3 py-1 bg-yellow-100 text-yellow-700 rounded-full text-sm font-semibold">
                            Pending Approval
                        </span>
                    </div>
                </div>

                <!-- Agreement Checkbox -->
                <div class="mb-6">
                    <label class="flex items-start cursor-pointer">
                        <input type="checkbox" id="agreeTerms" class="mt-1 mr-3 h-5 w-5 text-green-600 border-gray-300 rounded focus:ring-green-500">
                        <span class="text-sm text-gray-700">
                            I agree to the borrowing terms and conditions. I understand that I'm responsible for returning all books on time and in good condition.
                        </span>
                    </label>
                </div>

                <!-- Submit Button -->
                <button id="submitOrderBtn" onclick="submitOrder()" disabled
                        class="w-full bg-gray-400 text-white py-4 rounded-lg cursor-not-allowed transition font-bold text-lg mb-3">
                    <i class="fas fa-lock mr-2"></i>Submit Borrow Request
                </button>

                <a href="cart.php" class="block w-full text-center bg-gray-200 text-gray-700 py-3 rounded-lg hover:bg-gray-300 transition font-semibold">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Cart
                </a>

                <!-- Warning Box -->
                <div id="unavailableWarning" class="hidden mt-6 bg-red-50 border-l-4 border-red-500 p-4">
                    <div class="flex items-start">
                        <i class="fas fa-exclamation-triangle text-red-600 mt-1 mr-3"></i>
                        <div class="text-sm text-red-800">
                            <p class="font-semibold mb-1">Cannot Proceed</p>
                            <p id="unavailableMessage">Some items are no longer available.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Success Modal -->
<div id="successModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4 transform transition-all">
        <div class="p-6">
            <div class="flex items-center justify-center w-16 h-16 mx-auto bg-green-100 rounded-full mb-4">
                <i class="fas fa-check-circle text-green-600 text-3xl"></i>
            </div>
            <h3 class="text-2xl font-bold text-gray-900 text-center mb-2">Request Submitted!</h3>
            <p class="text-gray-600 text-center mb-6">
                Your borrow request has been submitted successfully. Please wait for librarian approval.
            </p>
            <div class="bg-blue-50 rounded-lg p-4 mb-6">
                <div class="flex items-start">
                    <i class="fas fa-info-circle text-blue-600 mt-1 mr-3"></i>
                    <div class="text-sm text-gray-700">
                        <p class="font-semibold mb-2">What's Next?</p>
                        <ul class="space-y-1 text-gray-600">
                            <li>• Librarian will review your request</li>
                            <li>• You'll receive a notification</li>
                            <li>• Check "My Borrowings" for status</li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="flex gap-3">
                <a href="../books/my_borrowings.php" 
                   class="flex-1 bg-blue-600 text-white text-center px-4 py-3 rounded-lg hover:bg-blue-700 transition font-semibold">
                    <i class="fas fa-book-reader mr-2"></i>My Borrowings
                </a>
                <a href="../books/books.php" 
                   class="flex-1 bg-gray-200 text-gray-700 text-center px-4 py-3 rounded-lg hover:bg-gray-300 transition font-semibold">
                    <i class="fas fa-book mr-2"></i>Browse Books
                </a>
            </div>
        </div>
    </div>
</div>

<script>
let checkoutData = null;

// Load checkout on page load
document.addEventListener('DOMContentLoaded', function() {
    loadCheckout();
    
    // Enable submit button when terms are agreed
    document.getElementById('agreeTerms').addEventListener('change', function() {
        const submitBtn = document.getElementById('submitOrderBtn');
        if (this.checked && checkoutData && !checkoutData.has_unavailable) {
            submitBtn.disabled = false;
            submitBtn.classList.remove('bg-gray-400', 'cursor-not-allowed');
            submitBtn.classList.add('bg-green-600', 'hover:bg-green-700', 'cursor-pointer');
            submitBtn.innerHTML = '<i class="fas fa-check-circle mr-2"></i>Submit Borrow Request';
        } else {
            submitBtn.disabled = true;
            submitBtn.classList.add('bg-gray-400', 'cursor-not-allowed');
            submitBtn.classList.remove('bg-green-600', 'hover:bg-green-700', 'cursor-pointer');
            submitBtn.innerHTML = '<i class="fas fa-lock mr-2"></i>Submit Borrow Request';
        }
    });
});

// Load checkout data
async function loadCheckout() {
    try {
        const response = await fetch('get_cart_data.php?action=items');
        const data = await response.json();
        
        document.getElementById('loadingSpinner').classList.add('hidden');
        
        if (data.success && data.items.length > 0) {
            checkoutData = data;
            displayCheckoutItems(data.items, data.total_items, data.has_unavailable);
            document.getElementById('checkoutContainer').classList.remove('hidden');
        } else {
            window.location.href = 'cart.php';
        }
    } catch (error) {
        console.error('Error loading checkout:', error);
        showAlert('error', 'Failed to load checkout data');
    }
}

// Display checkout items
function displayCheckoutItems(items, totalItems, hasUnavailable) {
    const container = document.getElementById('checkoutItemsContainer');
    let html = '';
    
    items.forEach(item => {
        const bookImage = item.cover_image ? `../uploads/books/${item.cover_image}` : '../assets/images/default-book.png';
        const statusClass = item.is_available ? 'text-green-600' : 'text-red-600';
        const statusIcon = item.is_available ? 'check-circle' : 'exclamation-triangle';
        const statusText = item.is_available ? 'Available' : 'Not Available';
        
        html += `
            <div class="flex items-center border rounded-lg p-4 ${!item.is_available ? 'bg-red-50 border-red-300' : 'bg-gray-50'}">
                <img src="${bookImage}" alt="${item.title}" 
                     class="w-16 h-20 object-cover rounded mr-4">
                <div class="flex-1">
                    <h3 class="font-bold text-gray-800">${item.title}</h3>
                    <p class="text-sm text-gray-600">by ${item.author}</p>
                    <div class="flex items-center justify-between mt-2">
                        <span class="text-lg font-bold text-gray-800">
                            Quantity: ${item.quantity}
                        </span>
                        <span class="${statusClass} font-semibold text-sm">
                            <i class="fas fa-${statusIcon} mr-1"></i>
                            ${statusText}
                        </span>
                    </div>
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
    
    // Update summary
    document.getElementById('summaryUniqueBooksCount').textContent = items.length;
    document.getElementById('summaryTotalCopiesCount').textContent = totalItems;
    
    // Handle unavailable items
    if (hasUnavailable) {
        document.getElementById('unavailableWarning').classList.remove('hidden');
        document.getElementById('unavailableMessage').textContent = 
            'Some items are no longer available. Please go back to cart and remove them.';
        document.getElementById('submitOrderBtn').disabled = true;
        document.getElementById('agreeTerms').disabled = true;
    }
}

// Submit order
async function submitOrder() {
    const submitBtn = document.getElementById('submitOrderBtn');
    
    if (!document.getElementById('agreeTerms').checked) {
        showAlert('error', 'Please agree to the terms and conditions');
        return;
    }
    
    // Disable button and show loading
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Submitting...';
    
    try {
        const response = await fetch('process_checkout.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Update cart count
            if (window.updateCartCount) {
                window.updateCartCount();
            }
            
            // Show success modal
            document.getElementById('successModal').classList.remove('hidden');
        } else {
            showAlert('error', data.message);
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-check-circle mr-2"></i>Submit Borrow Request';
        }
    } catch (error) {
        console.error('Error submitting order:', error);
        showAlert('error', 'Failed to submit request. Please try again.');
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-check-circle mr-2"></i>Submit Borrow Request';
    }
}

// Show alert helper
function showAlert(type, message) {
    const alertClass = type === 'success' ? 'green' : 'red';
    const icon = type === 'success' ? 'check-circle' : 'exclamation-circle';
    
    document.getElementById('alertContainer').innerHTML = `
        <div class="bg-${alertClass}-50 border-l-4 border-${alertClass}-500 text-${alertClass}-700 p-4 rounded" role="alert">
            <div class="flex items-center">
                <i class="fas fa-${icon} mr-2"></i>
                <span>${message}</span>
            </div>
        </div>
    `;
    
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
</script>

<?php require_once '../includes/footer.php'; ?>