<?php
/**
 * Edit Profile Page
 * Allows users to update username, avatar, email, etc.
 */
$pageTitle = 'Edit Profile';
require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/middleware.php';
requireAuth();

$user = Auth::user();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $currentAvatar = $user['avatar'];

    // Validate basic fields
    if (!$username) {
        $error = 'Username required.';
    } elseif (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Valid email required.';
    } else {
        $newAvatar = $currentAvatar;
        
        // Handle avatar upload
        if (!empty($_FILES['avatar_upload']['name']) && $_FILES['avatar_upload']['error'] === UPLOAD_ERR_OK) {
            // Define upload path
            $uploadDir = __DIR__ . '/../assets/images/avatars/';
            
            // Create directory if it doesn't exist
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            // Validate file size (max 2MB)
            if ($_FILES['avatar_upload']['size'] > 2 * 1024 * 1024) {
                $error = 'Avatar image must be less than 2MB';
            } else {
                // Generate unique filename
                $extension = strtolower(pathinfo($_FILES['avatar_upload']['name'], PATHINFO_EXTENSION));
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                
                if (in_array($extension, $allowedExtensions)) {
                    $filename = 'avatar_' . $user['id'] . '_' . time() . '.' . $extension;
                    $targetPath = $uploadDir . $filename;
                    
                    // Delete old avatar if it's a custom uploaded one (not default)
                    if (!empty($currentAvatar) && $currentAvatar !== 'default-avatar.png' && strpos($currentAvatar, 'avatar_' . $user['id']) === 0) {
                        $oldPath = $uploadDir . $currentAvatar;
                        if (file_exists($oldPath)) {
                            unlink($oldPath);
                        }
                    }
                    
                    if (move_uploaded_file($_FILES['avatar_upload']['tmp_name'], $targetPath)) {
                        $newAvatar = $filename;
                    } else {
                        $error = 'Failed to upload avatar';
                    }
                } else {
                    $error = 'Invalid image format. Allowed: JPG, PNG, GIF, WebP';
                }
            }
        }
        // Or use selected preset avatar
        elseif (!empty($_POST['avatar_preset'])) {
            $newAvatar = $_POST['avatar_preset'];
        }
        
        // Update profile if no errors
        if (empty($error)) {
            // Check if username is taken by another user
            $existingUser = Database::fetch(
                "SELECT id FROM users WHERE username = ? AND id != ?", 
                [$username, $user['id']]
            );
            
            if ($existingUser) {
                $error = 'Username already taken.';
            } else {
                // Check if email is taken by another user
                $existingEmail = Database::fetch(
                    "SELECT id FROM users WHERE email = ? AND id != ?", 
                    [$email, $user['id']]
                );
                
                if ($existingEmail) {
                    $error = 'Email already taken.';
                } else {
                    // Update fields
                    $res = Database::update(
                        "UPDATE users SET username=?, email=?, avatar=?, updated_at=NOW() WHERE id=?",
                        [$username, $email, $newAvatar, $user['id']]
                    );
                    $success = 'Profile updated successfully!';
                    $user = Database::fetch("SELECT * FROM users WHERE id = ?", [$user['id']]);
                    $_SESSION['user'] = $user;
                }
            }
        }
    }
}
?>

<div class="min-h-screen py-8 px-4 bg-gray-100">
    <div class="max-w-xl mx-auto">
        <h1 class="font-game text-3xl mb-6 gradient-text">👤 Edit Profile</h1>
        <?php if ($error): ?>
            <div class="bg-red-50 text-red-700 p-3 mb-3 rounded-lg"><?= $error ?></div>
        <?php elseif ($success): ?>
            <div class="bg-green-50 text-green-700 p-3 mb-3 rounded-lg"><?= $success ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="space-y-6">
            <?= csrfField() ?>
            
            <!-- Username -->
            <div>
                <label class="font-semibold text-gray-700 block mb-1">Username</label>
                <input type="text" name="username" required value="<?= e($user['username']) ?>"
                    pattern="[a-zA-Z0-9_]{3,20}"
                    title="3-20 characters, letters, numbers, and underscores only"
                    class="w-full px-4 py-2 border rounded-lg focus:border-blue-500 outline-none">
            </div>
            
            <!-- Email -->
            <div>
                <label class="font-semibold text-gray-700 block mb-1">Email</label>
                <input type="email" name="email" required value="<?= e($user['email']) ?>"
                    class="w-full px-4 py-2 border rounded-lg focus:border-blue-500 outline-none">
            </div>
            
            <!-- Avatar Section -->
            <div>
                <label class="font-semibold text-gray-700 block mb-2">Avatar</label>
                
                <!-- Current Avatar Preview -->
                <div class="text-center mb-4">
                    <img src="<?= baseUrl('/assets/images/avatars/' . ($user['avatar'] ?? 'default-avatar.png')) ?>" 
                         alt="Current Avatar" 
                         id="avatarPreview"
                         class="w-32 h-32 rounded-full object-cover mx-auto mb-4 border-4 border-gray-200"
                         onerror="this.src='<?= baseUrl('/assets/images/avatars/default-avatar.png') ?>'">
                </div>
                
                <!-- Upload Custom Avatar -->
                <div class="mb-4">
                    <label class="block text-sm font-semibold text-gray-600 mb-2">Upload Custom Avatar</label>
                    <div class="flex items-center gap-3">
                        <label class="cursor-pointer bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 inline-block transition-colors">
                            📷 Choose File
                            <input type="file" name="avatar_upload" accept="image/*" class="hidden" 
                                   onchange="previewAvatar(this)" id="avatarInput">
                        </label>
                        <span id="fileName" class="text-sm text-gray-500">No file chosen</span>
                    </div>
                    <p class="text-xs text-gray-500 mt-2">JPG, PNG, GIF or WebP. Max 2MB.</p>
                </div>
                
                <!-- OR Divider -->
                <div class="relative my-4">
                    <div class="absolute inset-0 flex items-center">
                        <div class="w-full border-t border-gray-300"></div>
                    </div>
                    <div class="relative flex justify-center text-sm">
                        <span class="px-2 bg-gray-100 text-gray-500">OR</span>
                    </div>
                </div>
                
                <!-- Choose from Preset Avatars -->
                <div>
                    <label class="block text-sm font-semibold text-gray-600 mb-2">Choose Preset Avatar</label>
                    <select name="avatar_preset" 
                            class="w-full px-4 py-2 border rounded-lg focus:border-blue-500 outline-none"
                            onchange="selectPresetAvatar(this)">
                        <option value="">-- Keep current avatar --</option>
                        <?php
                        $avatars = glob(__DIR__.'/../assets/images/avatars/*.png');
                        foreach ($avatars as $path):
                            $fname = basename($path);
                            // Skip user-uploaded avatars (they start with "avatar_")
                            if (strpos($fname, 'avatar_') === 0) continue;
                        ?>
                            <option value="<?= $fname ?>" <?= $user['avatar'] === $fname ? 'selected' : '' ?>>
                                <?= ucfirst(str_replace(['-', '.png'], [' ', ''], $fname)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <!-- Submit Button -->
            <button type="submit" class="w-full bg-friv-blue text-white font-bold py-3 rounded-xl hover:bg-blue-700 transition-colors">
                💾 Save Changes
            </button>
        </form>
    </div>
</div>

<script>
// Preview uploaded avatar
function previewAvatar(input) {
    const fileName = document.getElementById('fileName');
    const preview = document.getElementById('avatarPreview');
    
    if (input.files && input.files[0]) {
        // Update file name display
        fileName.textContent = input.files[0].name;
        
        // Check file size (2MB = 2 * 1024 * 1024 bytes)
        if (input.files[0].size > 2 * 1024 * 1024) {
            alert('File size must be less than 2MB');
            input.value = '';
            fileName.textContent = 'No file chosen';
            return;
        }
        
        // Preview the image
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
        };
        reader.readAsDataURL(input.files[0]);
        
        // Clear preset selection
        document.querySelector('select[name="avatar_preset"]').value = '';
    } else {
        fileName.textContent = 'No file chosen';
    }
}

// Preview preset avatar selection
function selectPresetAvatar(select) {
    if (select.value) {
        const preview = document.getElementById('avatarPreview');
        preview.src = '<?= baseUrl('/assets/images/avatars/') ?>' + select.value;
        
        // Clear file upload
        const fileInput = document.getElementById('avatarInput');
        fileInput.value = '';
        document.getElementById('fileName').textContent = 'No file chosen';
    }
}
</script>

<?php require_once __DIR__.'/../includes/footer.php'; ?>