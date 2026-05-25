document.addEventListener('DOMContentLoaded', () => {
    const pageRoot = document.querySelector('[data-page="staff-dashboard"]');
    if (!(pageRoot instanceof HTMLElement)) {
        return;
    }

    let currentPage = 1;
    const totalPages = Number(pageRoot.dataset.totalPages) || 1;
    const previousButton = pageRoot.querySelector('[data-pagination="previous"]');
    const nextButton = pageRoot.querySelector('[data-pagination="next"]');
    const currentPageEl = pageRoot.querySelector('#currentPage');

    function setVisibility(element, visible) {
        if (!(element instanceof HTMLElement)) {
            return;
        }

        element.classList.toggle('hidden', !visible);
    }

    function showPage(page) {
        const safePage = Math.max(1, Math.min(page, totalPages));

        pageRoot.querySelectorAll('[data-ticket-item]').forEach(item => {
            setVisibility(item, item.dataset.page === String(safePage));
        });

        if (currentPageEl instanceof HTMLElement) {
            currentPageEl.textContent = String(safePage);
        }

        currentPage = safePage;
    }

    previousButton?.addEventListener('click', (event) => {
        event.preventDefault();
        if (currentPage > 1) {
            showPage(currentPage - 1);
        }
    });

    nextButton?.addEventListener('click', (event) => {
        event.preventDefault();
        if (currentPage < totalPages) {
            showPage(currentPage + 1);
        }
    });

    showPage(1);
});