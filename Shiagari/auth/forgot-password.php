<?php
require_once __DIR__ . '/../config/session.php';

start_secure_session();
$csrfToken = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password · SHIAGARI</title>
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

        .auth-container {
            width: 100%;
            max-width: 420px;
            background: #0f172a;
            border-radius: 24px;
            padding: 40px;
            border: 1px solid rgba(59, 130, 246, 0.2);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.4);
        }

        .auth-header {
            text-align: center;
            margin-bottom: 32px;
        }

        .auth-header h1 {
            font-size: 28px;
            font-weight: 700;
            background: linear-gradient(135deg, #FFFFFF 0%, #3b82f6 100%);
            background-clip: text;
            -webkit-background-clip: text;
            color: transparent;
            margin-bottom: 8px;
        }

        .auth-header p {
            color: #94a3b8;
            font-size: 14px;
            line-height: 1.5;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            color: #e2e8f0;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 8px;
        }

        input {
            width: 100%;
            padding: 12px 16px;
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 12px;
            color: #e2e8f0;
            font-size: 14px;
            font-family: 'Inter', sans-serif;
            transition: all 0.3s ease;
        }

        input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            background: #0f172a;
        }

        input::placeholder {
            color: #64748b;
        }

        .btn-primary {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #3b82f6 0%, #1e40af 100%);
            border: none;
            border-radius: 12px;
            color: white;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(59, 130, 246, 0.4);
        }

        .btn-primary:active {
            transform: translateY(0);
        }

        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .spinner {
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            display: none;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .btn-primary:disabled .spinner {
            display: block;
        }

        .btn-primary:disabled .btn-text {
            display: none;
        }

        .message {
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 14px;
            display: none;
        }

        .message.success {
            background: rgba(34, 197, 94, 0.15);
            border: 1px solid rgba(34, 197, 94, 0.3);
            color: #86efac;
            display: block;
        }

        .message.error {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
            display: block;
        }

        .message.info {
            background: rgba(59, 130, 246, 0.15);
            border: 1px solid rgba(59, 130, 246, 0.3);
            color: #93c5fd;
            display: block;
        }

        .auth-links {
            text-align: center;
            margin-top: 24px;
            display: flex;
            gap: 8px;
            justify-content: center;
            font-size: 14px;
        }

        .auth-links a {
            color: #3b82f6;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .auth-links a:hover {
            color: #60a5fa;
            text-decoration: underline;
        }

        .auth-links span {
            color: #64748b;
        }

        .back-link {
            margin-top: 16px;
            text-align: center;
        }

        .back-link a {
            color: #94a3b8;
            text-decoration: none;
            font-size: 13px;
            transition: color 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .back-link a:hover {
            color: #cbd5e1;
        }

        .icon-wrap {
            width: 60px;
            height: 60px;
            background: rgba(59, 130, 246, 0.15);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            font-size: 28px;
            color: #3b82f6;
        }

        .success-state {
            display: none;
            text-align: center;
        }

        .success-state.active {
            display: block;
        }

        .form-state {
            display: block;
        }

        .form-state.hidden {
            display: none;
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="form-state" id="formState">
            <div class="auth-header">
                <h1>Reset Password</h1>
                <p>Enter your email address and we'll send you a link to reset your password.</p>
            </div>

            <div class="message" id="messageBox"></div>

            <form id="forgotPasswordForm">
                <div class="form-group">
                    <label for="email"><i class="fas fa-envelope"></i> Email Address</label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        placeholder="you@example.com" 
                        required
                        autocomplete="email"
                    >
                </div>

                <button type="submit" class="btn-primary" id="submitBtn">
                    <span class="spinner"></span>
                    <span class="btn-text">Send Reset Email</span>
                </button>
            </form>

            <div class="auth-links">
                <span>Remember your password?</span>
                <a href="../index.php">Sign In</a>
            </div>

            <div class="back-link">
                <a href="../index.php"><i class="fas fa-arrow-left"></i> Back to Home</a>
            </div>
        </div>

        <div class="success-state" id="successState">
            <div class="icon-wrap">
                <i class="fas fa-check-circle"></i>
            </div>
            <h2 style="color: #e2e8f0; margin-bottom: 8px;">Check Your Email</h2>
            <p style="color: #94a3b8; margin-bottom: 24px;">
                We've sent a password reset link to <strong id="confirmEmail" style="color: #cbd5e1;"></strong>. 
                Click the link in the email to reset your password.
            </p>
            <p style="color: #64748b; font-size: 13px; margin-bottom: 24px;">
                Didn't receive the email? Check your spam folder or <a href="#" id="resendBtn" style="color: #3b82f6; text-decoration: none;">try again</a>.
            </p>
            <a href="../index.php" class="btn-primary" style="text-decoration: none;">
                <i class="fas fa-sign-in-alt"></i> Back to Sign In
            </a>
        </div>
    </div>

    <script>
        const csrfToken = <?= json_encode($csrfToken, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    </script>
    <script src="forgot-password.js"></script>
</body>
</html>
