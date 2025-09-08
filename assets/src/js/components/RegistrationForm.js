import APIService from '../services/APIService';
import FormValidator from '../utils/FormValidator';
import UIManager from '../utils/UIManager';

class AURRegistrationForm {
    constructor(container) {
        this.container = container;
        this.apiService = new APIService();
        this.validator = new FormValidator();
        this.ui = new UIManager(container);

        this.attributes = JSON.parse(container.dataset.attributes || '{}');
        this.userStatus = JSON.parse(container.dataset.userStatus || '{}');
        this.availableFields = JSON.parse(container.dataset.availableFields || '[]');

        this.currentStep = this.userStatus.step || 'login';
        this.currentUser = null;

        this.init();
    }

    init() {
        this.bindEvents();
        this.checkEmailVerification();
        this.renderCurrentStep();
    }

    bindEvents() {
        // Login form events
        const loginForm = this.container.querySelector('#aur-login-form');
        const loginSubmit = this.container.querySelector('#aur-login-submit');
        const showRegister = this.container.querySelector('#aur-show-register');

        if (loginSubmit) {
            loginSubmit.addEventListener('click', (e) => {
                e.preventDefault();
                this.handleLogin();
            });
        }

        if (showRegister) {
            showRegister.addEventListener('click', () => {
                this.showStep('register');
            });
        }

        // Registration form events
        const registerSubmit = this.container.querySelector('#aur-register-submit');
        const showLogin = this.container.querySelector('#aur-show-login');

        if (registerSubmit) {
            registerSubmit.addEventListener('click', (e) => {
                e.preventDefault();
                this.handleRegister();
            });
        }

        if (showLogin) {
            showLogin.addEventListener('click', () => {
                this.showStep('login');
            });
        }

        // Email verification events
        const sendEmailCode = this.container.querySelector('#aur-send-email-code');
        const verifyEmailCode = this.container.querySelector('#aur-verify-email-code');
        const resendEmailCode = this.container.querySelector('#aur-resend-email-code');

        if (sendEmailCode) {
            sendEmailCode.addEventListener('click', () => this.sendEmailVerification());
        }

        if (verifyEmailCode) {
            verifyEmailCode.addEventListener('click', () => this.verifyEmailCode());
        }

        if (resendEmailCode) {
            resendEmailCode.addEventListener('click', () => this.sendEmailVerification());
        }

        // Phone verification events
        const sendPhoneCode = this.container.querySelector('#aur-send-phone-code');
        const verifyPhoneCode = this.container.querySelector('#aur-verify-phone-code');
        const resendPhoneCode = this.container.querySelector('#aur-resend-phone-code');
        const skipPhone = this.container.querySelector('#aur-skip-phone');
        const skipPhoneVerification = this.container.querySelector('#aur-skip-phone-verification');

        if (sendPhoneCode) {
            sendPhoneCode.addEventListener('click', () => this.sendPhoneVerification());
        }

        if (verifyPhoneCode) {
            verifyPhoneCode.addEventListener('click', () => this.verifyPhoneCode());
        }

        if (resendPhoneCode) {
            resendPhoneCode.addEventListener('click', () => this.sendPhoneVerification());
        }

        if (skipPhone) {
            skipPhone.addEventListener('click', () => this.skipPhoneVerification());
        }

        if (skipPhoneVerification) {
            skipPhoneVerification.addEventListener('click', () => this.skipPhoneVerification());
        }

        // Profile update events
        const updateProfile = this.container.querySelector('#aur-update-profile');
        if (updateProfile) {
            updateProfile.addEventListener('click', () => this.updateProfile());
        }

        // Enter key handling for verification codes
        const emailCodeInput = this.container.querySelector('#aur-email-code');
        const phoneCodeInput = this.container.querySelector('#aur-phone-code');

        if (emailCodeInput) {
            emailCodeInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    this.verifyEmailCode();
                }
            });
        }

        if (phoneCodeInput) {
            phoneCodeInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    this.verifyPhoneCode();
                }
            });
        }
    }

    checkEmailVerification() {
        // Check if email was verified via link
        if (window.aurEmailVerified) {
            this.ui.showSuccess('Email verified successfully!');
            this.userStatus.email_confirmed = true;
            this.userStatus.needs_email_verification = false;
            this.currentStep = this.determineNextStep();
            setTimeout(() => this.renderCurrentStep(), 2000);
        } else if (window.aurEmailVerificationFailed) {
            this.ui.showError('Email verification failed. The link may be expired.');
        }
    }

    async handleLogin() {
        const username = this.container.querySelector('#aur-login-username').value;
        const password = this.container.querySelector('#aur-login-password').value;

        if (!this.validator.validateLogin(username, password)) {
            this.ui.showError('Please enter both username and password.');
            return;
        }

        this.ui.showLoading();

        try {
            const response = await this.apiService.login(username, password);

            this.currentUser = response.data;
            this.userStatus = {
                logged_in: true,
                user_id: response.data.user_id,
                email_confirmed: response.data.email_confirmed === '1',
                phone_confirmed: response.data.phone_confirmed === '1',
                needs_email_verification: response.data.needs_email_verification === '1'
            };

            this.currentStep = this.determineNextStep();
            this.ui.showSuccess('Login successful!');

            setTimeout(() => {
                this.renderCurrentStep();
            }, 1000);

        } catch (error) {
            this.ui.showError(error.message || 'Login failed. Please try again.');
        }
    }

    async handleRegister() {
        const formData = this.collectRegistrationData();

        if (!this.validator.validateRegistration(formData)) {
            this.ui.showError('Please fill in all required fields correctly.');
            return;
        }

        this.ui.showLoading();

        try {
            const response = await this.apiService.register(formData);

            this.currentUser = { user_id: response.data.user_id };
            this.userStatus = {
                logged_in: true,
                user_id: response.data.user_id,
                email_confirmed: false,
                phone_confirmed: false,
                needs_email_verification: true
            };

            this.currentStep = 'email_verification';
            this.ui.showSuccess('Registration successful!');

            // Pre-fill email field
            setTimeout(() => {
                this.renderCurrentStep();
                const emailField = this.container.querySelector('#aur-email-address');
                if (emailField && formData.user_email) {
                    emailField.value = formData.user_email;
                }
            }, 1000);

        } catch (error) {
            this.ui.showError(error.message || 'Registration failed. Please try again.');
        }
    }

    async sendEmailVerification() {
        const email = this.container.querySelector('#aur-email-address').value;

        if (!this.validator.validateEmail(email)) {
            this.ui.showError('Please enter a valid email address.');
            return;
        }

        const sendButton = this.container.querySelector('#aur-send-email-code');
        this.ui.disableButton(sendButton, 'Sending...');

        try {
            await this.apiService.sendVerification(this.userStatus.user_id, 'email', email);

            this.ui.showSuccess('Verification code sent to your email!');
            this.container.querySelector('#aur-email-code-input').classList.remove('hidden');

        } catch (error) {
            this.ui.showError(error.message || 'Failed to send verification code.');
        } finally {
            this.ui.enableButton(sendButton, 'Send Verification Code');
        }
    }

    async verifyEmailCode() {
        const code = this.container.querySelector('#aur-email-code').value;

        if (!this.validator.validateCode(code)) {
            this.ui.showError('Please enter a valid 6-digit code.');
            return;
        }

        const verifyButton = this.container.querySelector('#aur-verify-email-code');
        this.ui.disableButton(verifyButton, 'Verifying...');

        try {
            await this.apiService.verifyCode(this.userStatus.user_id, code, 'email');

            this.userStatus.email_confirmed = true;
            this.userStatus.needs_email_verification = false;

            this.ui.showSuccess('Email verified successfully!');
            this.currentStep = this.determineNextStep();

            setTimeout(() => this.renderCurrentStep(), 1500);

        } catch (error) {
            this.ui.showError(error.message || 'Invalid or expired verification code.');
        } finally {
            this.ui.enableButton(verifyButton, 'Verify Email');
        }
    }

    async sendPhoneVerification() {
        const phone = this.container.querySelector('#aur-phone-number').value;

        if (!this.validator.validatePhone(phone)) {
            this.ui.showError('Please enter a valid phone number.');
            return;
        }

        const sendButton = this.container.querySelector('#aur-send-phone-code');
        this.ui.disableButton(sendButton, 'Sending...');

        try {
            await this.apiService.sendVerification(this.userStatus.user_id, 'phone', phone);

            this.ui.showSuccess('Verification code sent to your phone!');
            this.container.querySelector('#aur-phone-code-input').classList.remove('hidden');

        } catch (error) {
            this.ui.showError(error.message || 'Failed to send verification code.');
        } finally {
            this.ui.enableButton(sendButton, 'Send SMS Code');
        }
    }

    async verifyPhoneCode() {
        const code = this.container.querySelector('#aur-phone-code').value;

        if (!this.validator.validateCode(code)) {
            this.ui.showError('Please enter a valid 6-digit code.');
            return;
        }

        const verifyButton = this.container.querySelector('#aur-verify-phone-code');
        this.ui.disableButton(verifyButton, 'Verifying...');

        try {
            await this.apiService.verifyCode(this.userStatus.user_id, code, 'phone');

            this.userStatus.phone_confirmed = true;
            this.ui.showSuccess('Phone verified successfully!');
            this.currentStep = this.determineNextStep();

            setTimeout(() => this.renderCurrentStep(), 1500);

        } catch (error) {
            this.ui.showError(error.message || 'Invalid or expired verification code.');
        } finally {
            this.ui.enableButton(verifyButton, 'Verify Phone');
        }
    }

    skipPhoneVerification() {
        this.currentStep = this.determineNextStep(true);
        this.renderCurrentStep();
    }

    async updateProfile() {
        const formData = this.collectProfileData();

        const updateButton = this.container.querySelector('#aur-update-profile');
        this.ui.disableButton(updateButton, 'Updating...');

        try {
            await this.apiService.updateUser(formData);
            this.ui.showSuccess('Profile updated successfully!');

        } catch (error) {
            this.ui.showError(error.message || 'Failed to update profile.');
        } finally {
            this.ui.enableButton(updateButton, 'Update Profile');
        }
    }

    collectRegistrationData() {
        const data = {};
        const selectedFields = this.attributes.selectedFields || [];

        selectedFields.forEach(fieldName => {
            const input = this.container.querySelector(`[name="${fieldName}"]`);
            if (input) {
                data[fieldName] = input.value;
            }
        });

        // Add phone number if field is shown
        if (this.attributes.showPhoneField) {
            const phoneInput = this.container.querySelector('[name="aur_phone_number"]');
            if (phoneInput) {
                data.aur_phone_number = phoneInput.value;
            }
        }

        return data;
    }

    collectProfileData() {
        const data = {};
        const profileFields = this.container.querySelectorAll('#aur-profile-fields input, #aur-profile-fields select, #aur-profile-fields textarea');

        profileFields.forEach(field => {
            if (field.name) {
                data[field.name] = field.value;
            }
        });

        return data;
    }

    determineNextStep(skipPhone = false) {
        if (!this.userStatus.email_confirmed && this.userStatus.needs_email_verification) {
            return 'email_verification';
        }

        if (!skipPhone && !this.userStatus.phone_confirmed && this.attributes.showPhoneField) {
            return 'phone_verification';
        }

        return 'profile_edit';
    }

    renderCurrentStep() {
        this.ui.hideLoading();

        // Hide all steps
        const steps = ['login', 'register', 'email_verification', 'phone_verification', 'profile_edit', 'success'];
        steps.forEach(step => {
            const element = this.container.querySelector(`#aur-${step.replace('_', '-')}`);
            if (element) {
                element.classList.add('hidden');
            }
        });

        // Show current step
        const currentElement = this.container.querySelector(`#aur-${this.currentStep.replace('_', '-')}`);
        if (currentElement) {
            currentElement.classList.remove('hidden');
        }

        // Special handling for specific steps
        switch (this.currentStep) {
            case 'register':
                this.renderRegistrationFields();
                break;
            case 'profile_edit':
                this.renderProfileFields();
                break;
            case 'success':
                // Auto-hide after delay or redirect
                break;
        }
    }

    renderRegistrationFields() {
        const fieldsContainer = this.container.querySelector('#aur-register-fields');
        if (!fieldsContainer) return;

        fieldsContainer.innerHTML = '';
        const selectedFields = this.attributes.selectedFields || [];

        selectedFields.forEach(fieldName => {
            const fieldConfig = this.availableFields.find(f => f.field_name === fieldName);
            if (fieldConfig) {
                const fieldHtml = this.createFieldHTML(fieldConfig);
                fieldsContainer.insertAdjacentHTML('beforeend', fieldHtml);
            }
        });

        // Add phone field if enabled
        if (this.attributes.showPhoneField) {
            const phoneFieldConfig = this.availableFields.find(f => f.field_name === 'aur_phone_number');
            if (phoneFieldConfig) {
                const fieldHtml = this.createFieldHTML(phoneFieldConfig);
                fieldsContainer.insertAdjacentHTML('beforeend', fieldHtml);
            }
        }
    }

    renderProfileFields() {
        const fieldsContainer = this.container.querySelector('#aur-profile-fields');
        if (!fieldsContainer) return;

        fieldsContainer.innerHTML = '';

        // Show only editable fields
        const editableFields = this.availableFields.filter(f => f.is_editable === '1');

        editableFields.forEach(fieldConfig => {
            const fieldHtml = this.createFieldHTML(fieldConfig, true);
            fieldsContainer.insertAdjacentHTML('beforeend', fieldHtml);
        });
    }

    createFieldHTML(fieldConfig, isProfile = false) {
        const isRequired = fieldConfig.is_required === '1' && !isProfile;
        const fieldId = `aur-${isProfile ? 'profile' : 'register'}-${fieldConfig.field_name}`;

        let inputHtml = '';

        switch (fieldConfig.field_type) {
            case 'email':
                inputHtml = `<input type="email" id="${fieldId}" name="${fieldConfig.field_name}" 
                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" 
                            ${isRequired ? 'required' : ''}>`;
                break;
            case 'password':
                inputHtml = `<input type="password" id="${fieldId}" name="${fieldConfig.field_name}" 
                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" 
                            ${isRequired ? 'required' : ''}>`;
                break;
            case 'tel':
                inputHtml = `<input type="tel" id="${fieldId}" name="${fieldConfig.field_name}" 
                            placeholder="+1234567890"
                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" 
                            ${isRequired ? 'required' : ''}>`;
                break;
            case 'textarea':
                inputHtml = `<textarea id="${fieldId}" name="${fieldConfig.field_name}" rows="3"
                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" 
                            ${isRequired ? 'required' : ''}></textarea>`;
                break;
            case 'select':
                let options = '';
                if (fieldConfig.field_options) {
                    try {
                        const optionsList = JSON.parse(fieldConfig.field_options);
                        options = optionsList.map(opt => `<option value="${opt.value}">${opt.label}</option>`).join('');
                    } catch (e) {
                        // Handle simple comma-separated options
                        const optionsList = fieldConfig.field_options.split(',');
                        options = optionsList.map(opt => `<option value="${opt.trim()}">${opt.trim()}</option>`).join('');
                    }
                }
                inputHtml = `<select id="${fieldId}" name="${fieldConfig.field_name}"
                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" 
                            ${isRequired ? 'required' : ''}>
                            <option value="">Choose an option</option>
                            ${options}
                            </select>`;
                break;
            default:
                inputHtml = `<input type="text" id="${fieldId}" name="${fieldConfig.field_name}" 
                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" 
                            ${isRequired ? 'required' : ''}>`;
        }

        return `
            <div>
                <label for="${fieldId}" class="block text-sm font-medium text-gray-700">
                    ${fieldConfig.field_label}${isRequired ? ' *' : ''}
                </label>
                ${inputHtml}
            </div>
        `;
    }

    showStep(step) {
        this.currentStep = step;
        this.renderCurrentStep();
    }
}

export default AURRegistrationForm;