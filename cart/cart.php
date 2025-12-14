<?php
$page_title = "My Cart";
require_once '../includes/config.php';
require_once '../includes/header.php';

// Check if user is logged in and is a member
if (!isLoggedIn() || $_SESSION['role'] !== 'member') {
    setMessage('error', 'Access denied. Only members can view cart.');
    redirect('../pages/dashboard.php');
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

<!-- Page Header -->
<div class="mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">
                <i class="fas fa-shopping-cart text-green-600 mr-3"></i>My Cart
            </h1>
            <p class="text-gray-600 mt-2">Review and manage your book borrowing cart</p>
        </div>
        <a href="../books/books.php" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition font-semibold">
            <i class="fas fa-plus mr-2"></i>Add More Books
        </a>
    </div>
</div>

<!-- Loading Spinner -->
<div id="loadingSpinner" class="text-center py-12">
    <i class="fas fa-spinner fa-spin text-green-600 text-4xl"></i>
    <p class="text-gray-600 mt-4">Loading cart...</p>
</div>

<!-- Cart Container -->
<div id="cartContainer" class="hidden">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Cart Items (Left Column) -->
        <div class="lg:col-span-2 space-y-4" id="cartItemsContainer">
            <!-- Items will be loaded here -->
        </div>

        <!-- Cart Summary (Right Column) -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-lg shadow-md p-6 sticky top-24">
                <h2 class="text-xl font-bold text-gray-800 mb-6">
                    <i class="fas fa-clipboard-list mr-2"></i>Cart Summary
                </h2>
                
                <div class="space-y-4 mb-6">
                    <div class="flex justify-between items-center pb-3 border-b">
                        <span class="text-gray-600">Unique Books:</span>
                        <span class="text-xl font-bold text-gray-800" id="uniqueBooksCount">0</span>
                    </div>
                    <div class="flex justify-between items-center pb-3 border-b">
                        <span class="text-gray-600">Total Copies:</span>
                        <span class="text-xl font-bold text-green-600" id="totalCopiesCount">0</span>
                    </div>
                </div>

                <!-- Warning for unavailable items -->
                <div id="unavailableWarning" class="hidden bg-yellow-50 border-l-4 border-yellow-500 p-4 mb-6">
                    <div class="flex items-start">
                        <i class="fas fa-exclamation-triangle text-yellow-600 mt-1 mr-3"></i>
                        <div class="text-sm text-yellow-800">
                            <p class="font-semibold mb-1">Availability Issue</p>
                            <p>Some items exceed available quantity. Please adjust before checkout.</p>
                        </div>
                    </div>
                </div>

                <!-- Checkout Button -->
                <button id="checkoutBtn" onclick="proceedToCheckout()" 
                        class="w-full bg-green-600 text-white py-4 rounded-lg hover:bg-green-700 transition font-bold text-lg">
                    <i class="fas fa-check-circle mr-2"></i>Proceed to Checkout
                </button>

                <!-- Info Box -->
                <div class="mt-6 bg-blue-50 rounded-lg p-4">
                    <div class="flex items-start">
                        <i class="fas fa-info-circle text-blue-600 mt-1 mr-3"></i>
                        <div class="text-sm text-gray-700">
                            <p class="font-semibold mb-2">Borrowing Information:</p>
                            <ul class="space-y-1 text-gray-600">
                                <li>• Standard loan: 14 days</li>
                                <li>• Requires librarian approval</li>
                                <li>• Take care of all books</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Empty Cart Message -->
<div id="emptyCartContainer" class="hidden">
    <div class="bg-white rounded-lg shadow-md p-12 text-center">
        <i class="fas fa-shopping-cart text-gray-300 text-6xl mb-4"></i>
        <h3 class="text-2xl font-bold text-gray-800 mb-2">Your Cart is Empty</h3>
        <p class="text-gray-600 mb-6">Start adding books to borrow them together!</p>
        <a href="../books/books.php" class="inline-block bg-green-600 text-white px-8 py-3 rounded-lg hover:bg-green-700 transition font-semibold">
            <i class="fas fa-book mr-2"></i>Browse Books
        </a>
    </div>
</div>

<!-- Remove Confirmation Modal -->
<div id="removeModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
        <div class="p-6">
            <div class="flex items-center justify-center w-12 h-12 mx-auto bg-red-100 rounded-full mb-4">
                <i class="fas fa-trash text-red-600 text-2xl"></i>
            </div>
            <h3 class="text-xl font-bold text-gray-900 text-center mb-2">Remove Item</h3>
            <p class="text-gray-600 text-center mb-6">
                Are you sure you want to remove "<strong id="removeBookTitle"></strong>" from your cart?
            </p>
            <div class="flex gap-3">
                <button onclick="closeRemoveModal()" 
                        class="flex-1 bg-gray-200 text-gray-700 px-4 py-3 rounded-lg hover:bg-gray-300 transition font-semibold">
                    Cancel
                </button>
                <button id="confirmRemoveBtn" onclick="confirmRemove()" 
                        class="flex-1 bg-red-600 text-white px-4 py-3 rounded-lg hover:bg-red-700 transition font-semibold">
                    Remove
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let selectedCartId = null;

// Load cart on page load
document.addEventListener('DOMContentLoaded', function() {
    loadCart();
});

// Load cart items
async function loadCart() {
    try {
        const response = await fetch('get_cart_data.php?action=items');
        const data = await response.json();
        
        document.getElementById('loadingSpinner').classList.add('hidden');
        
        if (data.success && data.items.length > 0) {
            displayCartItems(data.items, data.total_items, data.has_unavailable);
            document.getElementById('cartContainer').classList.remove('hidden');
        } else {
            document.getElementById('emptyCartContainer').classList.remove('hidden');
        }
    } catch (error) {
        console.error('Error loading cart:', error);
        document.getElementById('loadingSpinner').classList.add('hidden');
        showAlert('error', 'Failed to load cart items');
    }
}

// Display cart items
function displayCartItems(items, totalItems, hasUnavailable) {
    const container = document.getElementById('cartItemsContainer');
    let html = '';
    
    items.forEach(item => {
        const bookImage = item.cover_image ? `../uploads/books/${item.cover_image}` : '../assets/images/default-book.png';
        const availabilityClass = item.is_available ? 'text-green-600' : 'text-red-600';
        const availabilityIcon = item.is_available ? 'fa-check-circle' : 'fa-exclamation-triangle';
        const availabilityText = item.is_available 
            ? `${item.available_quantity} available` 
            : `Only ${item.available_quantity} available`;
        
        html += `
            <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition ${!item.is_available ? 'border-2 border-red-300' : ''}">
                <div class="md:flex">
                    <!-- Book Image -->
                    <div class="md:w-32 h-48 bg-gray-200">
                        <img src="${bookImage}" alt="${item.title}" 
                             class="w-full h-full object-cover">
                    </div>
                    
                    <!-- Book Details -->
                    <div class="flex-1 p-4">
                        <div class="flex justify-between items-start mb-2">
                            <div class="flex-1">
                                <h3 class="text-lg font-bold text-gray-800 mb-1">${item.title}</h3>
                                <p class="text-gray-600 text-sm mb-1">by ${item.author}</p>
                                ${item.category_name ? `<span class="inline-block bg-blue-100 text-blue-700 px-2 py-1 rounded text-xs">${item.category_name}</span>` : ''}
                            </div>
                            <button onclick="openRemoveModal(${item.cart_id}, '${item.title.replace(/'/g, "\\\'")}')"
                                    class="text-red-600 hover:text-red-800 p-2">
                                <i class="fas fa-trash text-lg"></i>
                            </button>
                        </div>
                        
                        <div class="flex justify-between items-center mt-4">
                            <!-- Quantity Control -->
                            <div class="flex items-center space-x-3">
                                <button onclick="updateQuantity(${item.cart_id}, ${item.quantity - 1}, ${item.available_quantity})" 
                                        ${item.quantity <= 1 ? 'disabled' : ''}
                                        class="bg-gray-200 text-gray-700 px-3 py-1 rounded hover:bg-gray-300 disabled:opacity-50 disabled:cursor-not-allowed">
                                    <i class="fas fa-minus"></i>
                                </button>
                                <span class="text-xl font-bold text-gray-800 w-12 text-center">${item.quantity}</span>
                                <button onclick="updateQuantity(${item.cart_id}, ${item.quantity + 1}, ${item.available_quantity})" 
                                        ${item.quantity >= item.available_quantity ? 'disabled' : ''}
                                        class="bg-gray-200 text-gray-700 px-3 py-1 rounded hover:bg-gray-300 disabled:opacity-50 disabled:cursor-not-allowed">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                            
                            <!-- Availability Status -->
                            <div class="${availabilityClass} font-semibold text-sm">
                                <i class="fas ${availabilityIcon} mr-1"></i>
                                ${availabilityText}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
    
    // Update summary
    document.getElementById('uniqueBooksCount').textContent = items.length;
    document.getElementById('totalCopiesCount').textContent = totalItems;
    
    // Show/hide warning and disable checkout if unavailable items
    if (hasUnavailable) {
        document.getElementById('unavailableWarning').classList.remove('hidden');
        document.getElementById('checkoutBtn').disabled = true;
        document.getElementById('checkoutBtn').classList.add('opacity-50', 'cursor-not-allowed');
    } else {
        document.getElementById('unavailableWarning').classList.add('hidden');
        document.getElementById('checkoutBtn').disabled = false;
        document.getElementById('checkoutBtn').classList.remove('opacity-50', 'cursor-not-allowed');
    }
}

// Update quantity
async function updateQuantity(cartId, newQuantity, maxQuantity) {
    if (newQuantity < 1 || newQuantity > maxQuantity) return;
    
    try {
        const response = await fetch('update_cart.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `cart_id=${cartId}&quantity=${newQuantity}`
        });
        
        const data = await response.json();
        
        if (data.success) {
            loadCart();
            if (window.updateCartCount) {
                window.updateCartCount();
            }
        } else {
            showAlert('error', data.message);
        }
    } catch (error) {
        console.error('Error updating quantity:', error);
        showAlert('error', 'Failed to update quantity');
    }
}

// Remove modal functions
function openRemoveModal(cartId, bookTitle) {
    selectedCartId = cartId;
    document.getElementById('removeBookTitle').textContent = bookTitle;
    document.getElementById('removeModal').classList.remove('hidden');
}

function closeRemoveModal() {
    selectedCartId = null;
    document.getElementById('removeModal').classList.add('hidden');
}

async function confirmRemove() {
    if (!selectedCartId) return;
    
    try {
        const response = await fetch('remove_from_cart.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `cart_id=${selectedCartId}`
        });
        
        const data = await response.json();
        
        if (data.success) {
            closeRemoveModal();
            loadCart();
            if (window.updateCartCount) {
                window.updateCartCount();
            }
            showAlert('success', data.message);
        } else {
            showAlert('error', data.message);
        }
    } catch (error) {
        console.error('Error removing item:', error);
        showAlert('error', 'Failed to remove item');
    }
}

// Proceed to checkout
function proceedToCheckout() {
    window.location.href = 'checkout.php';
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
    
    // Scroll to top
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeRemoveModal();
    }
});

// Close modal when clicking outside
document.getElementById('removeModal').addEventListener('click', function(e) {
    if (e.target === this) closeRemoveModal();
});
</script>

<?php require_once '../includes/footer.php'; ?>