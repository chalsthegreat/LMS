<?php
$page_title = "Add New Book";
require_once '../includes/config.php';
require_once '../includes/header.php';

// Check if user has permission
if ($role !== 'admin' && $role !== 'librarian') {
    setMessage('error', 'You do not have permission to access this page');
    redirect('books.php');
}

// Get categories
$categories_sql = "SELECT * FROM categories ORDER BY category_name ASC";
$categories_result = $conn->query($categories_sql);

// Get alert message if any
$message = getMessage();
?>

<!-- Alert Messages -->
<?php if ($message): ?>
    <div class="mb-6">
        <?php if ($message['type'] === 'error'): ?>
            <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded" role="alert">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    <span><?php echo $message['message']; ?></span>
                </div>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- Page Header -->
<div class="mb-8">
    <div class="flex items-center">
        <a href="books.php" class="text-blue-600 hover:text-blue-800 mr-4">
            <i class="fas fa-arrow-left text-2xl"></i>
        </a>
        <div>
            <h1 class="text-3xl font-bold text-gray-800">Add New Book</h1>
            <p class="text-gray-600 mt-2">Fill in the book information below</p>
        </div>
    </div>
</div>

<!-- Add Book Form -->
<div class="bg-white rounded-lg shadow-md p-8">
    <form action="add_book_api.php" method="POST" enctype="multipart/form-data">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Left Column - Book Cover -->
            <div class="lg:col-span-1">
                <div class="sticky top-24">
                    <label class="block text-gray-700 text-sm font-semibold mb-2">
                        <i class="fas fa-image mr-2 text-gray-400"></i>Book Cover
                    </label>
                    <div class="border-2 border-dashed border-gray-300 rounded-lg p-4 text-center">
                        <img id="coverPreview" src="../assets/images/default-book.png" alt="Book Cover Preview" 
                             class="w-full h-64 object-cover rounded-lg mb-4">
                        <input type="file" id="coverInput" name="cover_image" accept="image/*" class="hidden" onchange="previewCover(this)">
                        <button type="button" onclick="document.getElementById('coverInput').click()" 
                                class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
                            <i class="fas fa-upload mr-2"></i>Upload Cover
                        </button>
                        <p class="text-xs text-gray-500 mt-2">JPG, PNG (Max 5MB)</p>
                    </div>
                </div>
            </div>

            <!-- Right Column - Book Information -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Basic Information -->
                <div class="border-b border-gray-200 pb-6">
                    <h2 class="text-xl font-bold text-gray-800 mb-4">
                        <i class="fas fa-info-circle text-blue-600 mr-2"></i>Basic Information
                    </h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-gray-700 text-sm font-semibold mb-2">
                                <i class="fas fa-book mr-2 text-gray-400"></i>Book Title *
                            </label>
                            <input type="text" name="title" required
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                   placeholder="Enter book title">
                        </div>

                        <div>
                            <label class="block text-gray-700 text-sm font-semibold mb-2">
                                <i class="fas fa-user-edit mr-2 text-gray-400"></i>Author *
                            </label>
                            <input type="text" name="author" required
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                   placeholder="Enter author name">
                        </div>

                        <div>
                            <label class="block text-gray-700 text-sm font-semibold mb-2">
                                <i class="fas fa-barcode mr-2 text-gray-400"></i>ISBN
                            </label>
                            <input type="text" name="isbn"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                   placeholder="Enter ISBN number">
                        </div>

                        <div>
                            <label class="block text-gray-700 text-sm font-semibold mb-2">
                                <i class="fas fa-building mr-2 text-gray-400"></i>Publisher
                            </label>
                            <input type="text" name="publisher"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                   placeholder="Enter publisher name">
                        </div>

                        <div>
                            <label class="block text-gray-700 text-sm font-semibold mb-2">
                                <i class="fas fa-calendar mr-2 text-gray-400"></i>Publish Year
                            </label>
                            <input type="number" name="publish_year" min="1000" max="<?php echo date('Y'); ?>"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                   placeholder="<?php echo date('Y'); ?>">
                        </div>

                        <div>
                            <label class="block text-gray-700 text-sm font-semibold mb-2">
                                <i class="fas fa-tag mr-2 text-gray-400"></i>Category
                            </label>
                            <select name="category_id"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">Select Category</option>
                                <?php while ($cat = $categories_result->fetch_assoc()): ?>
                                    <option value="<?php echo $cat['category_id']; ?>"><?php echo $cat['category_name']; ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-gray-700 text-sm font-semibold mb-2">
                                <i class="fas fa-map-marker-alt mr-2 text-gray-400"></i>Shelf Location
                            </label>
                            <input type="text" name="shelf_location"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                   placeholder="e.g., A-12">
                        </div>
                    </div>
                </div>

                <!-- Quantity Information -->
                <div class="border-b border-gray-200 pb-6">
                    <h2 class="text-xl font-bold text-gray-800 mb-4">
                        <i class="fas fa-layer-group text-green-600 mr-2"></i>Quantity Information
                    </h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-gray-700 text-sm font-semibold mb-2">
                                <i class="fas fa-boxes mr-2 text-gray-400"></i>Total Quantity *
                            </label>
                            <input type="number" name="quantity" min="1" value="1" required
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                   placeholder="Enter total copies">
                            <p class="text-xs text-gray-500 mt-1">Total number of copies</p>
                        </div>

                        <div>
                            <label class="block text-gray-700 text-sm font-semibold mb-2">
                                <i class="fas fa-check-circle mr-2 text-gray-400"></i>Available Quantity *
                            </label>
                            <input type="number" name="available_quantity" min="0" value="1" required
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                   placeholder="Enter available copies">
                            <p class="text-xs text-gray-500 mt-1">Number of copies available for borrowing</p>
                        </div>
                    </div>
                </div>

                <!-- Description -->
                <div>
                    <h2 class="text-xl font-bold text-gray-800 mb-4">
                        <i class="fas fa-align-left text-purple-600 mr-2"></i>Description
                    </h2>
                    <textarea name="description" rows="5"
                              class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                              placeholder="Enter book description, synopsis, or summary..."></textarea>
                </div>

                <!-- Action Buttons -->
                <div class="flex gap-4 pt-6">
                    <button type="submit" 
                            class="bg-blue-600 text-white px-8 py-3 rounded-lg hover:bg-blue-700 transition font-semibold">
                        <i class="fas fa-save mr-2"></i>Add Book
                    </button>
                    <a href="books.php" 
                       class="bg-gray-200 text-gray-700 px-8 py-3 rounded-lg hover:bg-gray-300 transition font-semibold">
                        <i class="fas fa-times mr-2"></i>Cancel
                    </a>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
function previewCover(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            document.getElementById('coverPreview').src = e.target.result;
        }
        
        reader.readAsDataURL(input.files[0]);
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>