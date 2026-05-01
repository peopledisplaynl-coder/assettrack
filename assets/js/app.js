/**
 * AssetTrack - Main JavaScript
 */

// DOM ready function
document.addEventListener('DOMContentLoaded', function() {
    // Initialize all components
    initFormValidation();
    initConfirmations();
    initTooltips();
    initSearch();
    initFilters();
    initDatalistFallback();
});

/**
 * Form validation
 */
function initFormValidation() {
    const forms = document.querySelectorAll('form[data-validate]');

    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!validateForm(this)) {
                e.preventDefault();
            }
        });
    });
}

/**
 * Validate form
 */
function validateForm(form) {
    let isValid = true;
    const requiredFields = form.querySelectorAll('[required]');

    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            showFieldError(field, 'Dit veld is verplicht');
            isValid = false;
        } else {
            clearFieldError(field);
        }
    });

    // Email validation
    const emailFields = form.querySelectorAll('input[type="email"]');
    emailFields.forEach(field => {
        if (field.value && !isValidEmail(field.value)) {
            showFieldError(field, 'Ongeldig e-mailadres');
            isValid = false;
        }
    });

    // Password confirmation
    const password = form.querySelector('input[name="password"]');
    const passwordConfirm = form.querySelector('input[name="password_confirm"]');

    if (password && passwordConfirm && password.value !== passwordConfirm.value) {
        showFieldError(passwordConfirm, 'Wachtwoorden komen niet overeen');
        isValid = false;
    }

    return isValid;
}

/**
 * Show field error
 */
function showFieldError(field, message) {
    clearFieldError(field);

    const errorDiv = document.createElement('div');
    errorDiv.className = 'field-error';
    errorDiv.textContent = message;

    field.parentNode.appendChild(errorDiv);
    field.classList.add('error');
}

/**
 * Clear field error
 */
function clearFieldError(field) {
    const existingError = field.parentNode.querySelector('.field-error');
    if (existingError) {
        existingError.remove();
    }
    field.classList.remove('error');
}

/**
 * Check if email is valid
 */
function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

/**
 * Confirmations for destructive actions
 */
function initConfirmations() {
    const confirmElements = document.querySelectorAll('[data-confirm]');

    confirmElements.forEach(element => {
        element.addEventListener('click', function(e) {
            const message = this.getAttribute('data-confirm');
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });
}

/**
 * Tooltips
 */
function initTooltips() {
    const tooltipElements = document.querySelectorAll('[data-tooltip]');

    tooltipElements.forEach(element => {
        element.addEventListener('mouseenter', showTooltip);
        element.addEventListener('mouseleave', hideTooltip);
    });
}

/**
 * Show tooltip
 */
function showTooltip(e) {
    const text = e.target.getAttribute('data-tooltip');

    const tooltip = document.createElement('div');
    tooltip.className = 'tooltip';
    tooltip.textContent = text;

    document.body.appendChild(tooltip);

    const rect = e.target.getBoundingClientRect();
    tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
    tooltip.style.top = rect.top - tooltip.offsetHeight - 5 + 'px';
}

/**
 * Hide tooltip
 */
function hideTooltip() {
    const tooltip = document.querySelector('.tooltip');
    if (tooltip) {
        tooltip.remove();
    }
}

/**
 * Search functionality
 */
function initSearch() {
    const searchInputs = document.querySelectorAll('[data-search]');

    searchInputs.forEach(input => {
        input.addEventListener('input', debounce(function() {
            performSearch(this);
        }, 300));
    });
}

/**
 * Perform search
 */
function performSearch(input) {
    const searchTerm = input.value.toLowerCase();
    const target = input.getAttribute('data-search');
    const items = document.querySelectorAll(target);

    items.forEach(item => {
        const text = item.textContent.toLowerCase();
        if (text.includes(searchTerm)) {
            item.style.display = '';
        } else {
            item.style.display = 'none';
        }
    });
}

/**
 * Filters
 */
function initFilters() {
    const filterSelects = document.querySelectorAll('[data-filter]');

    filterSelects.forEach(select => {
        select.addEventListener('change', function() {
            applyFilters();
        });
    });
}

/**
 * Apply filters
 */
function applyFilters() {
    const filters = {};

    document.querySelectorAll('[data-filter]').forEach(select => {
        const filterName = select.getAttribute('data-filter');
        const value = select.value;

        if (value) {
            filters[filterName] = value;
        }
    });

    const items = document.querySelectorAll('[data-filterable]');

    items.forEach(item => {
        let show = true;

        for (const [filterName, filterValue] of Object.entries(filters)) {
            const itemValue = item.getAttribute(`data-${filterName}`);

            if (itemValue !== filterValue) {
                show = false;
                break;
            }
        }

        item.style.display = show ? '' : 'none';
    });
}

/**
 * Debounce function
 */
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

/**
 * Show loading state
 */
function showLoading(element) {
    element.classList.add('loading');
    element.disabled = true;
    element.dataset.originalText = element.textContent;
    element.textContent = 'Bezig...';
}

/**
 * Hide loading state
 */
function hideLoading(element) {
    element.classList.remove('loading');
    element.disabled = false;
    if (element.dataset.originalText) {
        element.textContent = element.dataset.originalText;
    }
}

/**
 * AJAX helper
 */
function ajax(url, options = {}) {
    const defaults = {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: null
    };

    const config = { ...defaults, ...options };

    if (config.body && typeof config.body === 'object') {
        config.body = JSON.stringify(config.body);
    }

    return fetch(url, config)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        });
}

/**
 * Show notification
 */
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `alert alert-${type}`;
    notification.textContent = message;

    // Insert at top of page
    const container = document.querySelector('.container') || document.body;
    container.insertBefore(notification, container.firstChild);

    // Auto remove after 5 seconds
    setTimeout(() => {
        notification.remove();
    }, 5000);
}

/**
 * Format currency
 */
function formatCurrency(amount, currency = 'EUR') {
    return new Intl.NumberFormat('nl-NL', {
        style: 'currency',
        currency: currency
    }).format(amount);
}

/**
 * Format number
 */
function formatNumber(number) {
    return new Intl.NumberFormat('nl-NL').format(number);
}

/**
 * Copy to clipboard
 */
function copyToClipboard(text) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(() => {
            showNotification('Gekopieerd naar klembord', 'success');
        });
    } else {
        // Fallback for older browsers
        const textArea = document.createElement('textarea');
        textArea.value = text;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        showNotification('Gekopieerd naar klembord', 'success');
    }
}

/**
 * Toggle element visibility
 */
function toggleVisibility(selector) {
    const element = document.querySelector(selector);
    if (element) {
        element.classList.toggle('hidden');
    }
}

/**
 * Datalist fallback for browsers with limited support
 */
function initDatalistFallback() {
    document.querySelectorAll('input[list]').forEach(input => {
        input.addEventListener('input', function() {
            if (!this.value) {
                return;
            }
            this.value = this.value.charAt(0).toUpperCase() + this.value.slice(1);
        });
    });
}

/**
 * Get URL parameter
 */
function getUrlParameter(name) {
    const url = new URL(window.location);
    return url.searchParams.get(name);
}

/**
 * Set URL parameter
 */
function setUrlParameter(name, value) {
    const url = new URL(window.location);
    url.searchParams.set(name, value);
    window.history.pushState({}, '', url);
}