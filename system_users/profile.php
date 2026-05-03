<?php
require_once __DIR__ . '/../global/header.php';
require_once __DIR__ . '/../config/db_connection.php';

// Fetch User Data
$userId = (int)($_SESSION['user_id'] ?? 0);

$stmt = $conn->prepare('SELECT * FROM users WHERE user_id = :id LIMIT 1');
$stmt->execute([':id' => $userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

// Check for feedback messages
// Feedback is shown via SweetAlert2 toast in global/header.php (flash_toast)

$profilePicture = (string)($user['profile_picture'] ?? '');
if ($profilePicture !== '') {
    $profilePicture = ltrim($profilePicture, '/');
    if (str_starts_with($profilePicture, 'uploads/')) {
        $displayPic = BASE_URL . $profilePicture;
    } else {
        // If DB only stores filename, assume it belongs to uploads/profile_pic/
        $displayPic = BASE_URL . 'uploads/profile_pic/' . $profilePicture;
    }
} else {
    $displayPic = '';
}

$fullName = trim(((string)($user['first_name'] ?? '')) . ' ' . ((string)($user['last_name'] ?? '')) . ' ' . ((string)($user['name_extension'] ?? '')));
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />

<style>
    body { font-family: 'Poppins', sans-serif; background-color: #f8f9fa; }
    #wrapper { overflow-x: hidden; }
    #page-content-wrapper { min-width: 100vw; }
    .cursor-pointer { cursor: pointer; }

    .profile-section-card .card-header {
        border-bottom: 0;
        padding: 18px 22px;
        font-weight: 600;
        font-size: 1.05rem;
    }
    .profile-section-card .card-body {
        padding: 22px;
    }
    .profile-avatar {
        width: 160px;
        height: 160px;
        border-radius: 50%;
        border: 6px solid #fff;
        object-fit: cover;
        background: #e9ecef;
        box-shadow: 0 10px 25px rgba(0,0,0,0.10);
    }
	.profile-avatar-placeholder {
		display: flex;
		align-items: center;
		justify-content: center;
	}

    .pw-wrap { position: relative; }
    .pw-requirements {
        position: absolute;
        left: 0;
        top: calc(100% + 10px);
        transform: none;
        width: 340px;
        max-width: calc(100vw - 48px);
        z-index: 10;
        display: none;
    }
    .pw-requirements .card { border: 1px solid rgba(0,0,0,0.1); }
    .pw-requirements .card-body { max-height: 240px; overflow: auto; }
    .pw-requirements .title { font-weight: 600; margin-bottom: 6px; }
    .pw-requirements ul { margin: 0; padding-left: 18px; }
    .pw-requirements li { margin: 4px 0; }
    .pw-requirements::after {
        content: '';
        position: absolute;
        left: 28px;
        top: -8px;
        bottom: auto;
        width: 16px;
        height: 16px;
        background: #fff;
        border-left: 1px solid rgba(0,0,0,0.1);
        border-top: 1px solid rgba(0,0,0,0.1);
        transform: rotate(45deg);
    }
    
    #reportrange {
        min-width: 280px;
        white-space: nowrap;
        overflow: hidden;
    }

    #date-display {
        display: inline-block;
        max-width: 220px;
        text-overflow: ellipsis;
        overflow: hidden;
        vertical-align: middle;
    }

    @media (min-width: 768px) {
        #page-content-wrapper { min-width: 0; width: 100%; }
    }
</style>


<div class="d-flex" id="wrapper">
    <?php include __DIR__ . '/../global/sidebar.php'; ?>

    <div id="page-content-wrapper" class="w-100">
        <div class="container-fluid py-4 py-md-5">
            <div class="container">
                <div class="row g-4 align-items-start">
                    <!-- Basic Information -->
                    <div class="col-12 col-lg-7">
                        <div class="card shadow-sm profile-section-card">
                            <div class="card-header bg-success text-white">Basic Information</div>
                            <div class="card-body">
                                    <div class="d-flex justify-content-center mb-4">
                                        <div style="position: relative; width: 160px; height: 160px;">
                                            <img
                                                id="profileAvatarImg"
                                                src="<?php echo $displayPic !== '' ? htmlspecialchars($displayPic, ENT_QUOTES, 'UTF-8') : ''; ?>"
                                                alt="Profile"
                                                class="profile-avatar"
                                                style="display: <?php echo $displayPic !== '' ? 'block' : 'none'; ?>;"
                                            >
                                            <div
                                                id="profileAvatarPlaceholder"
                                                class="profile-avatar profile-avatar-placeholder"
                                                style="display: <?php echo $displayPic === '' ? 'flex' : 'none'; ?>;"
                                                aria-hidden="true"
                                            >
                                                <span class="material-symbols-outlined" style="font-size: 72px; color: rgba(0,0,0,0.35);">account_circle</span>
                                            </div>
                                        </div>
                                    </div>

                                <form action="../backend/users/unified_users_process.php" method="POST" enctype="multipart/form-data">
                                    <input type="hidden" name="action" value="update_profile">

                                    <div class="row g-3">
                                        <div class="col-12 col-md-4">
                                            <label class="form-label fw-semibold">Full Name<span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" name="display_name" value="<?php echo htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8'); ?>" readonly>
                                        </div>

                                        <div class="col-12 col-md-8">
                                            <label class="form-label fw-semibold">Photo <span class="text-muted" style="font-weight:400;">(The image size should be any square image)</span></label>
                                            <input type="file" class="form-control" name="profile_pic" id="profilePicInput" accept="image/*">
                                        </div>

                                        <div class="col-12 col-md-4">
                                            <label class="form-label fw-semibold">Current Email Address (For Recovery)</label>
                                            <input type="email" class="form-control" value="<?php echo htmlspecialchars((string)($user['recovery_email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" readonly>
                                        </div>

                                        <div class="col-12 col-md-8">
                                            <label class="form-label fw-semibold">Email Address (For Recovery)</label>
                                            <input type="email" class="form-control" name="recovery_email" value="">
                                        </div>
                                    </div>

                                    <div class="d-flex justify-content-center mt-4">
                                        <button type="submit" class="btn btn-success px-4">Update information</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Password -->
                    <div class="col-12 col-lg-5">
                        <div class="card shadow-sm profile-section-card">
                            <div class="card-header bg-success text-white">Password</div>
                            <div class="card-body">
                                <form action="../backend/users/unified_users_process.php" method="POST" autocomplete="off">
                                    <input type="hidden" name="action" value="change_password">

                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">Current password</label>
                                        <div class="input-group">
                                            <input type="password" class="form-control" name="current_password" id="current_password" required>
                                            <button class="btn btn-outline-secondary" type="button" data-toggle-password="current_password" aria-label="Toggle current password">
                                                <span class="material-symbols-outlined">visibility</span>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="mb-3 pw-wrap">
                                        <div class="pw-requirements" id="pwRequirements">
                                            <div class="card shadow-sm">
                                                <div class="card-body">
                                                    <div class="title">Password Requirements</div>
                                                    <div class="text-muted" style="font-size: 0.95rem;">Your password should be:</div>
                                                    <ul class="mt-2" style="font-size: 0.95rem;">
                                                        <li>At least 8 characters long</li>
                                                        <li>Should contain at least 1 special character</li>
                                                        <li>Should contain at least 1 lower case character</li>
                                                        <li>Should contain at least 1 upper case character</li>
                                                        <li>Should contain at least 1 number</li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>

                                        <label class="form-label fw-semibold">New password</label>
                                        <div class="input-group">
                                            <input type="password" class="form-control" name="new_password" id="new_password" required>
                                            <button class="btn btn-outline-secondary" type="button" data-toggle-password="new_password" aria-label="Toggle new password">
                                                <span class="material-symbols-outlined">visibility</span>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="mb-4">
                                        <label class="form-label fw-semibold">Confirm new password</label>
                                        <div class="input-group">
                                            <input type="password" class="form-control" name="confirm_password" id="confirm_password" required>
                                            <button class="btn btn-outline-secondary" type="button" data-toggle-password="confirm_password" aria-label="Toggle confirm password">
                                                <span class="material-symbols-outlined">visibility</span>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="d-flex justify-content-center">
                                        <button type="submit" class="btn btn-success px-4">Update password</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        const newPw = document.getElementById('new_password');
        const req = document.getElementById('pwRequirements');
        const positionRequirements = () => {
            if (!newPw || !req) return;
            req.style.left = '0px';
            req.style.right = '';
            req.style.display = 'block';

            const margin = 12;

            // Keep within viewport horizontally.
            const nextRect = req.getBoundingClientRect();
            if (nextRect.right > window.innerWidth - margin) {
                const shift = nextRect.right - (window.innerWidth - margin);
                const currentLeft = parseFloat(req.style.left || '0');
                req.style.left = Math.max(-((nextRect.left - margin)), currentLeft - shift) + 'px';
            }
            if (nextRect.left < margin) {
                const shift = margin - nextRect.left;
                const currentLeft = parseFloat(req.style.left || '0');
                req.style.left = (currentLeft + shift) + 'px';
            }
        };

        if (newPw && req) {
            newPw.addEventListener('focus', positionRequirements);
            newPw.addEventListener('input', positionRequirements);
            window.addEventListener('resize', () => {
                if (req.style.display === 'block') positionRequirements();
            });
            newPw.addEventListener('blur', () => {
                req.style.display = 'none';
            });
        }

        // Live preview for chosen profile picture
        const picInput = document.getElementById('profilePicInput');
        const avatarImg = document.getElementById('profileAvatarImg');
        const avatarPlaceholder = document.getElementById('profileAvatarPlaceholder');
        let objectUrl = null;
        if (picInput && avatarImg && avatarPlaceholder) {
            picInput.addEventListener('change', () => {
                const file = picInput.files && picInput.files[0] ? picInput.files[0] : null;
                if (!file) return;
                if (objectUrl) URL.revokeObjectURL(objectUrl);
                objectUrl = URL.createObjectURL(file);
                avatarImg.src = objectUrl;
                avatarImg.style.display = 'block';
                avatarPlaceholder.style.display = 'none';
            });
            window.addEventListener('beforeunload', () => {
                if (objectUrl) URL.revokeObjectURL(objectUrl);
            });
        }

        const toggleButtons = document.querySelectorAll('[data-toggle-password]');
        toggleButtons.forEach((btn) => {
            btn.addEventListener('click', () => {
                const id = btn.getAttribute('data-toggle-password');
                const input = id ? document.getElementById(id) : null;
                const icon = btn.querySelector('.material-symbols-outlined');
                if (!input) return;
                const next = input.type === 'password' ? 'text' : 'password';
                input.type = next;
                if (icon) icon.textContent = next === 'password' ? 'visibility' : 'visibility_off';
            });
        });
    })();
</script>

