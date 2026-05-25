document.addEventListener('DOMContentLoaded', () => {
    const categoryPageRoot = document.querySelector('[data-admin-page="categories"]') || document.querySelector('form#category-form');
    if (!categoryPageRoot) {
        return;
    }

    // Admin category pages are ready for future page-specific enhancements.
});