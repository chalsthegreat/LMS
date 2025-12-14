<?php
$page_title = "Profile";
require_once '../includes/config.php';
require_once '../includes/header.php';

// Check if viewing another user's profile (admin/librarian only)
$viewing_user_id = isset($_GET['id']) ? (int)$_GET['id'] : $user_id;
$is_viewing_other = ($viewing_user_id !== $user_id);

// Check permissions
if ($is_viewing_other) {
    // Only admin and librarian can view other profiles
    if ($role !== 'admin' && $role !== 'librarian') {
        setMessage('error', 'You do not have permission to view other profiles');
        redirect('profile.php');
    }
}

// Get user's full information from database
$sql = "SELECT * FROM users WHERE user_id = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $viewing_user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    setMessage('error', 'User not found');
    redirect($role === 'member' ? 'profile.php' : '../members/members.php');
}

$user_info = $result->fetch_assoc();

// Set default photo if none exists
$user_photo = $user_info['photo'] ? '../uploads/users/' . $user_info['photo'] : '../assets/images/default-avatar.png';

// Get user statistics
$stats_sql = "SELECT 
                (SELECT COUNT(*) FROM borrowings WHERE user_id = ? AND status = 'borrowed') as currently_borrowed,
                (SELECT COUNT(*) FROM borrowings WHERE user_id = ?) as total_borrowed,
                (SELECT COUNT(*) FROM borrowings WHERE user_id = ? AND status = 'returned') as returned_books,
                (SELECT COUNT(*) FROM borrowings WHERE user_id = ? AND status = 'borrowed' AND due_date < CURDATE()) as overdue_books";
$stmt = $conn->prepare($stats_sql);
$stmt->bind_param("iiii", $viewing_user_id, $viewing_user_id, $viewing_user_id, $viewing_user_id);
$stmt->execute();
$user_stats = $stmt->get_result()->fetch_assoc();

// Update page title
if ($is_viewing_other) {
    $page_title = $user_info['full_name'] . "'s Profile";
}

// Get alert message if any
$message = getMessage();
?>

<!-- Alert Messages -->
<?php if ($message): ?>
    <div class="mb-6">
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
    </div>
<?php endif; ?>

<!-- Profile Header -->
<div class="mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">
                <?php echo $is_viewing_other ? $user_info['full_name'] . "'s Profile" : "My Profile"; ?>
            </h1>
            <p class="text-gray-600 mt-2">
                <?php echo $is_viewing_other ? "View member account information" : "Manage your account information and settings"; ?>
            </p>
        </div>
        <?php if ($is_viewing_other): ?>
            <a href="../members/members.php" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-300 transition font-semibold">
                <i class="fas fa-arrow-left mr-2"></i>Back to Members
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Left Column - Profile Card & Stats -->
    <div class="lg:col-span-1 space-y-6">
        <!-- Profile Card -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="text-center">
                <div class="relative inline-block">
                    <img id="profileImage" src="<?php echo $user_photo; ?>" alt="<?php echo $user_info['full_name']; ?>" 
                        class="w-32 h-32 rounded-full object-cover border-4 border-blue-500 mx-auto">
                    <?php if (!$is_viewing_other): ?>
                        <button onclick="document.getElementById('photoInput').click()" 
                                class="absolute bottom-0 right-0 bg-blue-600 text-white rounded-full p-2 hover:bg-blue-700 transition">
                            <i class="fas fa-camera"></i>
                        </button>
                    <?php endif; ?>
                </div>
                
                <h2 class="text-2xl font-bold text-gray-800 mt-4"><?php echo $user_info['full_name']; ?></h2>
                <p class="text-gray-600">@<?php echo $user_info['username']; ?></p>
                
                <!-- Role Badge -->
                <div class="mt-3">
                    <?php 
                    $role_colors = [
                        'admin' => 'bg-red-100 text-red-700',
                        'librarian' => 'bg-purple-100 text-purple-700',
                        'member' => 'bg-blue-100 text-blue-700'
                    ];
                    $role_class = $role_colors[$user_info['role']] ?? 'bg-gray-100 text-gray-700';
                    ?>
                    <span class="px-4 py-1 rounded-full text-sm font-semibold <?php echo $role_class; ?>">
                        <i class="fas fa-shield-alt mr-1"></i><?php echo ucfirst($user_info['role']); ?>
                    </span>
                </div>

                <!-- Account Status -->
                <div class="mt-3">
                    <?php 
                    $status_colors = [
                        'active' => 'bg-green-100 text-green-700',
                        'inactive' => 'bg-gray-100 text-gray-700',
                        'suspended' => 'bg-red-100 text-red-700'
                    ];
                    $status_class = $status_colors[$user_info['status']] ?? 'bg-gray-100 text-gray-700';
                    ?>
                    <span class="px-4 py-1 rounded-full text-sm font-semibold <?php echo $status_class; ?>">
                        <?php echo ucfirst($user_info['status']); ?>
                    </span>
                </div>

                <p class="text-xs text-gray-500 mt-4">
                    Member since <?php echo date('F Y', strtotime($user_info['created_at'])); ?>
                </p>
            </div>

            <!-- Hidden File Input -->
            <form id="photoUploadForm" enctype="multipart/form-data" class="hidden">
                <input type="file" id="photoInput" name="photo" accept="image/*" onchange="uploadPhoto()">
            </form>
        </div>

        <!-- Statistics Card -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-bold text-gray-800 mb-4">
                <i class="fas fa-chart-bar text-blue-600 mr-2"></i>My Statistics
            </h3>
            <div class="space-y-4">
                <div class="flex justify-between items-center">
                    <span class="text-gray-600 text-sm">Currently Borrowed</span>
                    <span class="font-bold text-blue-600 text-lg"><?php echo $user_stats['currently_borrowed']; ?></span>
                </div>
                <div class="border-t border-gray-200"></div>
                <div class="flex justify-between items-center">
                    <span class="text-gray-600 text-sm">Total Borrowed</span>
                    <span class="font-bold text-green-600 text-lg"><?php echo $user_stats['total_borrowed']; ?></span>
                </div>
                <div class="border-t border-gray-200"></div>
                <div class="flex justify-between items-center">
                    <span class="text-gray-600 text-sm">Returned Books</span>
                    <span class="font-bold text-purple-600 text-lg"><?php echo $user_stats['returned_books']; ?></span>
                </div>
                <div class="border-t border-gray-200"></div>
                <div class="flex justify-between items-center">
                    <span class="text-gray-600 text-sm">Overdue Books</span>
                    <span class="font-bold text-red-600 text-lg"><?php echo $user_stats['overdue_books']; ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Column - Profile Information & Edit -->
    <div class="lg:col-span-2">
<!-- Tab Navigation -->
<div class="bg-white rounded-t-lg shadow-md" x-data="{ activeTab: 'info' }">
    <?php if (!$is_viewing_other): ?>
        <div class="flex border-b border-gray-200">
            <button @click="activeTab = 'info'" 
                    :class="activeTab === 'info' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-600'"
                    class="px-6 py-4 font-semibold border-b-2 transition">
                <i class="fas fa-user mr-2"></i>Personal Information
            </button>
            <button @click="activeTab = 'edit'" 
                    :class="activeTab === 'edit' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-600'"
                    class="px-6 py-4 font-semibold border-b-2 transition">
                <i class="fas fa-edit mr-2"></i>Edit Profile
            </button>
            <button @click="activeTab = 'password'" 
                    :class="activeTab === 'password' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-600'"
                    class="px-6 py-4 font-semibold border-b-2 transition">
                <i class="fas fa-lock mr-2"></i>Change Password
            </button>
        </div>
    <?php else: ?>
        <!-- Read-only header for viewing other profiles -->
        <div class="border-b border-gray-200 px-6 py-4">
            <h3 class="text-lg font-semibold text-gray-800">
                <i class="fas fa-user mr-2"></i>Member Information
            </h3>
        </div>
    <?php endif; ?>

    <!-- Personal Information Tab -->
    <div <?php if (!$is_viewing_other): ?>x-show="activeTab === 'info'"<?php endif; ?> class="p-6">
        <h3 class="text-xl font-bold text-gray-800 mb-6">Account Details</h3>
                
                <div class="space-y-4">
                    <div class="flex items-start">
                        <div class="w-1/3">
                            <p class="text-sm font-semibold text-gray-600">Full Name</p>
                        </div>
                        <div class="w-2/3">
                            <p class="text-gray-800"><?php echo $user_info['full_name']; ?></p>
                        </div>
                    </div>
                    <div class="border-t border-gray-200"></div>

                    <div class="flex items-start">
                        <div class="w-1/3">
                            <p class="text-sm font-semibold text-gray-600">Username</p>
                        </div>
                        <div class="w-2/3">
                            <p class="text-gray-800"><?php echo $user_info['username']; ?></p>
                        </div>
                    </div>
                    <div class="border-t border-gray-200"></div>

                    <div class="flex items-start">
                        <div class="w-1/3">
                            <p class="text-sm font-semibold text-gray-600">Email Address</p>
                        </div>
                        <div class="w-2/3">
                            <p class="text-gray-800"><?php echo $user_info['email']; ?></p>
                        </div>
                    </div>
                    <div class="border-t border-gray-200"></div>

                    <div class="flex items-start">
                        <div class="w-1/3">
                            <p class="text-sm font-semibold text-gray-600">Phone Number</p>
                        </div>
                        <div class="w-2/3">
                            <p class="text-gray-800"><?php echo $user_info['phone'] ?: 'Not provided'; ?></p>
                        </div>
                    </div>
                    <div class="border-t border-gray-200"></div>

                    <div class="flex items-start">
                        <div class="w-1/3">
                            <p class="text-sm font-semibold text-gray-600">Address</p>
                        </div>
                        <div class="w-2/3">
                            <p class="text-gray-800"><?php echo $user_info['address'] ?: 'Not provided'; ?></p>
                        </div>
                    </div>
                    <div class="border-t border-gray-200"></div>

                    <div class="flex items-start">
                        <div class="w-1/3">
                            <p class="text-sm font-semibold text-gray-600">Role</p>
                        </div>
                        <div class="w-2/3">
                            <p class="text-gray-800"><?php echo ucfirst($user_info['role']); ?></p>
                        </div>
                    </div>
                    <div class="border-t border-gray-200"></div>

                    <div class="flex items-start">
                        <div class="w-1/3">
                            <p class="text-sm font-semibold text-gray-600">Account Status</p>
                        </div>
                        <div class="w-2/3">
                            <p class="text-gray-800"><?php echo ucfirst($user_info['status']); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Edit Profile Tab -->
             
            <div x-show="activeTab === 'edit'" class="p-6">
                <h3 class="text-xl font-bold text-gray-800 mb-6">Edit Your Information</h3>
                
                <form action="update_profile.php" method="POST">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-gray-700 text-sm font-semibold mb-2">
                                <i class="fas fa-id-card mr-2 text-gray-400"></i>Full Name
                            </label>
                            <input type="text" name="full_name" value="<?php echo $user_info['full_name']; ?>" required
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>

                        <div>
                            <label class="block text-gray-700 text-sm font-semibold mb-2">
                                <i class="fas fa-envelope mr-2 text-gray-400"></i>Email Address
                            </label>
                            <input type="email" name="email" value="<?php echo $user_info['email']; ?>" required
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>

                        <div>
                            <label class="block text-gray-700 text-sm font-semibold mb-2">
                                <i class="fas fa-phone mr-2 text-gray-400"></i>Phone Number
                            </label>
                            <input type="tel" name="phone" value="<?php echo $user_info['phone']; ?>"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>

                        <div>
                            <label class="block text-gray-700 text-sm font-semibold mb-2">
                                <i class="fas fa-user mr-2 text-gray-400"></i>Username
                            </label>
                            <input type="text" value="<?php echo $user_info['username']; ?>" disabled
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-100 cursor-not-allowed">
                            <p class="text-xs text-gray-500 mt-1">Username cannot be changed</p>
                        </div>
                    </div>

                    <div class="mt-6">
                        <label class="block text-gray-700 text-sm font-semibold mb-2">
                            <i class="fas fa-map-marker-alt mr-2 text-gray-400"></i>Address
                        </label>
                        <textarea name="address" rows="3"
                                  class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"><?php echo $user_info['address']; ?></textarea>
                    </div>

                    <div class="mt-6 flex gap-4">
                        <button type="submit" 
                                class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition font-semibold">
                            <i class="fas fa-save mr-2"></i>Save Changes
                        </button>
                        <button type="button" @click="activeTab = 'info'"
                                class="bg-gray-200 text-gray-700 px-6 py-3 rounded-lg hover:bg-gray-300 transition font-semibold">
                            <i class="fas fa-times mr-2"></i>Cancel
                        </button>
                    </div>
                </form>
            </div>

            <!-- Change Password Tab -->
            <div x-show="activeTab === 'password'" class="p-6">
                <h3 class="text-xl font-bold text-gray-800 mb-6">Change Your Password</h3>
                
                <form action="change_password.php" method="POST">
                    <div class="space-y-6">
                        <div>
                            <label class="block text-gray-700 text-sm font-semibold mb-2">
                                <i class="fas fa-lock mr-2 text-gray-400"></i>Current Password
                            </label>
                            <input type="password" name="current_password" required
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                   placeholder="Enter your current password">
                        </div>

                        <div>
                            <label class="block text-gray-700 text-sm font-semibold mb-2">
                                <i class="fas fa-lock mr-2 text-gray-400"></i>New Password
                            </label>
                            <input type="password" name="new_password" required minlength="6"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                   placeholder="Enter your new password">
                            <p class="text-xs text-gray-500 mt-1">Minimum 6 characters</p>
                        </div>

                        <div>
                            <label class="block text-gray-700 text-sm font-semibold mb-2">
                                <i class="fas fa-lock mr-2 text-gray-400"></i>Confirm New Password
                            </label>
                            <input type="password" name="confirm_password" required minlength="6"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                   placeholder="Confirm your new password">
                        </div>
                    </div>

                    <div class="mt-6 flex gap-4">
                        <button type="submit" 
                                class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition font-semibold">
                            <i class="fas fa-key mr-2"></i>Change Password
                        </button>
                        <button type="button" @click="activeTab = 'info'"
                                class="bg-gray-200 text-gray-700 px-6 py-3 rounded-lg hover:bg-gray-300 transition font-semibold">
                            <i class="fas fa-times mr-2"></i>Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function uploadPhoto() {
    const formData = new FormData();
    const photoInput = document.getElementById('photoInput');
    const file = photoInput.files[0];
    
    if (!file) return;
    
    // Validate file type
    if (!file.type.match('image.*')) {
        alert('Please select an image file');
        return;
    }
    
    // Validate file size (max 5MB)
    if (file.size > 5242880) {
        alert('File size must be less than 5MB');
        return;
    }
    
    formData.append('photo', file);
    
    // Show loading state
    const profileImage = document.getElementById('profileImage');
    profileImage.style.opacity = '0.5';
    
    fetch('upload_photo.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update the image
            profileImage.src = data.photo_url + '?t=' + new Date().getTime();
            profileImage.style.opacity = '1';
            
            // Show success message
            alert('Profile photo updated successfully!');
            location.reload();
        } else {
            alert('Error: ' + data.message);
            profileImage.style.opacity = '1';
        }
    })
    .catch(error => {
        alert('Error uploading photo');
        profileImage.style.opacity = '1';
    });
}
</script>

<?php require_once '../includes/footer.php'; ?>