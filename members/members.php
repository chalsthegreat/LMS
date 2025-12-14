<?php
$page_title = "User Management"; // Updated title for clarity
session_start();
require_once '../includes/config.php';

// Check if user is logged in and has permission (Admin or Librarian)
if (!isLoggedIn() || (!hasRole('admin') && !hasRole('librarian'))) {
    redirect('../auth/login.php');
}

// Only Admin can delete/change roles for security
$can_manage = hasRole('admin'); 

// --- Deletion Logic (Admin Only) ---
// Note: We are keeping the backend PHP deletion logic here, but using the modal for frontend confirmation
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    if ($can_manage) {
        $user_id_to_delete = sanitize($_GET['id']);

        // Prevent admin from deleting their own account (recommended)
        if ((int)$user_id_to_delete === (int)$_SESSION['user_id']) {
            setMessage('error', 'You cannot delete your own account.');
            redirect('members.php');
        }

        // 1. Delete user
        $delete_sql = "DELETE FROM users WHERE user_id = ?";
        $delete_stmt = $conn->prepare($delete_sql);
        $delete_stmt->bind_param("i", $user_id_to_delete);

        if ($delete_stmt->execute()) {
            // Log the action
            $admin_id = $_SESSION['user_id'];
            $action = "User Deletion";
            $description = "User ID {$user_id_to_delete} was deleted by Admin ID {$admin_id}.";
            $ip_address = $_SERVER['REMOTE_ADDR'];
            $log_sql = "INSERT INTO activity_logs (user_id, action, description, ip_address) VALUES (?, ?, ?, ?)";
            $log_stmt = $conn->prepare($log_sql);
            $log_stmt->bind_param("isss", $admin_id, $action, $description, $ip_address);
            $log_stmt->execute();
            $log_stmt->close();
            
            setMessage('success', 'User successfully deleted.');
        } else {
            setMessage('error', 'Error deleting user: ' . $conn->error);
        }
        $delete_stmt->close();
    } else {
        setMessage('error', 'Permission denied.');
    }
    redirect('members.php');
}
// ------------------------------------

require_once '../includes/header.php';

// Pagination setup (rest of the query logic remains the same)
$limit = 10; 
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Total count query
$count_query = "SELECT COUNT(*) FROM users";
$count_result = $conn->query($count_query);
$total_users = $count_result->fetch_row()[0];
$total_pages = ceil($total_users / $limit);

// Main users query
$users_query = "SELECT user_id, full_name, username, email, role, status, created_at 
                FROM users 
                ORDER BY role ASC, created_at DESC 
                LIMIT ? OFFSET ?";
$users_stmt = $conn->prepare($users_query);
$users_stmt->bind_param("ii", $limit, $offset);
$users_stmt->execute();
$users_result = $users_stmt->get_result();

$message = getMessage();
?>

<style>
    html, body {
        height: 100%;
        margin: 0;
    }

    body {
        display: flex;
        flex-direction: column;
        min-height: 100vh;
    }

    /* This ensures your main content expands to fill the space before the footer */
    main {
        flex: 1;
    }
    
    /* Custom spinner for loading state (from users.php) */
    #submitLoader {
        border-right-color: white;
    }
</style>

<div class="container mx-auto p-4 sm:p-6 lg:p-8">
    
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-800">User Management</h1>
        <button id="addUserBtn" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-xl shadow-lg transition duration-200 transform hover:scale-[1.02]">
            <i class="fas fa-plus mr-2"></i>Add New User
        </button>
    </div>
    
    <div id="dynamicAlertContainer" class="mb-4">
        <?php if ($message): ?>
            <div role="alert" class="bg-<?php echo $message['type'] === 'success' ? 'green-100 border-green-400 text-green-700' : 'red-100 border-red-400 text-red-700'; ?> border px-4 py-3 rounded relative mb-6" data-alert>
                <strong class="font-bold"><?php echo ucfirst($message['type']); ?>!</strong>
                <span class="block sm:inline"><?php echo $message['message']; ?></span>
            </div>
        <?php endif; ?>
    </div>
    

    <div class="bg-white shadow-lg rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Username</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Joined</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if ($users_result->num_rows > 0): ?>
                        <?php while ($user = $users_result->fetch_assoc()): ?>
                            <tr data-user-id="<?php echo $user['user_id']; ?>" 
                                data-full-name="<?php echo htmlspecialchars($user['full_name']); ?>" 
                                data-role="<?php echo $user['role']; ?>">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($user['full_name']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($user['username']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?php 
                                            if ($user['role'] === 'admin') echo 'bg-red-100 text-red-800';
                                            else if ($user['role'] === 'librarian') echo 'bg-yellow-100 text-yellow-800';
                                            else echo 'bg-blue-100 text-blue-800';
                                        ?>">
                                        <?php echo ucfirst($user['role']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?php 
                                            if ($user['status'] === 'active') echo 'bg-green-100 text-green-800';
                                            else echo 'bg-red-110 text-red-800'; // NOTE: 'bg-red-110' might not be a valid Tailwind class
                                        ?>">
                                        <?php echo ucfirst($user['status']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <?php if ($can_manage): ?>
                                        <?php if ((int)$user['user_id'] !== (int)$_SESSION['user_id']): ?>
                                            <button onclick="openRoleModal(<?php echo $user['user_id']; ?>, '<?php echo htmlspecialchars($user['full_name']); ?>', '<?php echo $user['role']; ?>')"
                                                class="text-indigo-600 hover:text-indigo-900 mr-4" title="Change Role">
                                                <i class="fas fa-user-tag"></i>
                                            </button>
                                        <?php endif; ?>
                                        
                                        <?php if ((int)$user['user_id'] !== (int)$_SESSION['user_id']): ?>
                                            <button onclick="openDeleteModal(<?php echo $user['user_id']; ?>, '<?php echo htmlspecialchars($user['full_name']); ?>')" 
                                            class="text-red-600 hover:text-red-900" title="Delete User">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-gray-400">No Actions</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="px-6 py-4 text-center text-gray-500">No users found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div class="px-6 py-4 bg-gray-50 border-t flex justify-between items-center">
            <p class="text-sm text-gray-700">
                Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $limit, $total_users); ?> of <?php echo $total_users; ?> results
            </p>
            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                        <span class="sr-only">Previous</span>
                        <i class="fas fa-chevron-left"></i>
                    </a>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=<?php echo $i; ?>" 
                       class="<?php echo $i === $page ? 'z-10 bg-blue-50 border-blue-500 text-blue-600' : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'; ?> relative inline-flex items-center px-4 py-2 border text-sm font-medium">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                        <span class="sr-only">Next</span>
                        <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </nav>
        </div>
        </div>
    </div>

<div id="addUserModal" class="fixed inset-0 z-50 overflow-y-auto bg-gray-900 bg-opacity-75 hidden transition-opacity duration-300 ease-out" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div id="modalContent" class="bg-white rounded-xl shadow-2xl w-full max-w-lg transform transition-all duration-300 ease-out scale-95 opacity-0">
            <div class="bg-gradient-to-r from-blue-600 to-indigo-600 p-6 rounded-t-xl text-white">
                <h3 class="text-xl font-bold" id="modal-title">
                    <i class="fas fa-user-plus mr-2"></i>Add New User
                </h3>
            </div>

            <form id="addUserForm" method="POST" class="p-6">
                <div class="space-y-4">
                    <div id="formMessage" class="hidden p-3 rounded-lg text-sm" role="alert"></div>

                    <div>
                        <label for="full_name" class="block text-sm font-medium text-gray-700">Full Name <span class="text-red-500">*</span></label>
                        <input type="text" id="full_name" name="full_name" required class="mt-1 block w-full border border-gray-300 rounded-lg shadow-sm p-3 focus:ring-blue-500 focus:border-blue-500 transition">
                    </div>

                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-700">Username <span class="text-red-500">*</span></label>
                        <input type="text" id="username" name="username" required class="mt-1 block w-full border border-gray-300 rounded-lg shadow-sm p-3 focus:ring-blue-500 focus:border-blue-500 transition">
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700">Email <span class="text-red-500">*</span></label>
                        <input type="email" id="email" name="email" required class="mt-1 block w-full border border-gray-300 rounded-lg shadow-sm p-3 focus:ring-blue-500 focus:border-blue-500 transition">
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700">Password <span class="text-red-500">*</span></label>
                            <div class="relative mt-1">
                                <input type="password" id="password" name="password" required minlength="8" class="block w-full border border-gray-300 rounded-lg shadow-sm p-3 pr-10 focus:ring-blue-500 focus:border-blue-500 transition">
                                <span class="absolute inset-y-0 right-0 flex items-center pr-3 cursor-pointer text-gray-500 hover:text-blue-600" onclick="togglePassword('password', 'toggleIconP')">
                                    <i id="toggleIconP" class="fas fa-eye"></i>
                                </span>
                            </div>
                        </div>
                        
                        <div>
                            <label for="confirm_password" class="block text-sm font-medium text-gray-700">Confirm Password <span class="text-red-500">*</span></label>
                            <div class="relative mt-1">
                                <input type="password" id="confirm_password" name="confirm_password" required minlength="8" class="block w-full border border-gray-300 rounded-lg shadow-sm p-3 pr-10 focus:ring-blue-500 focus:border-blue-500 transition">
                                <span class="absolute inset-y-0 right-0 flex items-center pr-3 cursor-pointer text-gray-500 hover:text-blue-600" onclick="togglePassword('confirm_password', 'toggleIconCP')">
                                    <i id="toggleIconCP" class="fas fa-eye"></i>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="role" class="block text-sm font-medium text-gray-700">Role</label>
                            <select id="role" name="role" class="mt-1 block w-full border border-gray-300 bg-white rounded-lg shadow-sm p-3 focus:ring-blue-500 focus:border-blue-500 transition">
                                <option value="member">Member</option>
                                <option value="librarian">Librarian</option>
                                <?php if (hasRole('admin')): ?>
                                    <option value="admin">Admin</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label for="status" class="block text-sm font-medium text-gray-700">Status</label>
                            <select id="status" name="status" class="mt-1 block w-full border border-gray-300 bg-white rounded-lg shadow-sm p-3 focus:ring-blue-500 focus:border-blue-500 transition">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="suspended">Suspended</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="phone" class="block text-sm font-medium text-gray-700">Phone</label>
                            <input type="text" id="phone" name="phone" class="mt-1 block w-full border border-gray-300 rounded-lg shadow-sm p-3 focus:ring-blue-500 focus:border-blue-500 transition">
                        </div>

                        <div>
                            <label for="address" class="block text-sm font-medium text-gray-700">Address</label>
                            <input type="text" id="address" name="address" class="mt-1 block w-full border border-gray-300 rounded-lg shadow-sm p-3 focus:ring-blue-500 focus:border-blue-500 transition">
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex justify-end space-x-3">
                    <button type="button" onclick="closeAddUserModal()" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-xl hover:bg-gray-300 transition duration-150">
                        Cancel
                    </button>
                    <button type="submit" id="submitBtn" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-xl shadow-lg transition duration-200 flex items-center justify-center">
                        <i class="fas fa-user-plus mr-2" id="submitIcon"></i>
                        <span id="submitText">Add User</span>
                        <div id="submitLoader" class="hidden spinner-border animate-spin inline-block w-4 h-4 border-2 rounded-full border-t-transparent ml-2" role="status"></div>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="deleteModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center transition-opacity duration-300">
    <div class="bg-white rounded-lg shadow-2xl max-w-md w-full mx-4 transform transition-transform duration-300 scale-95" id="deleteModalContent">
        <div class="p-6">
            <div class="flex items-center justify-center w-12 h-12 mx-auto bg-red-100 rounded-full mb-4">
                <i class="fas fa-exclamation-triangle text-red-600 text-2xl"></i>
            </div>
            <h3 class="text-xl font-bold text-gray-900 text-center mb-2">Delete User</h3>
            <p class="text-gray-600 text-center mb-6">
                Are you sure you want to delete user "<strong id="deleteUserName"></strong>"? 
                This action cannot be undone and will permanently remove all their associated records, including borrowings and reservations.
            </p>
            <div class="flex gap-3">
                <button onclick="closeDeleteModal()" 
                        class="flex-1 bg-gray-200 text-gray-700 px-4 py-3 rounded-lg hover:bg-gray-300 transition font-semibold">
                    Cancel
                </button>
                <form id="deleteForm" method="GET" class="flex-1">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" id="deleteUserIdInput">
                    <button type="submit" 
                            class="w-full bg-red-600 text-white px-4 py-3 rounded-lg hover:bg-red-700 transition font-semibold">
                        Delete
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<div id="roleModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center transition-opacity duration-300">
    <div class="bg-white rounded-lg shadow-2xl max-w-md w-full mx-4 transform transition-transform duration-300 scale-95" id="roleModalContent">
        <div class="p-6">
            <div class="flex items-center justify-center w-12 h-12 mx-auto bg-indigo-100 rounded-full mb-4">
                <i class="fas fa-user-tag text-indigo-600 text-2xl"></i>
            </div>
            <h3 class="text-xl font-bold text-gray-900 text-center mb-2">Change User Role</h3>
            <p class="text-gray-600 text-center mb-6">
                Set the new role for user "<strong id="roleUserName"></strong>".
            </p>
            
            <form id="roleForm" action="change_role_api.php" method="POST">
                <input type="hidden" name="user_id" id="roleUserIdInput">
                
                <div class="mb-6">
                    <label for="new_role" class="block text-sm font-medium text-gray-700 mb-2">Select New Role</label>
                    <select id="new_role" name="new_role" required
                        class="mt-1 block w-full pl-3 pr-10 py-3 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md shadow-sm transition">
                        <option value="admin">Admin</option>
                        <option value="librarian">Librarian</option>
                        <option value="member">Member</option>
                    </select>
                </div>

                <div class="flex gap-3">
                    <button type="button" onclick="closeRoleModal()" 
                            class="flex-1 bg-gray-200 text-gray-700 px-4 py-3 rounded-lg hover:bg-gray-300 transition font-semibold">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="flex-1 w-full bg-indigo-600 text-white px-4 py-3 rounded-lg hover:bg-indigo-700 transition font-semibold">
                        <i class="fas fa-save mr-2"></i> Save Role
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // ====================== GENERAL FUNCTIONS (from users.php) ======================
    // General function to toggle password visibility
    function togglePassword(inputId, iconId) {
        const passwordInput = document.getElementById(inputId);
        const toggleIcon = document.getElementById(iconId);
        
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            toggleIcon.classList.remove('fa-eye');
            toggleIcon.classList.add('fa-eye-slash');
        } else {
            passwordInput.type = 'password';
            toggleIcon.classList.remove('fa-eye-slash');
            toggleIcon.classList.add('fa-eye');
        }
    }
    
    // Helper to display a top-level dynamic alert (from users.php)
    function displayTopAlert(type, message) {
        const dynamicAlertContainer = document.getElementById('dynamicAlertContainer');
        // Clear old alerts
        dynamicAlertContainer.innerHTML = '';
        
        const alertHtml = `
            <div class="p-4 rounded-xl shadow-md text-sm transition-opacity duration-500 ease-in-out opacity-0 ${type === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}" role="alert">
                <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'} mr-2"></i>
                ${message}
                <button type="button" class="float-right text-lg -mt-1" onclick="this.parentNode.remove()">
                    &times;
                </button>
            </div>
        `;
        dynamicAlertContainer.innerHTML = alertHtml;
        const newAlert = dynamicAlertContainer.querySelector('[role="alert"]');
        
        // Use a timeout to trigger fade-in
        setTimeout(() => newAlert.style.opacity = '1', 50);

        // Auto-hide after 5 seconds
        setTimeout(() => {
            if(newAlert) {
                newAlert.style.opacity = '0';
                setTimeout(() => newAlert.remove(), 500);
            }
        }, 5000);
    }
    
    // ====================== ADD USER MODAL FUNCTIONS (from users.php) ======================
    const addUserModal = document.getElementById('addUserModal');
    const modalContent = document.getElementById('modalContent');
    const form = document.getElementById('addUserForm');
    const formMessage = document.getElementById('formMessage');
    const submitBtn = document.getElementById('submitBtn');
    const submitText = document.getElementById('submitText');
    const submitLoader = document.getElementById('submitLoader');

    document.getElementById('addUserBtn').addEventListener('click', openAddUserModal);

    function openAddUserModal() {
        addUserModal.classList.remove('hidden');
        // Trigger opacity/scale animation after display change
        setTimeout(() => {
            addUserModal.style.opacity = '1';
            modalContent.classList.remove('scale-95', 'opacity-0');
            modalContent.classList.add('scale-100', 'opacity-100');
        }, 10);
    }

    function closeAddUserModal() {
        addUserModal.style.opacity = '0';
        modalContent.classList.remove('scale-100', 'opacity-100');
        modalContent.classList.add('scale-95', 'opacity-0');
        setTimeout(() => {
            addUserModal.classList.add('hidden');
            form.reset(); // Clear the form on close
            formMessage.classList.add('hidden'); // Hide any form message
        }, 300); 
    }
    
    // Close modal when clicking outside (on the overlay)
    addUserModal.addEventListener('click', (e) => {
        if (e.target === addUserModal) {
            closeAddUserModal();
        }
    });

    // Helper to display messages inside the form
    function displayFormMessage(type, message) {
        formMessage.textContent = message;
        formMessage.classList.remove('hidden', 'bg-green-100', 'text-green-700', 'bg-red-100', 'text-red-700');
        if (type === 'success') {
            formMessage.classList.add('bg-green-100', 'text-green-700');
        } else {
            formMessage.classList.add('bg-red-100', 'text-red-700');
        }
    }

    // Handle AJAX form submission
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        // Client-side password validation
        const password = document.getElementById('password').value;
        const confirmPassword = document.getElementById('confirm_password').value;
        
        if (password !== confirmPassword) {
            displayFormMessage('error', 'Passwords do not match!');
            return;
        }

        // Show loading state
        submitBtn.disabled = true;
        submitText.classList.add('hidden');
        submitLoader.classList.remove('hidden');
        formMessage.classList.add('hidden');


        const formData = new FormData(form);
        
        try {
            // IMPORTANT: Make sure 'user_add_api.php' path is correct relative to the executed file!
            const response = await fetch('user_add_api.php', { 
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                displayTopAlert('success', data.message);
                closeAddUserModal();
                // BEST PRACTICE: Reload the user list to show the new user
                setTimeout(() => {
                    window.location.reload(); 
                }, 500); // Reload slightly after the alert appears
            } else {
                // Server-side validation error or other issue
                displayFormMessage('error', data.message);
            }
            
        } catch (error) {
            console.error('Submission Error:', error);
            displayFormMessage('error', 'Network error or connection failed. Please try again.');
        } finally {
            // Revert loading state
            submitBtn.disabled = false;
            submitText.classList.remove('hidden');
            submitLoader.classList.add('hidden');
        }
    });
    
    // ====================== DELETE MODAL FUNCTIONS (Existing in members.php) ======================

    function openDeleteModal(userId, fullName) {
        document.getElementById('deleteUserName').textContent = fullName;
        document.getElementById('deleteUserIdInput').value = userId;
        
        const modal = document.getElementById('deleteModal');
        const content = document.getElementById('deleteModalContent');
        
        // Show modal and reset state
        modal.classList.remove('hidden');
        setTimeout(() => {
            modal.style.opacity = '1';
            content.style.transform = 'scale(1)';
        }, 10);
    }

    function closeDeleteModal() {
        const modal = document.getElementById('deleteModal');
        const content = document.getElementById('deleteModalContent');
        
        // Start transition out
        modal.style.opacity = '0';
        content.style.transform = 'scale(0.95)';

        // Hide after transition
        setTimeout(() => {
            modal.classList.add('hidden');
        }, 300);
    }

    // ====================== ROLE MODAL FUNCTIONS (Existing in members.php) ======================

    function openRoleModal(userId, fullName, currentRole) {
        document.getElementById('roleUserName').textContent = fullName;
        document.getElementById('roleUserIdInput').value = userId;
        document.getElementById('new_role').value = currentRole; // Pre-select current role
        
        const modal = document.getElementById('roleModal');
        const content = document.getElementById('roleModalContent');
        
        // Show modal and reset state
        modal.classList.remove('hidden');
        setTimeout(() => {
            modal.style.opacity = '1';
            content.style.transform = 'scale(1)';
        }, 10);
    }

    function closeRoleModal() {
        const modal = document.getElementById('roleModal');
        const content = document.getElementById('roleModalContent');
        
        // Start transition out
        modal.style.opacity = '0';
        content.style.transform = 'scale(0.95)';

        // Hide after transition
        setTimeout(() => {
            modal.classList.add('hidden');
        }, 300);
    }

    // Close modals on escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeAddUserModal();
            closeDeleteModal();
            closeRoleModal();
        }
    });

    // Close modals on backdrop click
    document.getElementById('deleteModal').addEventListener('click', function(e) {
        if (e.target.id === 'deleteModal') {
            closeDeleteModal();
        }
    });
    document.getElementById('roleModal').addEventListener('click', function(e) {
        if (e.target.id === 'roleModal') {
            closeRoleModal();
        }
    });
</script>

<?php 
$users_stmt->close();
require_once '../includes/footer.php'; 
?>