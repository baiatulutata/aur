class UIManager {
    constructor(container) {
        this.container = container;
        this.messagesContainer = container.querySelector('#aur-form-messages');
        this.loadingElement = container.querySelector('#aur-loading');
    }

    showMessage(message, type = 'info') {
        if (!this.messagesContainer) return;

        const typeClasses = {
            success: 'bg-green-50 border-green-200 text-green-800',
            error: 'bg-red-50 border-red-200 text-red-800',
            warning: 'bg-yellow-50 border-yellow-200 text-yellow-800',
            info: 'bg-blue-50 border-blue-200 text-blue-800'
        };

        const iconMap = {
            success: '✓',
            error: '✗',
            warning: '⚠',
            info: 'ℹ'
        };

        this.messagesContainer.innerHTML = `
            <div class="border rounded-md p-4 ${typeClasses[type]} animate-fade-in">
                <div class="flex items-center">
                    <span class="mr-2 text-lg">${iconMap[type]}</span>
                    <span>${message}</span>
                </div>
            </div>
        `;
        
        this.messagesContainer.classList.remove('hidden');

        // Auto-hide success messages
        if (type === 'success') {
            setTimeout(() => {
                this.hideMessage();
            }, 5000);
        }

        // Scroll to message
        this.messagesContainer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    showSuccess(message) {
        this.showMessage(message, 'success');
    }

    showError(message) {
        this.showMessage(message, 'error');
    }

    showWarning(message) {
        this.showMessage(message, 'warning');
    }

    showInfo(message) {
        this.showMessage(message, 'info');
    }

    hideMessage() {
        if (this.messagesContainer) {
            this.messagesContainer.classList.add('hidden');
            this.messagesContainer.innerHTML = '';
        }
    }

    showLoading(message = null) {
        if (this.loadingElement) {
            if (message) {
                const messageEl = this.loadingElement.querySelector('p');
                if (messageEl) {
                    messageEl.textContent = message;
                }
            }
            this.loadingElement.classList.remove('hidden');
        }
        
        // Hide all form steps
        this.hideAllSteps();
    }

    hideLoading() {
        if (this.loadingElement) {
            this.loadingElement.classList.add('hidden');
        }
    }

    hideAllSteps() {
        const steps = [
            'aur-login-form',
            'aur-register-form', 
            'aur-email-verification',
            'aur-phone-verification',
            'aur-profile-edit',
            'aur-success'
        ];

        steps.forEach(stepId => {
            const element = this.container.querySelector(`#${stepId}`);
            if (element) {
                element.classList.add('hidden');
            }
        });
    }

    disableButton(button, loadingText = null) {
        if (!button) return;

        button.disabled = true;
        button.classList.add('opacity-50', 'cursor-not-allowed');
        
        if (loadingText) {
            button.dataset.originalText = button.textContent;
            button.innerHTML = `
                <div class="flex items-center justify-center">
                    <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-white mr-2"></div>
                    ${loadingText}
                </div>
            `;
        }
    }

    enableButton(button, originalText = null) {
        if (!button) return;

        button.disabled = false;
        button.classList.remove('opacity-50', 'cursor-not-allowed');
        
        if (originalText || button.dataset.originalText) {
            button.textContent = originalText || button.dataset.originalText;
            delete button.dataset.originalText;
        }
    }

    addInputValidation(input, validator) {
        if (!input) return;

        input.addEventListener('blur', () => {
            this.validateInput(input, validator);
        });

        input.addEventListener('input', () => {
            // Clear validation errors on input
            this.clearInputError(input);
        });
    }

    validateInput(input, validator) {
        const value = input.value.trim();
        let isValid = true;
        let errorMessage = '';

        switch (validator) {
            case 'email':
                isValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
                errorMessage = 'Please enter a valid email address';
                break;
            case 'phone':
                isValid = /^\+?[\d\s\-\(\)]+$/.test(value) && value.length >= 10;
                errorMessage = 'Please enter a valid phone number';
                break;
            case 'password':
                isValid = value.length >= 6;
                errorMessage = 'Password must be at least 6 characters long';
                break;
            case 'required':
                isValid = value !== '';
                errorMessage = 'This field is required';
                break;
        }

        if (!isValid) {
            this.showInputError(input, errorMessage);
        } else {
            this.clearInputError(input);
        }

        return isValid;
    }

    showInputError(input, message) {
        this.clearInputError(input);
        
        input.classList.add('border-red-300', 'focus:border-red-500', 'focus:ring-red-500');
        input.classList.remove('border-gray-300', 'focus:border-blue-500', 'focus:ring-blue-500');

        const errorElement = document.createElement('p');
        errorElement.className = 'mt-1 text-sm text-red-600 aur-field-error';
        errorElement.textContent = message;
        
        input.parentNode.appendChild(errorElement);
    }

    clearInputError(input) {
        input.classList.remove('border-red-300', 'focus:border-red-500', 'focus:ring-red-500');
        input.classList.add('border-gray-300', 'focus:border-blue-500', 'focus:ring-blue-500');

        const errorElement = input.parentNode.querySelector('.aur-field-error');
        if (errorElement) {
            errorElement.remove();
        }
    }

    animateSuccess() {
        const successElement = this.container.querySelector('#aur-success');
        if (successElement) {
            successElement.classList.remove('hidden');
            successElement.classList.add('animate-fade-in');
        }
    }

    addProgressIndicator(currentStep, totalSteps) {
        const progressContainer = this.container.querySelector('.aur-progress');
        
        if (!progressContainer) {
            const progress = document.createElement('div');
            progress.className = 'aur-progress mb-6';
            progress.innerHTML = `
                <div class="flex items-center justify-between text-sm text-gray-500 mb-2">
                    <span>Step ${currentStep} of ${totalSteps}</span>
                    <span>${Math.round((currentStep / totalSteps) * 100)}%</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <div class="bg-blue-600 h-2 rounded-full transition-all duration-300" style="width: ${(currentStep / totalSteps) * 100}%"></div>
                </div>
            `;
            
            this.container.insertBefore(progress, this.container.firstChild.nextSibling);
        } else {
            const progressBar = progressContainer.querySelector('.bg-blue-600');
            const stepText = progressContainer.querySelector('span');
            const percentText = progressContainer.querySelectorAll('span')[1];
            
            if (progressBar) progressBar.style.width = `${(currentStep / totalSteps) * 100}%`;
            if (stepText) stepText.textContent = `Step ${currentStep} of ${totalSteps}`;
            if (percentText) percentText.textContent = `${Math.round((currentStep / totalSteps) * 100)}%`;
        }
    }
}

export default UIManager;