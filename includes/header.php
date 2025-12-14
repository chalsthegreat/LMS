<?php
// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../notifications/functions.php';
$unread_count = getUnreadCount($conn, $_SESSION['user_id']);

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('../auth/login.php');
}

// Get user info from session
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$full_name = $_SESSION['full_name'];
$role = $_SESSION['role'];
$photo = $_SESSION['photo'] ?? null;

// Set default photo if none exists or file doesn't exist
if ($photo && file_exists(__DIR__ . '/../uploads/users/' . $photo)) {
    $user_photo = '../uploads/users/' . $photo;
} else {
    // Use a default avatar based on the first letter of the name
    $user_photo = null; // We'll use CSS to show initials
    $user_initial = strtoupper(substr($full_name, 0, 1));
}

// Check if this is the home page
$is_home_page = (basename($_SERVER['PHP_SELF']) === 'home.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Dashboard'; ?> - <?php echo SITE_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Default avatar with initials */
        .avatar-initial {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 16px;
            border-radius: 9999px;
            border: 2px solid #e5e7eb;
        }
        .avatar-initial-small {
            width: 24px;
            height: 24px;
            font-size: 12px;
        }
        .avatar-initial-large {
            width: 48px;
            height: 48px;
            font-size: 20px;
        }
    </style>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#3B82F6',
                        secondary: '#6366F1',
                    }
                }
            }
        }
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('nav a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
                    // Only apply smooth scroll if the link is a local anchor link
                    if (this.hash.length > 0 && this.pathname === location.pathname) {
                        e.preventDefault();
                        const target = document.querySelector(this.getAttribute('href'));
                        if (target) {
                            target.scrollIntoView({
                                behavior: 'smooth',
                                block: 'start'
                            });
                        }
                    }
                });
            });
        });
    </script>
</head>
<body class="bg-gray-50">
    <?php if ($is_home_page): ?>
    <!-- Navigation for Home Page (Logged In Users) -->
    <nav class="bg-white shadow-lg sticky top-0 z-50" x-data="{ mobileMenuOpen: false }">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center">
                    <i class="fas fa-book-reader text-blue-600 text-2xl mr-3"></i>
                    <span class="text-xl font-bold text-gray-800"><?php echo SITE_NAME; ?></span>
                </div>
                <div class="hidden md:flex items-center space-x-8">
                    <a href="#home" class="text-gray-700 hover:text-blue-600 transition">Home</a>
                    <a href="#about" class="text-gray-700 hover:text-blue-600 transition">About</a>
                    <a href="#features" class="text-gray-700 hover:text-blue-600 transition">Features</a>
                    <a href="#books" class="text-gray-700 hover:text-blue-600 transition">Books</a>
                    <a href="#categories" class="text-gray-700 hover:text-blue-600 transition">Categories</a>
                    <a href="#contact" class="text-gray-700 hover:text-blue-600 transition">Contact</a>
                </div>
                <div class="hidden md:flex items-center space-x-4">
                    <?php if ($role === 'member'): ?>
                    <!-- Cart Icon for Home Page -->
                    <a href="../cart/cart.php" class="relative p-2 text-gray-600 hover:text-blue-600 transition">
                        <i class="fas fa-shopping-cart text-xl"></i>
                        <span id="cart-badge-home" class="absolute top-0 right-0 inline-flex items-center justify-center w-5 h-5 text-xs font-bold text-white bg-green-500 rounded-full" 
                            style="display: none;">
                            0
                        </span>
                    </a>
                    <?php endif; ?>
                    <a href="../pages/dashboard.php" class="text-blue-600 hover:text-blue-700 font-semibold">Dashboard</a>
                    <a href="../profile/profile.php" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition flex items-center">
                        <?php if ($user_photo): ?>
                            <img class="h-6 w-6 rounded-full object-cover mr-2" src="<?php echo $user_photo; ?>" alt="<?php echo $full_name; ?>">
                        <?php else: ?>
                            <div class="avatar-initial avatar-initial-small mr-2">
                                <?php echo $user_initial; ?>
                            </div>
                        <?php endif; ?>
                        <?php echo $full_name; ?>
                    </a>
                </div>
                
                <!-- Mobile menu button -->
                <button @click="mobileMenuOpen = !mobileMenuOpen" class="md:hidden p-2 rounded-md text-gray-600 hover:text-blue-600 hover:bg-blue-50 transition">
                    <i class="fas fa-bars text-xl" x-show="!mobileMenuOpen"></i>
                    <i class="fas fa-times text-xl" x-show="mobileMenuOpen" style="display: none;"></i>
                </button>
            </div>
        </div>

        <!-- Mobile Navigation for Home Page -->
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
                <!-- User Info Section -->
                <div class="flex items-center pb-3 mb-3 border-b border-gray-200">
                    <?php if ($user_photo): ?>
                        <img class="h-12 w-12 rounded-full object-cover border-2 border-gray-300" src="<?php echo $user_photo; ?>" alt="<?php echo $full_name; ?>">
                    <?php else: ?>
                        <div class="avatar-initial avatar-initial-large">
                            <?php echo $user_initial; ?>
                        </div>
                    <?php endif; ?>
                    <div class="ml-3">
                        <p class="text-base font-medium text-gray-800"><?php echo $full_name; ?></p>
                        <p class="text-sm text-gray-500"><?php echo ucfirst($role); ?></p>
                    </div>
                </div>

                <!-- Home Page Navigation -->
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

                <!-- Dashboard Link -->
                <a href="../pages/dashboard.php" class="flex items-center px-3 py-3 rounded-md text-base font-medium text-blue-600 hover:bg-blue-50 transition bg-blue-50 font-semibold">
                    <i class="fas fa-tachometer-alt mr-3 w-5"></i>Dashboard
                </a>
                
                <a href="../profile/profile.php" class="flex items-center px-3 py-3 rounded-md text-base font-medium text-gray-700 hover:text-blue-600 hover:bg-blue-50 transition">
                    <i class="fas fa-user mr-3 w-5"></i>My Profile
                </a>
                
                <a href="../books/books.php" class="flex items-center px-3 py-3 rounded-md text-base font-medium text-gray-700 hover:text-blue-600 hover:bg-blue-50 transition">
                    <i class="fas fa-book-open mr-3 w-5"></i>Browse Books
                </a>
                
                <?php if ($role === 'member'): ?>
                <!-- Cart (Mobile) -->
                <a href="../cart/cart.php" class="flex items-center justify-between px-3 py-3 rounded-md text-base font-medium text-gray-700 hover:text-blue-600 hover:bg-blue-50 transition">
                    <span><i class="fas fa-shopping-cart mr-3 w-5"></i>My Cart</span>
                    <span id="cart-badge-home-mobile" class="inline-flex items-center justify-center w-6 h-6 text-xs font-bold text-white bg-green-500 rounded-full" 
                        style="display: none;">
                        0
                    </span>
                </a>
                <?php endif; ?>

                <!-- Divider -->
                <div class="border-t border-gray-200 my-3"></div>

                <!-- Logout -->
                <a href="../auth/logout.php" class="flex items-center px-3 py-3 rounded-md text-base font-medium text-red-600 hover:bg-red-50 transition">
                    <i class="fas fa-sign-out-alt mr-3 w-5"></i>Logout
                </a>
            </div>
        </div>
    </nav>
    
    <!-- Alpine.js for mobile menu -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <?php else: ?>
    <!-- Full Navigation Bar (For other pages) -->
    <nav class="bg-white shadow-lg sticky top-0 z-50" x-data="{ open: false, mobileMenuOpen: false }">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <!-- Left side: Logo and Main Navigation -->
                <div class="flex">
                    <!-- Logo -->
                    <div class="flex-shrink-0 flex items-center">
                        <a href="../pages/home.php" class="flex items-center">
                            <i class="fas fa-book-reader text-blue-600 text-2xl mr-3"></i>
                            <span class="text-xl font-bold text-gray-800"><?php echo SITE_NAME; ?></span>
                        </a>
                    </div>

                    <!-- Main Navigation -->
                    <div class="hidden md:ml-6 md:flex md:space-x-4">
                        <a href="../pages/home.php" class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-700 hover:text-blue-600 hover:bg-blue-50 rounded-md transition">
                            <i class="fas fa-home mr-2"></i>Home
                        </a>
                        <a href="../books/books.php" class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-700 hover:text-blue-600 hover:bg-blue-50 rounded-md transition">
                            <i class="fas fa-book mr-2"></i>Books
                        </a>
                    </div>
                </div>

                <!-- Right side: User Menu -->
                <div class="flex items-center">
                    <?php if ($role === 'member'): ?>
                    <!-- Cart Icon - Hidden on mobile -->
                    <div class="hidden md:block relative mr-2">
                        <a href="../cart/cart.php" class="p-2 text-gray-600 hover:text-blue-600 hover:bg-blue-50 rounded-full transition relative">
                            <i class="fas fa-shopping-cart text-xl"></i>
                            <span id="cart-badge" class="absolute top-1 right-1 inline-flex items-center justify-center w-5 h-5 text-xs font-bold text-white bg-green-500 rounded-full" 
                                style="display: none;">
                                0
                            </span>
                        </a>
                    </div>
                    <?php endif; ?>

                    <!-- Notifications - Hidden on mobile -->
                    <div class="hidden md:block relative" x-data="{ notifOpen: false }">
                        <button @click="notifOpen = !notifOpen; if(notifOpen) loadNotifications()" 
                                class="p-2 text-gray-600 hover:text-blue-600 hover:bg-blue-50 rounded-full transition mr-2 relative">
                            <i class="fas fa-bell text-xl"></i>
                            <span id="notif-badge" class="absolute top-1 right-1 inline-flex items-center justify-center w-5 h-5 text-xs font-bold text-white bg-red-500 rounded-full" 
                                style="<?php echo $unread_count > 0 ? '' : 'display: none;'; ?>">
                                <?php echo $unread_count; ?>
                            </span>
                        </button>
                        
                        <!-- Notification Dropdown -->
                        <div x-show="notifOpen" 
                            @click.away="notifOpen = false"
                            x-transition:enter="transition ease-out duration-100"
                            x-transition:enter-start="transform opacity-0 scale-95"
                            x-transition:enter-end="transform opacity-100 scale-100"
                            x-transition:leave="transition ease-in duration-75"
                            x-transition:leave-start="transform opacity-100 scale-100"
                            x-transition:leave-end="transform opacity-0 scale-95"
                            class="origin-top-right absolute right-0 mt-2 w-96 rounded-lg shadow-xl bg-white ring-1 ring-black ring-opacity-5 z-50"
                            style="display: none;">
                            <div id="notification-dropdown">
                                <!-- Content loaded via JavaScript -->
                            </div>
                        </div>
                    </div>

                    <!-- User Dropdown - Hidden on mobile -->
                    <div class="hidden md:block ml-3 relative">
                        <button @click="open = !open" class="flex items-center text-sm rounded-full focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <?php if ($user_photo): ?>
                                <img class="h-10 w-10 rounded-full object-cover border-2 border-gray-300" src="<?php echo $user_photo; ?>" alt="<?php echo $full_name; ?>">
                            <?php else: ?>
                                <div class="avatar-initial">
                                    <?php echo $user_initial; ?>
                                </div>
                            <?php endif; ?>
                            <span class="ml-2 text-gray-700 font-medium"><?php echo $full_name; ?></span>
                            <i class="fas fa-chevron-down ml-2 text-gray-500 text-xs"></i>
                        </button>

                        <!-- Dropdown Menu -->
                        <div x-show="open" 
                             @click.away="open = false"
                             x-transition:enter="transition ease-out duration-100"
                             x-transition:enter-start="transform opacity-0 scale-95"
                             x-transition:enter-end="transform opacity-100 scale-100"
                             x-transition:leave="transition ease-in duration-75"
                             x-transition:leave-start="transform opacity-100 scale-100"
                             x-transition:leave-end="transform opacity-0 scale-95"
                             class="origin-top-right absolute right-0 mt-2 w-56 rounded-lg shadow-lg bg-white ring-1 ring-black ring-opacity-5"
                             style="display: none;">
                            <div class="py-1">
                                <div class="px-4 py-3 border-b">
                                    <p class="text-sm font-medium text-gray-900"><?php echo $full_name; ?></p>
                                    <p class="text-xs text-gray-500"><?php echo ucfirst($role); ?></p>
                                </div>
                                
                                <a href="../pages/dashboard.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-600 transition">
                                    <i class="fas fa-tachometer-alt mr-2"></i>Dashboard
                                </a>
                                
                                <a href="../profile/profile.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-600 transition">
                                    <i class="fas fa-user mr-2"></i>My Profile
                                </a>
                                
                                <div class="border-t"></div>
                                <a href="../auth/logout.php" class="block px-4 py-2 text-sm text-red-600 hover:bg-red-50 transition">
                                    <i class="fas fa-sign-out-alt mr-2"></i>Logout
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Mobile menu button -->
                    <button @click="mobileMenuOpen = !mobileMenuOpen" class="md:hidden p-2 rounded-md text-gray-600 hover:text-blue-600 hover:bg-blue-50 transition">
                        <i class="fas fa-bars text-xl" x-show="!mobileMenuOpen"></i>
                        <i class="fas fa-times text-xl" x-show="mobileMenuOpen" style="display: none;"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Mobile Navigation -->
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
                <!-- User Info Section -->
                <div class="flex items-center pb-3 mb-3 border-b border-gray-200">
                    <?php if ($user_photo): ?>
                        <img class="h-12 w-12 rounded-full object-cover border-2 border-gray-300" src="<?php echo $user_photo; ?>" alt="<?php echo $full_name; ?>">
                    <?php else: ?>
                        <div class="avatar-initial avatar-initial-large">
                            <?php echo $user_initial; ?>
                        </div>
                    <?php endif; ?>
                    <div class="ml-3">
                        <p class="text-base font-medium text-gray-800"><?php echo $full_name; ?></p>
                        <p class="text-sm text-gray-500"><?php echo ucfirst($role); ?></p>
                    </div>
                </div>

                <!-- Navigation Links -->
                <a href="../pages/home.php" class="flex items-center px-3 py-3 rounded-md text-base font-medium text-gray-700 hover:text-blue-600 hover:bg-blue-50 transition">
                    <i class="fas fa-home mr-3 w-5"></i>Home
                </a>
                <a href="../books/books.php" class="flex items-center px-3 py-3 rounded-md text-base font-medium text-gray-700 hover:text-blue-600 hover:bg-blue-50 transition">
                    <i class="fas fa-book mr-3 w-5"></i>Books
                </a>

                <!-- Divider -->
                <div class="border-t border-gray-200 my-3"></div>

                <!-- Dashboard Link (ADDED) -->
                <a href="../pages/dashboard.php" class="flex items-center px-3 py-3 rounded-md text-base font-medium text-blue-600 hover:bg-blue-50 transition bg-blue-50 font-semibold">
                    <i class="fas fa-tachometer-alt mr-3 w-5"></i>Dashboard
                </a>
                
                <a href="../profile/profile.php" class="flex items-center px-3 py-3 rounded-md text-base font-medium text-gray-700 hover:text-blue-600 hover:bg-blue-50 transition">
                    <i class="fas fa-user mr-3 w-5"></i>My Profile
                </a>
                
                <?php if ($role === 'member'): ?>
                <!-- Cart (Mobile) - ADDED -->
                <a href="../cart/cart.php" class="flex items-center justify-between px-3 py-3 rounded-md text-base font-medium text-gray-700 hover:text-blue-600 hover:bg-blue-50 transition">
                    <span><i class="fas fa-shopping-cart mr-3 w-5"></i>My Cart</span>
                    <span id="cart-badge-mobile" class="inline-flex items-center justify-center w-6 h-6 text-xs font-bold text-white bg-green-500 rounded-full" 
                        style="display: none;">
                        0
                    </span>
                </a>
                <?php endif; ?>
                
                <!-- Notifications (Mobile) -->
                <a href="../notifications/view_all.php" class="flex items-center justify-between px-3 py-3 rounded-md text-base font-medium text-gray-700 hover:text-blue-600 hover:bg-blue-50 transition">
                    <span><i class="fas fa-bell mr-3 w-5"></i>Notifications</span>
                    <span id="notif-badge-mobile" class="inline-flex items-center justify-center w-6 h-6 text-xs font-bold text-white bg-red-500 rounded-full" 
                        style="<?php echo $unread_count > 0 ? '' : 'display: none;'; ?>">
                        <?php echo $unread_count; ?>
                    </span>
                </a>

                <!-- Divider -->
                <div class="border-t border-gray-200 my-3"></div>

                <!-- Logout -->
                <a href="../auth/logout.php" class="flex items-center px-3 py-3 rounded-md text-base font-medium text-red-600 hover:bg-red-50 transition">
                    <i class="fas fa-sign-out-alt mr-3 w-5"></i>Logout
                </a>
            </div>
        </div>
    </nav>

    <!-- Alpine.js for dropdown functionality -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="../assets/script/notifications.js"></script>
    
    <!-- Cart Count Script -->
    <script>
    // Update cart count on page load
    document.addEventListener('DOMContentLoaded', async function() {
        <?php if ($role === 'member'): ?>
        try {
            const response = await fetch('../cart/get_cart_data.php?action=count');
            const data = await response.json();
            if (data.success) {
                updateCartBadges(data.count);
            }
        } catch (error) {
            console.error('Error loading cart count:', error);
        }
        <?php endif; ?>
    });

    // Function to update all cart badges
    function updateCartBadges(count) {
        const badges = ['cart-badge', 'cart-badge-mobile', 'cart-badge-home', 'cart-badge-home-mobile'];
        badges.forEach(badgeId => {
            const badge = document.getElementById(badgeId);
            if (badge) {
                badge.textContent = count;
                badge.style.display = count > 0 ? 'inline-flex' : 'none';
            }
        });
    }

    // Global function to update cart count (can be called from other pages)
    window.updateCartCount = async function() {
        try {
            const response = await fetch('../cart/get_cart_data.php?action=count');
            const data = await response.json();
            if (data.success) {
                updateCartBadges(data.count);
            }
        } catch (error) {
            console.error('Error updating cart count:', error);
        }
    };
    </script>
    <?php endif; ?>

    <?php if (!$is_home_page): ?>
    <!-- Main Content Container with proper spacing -->
    <main class="container mx-auto px-4 sm:px-6 lg:px-8 py-8 max-w-7xl">
    <?php endif; ?>