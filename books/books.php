<?php
$page_title = "Books";
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
            <h1 class="text-3xl font-bold text-gray-800">Books Library</h1>
            <p class="text-gray-600 mt-2">Browse and manage book collection</p>
        </div>
        <?php if ($role === 'admin' || $role === 'librarian'): ?>
        <a href="add_book.php" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition font-semibold">
            <i class="fas fa-plus mr-2"></i>Add New Book
        </a>
        <?php endif; ?>
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

        <!-- Category Filter -->
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">
                <i class="fas fa-filter mr-2"></i>Category
            </label>
            <select id="categoryFilter" name="category" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="0">All Categories</option>
            </select>
        </div>

        <!-- Sort Filter -->
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">
                <i class="fas fa-sort mr-2"></i>Sort By
            </label>
            <select id="sortFilter" name="sort" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="title_asc">Title (A-Z)</option>
                <option value="title_desc">Title (Z-A)</option>
                <option value="author_asc">Author (A-Z)</option>
                <option value="author_desc">Author (Z-A)</option>
                <option value="newest">Newest First</option>
                <option value="oldest">Oldest First</option>
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
    <p class="text-gray-600 mt-4">Loading books...</p>
</div>

<!-- Books Container -->
<div id="booksContainer">
    <!-- Books will be loaded here -->
</div>

<!-- Pagination Container -->
<div id="paginationContainer" class="mt-8">
    <!-- Pagination will be loaded here -->
</div>

<script>
// Global variables
let currentPage = 1;
const userRole = '<?php echo $role; ?>';

// Load books on page load
document.addEventListener('DOMContentLoaded', function() {
    loadCategories();
    loadStatistics();
    loadBooks();
});

// Search form submit
document.getElementById('searchForm').addEventListener('submit', function(e) {
    e.preventDefault();
    currentPage = 1;
    loadBooks();
});

// Reset button
document.getElementById('resetBtn').addEventListener('click', function() {
    document.getElementById('searchInput').value = '';
    document.getElementById('categoryFilter').value = '0';
    document.getElementById('sortFilter').value = 'title_asc';
    currentPage = 1;
    loadBooks();
});

// Filter change handlers
document.getElementById('categoryFilter').addEventListener('change', function() {
    currentPage = 1;
    loadBooks();
});

document.getElementById('sortFilter').addEventListener('change', function() {
    currentPage = 1;
    loadBooks();
});

// Load categories
async function loadCategories() {
    try {
        const response = await fetch('get_books_data.php?action=categories');
        const data = await response.json();
        
        if (data.success) {
            const select = document.getElementById('categoryFilter');
            data.categories.forEach(cat => {
                const option = document.createElement('option');
                option.value = cat.category_id;
                option.textContent = cat.category_name;
                select.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Error loading categories:', error);
    }
}

// Load statistics
async function loadStatistics() {
    try {
        const response = await fetch('get_books_data.php?action=statistics');
        const data = await response.json();
        
        if (data.success) {
            const stats = data.statistics;
            let html = '';
            
            // Check if admin/librarian stats or member stats
            if (stats.hasOwnProperty('total_books')) {
                // Admin/Librarian view - show library statistics
                html = `
                    <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-blue-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 text-sm font-semibold uppercase">Total Books</p>
                                <p class="text-3xl font-bold text-gray-800 mt-2">${stats.total_books}</p>
                            </div>
                            <div class="bg-blue-100 rounded-full p-4">
                                <i class="fas fa-book text-blue-600 text-2xl"></i>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-green-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 text-sm font-semibold uppercase">Total Copies</p>
                                <p class="text-3xl font-bold text-gray-800 mt-2">${stats.total_copies}</p>
                            </div>
                            <div class="bg-green-100 rounded-full p-4">
                                <i class="fas fa-layer-group text-green-600 text-2xl"></i>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-yellow-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 text-sm font-semibold uppercase">Available</p>
                                <p class="text-3xl font-bold text-gray-800 mt-2">${stats.available_copies}</p>
                            </div>
                            <div class="bg-yellow-100 rounded-full p-4">
                                <i class="fas fa-check-circle text-yellow-600 text-2xl"></i>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-red-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 text-sm font-semibold uppercase">Borrowed</p>
                                <p class="text-3xl font-bold text-gray-800 mt-2">${stats.borrowed_copies}</p>
                            </div>
                            <div class="bg-red-100 rounded-full p-4">
                                <i class="fas fa-book-reader text-red-600 text-2xl"></i>
                            </div>
                        </div>
                    </div>
                `;
            } else {
                // Member view - show personal borrowing statistics
                html = `
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
                                <p class="text-gray-500 text-sm font-semibold uppercase">Total Returned</p>
                                <p class="text-3xl font-bold text-gray-800 mt-2">${stats.total_returned}</p>
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
                                <p class="text-3xl font-bold text-gray-800 mt-2">₱${parseFloat(stats.total_fines).toFixed(2)}</p>
                            </div>
                            <div class="bg-yellow-100 rounded-full p-4">
                                <i class="fas fa-coins text-yellow-600 text-2xl"></i>
                            </div>
                        </div>
                    </div>
                `;
            }
            
            document.getElementById('statsContainer').innerHTML = html;
        }
    } catch (error) {
        console.error('Error loading statistics:', error);
    }
}

// Load books
async function loadBooks() {
    const search = document.getElementById('searchInput').value;
    const category = document.getElementById('categoryFilter').value;
    const sort = document.getElementById('sortFilter').value;
    
    // Show loading spinner
    document.getElementById('loadingSpinner').classList.remove('hidden');
    document.getElementById('booksContainer').innerHTML = '';
    document.getElementById('paginationContainer').innerHTML = '';
    
    try {
        const params = new URLSearchParams({
            action: 'books',
            page: currentPage,
            search: search,
            category: category,
            sort: sort
        });
        
        const response = await fetch(`get_books_data.php?${params}`);
        const data = await response.json();
        
        // Hide loading spinner
        document.getElementById('loadingSpinner').classList.add('hidden');
        
        if (data.success && data.books.length > 0) {
            displayBooks(data.books);
            displayPagination(data.pagination);
        } else {
            displayNoBooksMessage(search, category);
        }
    } catch (error) {
        console.error('Error loading books:', error);
        document.getElementById('loadingSpinner').classList.add('hidden');
        document.getElementById('booksContainer').innerHTML = `
            <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded">
                <p><i class="fas fa-exclamation-circle mr-2"></i>Error loading books. Please try again.</p>
            </div>
        `;
    }
}

// Display books
function displayBooks(books) {
    const container = document.getElementById('booksContainer');
    
    let html = '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">';
    
    books.forEach(book => {
        // Handle cover image - use default if null or empty
        const bookImage = (book.cover_image && book.cover_image !== '' && book.cover_image !== 'null')
            ? `../uploads/books/${book.cover_image}` 
            : '../assets/images/default-book.png';
        
        const isAvailable = book.available_quantity > 0;
        const availabilityBadge = isAvailable 
            ? '<div class="absolute top-2 right-2 bg-green-500 text-white px-3 py-1 rounded-full text-xs font-semibold">Available</div>'
            : '<div class="absolute top-2 right-2 bg-red-500 text-white px-3 py-1 rounded-full text-xs font-semibold">Not Available</div>';
        const categoryBadge = book.category_name 
            ? `<div class="absolute top-2 left-2 bg-blue-500 text-white px-3 py-1 rounded-full text-xs font-semibold">${book.category_name}</div>`
            : '';
        
        const editButton = (userRole === 'admin' || userRole === 'librarian')
            ? `<a href="edit_book.php?id=${book.book_id}" 
                   class="flex-1 bg-green-600 text-white text-center py-2 rounded-lg hover:bg-green-700 transition text-sm font-semibold">
                    <i class="fas fa-edit mr-1"></i>Edit
                </a>`
            : '';
        
        html += `
            <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-xl transition">
                <div class="relative h-64 bg-gray-200">
                    <img src="${bookImage}" alt="${book.title}" class="w-full h-full object-cover" onerror="this.src='../assets/images/default-book.png'">
                    ${availabilityBadge}
                    ${categoryBadge}
                </div>
                <div class="p-4">
                    <h3 class="font-bold text-lg text-gray-800 mb-2 line-clamp-2 h-14">${book.title}</h3>
                    <p class="text-sm text-gray-600 mb-1">
                        <i class="fas fa-user text-gray-400 mr-1"></i>${book.author}
                    </p>
                    ${book.isbn ? `
                        <p class="text-xs text-gray-500 mb-3">
                            <i class="fas fa-barcode text-gray-400 mr-1"></i>ISBN: ${book.isbn}
                        </p>
                    ` : '<div class="mb-3"></div>'}
                    <div class="flex justify-between items-center mb-4 text-sm">
                        <span class="text-gray-600">
                            <i class="fas fa-layer-group text-gray-400 mr-1"></i>
                            ${book.quantity} copies
                        </span>
                        <span class="${isAvailable ? 'text-green-600' : 'text-red-600'} font-semibold">
                            ${book.available_quantity} available
                        </span>
                    </div>
                    <div class="flex gap-2">
                        <a href="book_details.php?id=${book.book_id}" 
                           class="flex-1 bg-blue-600 text-white text-center py-2 rounded-lg hover:bg-blue-700 transition text-sm font-semibold">
                            <i class="fas fa-eye mr-1"></i>View
                        </a>
                        ${editButton}
                    </div>
                </div>
            </div>
        `;
    });
    
    html += '</div>';
    container.innerHTML = html;
}

// Display no books message
function displayNoBooksMessage(search = '', category = 0) {
    const container = document.getElementById('booksContainer');
    const hasFilters = search || category > 0;
    const message = hasFilters 
        ? 'No books match your search criteria. Try adjusting your filters.'
        : 'There are no books in the library yet.';
    
    const addButton = (userRole === 'admin' || userRole === 'librarian')
        ? `<a href="add_book.php" class="inline-block bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition font-semibold mt-4">
                <i class="fas fa-plus mr-2"></i>Add Your First Book
            </a>`
        : '';
    
    container.innerHTML = `
        <div class="bg-white rounded-lg shadow-md p-12 text-center">
            <i class="fas fa-book-open text-gray-300 text-6xl mb-4"></i>
            <h3 class="text-2xl font-bold text-gray-800 mb-2">No Books Found</h3>
            <p class="text-gray-600 mb-6">${message}</p>
            ${addButton}
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
                (${pagination.total_books} total books)
            </p>`;
    
    container.innerHTML = html;
}

// Change page
function changePage(page) {
    currentPage = page;
    loadBooks();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
</script>

<?php require_once '../includes/footer.php'; ?>