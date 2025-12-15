<?php
$page_title = "My Borrowings";
require_once '../includes/config.php';
require_once '../includes/header.php';

// Check if user is logged in and is a member
if (!isLoggedIn() || $_SESSION['role'] !== 'member') {
    setMessage('error', 'Access denied. Only members can view this page.');
    redirect('dashboard.php');
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
            <h1 class="text-3xl font-bold text-gray-800">My Borrowings</h1>
            <p class="text-gray-600 mt-2">Track your borrowed books and borrowing history</p>
        </div>
        <a href="books.php" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition font-semibold">
            <i class="fas fa-book mr-2"></i>Browse Books
        </a>
    </div>
</div>

<!-- Statistics Cards -->
<div id="statsContainer" class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
    <!-- Statistics will be loaded here -->
</div>

<!-- Search and Filter Section -->
<div class="bg-white rounded-lg shadow-md p-6 mb-8">
    <form id="searchForm" class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <!-- Search Input -->
        <div class="md:col-span-2">
            <label class="block text-sm font-semibold text-gray-700 mb-2">
                <i class="fas fa-search mr-2"></i>Search Books
            </label>
            <input type="text" id="searchInput" name="search" 
                   placeholder="Search by title, author, or ISBN..."
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>

        <!-- Status Filter -->
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">
                <i class="fas fa-filter mr-2"></i>Status
            </label>
            <select id="statusFilter" name="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="all">All Status</option>
                <option value="pending">Pending</option>
                <option value="borrowed">Currently Borrowed</option>
                <option value="overdue">Overdue</option>
                <option value="returned">Returned</option>
            </select>
        </div>

        <!-- Sort Filter -->
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">
                <i class="fas fa-sort mr-2"></i>Sort By
            </label>
            <select id="sortFilter" name="sort" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="borrow_date_desc">Borrow Date (Newest)</option>
                <option value="borrow_date_asc">Borrow Date (Oldest)</option>
                <option value="due_date_asc">Due Date (Soonest)</option>
                <option value="due_date_desc">Due Date (Latest)</option>
                <option value="title_asc">Title (A-Z)</option>
                <option value="title_desc">Title (Z-A)</option>
            </select>
        </div>

        <!-- Search Button -->
        <div class="md:col-span-4 flex gap-2">
            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition font-semibold">
                <i class="fas fa-search mr-2"></i>Search
            </button>
            <button type="button" id="resetBtn" class="bg-gray-200 text-gray-700 px-6 py-2 rounded-lg hover:bg-gray-300 transition font-semibold">
                <i class="fas fa-redo mr-2"></i>Reset
            </button>
        </div>
    </form>
</div>

<!-- Loading Spinner -->
<div id="loadingSpinner" class="hidden text-center py-12">
    <i class="fas fa-spinner fa-spin text-blue-600 text-4xl"></i>
    <p class="text-gray-600 mt-4">Loading borrowings...</p>
</div>

<!-- Borrowings Container -->
<div id="borrowingsContainer">
    <!-- Borrowings will be loaded here -->
</div>

<!-- Pagination Container -->
<div id="paginationContainer" class="mt-8">
    <!-- Pagination will be loaded here -->
</div>




<script>
// Global variables
let currentPage = 1;
let selectedBorrowingId = null;
let selectedBookId = null;

// Load borrowings on page load
document.addEventListener('DOMContentLoaded', function() {
    loadStatistics();
    loadBorrowings();
});

// Search form submit
document.getElementById('searchForm').addEventListener('submit', function(e) {
    e.preventDefault();
    currentPage = 1;
    loadBorrowings();
});

// Reset button
document.getElementById('resetBtn').addEventListener('click', function() {
    document.getElementById('searchInput').value = '';
    document.getElementById('statusFilter').value = 'all';
    document.getElementById('sortFilter').value = 'borrow_date_desc';
    currentPage = 1;
    loadBorrowings();
});

// Filter change handlers
document.getElementById('statusFilter').addEventListener('change', function() {
    currentPage = 1;
    loadBorrowings();
});

document.getElementById('sortFilter').addEventListener('change', function() {
    currentPage = 1;
    loadBorrowings();
});

// Load statistics
async function loadStatistics() {
    try {
        const response = await fetch('get_borrowings_data.php?action=statistics');
        const data = await response.json();
        
        if (data.success) {
            const stats = data.statistics;
            document.getElementById('statsContainer').innerHTML = `
                <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-blue-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm font-semibold uppercase">Currently Borrowed</p>
                            <p class="text-3xl font-bold text-gray-800 mt-2">${stats.currently_borrowed}</p>
                        </div>
                        <div class="bg-blue-100 rounded-full p-4">
                            <i class="fas fa-book-reader text-blue-600 text-2xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-red-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm font-semibold uppercase">Overdue Books</p>
                            <p class="text-3xl font-bold text-gray-800 mt-2">${stats.overdue_books}</p>
                        </div>
                        <div class="bg-red-100 rounded-full p-4">
                            <i class="fas fa-exclamation-triangle text-red-600 text-2xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-green-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm font-semibold uppercase">Returned Books</p>
                            <p class="text-3xl font-bold text-gray-800 mt-2">${stats.returned_books}</p>
                        </div>
                        <div class="bg-green-100 rounded-full p-4">
                            <i class="fas fa-check-circle text-green-600 text-2xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-yellow-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm font-semibold uppercase">Total Fines</p>
                            <p class="text-3xl font-bold text-gray-800 mt-2">₱${stats.total_fines}</p>
                        </div>
                        <div class="bg-yellow-100 rounded-full p-4">
                            <i class="fas fa-money-bill-wave text-yellow-600 text-2xl"></i>
                        </div>
                    </div>
                </div>
            `;
        } else {
            document.getElementById('statsContainer').innerHTML = `
                <div class="bg-white rounded-lg shadow-md p-6 col-span-full">
                    <p class="text-red-600 text-center">Failed to load statistics</p>
                </div>
            `;
        }
    } catch (error) {
        console.error('Error loading statistics:', error);
        document.getElementById('statsContainer').innerHTML = `
            <div class="bg-white rounded-lg shadow-md p-6 col-span-full">
                <p class="text-red-600 text-center">Error loading statistics</p>
            </div>
        `;
    }
}

// Enhanced Load borrowings function with better error handling
async function loadBorrowings() {
    const search = document.getElementById('searchInput').value;
    const status = document.getElementById('statusFilter').value;
    const sort = document.getElementById('sortFilter').value;
    
    document.getElementById('loadingSpinner').classList.remove('hidden');
    document.getElementById('borrowingsContainer').innerHTML = '';
    document.getElementById('paginationContainer').innerHTML = '';
    
    try {
        const url = `get_borrowings_data.php?action=borrowings&page=${currentPage}&search=${encodeURIComponent(search)}&status=${status}&sort=${sort}`;
        console.log('Fetching:', url); // Debug log
        
        const response = await fetch(url);
        
        // Check if response is OK
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        // Get response as text first to see what we're receiving
        const responseText = await response.text();
        console.log('Response text:', responseText); // Debug log
        
        // Try to parse as JSON
        let data;
        try {
            data = JSON.parse(responseText);
        } catch (parseError) {
            console.error('JSON parse error:', parseError);
            console.error('Response was:', responseText);
            throw new Error('Invalid JSON response from server');
        }
        
        console.log('Parsed data:', data); // Debug log
        
        document.getElementById('loadingSpinner').classList.add('hidden');
        
        if (data.success && data.borrowings.length > 0) {
            console.log('Displaying borrowings:', data.borrowings.length); // Debug log
            displayBorrowings(data.borrowings);
            displayPagination(data.pagination);
        } else if (data.success && data.borrowings.length === 0) {
            console.log('No borrowings found'); // Debug log
            displayNoBorrowings(search, status);
        } else {
            console.error('Server returned error:', data.message); // Debug log
            throw new Error(data.message || 'Unknown error from server');
        }
    } catch (error) {
        console.error('Error loading borrowings:', error);
        document.getElementById('loadingSpinner').classList.add('hidden');
        document.getElementById('borrowingsContainer').innerHTML = `
            <div class="bg-white rounded-lg shadow-md p-12 text-center">
                <i class="fas fa-exclamation-circle text-red-600 text-6xl mb-4"></i>
                <h3 class="text-2xl font-bold text-gray-800 mb-2">Error</h3>
                <p class="text-gray-600 mb-4">Failed to load borrowings: ${error.message}</p>
                <button onclick="loadBorrowings()" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition font-semibold">
                    <i class="fas fa-redo mr-2"></i>Try Again
                </button>
            </div>
        `;
    }
}

// Display borrowings with cart badge support and quantity grouping
function displayBorrowings(borrowings) {
    const container = document.getElementById('borrowingsContainer');
    
    // Group cart items together by cart_batch_id and book_id
    const groupedBorrowings = {};
    borrowings.forEach(borrowing => {
        if (borrowing.cart_batch_id && borrowing.status === 'pending') {
            // Create a unique key: cart_batch_id + book_id
            const key = `${borrowing.cart_batch_id}_${borrowing.book_id}`;
            
            if (!groupedBorrowings[key]) {
                groupedBorrowings[key] = {
                    ...borrowing,
                    quantity: 1,
                    borrowing_ids: [borrowing.borrowing_id]
                };
            } else {
                groupedBorrowings[key].quantity++;
                groupedBorrowings[key].borrowing_ids.push(borrowing.borrowing_id);
            }
        } else {
            // Non-cart or non-pending items display normally
            const key = `single_${borrowing.borrowing_id}`;
            groupedBorrowings[key] = { 
                ...borrowing, 
                quantity: 1, 
                borrowing_ids: [borrowing.borrowing_id] 
            };
        }
    });
    
    let html = '<div class="grid grid-cols-1 gap-6">';
    
    // Iterate over grouped borrowings
    Object.values(groupedBorrowings).forEach(borrowing => {
        const bookImage = borrowing.cover_image ? `../uploads/books/${borrowing.cover_image}` : '../assets/images/default-book.png';
        
        const statusColors = {
            'pending': 'bg-blue-100 text-blue-700 border-blue-200',
            'borrowed': 'bg-yellow-100 text-yellow-700 border-yellow-200',
            'returned': 'bg-green-100 text-green-700 border-green-200',
            'overdue': 'bg-red-100 text-red-700 border-red-200',
            'declined': 'bg-gray-100 text-gray-700 border-gray-200'
        };
        const statusClass = statusColors[borrowing.status] || 'bg-gray-100 text-gray-700 border-gray-200';
        
        // Check if this is part of a cart batch
        const isCartBatch = borrowing.cart_batch_id != null && borrowing.cart_batch_id !== '';
        const cartBadge = isCartBatch ? `
            <span class="inline-flex items-center px-2 py-1 bg-purple-100 text-purple-700 text-xs font-semibold rounded-full ml-2">
                <i class="fas fa-shopping-cart mr-1"></i>Cart Order
            </span>
        ` : '';
        
        // Quantity badge for multiple copies
        const quantityBadge = borrowing.quantity > 1 ? `
            <span class="inline-flex items-center px-2 py-1 bg-blue-100 text-blue-700 text-sm font-semibold rounded-full ml-2">
                ×${borrowing.quantity}
            </span>
        ` : '';
        
        // Calculate days info
        let daysInfo = '';
        let urgencyClass = '';
        if (borrowing.status === 'pending') {
            daysInfo = 'Awaiting librarian approval';
            urgencyClass = 'text-blue-600 font-bold';
        } else if (borrowing.status === 'borrowed') {
            if (borrowing.days_remaining > 3) {
                daysInfo = `${borrowing.days_remaining} days remaining`;
                urgencyClass = 'text-green-600';
            } else if (borrowing.days_remaining > 0) {
                daysInfo = `${borrowing.days_remaining} days remaining`;
                urgencyClass = 'text-orange-600 font-bold';
            } else if (borrowing.days_remaining === 0) {
                daysInfo = 'Due today!';
                urgencyClass = 'text-red-600 font-bold';
            } else {
                daysInfo = `${Math.abs(borrowing.days_remaining)} days overdue`;
                urgencyClass = 'text-red-600 font-bold';
            }
        } else if (borrowing.status === 'overdue') {
            daysInfo = `${borrowing.days_overdue} days overdue`;
            urgencyClass = 'text-red-600 font-bold';
        } else if (borrowing.status === 'returned' && borrowing.return_date) {
            const returnDate = new Date(borrowing.return_date);
            daysInfo = `Returned on ${returnDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}`;
            urgencyClass = 'text-green-600';
        } else if (borrowing.status === 'declined' && borrowing.declined_date) {
            const declinedDate = new Date(borrowing.declined_date);
            daysInfo = `Declined on ${declinedDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}`;
            urgencyClass = 'text-gray-600';
        }
        
        const borrowDate = new Date(borrowing.borrow_date);
        const dueDate = borrowing.due_date && borrowing.due_date !== '0000-00-00' ? new Date(borrowing.due_date) : null;
        
        const fineSection = borrowing.fine_amount > 0
            ? `<div class="bg-red-50 border-l-4 border-red-500 p-3 rounded mb-4">
                    <p class="text-red-700 font-semibold">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        Fine Amount: ₱${parseFloat(borrowing.fine_amount).toFixed(2)}
                    </p>
                    ${borrowing.remarks ? `<p class="text-red-600 text-sm mt-1">${borrowing.remarks}</p>` : ''}
                </div>`
            : '';
        
        html += `
            <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition ${isCartBatch ? 'border-l-4 border-purple-500' : ''}">
                <div class="md:flex">
                    <!-- Book Image -->
                    <div class="md:w-48 h-64 md:h-auto bg-gray-200">
                        <img src="${bookImage}" alt="${borrowing.title}" 
                             class="w-full h-full object-cover">
                    </div>
                    
                    <!-- Book Details -->
                    <div class="flex-1 p-6">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <div class="flex items-center flex-wrap gap-2 mb-1">
                                    <h3 class="text-xl font-bold text-gray-800">${borrowing.title}${quantityBadge}</h3>
                                    ${cartBadge}
                                </div>
                                <p class="text-gray-600 mb-2">by ${borrowing.author}</p>
                                ${borrowing.isbn ? `<p class="text-sm text-gray-500">ISBN: ${borrowing.isbn}</p>` : ''}
                            </div>
                            <span class="px-4 py-2 rounded-full text-sm font-semibold border ${statusClass}">
                                ${borrowing.status.charAt(0).toUpperCase() + borrowing.status.slice(1)}
                            </span>
                        </div>
                        
                        <!-- Borrowing Information -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                            <div class="bg-gray-50 p-3 rounded-lg">
                                <p class="text-xs text-gray-600 font-semibold uppercase mb-1">Borrow Date</p>
                                <p class="text-gray-800 font-semibold">
                                    <i class="fas fa-calendar-plus text-blue-600 mr-2"></i>
                                    ${borrowDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}
                                </p>
                            </div>
                            
                            <div class="bg-gray-50 p-3 rounded-lg">
                                <p class="text-xs text-gray-600 font-semibold uppercase mb-1">Due Date</p>
                                <p class="text-gray-800 font-semibold">
                                    <i class="fas fa-calendar-times text-orange-600 mr-2"></i>
                                    ${dueDate ? dueDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : 'To be set'}
                                </p>
                            </div>
                            
                            <div class="bg-gray-50 p-3 rounded-lg">
                                <p class="text-xs text-gray-600 font-semibold uppercase mb-1">Status</p>
                                <p class="${urgencyClass}">
                                    <i class="fas fa-clock mr-2"></i>
                                    ${daysInfo}
                                </p>
                            </div>
                        </div>
                        
                        <!-- Fine Information -->
                        ${fineSection}
                        
                        <!-- Action Buttons -->
                        <div class="flex gap-3">
                            <a href="book_details.php?id=${borrowing.book_id}" 
                               class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition text-sm font-semibold">
                                <i class="fas fa-eye mr-2"></i>View Book
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        `;
    });
    
    html += '</div>';
    container.innerHTML = html;
}

// Display no borrowings message
function displayNoBorrowings(search = '', status = 'all') {
    const container = document.getElementById('borrowingsContainer');
    const hasFilters = search || status !== 'all';
    const message = hasFilters 
        ? 'No borrowings match your search criteria. Try adjusting your filters.'
        : "You haven't borrowed any books yet. Start exploring our library!";
    
    container.innerHTML = `
        <div class="bg-white rounded-lg shadow-md p-12 text-center">
            <i class="fas fa-book-open text-gray-300 text-6xl mb-4"></i>
            <h3 class="text-2xl font-bold text-gray-800 mb-2">No Borrowings Found</h3>
            <p class="text-gray-600 mb-6">${message}</p>
            <a href="books.php" class="inline-block bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition font-semibold">
                <i class="fas fa-book mr-2"></i>Browse Books
            </a>
        </div>
    `;
}

// Display pagination
function displayPagination(pagination) {
    const container = document.getElementById('paginationContainer');
    
    if (pagination.total_pages <= 1) {
        container.innerHTML = '';
        return;
    }
    
    let html = '<div class="flex justify-center items-center space-x-2">';
    
    // Previous button
    if (pagination.current_page > 1) {
        html += `<button onclick="changePage(${pagination.current_page - 1})" 
                    class="px-4 py-2 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                    <i class="fas fa-chevron-left"></i>
                </button>`;
    }
    
    // Page numbers
    const startPage = Math.max(1, pagination.current_page - 2);
    const endPage = Math.min(pagination.total_pages, pagination.current_page + 2);
    
    for (let i = startPage; i <= endPage; i++) {
        const activeClass = i === pagination.current_page 
            ? 'bg-blue-600 text-white' 
            : 'bg-white border border-gray-300 hover:bg-gray-50';
        
        html += `<button onclick="changePage(${i})" 
                    class="px-4 py-2 ${activeClass} rounded-lg transition">
                    ${i}
                </button>`;
    }
    
    // Next button
    if (pagination.current_page < pagination.total_pages) {
        html += `<button onclick="changePage(${pagination.current_page + 1})" 
                    class="px-4 py-2 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                    <i class="fas fa-chevron-right"></i>
                </button>`;
    }
    
    html += '</div>';
    html += `<p class="text-center text-sm text-gray-600 mt-4">
                Showing page ${pagination.current_page} of ${pagination.total_pages} 
                (${pagination.total_borrowings} total borrowings)
            </p>`;
    
    container.innerHTML = html;
}

// Change page
function changePage(page) {
    currentPage = page;
    loadBorrowings();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// Modal functions
function openCancelModal(borrowingId, bookId, bookTitle) {
    selectedBorrowingId = borrowingId;
    selectedBookId = bookId;
    document.getElementById('cancelBookTitle').textContent = bookTitle;
    document.getElementById('cancelModal').classList.remove('hidden');
}

function closeCancelModal() {
    document.getElementById('cancelModal').classList.add('hidden');
    selectedBorrowingId = null;
    selectedBookId = null;
}

// Confirm renewal request
document.getElementById('confirmRenewBtn').addEventListener('click', async function() {
    if (selectedBorrowingId) {
        try {
            const response = await fetch('renew_borrow.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `borrowing_id=${selectedBorrowingId}`
            });
            const data = await response.json();
            
            if (data.success) {
                document.getElementById('alertContainer').innerHTML = `
                    <div class="bg-green-50 border-l-4 border-green-500 text-green-700 p-4 rounded" role="alert">
                        <div class="flex items-center">
                            <i class="fas fa-check-circle mr-2"></i>
                            <span>${data.message}</span>
                        </div>
                    </div>
                `;
                loadBorrowings();
            } else {
                document.getElementById('alertContainer').innerHTML = `
                    <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded" role="alert">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-circle mr-2"></i>
                            <span>${data.message}</span>
                        </div>
                    </div>
                `;
            }
            closeRenewModal();
        } catch (error) {
            console.error('Error requesting renewal:', error);
            document.getElementById('alertContainer').innerHTML = `
                <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded" role="alert">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        <span>Error requesting renewal</span>
                    </div>
                </div>
            `;
            closeRenewModal();
        }
    }
});

// Confirm cancel request
document.getElementById('confirmCancelBtn').addEventListener('click', async function() {
    if (selectedBorrowingId && selectedBookId) {
        try {
            const response = await fetch(`cancel_borrow.php?borrowing_id=${selectedBorrowingId}&book_id=${selectedBookId}`);
            const data = await response.json();
            
            if (data.success) {
                document.getElementById('alertContainer').innerHTML = `
                    <div class="bg-green-50 border-l-4 border-green-500 text-green-700 p-4 rounded" role="alert">
                        <div class="flex items-center">
                            <i class="fas fa-check-circle mr-2"></i>
                            <span>${data.message}</span>
                        </div>
                    </div>
                `;
                loadBorrowings();
                loadStatistics();
            } else {
                document.getElementById('alertContainer').innerHTML = `
                    <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded" role="alert">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-circle mr-2"></i>
                            <span>${data.message}</span>
                        </div>
                    </div>
                `;
            }
            closeCancelModal();
        } catch (error) {
            console.error('Error cancelling request:', error);
            document.getElementById('alertContainer').innerHTML = `
                <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded" role="alert">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        <span>Error cancelling request</span>
                    </div>
                </div>
            `;
            closeCancelModal();
        }
    }
});

// Close modals when clicking outside
document.getElementById('renewModal').addEventListener('click', function(e) {
    if (e.target === this) closeRenewModal();
});


// Close modals with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeRenewModal();
        closeCancelModal();
    }
});

// Add this script section to my_borrowings.php after the existing JavaScript

// Check for URL filter parameter on page load
document.addEventListener('DOMContentLoaded', function() {
    // Get filter from URL parameter
    const urlParams = new URLSearchParams(window.location.search);
    const filterParam = urlParams.get('filter');
    
    if (filterParam) {
        // Map filter values to status filter options
        const filterMap = {
            'borrowed': 'borrowed',
            'overdue': 'overdue',
            'pending': 'pending',
            'returned': 'returned'
        };
        
        if (filterMap[filterParam]) {
            document.getElementById('statusFilter').value = filterMap[filterParam];
        }
    }
    
    loadStatistics();
    loadBorrowings();
});
</script>

<?php require_once '../includes/footer.php'; ?>