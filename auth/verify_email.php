<?php
session_start();
require_once '../includes/config.php';

// Check if there's a pending verification
if (!isset($_SESSION['pending_verification'])) {
    setMessage('error', 'No pending verification found. Please register first.');
    redirect('register.php');
}

$pending = $_SESSION['pending_verification'];

// Check if verification has expired
if (time() > $pending['expires']) {
    // Delete the unverified user
    $delete_sql = "DELETE FROM users WHERE user_id = ?";
    $stmt = $conn->prepare($delete_sql);
    $stmt->bind_param("i", $pending['user_id']);
    $stmt->execute();
    $stmt->close();
    
    unset($_SESSION['pending_verification']);
    setMessage('error', 'Verification code has expired. Please register again.');
    redirect('register.php');
}

// Get any messages
$message = getMessage();

// Calculate remaining time
$remaining_seconds = $pending['expires'] - time();
$remaining_minutes = ceil($remaining_seconds / 60);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Email - <?php echo SITE_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <!-- Verification Card -->
        <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
            <!-- Header -->
            <div class="bg-gradient-to-r from-blue-600 to-indigo-600 p-8 text-white text-center">
                <div class="w-20 h-20 bg-white rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-envelope-open-text text-blue-600 text-3xl"></i>
                </div>
                <h1 class="text-2xl font-bold">Verify Your Email</h1>
                <p class="text-blue-100 mt-2">We've sent a code to your email</p>
            </div>

            <!-- Alert Messages -->
            <?php if ($message): ?>
                <div class="mx-6 mt-6">
                    <?php if ($message['type'] === 'error'): ?>
                        <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded" role="alert">
                            <div class="flex items-center">
                                <i class="fas fa-exclamation-circle mr-2"></i>
                                <span><?php echo $message['message']; ?></span>
                            </div>
                        </div>
                    <?php elseif ($message['type'] === 'success'): ?>
                        <div class="bg-green-50 border-l-4 border-green-500 text-green-700 p-4 rounded" role="alert">
                            <div class="flex items-center">
                                <i class="fas fa-check-circle mr-2"></i>
                                <span><?php echo $message['message']; ?></span>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Content -->
            <div class="p-8">
                <!-- Email Info -->
                <div class="bg-blue-50 border-l-4 border-blue-500 p-4 rounded mb-6">
                    <p class="text-sm text-gray-700">
                        <i class="fas fa-info-circle text-blue-500 mr-2"></i>
                        A 6-digit verification code has been sent to:<br>
                        <strong class="text-blue-700"><?php echo htmlspecialchars($pending['email']); ?></strong>
                    </p>
                </div>

                <!-- Verification Form -->
                <form id="verifyForm" method="POST" action="verify_email_api.php">
                    <div class="mb-6">
                        <label for="otp" class="block text-gray-700 text-sm font-semibold mb-3 text-center">
                            Enter 6-Digit Code
                        </label>
                        <input type="text" 
                               id="otp" 
                               name="otp" 
                               required 
                               maxlength="6"
                               pattern="[0-9]{6}"
                               autofocus
                               class="w-full px-4 py-4 text-center text-2xl tracking-widest font-mono border-2 border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200"
                               placeholder="000000">
                        <p class="text-xs text-gray-500 mt-2 text-center">Please check your email inbox (and spam folder)</p>
                    </div>

                    <!-- Timer -->
                    <div class="text-center mb-6">
                        <p class="text-sm text-gray-600">
                            <i class="fas fa-clock mr-1"></i>
                            Code expires in: <span id="timer" class="font-semibold text-red-600"><?php echo $remaining_minutes; ?> minutes</span>
                        </p>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" 
                            class="w-full bg-gradient-to-r from-blue-600 to-indigo-600 text-white font-semibold py-3 rounded-lg hover:from-blue-700 hover:to-indigo-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition duration-200 transform hover:scale-[1.02]">
                        <i class="fas fa-check-circle mr-2"></i>Verify Email
                    </button>
                </form>

                <!-- Resend Code -->
                <div class="mt-6 text-center">
                    <p class="text-sm text-gray-600">
                        Didn't receive the code?
                    </p>
                    <form method="POST" action="resend_otp.php" class="mt-2">
                        <button type="submit" 
                                class="text-blue-600 hover:text-blue-800 font-semibold text-sm">
                            <i class="fas fa-redo mr-1"></i>Resend Code
                        </button>
                    </form>
                </div>
            </div>

            <!-- Footer -->
            <div class="bg-gray-50 px-8 py-4 border-t border-gray-200 text-center">
                <p class="text-sm text-gray-600">
                    Wrong email? 
                    <a href="register.php" class="text-blue-600 hover:text-blue-800 font-semibold">Register again</a>
                </p>
            </div>
        </div>

        <!-- Copyright -->
        <div class="text-center mt-6">
            <p class="text-sm text-gray-600">© 2024 <?php echo SITE_NAME; ?>. All rights reserved.</p>
        </div>
    </div>

    <script>
        // Auto-format OTP input (only numbers)
        document.getElementById('otp').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
        });

        // Countdown timer
        let remainingSeconds = <?php echo $remaining_seconds; ?>;
        const timerElement = document.getElementById('timer');
        
        function updateTimer() {
            if (remainingSeconds <= 0) {
                window.location.href = 'verify_email.php'; // Refresh to show expiration
                return;
            }
            
            const minutes = Math.floor(remainingSeconds / 60);
            const seconds = remainingSeconds % 60;
            timerElement.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
            remainingSeconds--;
        }
        
        // Update every second
        setInterval(updateTimer, 1000);
        updateTimer();
    </script>
</body>
</html>