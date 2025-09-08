import '../css/admin.css';

class AURAdmin {
    constructor() {
        this.init();
    }

    init() {
        document.addEventListener('DOMContentLoaded', () => {
            this.bindEvents();
            this.initSortable();
            this.initTabs();
        });
    }

    bindEvents() {
        // Field management
        this.bindFieldEvents();

        // Settings
        this.bindSettingsEvents();

        // SMS testing
        this.bindSMSTestEvents();
    }

    bindFieldEvents() {
        const addFieldBtn = document.querySelector('#aur-add-field-btn');
        const saveFieldsBtn = document.querySelector('#aur-save-fields-btn');
        const deleteFieldBtns = document.querySelectorAll('.aur-delete-field');

        if (addFieldBtn) {
            addFieldBtn.addEventListener('click', () => this.showAddFieldModal());
        }

        if (saveFieldsBtn) {
            saveFieldsBtn.addEventListener('click', () => this.saveFields());
        }

        deleteFieldBtns.forEach(btn => {
            btn.addEventListener('click', (e) => this.deleteField(e.target.dataset.fieldId));
        });

        // Field type change handler
        const fieldTypeSelects = document.querySelectorAll('.aur-field-type');
        fieldTypeSelects.forEach(select => {
            select.addEventListener('change', (e) => this.handleFieldTypeChange(e.target));
        });
    }

    bindSettingsEvents() {
        const saveSettingsBtn = document.querySelector('#aur-save-settings-btn');
        const resetSettingsBtn = document.querySelector('#aur-reset-settings-btn');

        if (saveSettingsBtn) {
            saveSettingsBtn.addEventListener('click', () => this.saveSettings());
        }

        if (resetSettingsBtn) {
            resetSettingsBtn.addEventListener('click', () => this.resetSettings());
        }

        // Provider change handler
        const providerSelect = document.querySelector('#sms_provider');
        if (providerSelect) {
            providerSelect.addEventListener('change', (e) => this.handleProviderChange(e.target.value));
        }
    }

    bindSMSTestEvents() {
        const testSMSBtn = document.querySelector('#aur-test-sms-btn');

        if (testSMSBtn) {
            testSMSBtn.addEventListener('click', () => this.testSMSConfiguration());
        }
    }

    showAddFieldModal() {
        const modal = document.querySelector('#aur-add-field-modal');
        if (modal) {
            modal.classList.remove('hidden');
        }
    }

    hideAddFieldModal() {
        const modal = document.querySelector('#aur-add-field-modal');
        if (modal) {
            modal.classList.add('hidden');
        }
    }

    async saveFields() {
        const fields = this.collectFieldsData();
        const saveBtn = document.querySelector('#aur-save-fields-btn');

        this.showLoading(saveBtn, 'Saving...');

        try {
            const response = await fetch(window.aurAdmin.restUrl + 'save-fields', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': window.aurAdmin.nonce
                },
                body: JSON.stringify({ fields })
            });

            const result = await response.json();

            if (result.success) {
                this.showNotice('Fields saved successfully!', 'success');
            } else {
                throw new Error(result.message || 'Failed to save fields');
            }
        } catch (error) {
            this.showNotice(error.message, 'error');
        } finally {
            this.hideLoading(saveBtn, 'Save Fields');
        }
    }

    async deleteField(fieldId) {
        if (!confirm('Are you sure you want to delete this field?')) {
            return;
        }

        try {
            const response = await fetch(window.aurAdmin.restUrl + 'delete-field', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': window.aurAdmin.nonce
                },
                body: JSON.stringify({ field_id: fieldId })
            });

            const result = await response.json();

            if (result.success) {
                const fieldRow = document.querySelector(`[data-field-id="${fieldId}"]`).closest('tr');
                fieldRow.remove();
                this.showNotice('Field deleted successfully!', 'success');
            } else {
                throw new Error(result.message || 'Failed to delete field');
            }
        } catch (error) {
            this.showNotice(error.message, 'error');
        }
    }

    handleFieldTypeChange(select) {
        const optionsContainer = select.closest('tr').querySelector('.aur-field-options');
        const fieldType = select.value;

        if (fieldType === 'select' || fieldType === 'radio' || fieldType === 'checkbox') {
            optionsContainer.style.display = 'block';
        } else {
            optionsContainer.style.display = 'none';
        }
    }

    async saveSettings() {
        const settings = this.collectSettingsData();
        const saveBtn = document.querySelector('#aur-save-settings-btn');

        this.showLoading(saveBtn, 'Saving...');

        try {
            const response = await fetch(window.aurAdmin.restUrl + 'save-settings', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': window.aurAdmin.nonce
                },
                body: JSON.stringify({ settings })
            });

            const result = await response.json();

            if (result.success) {
                this.showNotice('Settings saved successfully!', 'success');
            } else {
                throw new Error(result.message || 'Failed to save settings');
            }
        } catch (error) {
            this.showNotice(error.message, 'error');
        } finally {
            this.hideLoading(saveBtn, 'Save Settings');
        }
    }

    resetSettings() {
        if (!confirm('Are you sure you want to reset all settings to default values?')) {
            return;
        }

        // Reset form fields to default values
        const form = document.querySelector('#aur-settings-form');
        if (form) {
            form.reset();
            this.showNotice('Settings reset to defaults. Don\'t forget to save!', 'info');
        }
    }

    handleProviderChange(provider) {
        const providerSections = document.querySelectorAll('.aur-provider-section');
        providerSections.forEach(section => {
            section.style.display = 'none';
        });

        const activeSection = document.querySelector(`#aur-provider-${provider}`);
        if (activeSection) {
            activeSection.style.display = 'block';
        }
    }

    async testSMSConfiguration() {
        const phoneNumber = document.querySelector('#aur-test-phone').value;

        if (!phoneNumber) {
            this.showNotice('Please enter a phone number for testing', 'error');
            return;
        }

        const testBtn = document.querySelector('#aur-test-sms-btn');
        this.showLoading(testBtn, 'Sending...');

        try {
            const response = await fetch(window.aurAdmin.restUrl + 'test-sms', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': window.aurAdmin.nonce
                },
                body: JSON.stringify({ phone_number: phoneNumber })
            });

            const result = await response.json();

            if (result.success) {
                this.showNotice('Test SMS sent successfully! Check your phone and email.', 'success');
            } else {
                throw new Error(result.message || 'Failed to send test SMS');
            }
        } catch (error) {
            this.showNotice(error.message, 'error');
        } finally {
            this.hideLoading(testBtn, 'Send Test SMS');
        }
    }

    collectFieldsData() {
        const fields = [];
        const fieldRows = document.querySelectorAll('.aur-field-row');

        fieldRows.forEach(row => {
            const fieldData = {
                id: row.dataset.fieldId,
                field_name: row.querySelector('.aur-field-name').value,
                field_label: row.querySelector('.aur-field-label').value,
                field_type: row.querySelector('.aur-field-type').value,
                field_options: row.querySelector('.aur-field-options-input').value,
                is_required: row.querySelector('.aur-field-required').checked ? 1 : 0,
                is_editable: row.querySelector('.aur-field-editable').checked ? 1 : 0,
                field_order: row.dataset.fieldOrder || 999
            };
            fields.push(fieldData);
        });

        return fields;
    }

    collectSettingsData() {
        const settings = {};
        const form = document.querySelector('#aur-settings-form');

        if (form) {
            const formData = new FormData(form);
            for (let [key, value] of formData.entries()) {
                settings[key] = value;
            }
        }

        return settings;
    }

    initSortable() {
        const fieldsTable = document.querySelector('#aur-fields-table tbody');

        if (fieldsTable && window.Sortable) {
            new Sortable(fieldsTable, {
                animation: 150,
                handle: '.aur-drag-handle',
                onEnd: (evt) => {
                    this.updateFieldOrder();
                }
            });
        }
    }

    updateFieldOrder() {
        const fieldRows = document.querySelectorAll('.aur-field-row');
        fieldRows.forEach((row, index) => {
            row.dataset.fieldOrder = index + 1;
        });
    }

    initTabs() {
        const tabButtons = document.querySelectorAll('.aur-tab-button');
        const tabContents = document.querySelectorAll('.aur-tab-content');

        tabButtons.forEach(button => {
            button.addEventListener('click', () => {
                const targetTab = button.dataset.tab;

                // Remove active class from all tabs and contents
                tabButtons.forEach(btn => btn.classList.remove('nav-tab-active'));
                tabContents.forEach(content => content.classList.add('hidden'));

                // Add active class to clicked tab and show content
                button.classList.add('nav-tab-active');
                const targetContent = document.querySelector(`#aur-tab-${targetTab}`);
                if (targetContent) {
                    targetContent.classList.remove('hidden');
                }
            });
        });
    }

    showLoading(button, text) {
        if (button) {
            button.disabled = true;
            button.dataset.originalText = button.textContent;
            button.innerHTML = `<span class="spinner is-active" style="float: none; margin: 0 5px 0 0;"></span>${text}`;
        }
    }

    hideLoading(button, originalText) {
        if (button) {
            button.disabled = false;
            button.textContent = originalText || button.dataset.originalText || 'Save';
        }
    }

    showNotice(message, type = 'info') {
        const noticesContainer = document.querySelector('#aur-admin-notices');

        if (!noticesContainer) {
            // Create notices container if it doesn't exist
            const container = document.createElement('div');
            container.id = 'aur-admin-notices';
            container.style.position = 'fixed';
            container.style.top = '32px';
            container.style.right = '20px';
            container.style.zIndex = '9999';
            document.body.appendChild(container);
        }

        const notice = document.createElement('div');
        notice.className = `notice notice-${type} is-dismissible`;
        notice.style.position = 'relative';
        notice.style.margin = '0 0 10px 0';
        notice.style.maxWidth = '400px';

        notice.innerHTML = `
            <p>${message}</p>
            <button type="button" class="notice-dismiss" onclick="this.parentElement.remove()">
                <span class="screen-reader-text">Dismiss this notice.</span>
            </button>
        `;

        const container = document.querySelector('#aur-admin-notices');
        container.appendChild(notice);

        // Auto-remove after 5 seconds for success messages
        if (type === 'success') {
            setTimeout(() => {
                if (notice.parentElement) {
                    notice.remove();
                }
            }, 5000);
        }
    }
}

// Initialize admin
new AURAdmin();