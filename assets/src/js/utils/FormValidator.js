class FormValidator {
    validateLogin(username, password) {
        return username.trim() !== '' && password.trim() !== '';
    }

    validateRegistration(data) {
        // Check required fields
        if (!data.user_login || data.user_login.trim() === '') {
            return false;
        }

        if (!data.user_email || !this.validateEmail(data.user_email)) {
            return false;
        }

        if (!data.user_pass || data.user_pass.length < 6) {
            return false;
        }

        return true;
    }

    validateEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    validatePhone(phone) {
        // Remove all non-numeric characters except +
        const cleaned = phone.replace(/[^\d+]/g, '');

        // Check for international format
        if (/^\+[1-9]\d{10,14}$/.test(cleaned)) {
            return true;
        }

        // Check for US format without country code
        if (/^\d{10}$/.test(cleaned)) {
            return true;
        }

        return false;
    }

    validateCode(code) {
        return /^\d{6}$/.test(code);
    }

    validatePassword(password) {
        return password.length >= 6;
    }

    validateUsername(username) {
        // WordPress username requirements
        const usernameRegex = /^[a-zA-Z0-9._-]+$/;
        return username.length >= 3 && username.length <= 60 && usernameRegex.test(username);
    }

    sanitizeInput(input) {
        return input.trim().replace(/[<>]/g, '');
    }

    getPasswordStrength(password) {
        let strength = 0;

        if (password.length >= 8) strength++;
        if (/[a-z]/.test(password)) strength++;
        if (/[A-Z]/.test(password)) strength++;
        if (/[0-9]/.test(password)) strength++;
        if (/[^A-Za-z0-9]/.test(password)) strength++;

        const levels = ['Very Weak', 'Weak', 'Fair', 'Good', 'Strong'];
        return {
            score: strength,
            text: levels[Math.min(strength, 4)]
        };
    }
}

export default FormValidator;