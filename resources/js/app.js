/**
 * Main Application Entry Point
 * Imports all JavaScript modules for the helpdesk system
 */

import './bootstrap';
import './ui';

// Admin modules
import './admin/articles';
import './admin/categories';
import './admin/users';

// Staff modules
import './staff/dashboard';
import './staff/tickets';
import './staff/ticketChat';

// Import utilities and expose to global window for inline scripts
import { safeJson, safeFetch, getCsrfToken } from './utils/http';

window.safeJson = safeJson;
window.safeFetch = safeFetch;
window.getCsrfToken = getCsrfToken;

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();
