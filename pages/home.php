
<?php
$page_title = "Home";
// Don't require login for home page - allow guest access
session_start();
require_once '../includes/config.php';

// Get public statistics
$stats_query = "SELECT 
    COUNT(DISTINCT book_id) as total_books,
    COALESCE(SUM(available_quantity), 0) as available_books,
    (SELECT COUNT(*) FROM categories) as total_categories,
    (SELECT COUNT(*) FROM users WHERE role = 'member' AND status = 'active') as total_members
    FROM books";
$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();

// Get featured books (recently added)
$featured_query = "SELECT b.*, c.category_name 
                    FROM books b 
                    LEFT JOIN categories c ON b.category_id = c.category_id 
                    WHERE b.available_quantity > 0 
                    ORDER BY b.created_at DESC 
                    LIMIT 4";
$featured_result = $conn->query($featured_query);

// Get all categories for showcase
$categories_query = "SELECT * FROM categories ORDER BY category_name ASC LIMIT 8";
$categories_result = $conn->query($categories_query);

// Check if user is logged in
$is_logged_in = isset($_SESSION['user_id']);
if ($is_logged_in) {
    require_once '../includes/header.php';
}
?>

<?php if (!$is_logged_in): ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to Yapidz Library</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Dimmed gradient directly on background (no overlay div needed) */
        .gradient-text {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .card-hover {
            transition: all 0.3s ease;
        }

        .card-hover:hover {
            transform: translateY(-5px);
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }

        .float-animation {
            animation: float 3s ease-in-out infinite;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Navigation with Mobile Menu -->
    <nav class="bg-white shadow-lg sticky top-0 z-50" x-data="{ mobileMenuOpen: false }">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <!-- Logo -->
                <div class="flex items-center">
                    <i class="fas fa-book-reader text-blue-600 text-2xl mr-3"></i>
                    <span class="text-xl font-bold text-gray-800"><?php echo SITE_NAME; ?></span>
                </div>
                
                <!-- Desktop Navigation -->
                <div class="hidden md:flex items-center space-x-8">
                    <a href="#home" class="text-gray-700 hover:text-blue-600 transition">Home</a>
                    <a href="#about" class="text-gray-700 hover:text-blue-600 transition">About</a>
                    <a href="#features" class="text-gray-700 hover:text-blue-600 transition">Features</a>
                    <a href="#books" class="text-gray-700 hover:text-blue-600 transition">Books</a>
                    <a href="#categories" class="text-gray-700 hover:text-blue-600 transition">Categories</a>
                    <a href="#contact" class="text-gray-700 hover:text-blue-600 transition">Contact</a>
                </div>
                
                <!-- Desktop Auth Buttons -->
                <div class="hidden md:flex items-center space-x-4">
                    <a href="../auth/login.php" class="text-blue-600 hover:text-blue-700 font-semibold">Login</a>
                    <a href="../auth/register.php" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition">
                        Get Started
                    </a>
                </div>

                <!-- Mobile menu button -->
                <button @click="mobileMenuOpen = !mobileMenuOpen" class="md:hidden p-2 rounded-md text-gray-600 hover:text-blue-600 hover:bg-blue-50 transition">
                    <i class="fas fa-bars text-xl" x-show="!mobileMenuOpen"></i>
                    <i class="fas fa-times text-xl" x-show="mobileMenuOpen" style="display: none;"></i>
                </button>
            </div>
        </div>

        <!-- Mobile Navigation Sidebar -->
        <div x-show="mobileMenuOpen" 
             @click.away="mobileMenuOpen = false"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 transform -translate-y-2"
             x-transition:enter-end="opacity-100 transform translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 transform translate-y-0"
             x-transition:leave-end="opacity-0 transform -translate-y-2"
             class="md:hidden border-t border-gray-200 bg-white shadow-lg"
             style="display: none;">
            <div class="px-4 pt-4 pb-3 space-y-1">
                <!-- Guest Welcome Section -->
                <div class="pb-3 mb-3 border-b border-gray-200">
                    <div class="flex items-center">
                        <div class="bg-gradient-to-br from-blue-500 to-purple-600 w-12 h-12 rounded-full flex items-center justify-center">
                            <i class="fas fa-user text-white text-xl"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-base font-medium text-gray-800">Welcome, Guest!</p>
                            <p class="text-sm text-gray-500">Join our library community</p>
                        </div>
                    </div>
                </div>

                <!-- Navigation Links -->
                <a href="#home" @click="mobileMenuOpen = false" class="flex items-center px-3 py-3 rounded-md text-base font-medium text-gray-700 hover:text-blue-600 hover:bg-blue-50 transition">
                    <i class="fas fa-home mr-3 w-5"></i>Home
                </a>
                <a href="#about" @click="mobileMenuOpen = false" class="flex items-center px-3 py-3 rounded-md text-base font-medium text-gray-700 hover:text-blue-600 hover:bg-blue-50 transition">
                    <i class="fas fa-info-circle mr-3 w-5"></i>About
                </a>
                <a href="#features" @click="mobileMenuOpen = false" class="flex items-center px-3 py-3 rounded-md text-base font-medium text-gray-700 hover:text-blue-600 hover:bg-blue-50 transition">
                    <i class="fas fa-star mr-3 w-5"></i>Features
                </a>
                <a href="#books" @click="mobileMenuOpen = false" class="flex items-center px-3 py-3 rounded-md text-base font-medium text-gray-700 hover:text-blue-600 hover:bg-blue-50 transition">
                    <i class="fas fa-book mr-3 w-5"></i>Books
                </a>
                <a href="#categories" @click="mobileMenuOpen = false" class="flex items-center px-3 py-3 rounded-md text-base font-medium text-gray-700 hover:text-blue-600 hover:bg-blue-50 transition">
                    <i class="fas fa-th-large mr-3 w-5"></i>Categories
                </a>
                <a href="#contact" @click="mobileMenuOpen = false" class="flex items-center px-3 py-3 rounded-md text-base font-medium text-gray-700 hover:text-blue-600 hover:bg-blue-50 transition">
                    <i class="fas fa-envelope mr-3 w-5"></i>Contact
                </a>

                <!-- Divider -->
                <div class="border-t border-gray-200 my-3"></div>

                <!-- Auth Buttons -->
                <a href="../auth/login.php" class="flex items-center px-3 py-3 rounded-md text-base font-medium text-blue-600 hover:bg-blue-50 transition bg-blue-50 font-semibold">
                    <i class="fas fa-sign-in-alt mr-3 w-5"></i>Login
                </a>
                <a href="../auth/register.php" class="flex items-center px-3 py-3 rounded-md text-base font-medium text-white bg-blue-600 hover:bg-blue-700 transition font-semibold">
                    <i class="fas fa-user-plus mr-3 w-5"></i>Get Started
                </a>
            </div>
        </div>
    </nav>

    <!-- Alpine.js for mobile menu -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<?php else: ?>
    <style>
        /* Dimmed gradient directly on background (no overlay div needed) */
        .gradient-text {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .card-hover {
            transition: all 0.3s ease;
        }

        .card-hover:hover {
            transform: translateY(-5px);
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }

        .float-animation {
            animation: float 3s ease-in-out infinite;
        }
    </style>
<?php endif; ?>

<section 
    id="home"
    class="relative h-[900px] flex items-center justify-center"
    style="background: linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.3)), url('../assets/images/library.jpg') center/cover no-repeat;">
    <div class="relative z-10 text-center text-white px-4">
        <h1 class="text-5xl md:text-7xl font-bold mb-4 float-animation leading-tight drop-shadow-lg">
            Welcome to<br>
            <span class="bg-gradient-to-r from-blue-400 via-purple-500 to-pink-500 bg-clip-text text-transparent"
                style="-webkit-text-fill-color: transparent;">Yapidz</span>
            <span class="text-orange-400"> Library</span>
        </h1>

        <p class="text-xl md:text-2xl mb-8 max-w-3xl mx-auto drop-shadow-md">
            Discover thousands of books, digital resources, and community events. Your gateway to knowledge and learning.
        </p>

        <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <a href="../books/books.php" 
                class="bg-purple-600 text-white px-8 py-4 rounded-full text-lg font-semibold hover:bg-purple-700 transition shadow-lg">
                <i class="fas fa-search mr-2"></i>Browse Books
            </a>

            <?php if (!$is_logged_in): ?>
            <a href="#about" 
                class="bg-gray-800 bg-opacity-40 backdrop-blur-sm text-white px-8 py-4 rounded-full text-lg font-semibold hover:bg-opacity-60 transition shadow-lg border border-white border-opacity-30">
                Get Library Card <i class="fas fa-arrow-right ml-2"></i>
            </a>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="py-16 bg-gradient-to-r from-blue-600 to-indigo-600">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-8 text-center">
            <div class="card-hover">
                <div class="text-5xl font-bold text-white mb-2"><?php echo number_format($stats['total_books']); ?>+</div>
                <div class="text-white text-lg">Books Available</div>
            </div>
            <div class="card-hover">
                <div class="text-5xl font-bold text-white mb-2"><?php echo number_format($stats['available_books']); ?>+</div>
                <div class="text-white text-lg">Ready to Borrow</div>
            </div>
            <div class="card-hover">
                <div class="text-5xl font-bold text-white mb-2"><?php echo $stats['total_categories']; ?>+</div>
                <div class="text-white text-lg">Categories</div>
            </div>
            <div class="card-hover">
                <div class="text-5xl font-bold text-white mb-2"><?php echo number_format($stats['total_members']); ?>+</div>
                <div class="text-white text-lg">Active Members</div>
            </div>
        </div>
    </div>
</section>

<section id="about" class="py-20 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid md:grid-cols-2 gap-12 items-center">
            <div>
                <h2 class="text-4xl font-bold text-gray-900 mb-6">About <span class="gradient-text">Yapidz Library</span></h2>
                <p class="text-gray-600 text-lg mb-6">
                    Welcome to Yapidz Library, your community's hub for knowledge, learning, and discovery. We are dedicated to providing access to a vast collection of books, digital resources, and educational materials for everyone.
                </p>
                <p class="text-gray-600 text-lg mb-6">
                    Our mission is to foster a love for reading, support lifelong learning, and create a welcoming space where knowledge meets community. Whether you're a student, researcher, or casual reader, we have something for everyone.
                </p>
                <div class="grid grid-cols-2 gap-4 mt-8">
                    <div class="flex items-start">
                        <i class="fas fa-check-circle text-blue-600 text-2xl mr-3 mt-1"></i>
                        <div>
                            <h4 class="font-semibold text-gray-900">Free Membership</h4>
                            <p class="text-gray-600 text-sm">Join our community at no cost</p>
                        </div>
                    </div>
                    <div class="flex items-start">
                        <i class="fas fa-check-circle text-blue-600 text-2xl mr-3 mt-1"></i>
                        <div>
                            <h4 class="font-semibold text-gray-900">Digital Access</h4>
                            <p class="text-gray-600 text-sm">Browse and manage online</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="relative">
                <div>
                    <img src="../assets/images/interior.jpg" alt="Library Interior" class="rounded-lg shadow-xl">
                </div>
            </div>
        </div>
    </div>
</section>

<section id="features" class="py-20 bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16">
            <h2 class="text-4xl font-bold text-gray-900 mb-4">Why Choose <span class="gradient-text">Yapidz Library?</span></h2>
            <p class="text-gray-600 text-lg max-w-2xl mx-auto">
                Experience modern library services with cutting-edge features designed for your convenience
            </p>
        </div>
        <div class="grid md:grid-cols-3 gap-8">
            <div class="bg-white p-8 rounded-xl shadow-lg card-hover">
                <div class="bg-blue-100 w-16 h-16 rounded-full flex items-center justify-center mb-6">
                    <i class="fas fa-search text-blue-600 text-2xl"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-4">Easy Search</h3>
                <p class="text-gray-600">
                    Find exactly what you need with our advanced search system. Filter by title, author, category, or ISBN.
                </p>
            </div>
            <div class="bg-white p-8 rounded-xl shadow-lg card-hover">
                <div class="bg-indigo-100 w-16 h-16 rounded-full flex items-center justify-center mb-6">
                    <i class="fas fa-clock text-indigo-600 text-2xl"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-4">24/7 Access</h3>
                <p class="text-gray-600">
                    Browse our catalog anytime, anywhere. Check availability and manage your borrowed books online.
                </p>
            </div>
            <div class="bg-white p-8 rounded-xl shadow-lg card-hover">
                <div class="bg-purple-100 w-16 h-16 rounded-full flex items-center justify-center mb-6">
                    <i class="fas fa-bell text-purple-600 text-2xl"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-4">Smart Notifications</h3>
                <p class="text-gray-600">
                    Never miss a due date! Get reminders for return dates and notifications for newly available books.
                </p>
            </div>
            <div class="bg-white p-8 rounded-xl shadow-lg card-hover">
                <div class="bg-green-100 w-16 h-16 rounded-full flex items-center justify-center mb-6">
                    <i class="fas fa-book-open text-green-600 text-2xl"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-4">Vast Collection</h3>
                <p class="text-gray-600">
                    Access thousands of books across multiple genres and categories for all ages and interests.
                </p>
            </div>
            <div class="bg-white p-8 rounded-xl shadow-lg card-hover">
                <div class="bg-yellow-100 w-16 h-16 rounded-full flex items-center justify-center mb-6">
                    <i class="fas fa-user-circle text-yellow-600 text-2xl"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-4">Personal Dashboard</h3>
                <p class="text-gray-600">
                    Track your borrowing history, manage active loans, and view your reading statistics in one place.
                </p>
            </div>
            <div class="bg-white p-8 rounded-xl shadow-lg card-hover">
                <div class="bg-red-100 w-16 h-16 rounded-full flex items-center justify-center mb-6">
                    <i class="fas fa-shield-alt text-red-600 text-2xl"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-4">Secure & Reliable</h3>
                <p class="text-gray-600">
                    Your data is safe with us. We use industry-standard security measures to protect your information.
                </p>
            </div>
        </div>
    </div>
</section>

<section id="books" class="py-20 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16">
            <h2 class="text-4xl font-bold text-gray-900 mb-4">Featured <span class="gradient-text">Books</span></h2>
            <p class="text-gray-600 text-lg">Check out our latest additions to the collection</p>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <?php if ($featured_result->num_rows > 0): ?>
                <?php while ($book = $featured_result->fetch_assoc()): ?>
                <div class="bg-white border border-gray-200 rounded-xl overflow-hidden shadow-lg card-hover">
                    <div class="bg-gradient-to-br from-blue-400 to-indigo-500 h-48 flex items-center justify-center">
                        <?php if ($book['cover_image']): ?>
                            <img src="../uploads/books/<?php echo htmlspecialchars($book['cover_image']); ?>" 
                                alt="<?php echo htmlspecialchars($book['title']); ?>" 
                                class="h-full w-full object-cover">
                        <?php else: ?>
                            <i class="fas fa-book text-white text-6xl"></i>
                        <?php endif; ?>
                    </div>
                    <div class="p-5">
                        <span class="text-xs font-semibold text-blue-600 bg-blue-100 px-3 py-1 rounded-full">
                            <?php echo htmlspecialchars($book['category_name']); ?>
                        </span>
                        <h3 class="text-lg font-bold text-gray-900 mt-4 mb-2">
                            <?php echo htmlspecialchars($book['title']); ?>
                        </h3>
                        <p class="text-gray-600 mb-3 text-sm">by <?php echo htmlspecialchars($book['author']); ?></p>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-500">
                                <i class="fas fa-check-circle text-green-500 mr-1"></i>
                                <?php echo $book['available_quantity']; ?> available
                            </span>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-span-2 text-center py-12">
                    <i class="fas fa-book-open text-gray-300 text-6xl mb-4"></i>
                    <p class="text-gray-500 text-lg">No books available yet</p>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="text-center mt-12">
            <a href="../books/books.php" class="bg-blue-600 text-white px-8 py-3 rounded-lg text-lg font-semibold hover:bg-blue-700 transition inline-block">
                Browse All Books <i class="fas fa-arrow-right ml-2"></i>
            </a>
        </div>
    </div>
</section>

<section id="categories" class="py-20 bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16">
            <h2 class="text-4xl font-bold text-gray-900 mb-4">Explore <span class="gradient-text">Categories</span></h2>
            <p class="text-gray-600 text-lg">Find books in your favorite genres</p>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
            <?php 
            $category_icons = [
                'Fiction' => 'fa-book',
                'Non-Fiction' => 'fa-newspaper',
                'Science & Technology' => 'fa-flask',
                'History' => 'fa-landmark',
                'Biography & Memoir' => 'fa-user',
                'Self-Help' => 'fa-heart',
                'Business & Economics' => 'fa-chart-line',
                'Education' => 'fa-graduation-cap'
            ];
            $colors = ['blue', 'indigo', 'purple', 'green', 'yellow', 'red', 'pink', 'orange'];
            $i = 0;
            while ($category = $categories_result->fetch_assoc()): 
                $color = $colors[$i % count($colors)];
                $icon = $category_icons[$category['category_name']] ?? 'fa-book';
                $i++;
            ?>
            <a href="../books/books.php?category=<?php echo $category['category_id']; ?>" class="bg-white p-6 rounded-xl shadow-lg card-hover text-center">
                <div class="bg-<?php echo $color; ?>-100 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas <?php echo $icon; ?> text-<?php echo $color; ?>-600 text-2xl"></i>
                </div>
                <h3 class="font-bold text-gray-900"><?php echo htmlspecialchars($category['category_name']); ?></h3>
            </a>
            <?php endwhile; ?>
        </div>
    </div>
</section>

<section id="contact" class="py-20 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16">
            <h2 class="text-4xl font-bold text-gray-900 mb-4">Get in <span class="gradient-text">Touch</span></h2>
            <p class="text-gray-600 text-lg">Visit us or reach out via phone and email.</p>
        </div>
        <div class="grid md:grid-cols-2 gap-12 items-stretch"> 
            
            <div class="flex flex-col h-full"> 
                <h3 class="text-2xl font-bold text-gray-900 mb-6">Our Details</h3>
                <div class="space-y-6 flex-grow"> 
                    <div class="flex items-start">
                        <i class="fas fa-map-marker-alt text-2xl text-blue-600 mr-4 mt-1"></i>
                        <div>
                            <p class="font-semibold text-gray-900">Address</p>
                            <p class="text-gray-600">123 Yapidz Avenue, Knowledge District, Yapidz City, 1001</p>
                        </div>
                    </div>
                    <div class="flex items-start">
                        <i class="fas fa-phone text-2xl text-blue-600 mr-4 mt-1"></i>
                        <div>
                            <p class="font-semibold text-gray-900">Phone</p>
                            <p class="text-gray-600">+63 123 456 7890 (Main Line)</p>
                        </div>
                    </div>
                    <div class="flex items-start">
                        <i class="fas fa-envelope text-2xl text-blue-600 mr-4 mt-1"></i>
                        <div>
                            <p class="font-semibold text-gray-900">Email</p>
                            <p class="text-gray-600">info@yapidzlibrary.com</p>
                        </div>
                    </div>
                    <div class="flex items-start">
                        <i class="fas fa-clock text-2xl text-blue-600 mr-4 mt-1"></i>
                        <div>
                            <p class="font-semibold text-gray-900">Hours</p>
                            <p class="text-gray-600">Mon - Fri: 9:00 AM - 7:00 PM<br>Sat: 10:00 AM - 5:00 PM</p>
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <h3 class="text-2xl font-bold text-gray-900 mb-6">Where to Find Us</h3>
                <div class="rounded-xl overflow-hidden shadow-xl">
                    <iframe 
                        src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d5157.301553261356!2d122.05879767604733!3d6.9135941930859435!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x325041dd7a24816f%3A0x51af215fb64cc81a!2sWestern%20Mindanao%20State%20University!5e1!3m2!1sen!2sph!4v1760757856822!5m2!1sen!2sph" 
                        width="100%" 
                        height="450" 
                        style="border:0;" 
                        allowfullscreen="" 
                        loading="lazy" 
                        referrerpolicy="no-referrer-when-downgrade">
                    </iframe>
                </div>
                <p class="text-sm text-gray-500 mt-2 text-center hidden md:block">
                    <span class="opacity-0">Filler text to match the bottom alignment if required.</span>
                </p>
            </div>
        </div>
    </div>
</section>

    
<?php if (!$is_logged_in): ?>
    <section class="py-20 bg-gradient-to-r from-blue-600 to-indigo-600">
        <div class="max-w-4xl mx-auto text-center px-4">
            <h2 class="text-4xl font-bold text-white mb-6">Ready to Start Your Reading Journey?</h2>
            <p class="text-xl text-white mb-8">
                Join thousands of readers and get access to our amazing collection today!
            </p>
            <a href="../auth/register.php" class="bg-white text-blue-600 px-8 py-4 rounded-lg text-lg font-semibold hover:bg-gray-100 transition inline-block shadow-lg">
                <i class="fas fa-user-plus mr-2"></i>Create Free Account
            </a>
        </div>
    </section>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>

    <script>
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    </script>
</body>
</html>
