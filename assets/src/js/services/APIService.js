class APIService {
    constructor() {
        this.ajaxUrl = window.aurAjax?.ajaxUrl || '/wp-admin/admin-ajax.php';
        this.restUrl = window.aurAjax?.restUrl || '/wp-json/aur/v1/';
        this.nonce = window.aurAjax?.nonce || '';
        this.useRest = true; // Use REST API by default, fallback to AJAX
    }

    async makeRequest(endpoint, data = {}, method = 'POST') {
        const config = {
            method,
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': this.nonce
            }
        };

        if (method !== 'GET') {
            config.body = JSON.stringify(data);
        }

        try {
            // Try REST API first
            if (this.useRest) {
                const url = method === 'GET' ?
                    `${this.restUrl}${endpoint}${this.buildQueryString(data)}` :
                    `${this.restUrl}${endpoint}`;

                const response = await fetch(url, config);

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                return await response.json();
            }
        } catch (error) {
            console.warn('REST API failed, falling back to AJAX:', error);
            this.useRest = false;
        }

        // Fallback to AJAX
        return this.makeAjaxRequest(endpoint, data);
    }

    async makeAjaxRequest(action, data = {}) {
        const formData = new FormData();
        formData.append('action', `aur_${action}`);
        formData.append('nonce', this.nonce);

        Object.keys(data).forEach(key => {
            if (typeof data[key] === 'object') {
                formData.append(key, JSON.stringify(data[key]));
            } else {
                formData.append(key, data[key]);
            }
        });

        const response = await fetch(this.ajaxUrl, {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (!result.success) {
            throw new Error(result.data?.message || 'Request failed');
        }

        return result;
    }

    buildQueryString(params) {
        const query = new URLSearchParams();
        Object.keys(params).forEach(key => {
            query.append(key, params[key]);
        });
        return query.toString() ? `?${query.toString()}` : '';
    }

    async login(username, password) {
        return this.makeRequest('login', { username, password });
    }

    async register(userData) {
        return this.makeRequest('register', { user_data: userData });
    }

    async sendVerification(userId, type, contact) {
        return this.makeRequest('send-verification', {
            user_id: userId,
            type,
            contact
        });
    }

    async verifyCode(userId, code, type) {
        return this.makeRequest('verify-code', {
            user_id: userId,
            code,
            type
        });
    }

    async updateUser(userData) {
        return this.makeRequest('update-user', { user_data: userData });
    }

    async getUserStatus() {
        return this.makeRequest('user-status', {}, 'GET');
    }

    async getFields() {
        return this.makeRequest('fields', {}, 'GET');
    }
}

export default APIService;