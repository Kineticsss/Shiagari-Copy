<?php
require_once __DIR__ . '/config/session.php';

start_secure_session();
$csrfToken = csrf_token();

// If user is already logged in, redirect to dashboard
if (isset($_SESSION['uid'])) {
    header('Location: landing/landing.html');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SHIAGARI · Rise to the challenge</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(135deg, #0b1626 0%, #0a0f1c 100%);
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .landing-container {
            max-width: 1200px;
            width: 100%;
            margin: 0 auto;
        }

        /* Hero Section */
        .hero {
            text-align: center;
            margin-bottom: 60px;
        }

        .hero h1 {
            font-size: 72px;
            font-weight: 800;
            background: linear-gradient(135deg, #FFFFFF 0%, #94a3b8 50%, #3b82f6 100%);
            background-clip: text;
            -webkit-background-clip: text;
            color: transparent;
            margin-bottom: 16px;
            letter-spacing: -2px;
        }

        .hero p {
            font-size: 20px;
            color: #94a3b8;
            max-width: 600px;
            margin: 0 auto;
        }

        /* Cards Container */
        .cards-container {
            display: flex;
            gap: 32px;
            justify-content: center;
            flex-wrap: wrap;
            margin-bottom: 60px;
        }

        .card {
            background: #0f172a;
            border-radius: 32px;
            padding: 40px;
            width: 360px;
            text-align: center;
            border: 1px solid rgba(59, 130, 246, 0.2);
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .card:hover {
            transform: translateY(-8px);
            border-color: #3b82f6;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        }

        .card-icon {
            width: 80px;
            height: 80px;
            background: rgba(59, 130, 246, 0.15);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
        }

        .card-icon i {
            font-size: 40px;
            color: #3b82f6;
        }

        .card h2 {
            font-size: 28px;
            font-weight: 700;
            color: #e2e8f0;
            margin-bottom: 12px;
        }

        .card p {
            color: #94a3b8;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 24px;
        }

        .card-btn {
            background: transparent;
            border: 1px solid #3b82f6;
            padding: 12px 24px;
            border-radius: 40px;
            color: #3b82f6;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 14px;
        }

        .card:hover .card-btn {
            background: #3b82f6;
            color: white;
        }

        /* Stats Section */
        .stats {
            display: flex;
            justify-content: center;
            gap: 60px;
            flex-wrap: wrap;
            padding-top: 40px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .stat-item {
            text-align: center;
        }

        .stat-number {
            font-size: 36px;
            font-weight: 800;
            color: #3b82f6;
            display: block;
        }

        .stat-label {
            font-size: 14px;
            color: #6b7280;
        }

        /* Footer */
        .footer {
            text-align: center;
            margin-top: 60px;
            color: #6b7280;
            font-size: 12px;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.9);
            backdrop-filter: blur(8px);
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: #0f172a;
            border-radius: 32px;
            width: 90%;
            max-width: 440px;
            padding: 40px;
            border: 1px solid #2d3a5e;
            animation: slideUp 0.3s ease;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            text-align: center;
            margin-bottom: 32px;
        }

        .modal-header h2 {
            font-size: 28px;
            font-weight: 700;
            color: #e2e8f0;
        }

        .modal-header p {
            color: #6b7280;
            font-size: 14px;
            margin-top: 8px;
        }

        .input-group {
            margin-bottom: 20px;
        }

        .password-group {
            position: relative;
        }

        .input-group input {
            width: 100%;
            padding: 14px 16px;
            background: #1e293b;
            border: 1px solid #2d3a5e;
            border-radius: 16px;
            color: white;
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            transition: 0.2s;
        }

        .input-group input:focus {
            outline: none;
            border-color: #3b82f6;
        }

        .password-group input {
            padding-right: 48px;
        }

        .password-toggle {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            width: 28px;
            height: 28px;
            border: 0;
            background: transparent;
            color: #94a3b8;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
        }

        .password-toggle:hover,
        .password-toggle:focus {
            color: #e2e8f0;
            outline: none;
        }

        .modal-btn {
            width: 100%;
            padding: 14px;
            background: #3b82f6;
            border: none;
            border-radius: 16px;
            color: white;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            transition: 0.2s;
        }

        .modal-btn:hover {
            background: #2563eb;
        }

        .modal-switch {
            text-align: center;
            margin-top: 24px;
            color: #6b7280;
            font-size: 14px;
        }

        .modal-switch a {
            color: #3b82f6;
            text-decoration: none;
            cursor: pointer;
        }

        .close-modal {
            position: absolute;
            top: 20px;
            right: 30px;
            font-size: 40px;
            color: #94a3b8;
            cursor: pointer;
        }

        .close-modal:hover {
            color: white;
        }

        @media (max-width: 768px) {
            .hero h1 {
                font-size: 48px;
            }
            .card {
                width: 100%;
                max-width: 360px;
            }
        }
    </style>
</head>
<body>

<div class="landing-container">
    <!-- Hero Section -->
    <div class="hero">
        <h1>SHIAGARI</h1>
        <p>Rise to the challenge. A modern productivity suite for creative teams.</p>
    </div>

    <!-- Cards -->
    <div class="cards-container">
        <div class="card" id="loginCard">
            <div class="card-icon">
                <i class="fas fa-sign-in-alt"></i>
            </div>
            <h2>Welcome Back</h2>
            <p>Sign in to access your projects, ideas, and team workspace.</p>
            <button class="card-btn">Login →</button>
        </div>

        <div class="card" id="signupCard">
            <div class="card-icon">
                <i class="fas fa-user-plus"></i>
            </div>
            <h2>Get Started</h2>
            <p>Create a free account and start organizing your creative workflow.</p>
            <button class="card-btn">Sign Up →</button>
        </div>
    </div>

    <!-- Stats -->
    <div class="stats">
        <div class="stat-item">
            <span class="stat-number">6</span>
            <span class="stat-label">Modules</span>
        </div>
        <div class="stat-item">
            <span class="stat-number">∞</span>
            <span class="stat-label">Ideas</span>
        </div>
        <div class="stat-item">
            <span class="stat-number">100%</span>
            <span class="stat-label">Focus</span>
        </div>
    </div>

    <div class="footer">
        <p>© 2026 SHIAGARI · Rise to the challenge</p>
    </div>
</div>

<!-- Login Modal -->
<div id="loginModal" class="modal">
    <span class="close-modal" onclick="closeModals()">&times;</span>
    <div class="modal-content">
        <div class="modal-header">
            <h2>Welcome Back</h2>
            <p>Sign in to continue to SHIAGARI</p>
        </div>
        <form id="loginForm">
            <div class="input-group">
                <input type="text" id="loginEmail" placeholder="Email or Username" required>
            </div>
            <div class="input-group">
                <input type="password" id="loginPassword" placeholder="Password" required>
            </div>
            <button type="submit" class="modal-btn">Login</button>
        </form>
        <div class="modal-switch">
            Don't have an account? <a onclick="switchToSignup()">Sign up</a>
        </div>
    </div>
</div>

<!-- Signup Modal -->
<div id="signupModal" class="modal">
    <span class="close-modal" onclick="closeModals()">&times;</span>
    <div class="modal-content">
        <div class="modal-header">
            <h2>Create Account</h2>
            <p>Join SHIAGARI and start creating</p>
        </div>
        <form id="signupForm">
            <div class="input-group">
                <input type="text" id="signupFullName" placeholder="Full Name" required>
            </div>
            <div class="input-group">
                <input type="text" id="signupUsername" placeholder="Username" required>
            </div>
            <div class="input-group">
                <input type="email" id="signupEmail" placeholder="Email" required>
            </div>
            <div class="input-group password-group">
                <input type="password" id="signupPassword" placeholder="Password" required>
                <button type="button" class="password-toggle" data-target="signupPassword" aria-label="Show password">
                    <i class="fas fa-eye"></i>
                </button>
            </div>
            <div class="input-group password-group">
                <input type="password" id="signupConfirmPassword" placeholder="Confirm Password" required>
                <button type="button" class="password-toggle" data-target="signupConfirmPassword" aria-label="Show confirm password">
                    <i class="fas fa-eye"></i>
                </button>
            </div>
            <button type="submit" class="modal-btn">Create Account</button>
        </form>
        <div class="modal-switch">
            Already have an account? <a onclick="switchToLogin()">Login</a>
        </div>
    </div>
</div>

<script>
    const csrfToken = <?= json_encode($csrfToken, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

    // Modal elements
    const loginModal = document.getElementById('loginModal');
    const signupModal = document.getElementById('signupModal');

    // Open modals
    document.getElementById('loginCard').onclick = () => {
        loginModal.classList.add('active');
    };

    document.getElementById('signupCard').onclick = () => {
        signupModal.classList.add('active');
    };

    function closeModals() {
        loginModal.classList.remove('active');
        signupModal.classList.remove('active');
    }

    function switchToSignup() {
        loginModal.classList.remove('active');
        signupModal.classList.add('active');
    }

    function switchToLogin() {
        signupModal.classList.remove('active');
        loginModal.classList.add('active');
    }

    // Close modal on escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeModals();
        }
    });

    // Close modal on outside click
    window.onclick = (e) => {
        if (e.target === loginModal) loginModal.classList.remove('active');
        if (e.target === signupModal) signupModal.classList.remove('active');
    };

    document.querySelectorAll('.password-toggle').forEach((button) => {
        button.addEventListener('click', () => {
            const input = document.getElementById(button.dataset.target);
            const icon = button.querySelector('i');
            const isHidden = input.type === 'password';

            input.type = isHidden ? 'text' : 'password';
            icon.className = isHidden ? 'fas fa-eye-slash' : 'fas fa-eye';
            button.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
        });
    });

    async function submitAuthForm(endpoint, payload, button) {
        const originalText = button.textContent;
        button.disabled = true;
        button.textContent = 'Please wait...';

        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                credentials: 'same-origin',
                body: JSON.stringify({ ...payload, csrf_token: csrfToken })
            });

            const result = await response.json().catch(() => ({
                success: false,
                error: 'Unexpected server response.'
            }));

            if (!response.ok || !result.success) {
                throw new Error(result.error || 'Authentication failed.');
            }

            if (endpoint.includes('auth/register.php')) {
                // Store in localStorage for frontend use
                localStorage.setItem('shiagari_user_name', payload.full_name);
                localStorage.setItem('shiagari_user_full_name', payload.full_name);
                localStorage.setItem('shiagari_user_username', payload.username);
                localStorage.setItem('shiagari_user_email', payload.email);
                localStorage.setItem('shiagari_user_member_since', new Date().toLocaleString('default', { month: 'long', year: 'numeric' }));
            } else if (endpoint.includes('auth/login.php')) {
                // For login: backend will sync to DB, frontend uses localStorage as fallback
                // These get populated from server response via session
                localStorage.setItem('shiagari_user_email', payload.email);
            }

            window.location.href = result.redirect || 'landing/landing.html';
        } catch (error) {
            alert(error.message);
        } finally {
            button.disabled = false;
            button.textContent = originalText;
        }
    }

    // Handle Login
    document.getElementById('loginForm').onsubmit = async (e) => {
        e.preventDefault();
        const button = e.submitter || document.querySelector('#loginForm button[type="submit"]');
        await submitAuthForm('auth/login.php', {
            email: document.getElementById('loginEmail').value.trim(),
            password: document.getElementById('loginPassword').value
        }, button);
    };

    // Handle Signup
    document.getElementById('signupForm').onsubmit = async (e) => {
        e.preventDefault();
        const button = e.submitter || document.querySelector('#signupForm button[type="submit"]');
        await submitAuthForm('auth/register.php', {
            full_name: document.getElementById('signupFullName').value.trim(),
            username: document.getElementById('signupUsername').value.trim(),
            email: document.getElementById('signupEmail').value.trim(),
            password: document.getElementById('signupPassword').value,
            confirm_password: document.getElementById('signupConfirmPassword').value
        }, button);
    };
</script>

</body>
</html>
