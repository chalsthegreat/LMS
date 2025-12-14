<?php
$page_title = "Transactions";
require_once '../includes/config.php';
require_once '../includes/header.php';

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
            <h1 class="text-3xl font-bold text-gray-800">Transactions</h1>
            <p class="text-gray-600 mt-2">View all borrowing and returning transactions</p>
        </div>
    </div>
</div>

<!-- Search and Filter Section -->
<div class="bg-white rounded-lg shadow-md p-6 mb-8">
    <form id="searchForm" class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <!-- Search Input -->
        <div class="md:col-span-2">
            <label class="block text-sm font-semibold text-gray-700 mb-2">
                <i class="fas fa-search mr-2"></i>Search Transactions
            </label>
            <input type="text" id="searchInput" name="search" 
                   placeholder="Search by user, book title, or ISBN..."
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>

        <!-- Status Filter -->
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">
                <i class="fas fa-filter mr-2"></i>Status
            </label>
        <select id="statusFilter" name="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">All Statuses</option>
            <option value="pending">Pending</option>
            <option value="borrowed">Borrowed</option>
            <option value="overdue">Overdue</option>
            <option value="returned">Returned</option>
            <option value="declined">Declined</option>
        </select>
        </div>

        <!-- Sort Filter -->
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">
                <i class="fas fa-sort mr-2"></i>Sort By
            </label>
            <select id="sortFilter" name="sort" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="borrow_date_desc">Latest Borrow</option>
                <option value="borrow_date_asc">Oldest Borrow</option>
                <option value="due_date_desc">Due Date (Latest)</option>
                <option value="due_date_asc">Due Date (Oldest)</option>
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
    <p class="text-gray-600 mt-4">Loading transactions...</p>
</div>

<!-- Transactions Container -->
<div id="transactionsContainer">
    <!-- Transactions will be loaded here -->
</div>

<!-- Pagination Container -->
<div id="paginationContainer" class="mt-8">
    <!-- Pagination will be loaded here -->
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
let currentPage = 1;

// Load transactions on page load
document.addEventListener('DOMContentLoaded', function() {
    loadTransactions();
});

// Search form submit
document.getElementById('searchForm').addEventListener('submit', function(e) {
    e.preventDefault();
    currentPage = 1;
    loadTransactions();
});

// Reset button
document.getElementById('resetBtn').addEventListener('click', function() {
    document.getElementById('searchInput').value = '';
    document.getElementById('statusFilter').value = '';
    document.getElementById('sortFilter').value = 'borrow_date_desc';
    currentPage = 1;
    loadTransactions();
});

// Filter change handlers
document.getElementById('statusFilter').addEventListener('change', function() {
    currentPage = 1;
    loadTransactions();
});

document.getElementById('sortFilter').addEventListener('change', function() {
    currentPage = 1;
    loadTransactions();
});

// Load transactions
async function loadTransactions() {
    const search = document.getElementById('searchInput').value;
    const status = document.getElementById('statusFilter').value;
    const sort = document.getElementById('sortFilter').value;
    
    // Show loading spinner
    document.getElementById('loadingSpinner').classList.remove('hidden');
    document.getElementById('transactionsContainer').innerHTML = '';
    document.getElementById('paginationContainer').innerHTML = '';
    
    try {
        const params = new URLSearchParams({
            action: 'transactions',
            page: currentPage,
            search: search,
            status: status,
            sort: sort
        });
        
        const response = await fetch(`get_transactions_data.php?${params}`);
        if (!response.ok) {
            throw new Error(`HTTP error! Status: ${response.status}`);
        }
        const data = await response.json();
        
        // Hide loading spinner
        document.getElementById('loadingSpinner').classList.add('hidden');
        
        if (data.success && data.transactions.length > 0) {
            displayTransactions(data.transactions);
            displayPagination(data.pagination);
        } else {
            displayNoTransactionsMessage(search, status);
        }
    } catch (error) {
        console.error('Error loading transactions:', error.message);
        document.getElementById('loadingSpinner').classList.add('hidden');
        document.getElementById('transactionsContainer').innerHTML = `
            <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded">
                <p><i class="fas fa-exclamation-circle mr-2"></i>Error loading transactions. Please try again. (Details: ${error.message})</p>
            </div>
        `;
    }
}

// Display transactions
// Revised Display transactions function
function displayTransactions(transactions) {
    const container = document.getElementById('transactionsContainer');
    
    let html = `
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Book Title</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Author</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Borrower</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Borrow Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Due Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Return Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
    `;
    
    transactions.forEach(transaction => {
    const statusClass = transaction.status === 'pending' ? 'bg-blue-100 text-blue-800' :
                        transaction.status === 'borrowed' ? 'bg-yellow-100 text-yellow-800' :
                        transaction.status === 'overdue' ? 'bg-red-100 text-red-800' :
                        transaction.status === 'declined' ? 'bg-gray-100 text-gray-800' :
                        'bg-green-100 text-green-800';
        const statusText = transaction.status.charAt(0).toUpperCase() + transaction.status.slice(1);
        
        let returnButtonHtml = '';
        
        // --- START REVISED LOGIC FOR RETURN BUTTON ---
        if (transaction.status === 'returned') {
            // Case 1: Status is 'returned'. Show a checked, read-only icon.
            returnButtonHtml = `
                <span title="Returned" class="text-green-600 cursor-default">
                    <i class="fas fa-check-square text-lg"></i>
                </span>
            `;
        } else if (transaction.status === 'borrowed' || transaction.status === 'overdue') {
            // Case 2: Status is 'borrowed' or 'overdue'. Show a clickable, unchecked icon.
            returnButtonHtml = `
                <button onclick="markAsReturned(${transaction.borrowing_id})" 
                        class="text-blue-600 hover:text-blue-800 transition" 
                        title="Mark as Returned (Click to Check)">
                    <i class="far fa-square text-lg"></i> </button>
            `;
        }
        // --- END REVISED LOGIC FOR RETURN BUTTON ---

        // Note: The approve/reject buttons for 'pending' requests remain unchanged
        const pendingActions = transaction.status === 'pending' ? `
            <button onclick="approveRequest(${transaction.borrowing_id})" 
                    class="text-green-600 hover:text-green-800 transition" 
                    title="Approve">
                <i class="fas fa-check text-lg"></i>
            </button>
            <button onclick="rejectRequest(${transaction.borrowing_id})" 
                    class="text-red-600 hover:text-red-800 transition" 
                    title="Reject">
                <i class="fas fa-times text-lg"></i>
            </button>
        ` : '';

        html += `
            <tr class="hover:bg-gray-50 transition">
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm font-medium text-gray-900">${transaction.title}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm text-gray-900">${transaction.author}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm text-gray-900">${transaction.full_name || 'Unknown'}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm text-gray-900">${new Date(transaction.borrow_date).toLocaleDateString()}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm text-gray-900">${transaction.due_date && transaction.due_date !== '0000-00-00' ? new Date(transaction.due_date).toLocaleDateString() : 'To be set'}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm text-gray-900">${transaction.return_date ? new Date(transaction.return_date).toLocaleDateString() : '-'}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full ${statusClass}">
                        ${statusText}
                    </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-center">
                    <div class="flex items-center justify-center gap-2">
                        ${pendingActions}
                        ${returnButtonHtml}
                        <a href="transaction_details.php?id=${transaction.borrowing_id}" 
                           class="text-gray-600 hover:text-gray-800 transition" 
                           title="View Details">
                            <i class="fas fa-eye text-lg"></i>
                        </a>
                    </div>
                </td>
            </tr>
        `;
    });
    
    html += `
                    </tbody>
                </table>
            </div>
        </div>
    `;
    
    container.innerHTML = html;
}

// Display no transactions message
function displayNoTransactionsMessage(search = '', status = '') {
    const container = document.getElementById('transactionsContainer');
    const hasFilters = search || status;
    const message = hasFilters 
        ? 'No transactions match your search criteria. Try adjusting your filters.'
        : 'There are no transactions recorded yet.';
    
    container.innerHTML = `
        <div class="bg-white rounded-lg shadow-md p-12 text-center">
            <i class="fas fa-exchange-alt text-gray-300 text-6xl mb-4"></i>
            <h3 class="text-2xl font-bold text-gray-800 mb-2">No Transactions Found</h3>
            <p class="text-gray-600 mb-6">${message}</p>
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
                (${pagination.total_transactions} total transactions)
            </p>`;
    
    container.innerHTML = html;
}

// Change page
function changePage(page) {
    currentPage = page;
    loadTransactions();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// Show date selection modal before approving
// Show date selection modal before approving
function showDateModal(borrowingId, borrowDate) {
    const borrow = new Date(borrowDate);
    const today = new Date();
    
    // Set minimum due date to either borrow date or today, whichever is later
    const minDate = borrow > today ? borrow : today;
    const minDateStr = minDate.toISOString().split('T')[0];
    
    // Default due date: 14 days from borrow date
    const defaultDue = new Date(borrow);
    defaultDue.setDate(defaultDue.getDate() + 14);
    const defaultDueDate = defaultDue.toISOString().split('T')[0];
    
    const modal = document.getElementById('confirmModal');
    const modalTitle = document.getElementById('modalTitle');
    const modalMessage = document.getElementById('modalMessage');
    const modalIcon = document.getElementById('modalIcon');
    const confirmBtn = document.getElementById('modalConfirmBtn');
    const cancelBtn = document.getElementById('modalCancelBtn');
    
    // Set modal content
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
    
    const handleConfirm = () => {
        const dueDate = document.getElementById('dueDate').value;
        
        if (!dueDate) {
            showAlert('error', 'Please select a due date');
            return;
        }
        
        if (new Date(dueDate) <= new Date(borrowDate)) {
            showAlert('error', 'Due date must be after borrow date');
            return;
        }
        
        approveRequestWithDates(borrowingId, dueDate);
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

// Modal functionality
function showConfirmModal(title, message, confirmText, confirmClass, onConfirm) {
    const modal = document.getElementById('confirmModal');
    const modalTitle = document.getElementById('modalTitle');
    const modalMessage = document.getElementById('modalMessage');
    const modalIcon = document.getElementById('modalIcon');
    const confirmBtn = document.getElementById('modalConfirmBtn');
    const cancelBtn = document.getElementById('modalCancelBtn');
    
    // Set modal content
    modalTitle.textContent = title;
    modalMessage.textContent = message;
    confirmBtn.textContent = confirmText;
    confirmBtn.className = `px-4 py-2 ${confirmClass} text-white rounded-lg hover:opacity-90 transition font-semibold`;
    
    // Set icon color
    if (confirmClass.includes('green')) {
        modalIcon.className = 'w-12 h-12 rounded-full bg-green-100 flex items-center justify-center mr-4';
        modalIcon.querySelector('i').className = 'fas fa-check-circle text-2xl text-green-600';
    } else {
        modalIcon.className = 'w-12 h-12 rounded-full bg-red-100 flex items-center justify-center mr-4';
        modalIcon.querySelector('i').className = 'fas fa-exclamation-circle text-2xl text-red-600';
    }
    
    // Show modal
    modal.classList.remove('hidden');
    
    // Handle confirm
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
    
    // Close on outside click
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            closeModal();
        }
    });
}

// Approve borrow request
function approveRequest(borrowingId) {
    // First, fetch the borrowing record to get the borrow_date
    fetch(`get_borrowing_details.php?id=${borrowingId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showDateModal(borrowingId, data.borrow_date);
            } else {
                showAlert('error', 'Failed to load borrowing details');
            }
        })
        .catch(error => {
            showAlert('error', 'Error loading borrowing details');
        });
}

// Updated function - only sends due_date now
async function approveRequestWithDates(borrowingId, dueDate) {
    try {
        const response = await fetch('approve_borrow.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `borrowing_id=${borrowingId}&due_date=${dueDate}`
        });
        const data = await response.json();
        
        if (data.success) {
            showAlert('success', data.message);
            loadTransactions();
        } else {
            showAlert('error', data.message);
        }
    } catch (error) {
        showAlert('error', 'Failed to approve request');
    }
}

// Reject borrow request
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
                    showAlert('success', data.message);
                    loadTransactions();
                } else {
                    showAlert('error', data.message);
                }
            } catch (error) {
                showAlert('error', 'Failed to reject request');
            }
        }
    );
}

// Show alert message
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

// Mark as Returned
function markAsReturned(borrowingId) {
    // We use the existing modal for confirmation
    showConfirmModal(
        'Confirm Return',
        'Are you sure you want to mark this book as returned?',
        'Mark Returned',
        'bg-blue-600', // Use blue for the return action
        async () => {
            try {
                // *** IMPORTANT: We call your existing 'return_book.php' file ***
                const response = await fetch('return_book.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `borrowing_id=${borrowingId}`
                });
                
                const data = await response.json();

                if (data.success) {
                    // Show success message (will also show fine if applicable)
                    showAlert('success', data.message);
                    
                    // Reload transactions: this is the dynamic update. 
                    // The row will refresh, show status 'Returned', and the button will disappear.
                    loadTransactions(); 
                } else {
                    // Show error message from the PHP script (e.g., already returned, not found)
                    showAlert('error', data.message);
                }
            } catch (error) {
                showAlert('error', 'Failed to mark as returned. Server connection error.');
                console.error('Return error:', error);
            }
        }
    );
}
</script>

<?php require_once '../includes/footer.php'; ?>