document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('forgotPasswordForm');
    const emailInput = document.getElementById('email');
    const messageBox = document.getElementById('messageBox');
    const submitBtn = document.getElementById('submitBtn');
    const formState = document.getElementById('formState');
    const successState = document.getElementById('successState');
    const confirmEmail = document.getElementById('confirmEmail');
    const resendBtn = document.getElementById('resendBtn');

    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        await sendResetEmail();
    });

    resendBtn.addEventListener('click', async function(e) {
        e.preventDefault();
        resetForm();
    });

    async function sendResetEmail() {
        const email = emailInput.value.trim();

        // Clear previous messages
        messageBox.className = 'message';
        messageBox.textContent = '';

        // Validate email
        if (!email) {
            showMessage('Please enter your email address.', 'error');
            return;
        }

        if (!isValidEmail(email)) {
            showMessage('Please enter a valid email address.', 'error');
            return;
        }

        // Disable button
        submitBtn.disabled = true;

        try {
            // Send reset request
            const response = await fetch('../auth/forgot-password.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    email: email,
                    csrf_token: csrfToken,
                }),
            });

            const data = await response.json();

            if (data.success) {
                // Show success state
                confirmEmail.textContent = email;
                formState.classList.add('hidden');
                successState.classList.add('active');
            } else {
                showMessage(data.error || 'Failed to send reset email.', 'error');
                submitBtn.disabled = false;
            }
        } catch (error) {
            console.error('Error:', error);
            showMessage('An error occurred. Please try again.', 'error');
            submitBtn.disabled = false;
        }
    }

    function resetForm() {
        form.reset();
        messageBox.className = 'message';
        messageBox.textContent = '';
        submitBtn.disabled = false;
        formState.classList.remove('hidden');
        successState.classList.remove('active');
        emailInput.focus();
    }

    function showMessage(text, type) {
        messageBox.textContent = text;
        messageBox.className = `message ${type}`;
    }

    function isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }
});
