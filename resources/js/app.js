/**
 * Main Application Entry Point
 * Imports all JavaScript modules for the helpdesk system
 */

import './bootstrap';
import './ui';

// Dynamic imports based on DOM elements
document.addEventListener('DOMContentLoaded', () => {
    // Admin article page
    if (document.querySelector('[data-admin-page="articles"]') || document.querySelector('[data-open-modal][data-modal-form-action]')) {
        import('./admin/articles');
    }
    
    // Admin category page
    if (document.querySelector('[data-admin-page="categories"]') || document.querySelector('form#category-form')) {
        import('./admin/categories');
    }
    
    // Admin users confirmation
    if (document.querySelector('[data-confirm]')) {
        import('./admin/users');
    }
    
    // Staff dashboard
    if (document.querySelector('[data-page="staff-dashboard"]')) {
        import('./staff/dashboard');
    }
    
    // Staff tickets list
    if (document.querySelector('[data-page="staff-tickets"]')) {
        import('./staff/tickets');
    }
    
    // Staff ticket chat
    if (document.getElementById('ticket-data') || document.getElementById('messages-list')) {
        import('./staff/ticketChat');
    }
});

// Import utilities and expose to global window for inline scripts
import { safeJson, safeFetch, getCsrfToken } from './utils/http';

window.safeJson = safeJson;
window.safeFetch = safeFetch;
window.getCsrfToken = getCsrfToken;

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();
