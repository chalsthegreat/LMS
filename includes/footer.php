<?php
// CRITICAL: Ensure session is started and includes are available if this file is used standalone
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Assume the $is_logged_in variable is set in the main page (home.php)
// If it's not set, we'll check the session directly as a fallback.
$is_logged_in = isset($_SESSION['user_id']); 
?>

</main>
    <footer class="bg-gray-900 text-white py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-8">
                
                <div>
                    <div class="flex items-center mb-4">
                        <i class="fas fa-book-reader text-3xl text-blue-400 mr-3"></i>
                        <span class="text-2xl font-bold"><?php echo SITE_NAME; ?></span>
                    </div>
                    <p class="text-gray-400">
                        Your gateway to knowledge and learning.
                    </p>
                </div>

                <div>
                    <h4 class="text-lg font-bold mb-4">Quick Links</h4>
                    <ul class="space-y-2 text-sm">
                        <?php if ($is_logged_in): ?>
                            <li>
                                <a href="../pages/dashboard.php" class="text-gray-400 hover:text-white transition">
                                    <i class="fas fa-chevron-right text-xs mr-2"></i>Dashboard
                                </a>
                            </li>
                            <li>
                                <a href="../books/books.php" class="text-gray-400 hover:text-white transition">
                                    <i class="fas fa-chevron-right text-xs mr-2"></i>Browse Books
                                </a>
                            </li>
                            <li>
                                <a href="../profile/profile.php" class="text-gray-400 hover:text-white transition">
                                    <i class="fas fa-chevron-right text-xs mr-2"></i>My Profile
                                </a>
                            </li>
                            <li>
                                <a href="settings.php" class="text-gray-400 hover:text-white transition">
                                    <i class="fas fa-chevron-right text-xs mr-2"></i>Settings
                                </a>
                            </li>
                        <?php else: ?>
                            <li><a href="#home" class="text-gray-400 hover:text-white transition">Home</a></li>
                            <li><a href="#about" class="text-gray-400 hover:text-white transition">About</a></li>
                            <li><a href="../books/books.php" class="text-gray-400 hover:text-white transition">Books</a></li>
                            <li><a href="#categories" class="text-gray-400 hover:text-white transition">Categories</a></li>
                            <li><a href="#contact" class="text-gray-400 hover:text-white transition">Contact</a></li>
                        <?php endif; ?>
                    </ul>
                </div>

                <div>
                    <?php if ($is_logged_in): ?>
                        <h4 class="text-lg font-bold mb-4">Resources</h4>
                        <ul class="space-y-2 text-sm">
                            <li><a href="#" class="text-gray-400 hover:text-white transition"><i class="fas fa-chevron-right text-xs mr-2"></i>Help Center</a></li>
                            <li><a href="#" class="text-gray-400 hover:text-white transition"><i class="fas fa-chevron-right text-xs mr-2"></i>FAQs</a></li>
                            <li><a href="#" class="text-gray-400 hover:text-white transition"><i class="fas fa-chevron-right text-xs mr-2"></i>Terms of Service</a></li>
                            <li><a href="#" class="text-gray-400 hover:text-white transition"><i class="fas fa-chevron-right text-xs mr-2"></i>Privacy Policy</a></li>
                        </ul>
                    <?php else: ?>
                        <h4 class="text-lg font-bold mb-4">Account</h4>
                        <ul class="space-y-2 text-sm">
                            <li><a href="../auth/login.php" class="text-gray-400 hover:text-white transition">Login</a></li>
                            <li><a href="../auth/register.php" class="text-gray-400 hover:text-white transition">Register</a></li>
                        </ul>
                    <?php endif; ?>
                </div>

                <div>
                    <h4 class="text-lg font-bold mb-4">Contact</h4>
                    <ul class="space-y-2 text-sm text-gray-400">
                        <li><i class="fas fa-envelope mr-2"></i>info@yapidzlibrary.com</li>
                        <li><i class="fas fa-phone mr-2"></i>+63 123 456 7890</li>
                        <li><i class="fas fa-map-marker-alt mr-2"></i>Yapidz City</li>
                    </ul>

                    <div class="mt-4 flex space-x-3">
                        <a href="#" class="w-8 h-8 bg-gray-700 text-gray-400 rounded-full flex items-center justify-center hover:bg-blue-600 hover:text-white transition">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="#" class="w-8 h-8 bg-gray-700 text-gray-400 rounded-full flex items-center justify-center hover:bg-blue-600 hover:text-white transition">
                            <i class="fab fa-twitter"></i>
                        </a>
                        <a href="#" class="w-8 h-8 bg-gray-700 text-gray-400 rounded-full flex items-center justify-center hover:bg-blue-600 hover:text-white transition">
                            <i class="fab fa-instagram"></i>
                        </a>
                        <a href="#" class="w-8 h-8 bg-gray-700 text-gray-400 rounded-full flex items-center justify-center hover:bg-blue-600 hover:text-white transition">
                            <i class="fab fa-linkedin-in"></i>
                        </a>
                    </div>
                </div>
            </div>

            <div class="border-t border-gray-800 mt-8 pt-8 text-center text-gray-400 flex flex-col md:flex-row justify-between items-center">
                <p class="mb-2 md:mb-0">
                    © <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved.
                </p>
                <p class="text-sm">
                    Built with <i class="fas fa-heart text-red-500"></i> by Your Team
                </p>
            </div>
        </div>
    </footer>

    <button id="scrollToTop" 
            class="fixed bottom-8 right-8 w-12 h-12 bg-blue-600 text-white rounded-full shadow-lg hover:bg-blue-700 transition transform hover:scale-110 hidden"
            onclick="window.scrollTo({top: 0, behavior: 'smooth'})">
        <i class="fas fa-arrow-up"></i>
    </button>

    <script>
        // Scroll to top button functionality
        window.addEventListener('scroll', function() {
            const scrollButton = document.getElementById('scrollToTop');
            if (window.pageYOffset > 300) {
                scrollButton.classList.remove('hidden');
            } else {
                scrollButton.classList.add('hidden');
            }
        });

        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('[role="alert"]');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.transition = 'opacity 0.5s';
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 500);
                }, 5000);
            });
        });
    </script>
</body>
</html>