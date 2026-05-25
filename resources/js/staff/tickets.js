document.addEventListener('DOMContentLoaded', () => {
    const pageRoot = document.querySelector('[data-page="staff-tickets"]');
    if (!pageRoot) {
        return;
    }

    const tabs = pageRoot.querySelectorAll('[data-tab-btn]');
    const tabContents = pageRoot.querySelectorAll('[data-tab-content]');
    const paginationButtons = pageRoot.querySelectorAll('[data-pagination]');

    const totalPages = {
        all: Number(pageRoot.dataset.allPages) || 1,
        completed: Number(pageRoot.dataset.completedPages) || 1,
        waiting: Number(pageRoot.dataset.waitingPages) || 1,
    };

    const currentPage = {
        all: 1,
        completed: 1,
        waiting: 1,
    };

    function setVisibility(element, visible) {
        if (!(element instanceof HTMLElement)) {
            return;
        }

        element.classList.toggle('hidden', !visible);
    }

    function showPage(tab, page) {
        if (!totalPages[tab]) {
            return;
        }

        const normalizedPage = Math.max(1, Math.min(page, totalPages[tab]));

        pageRoot.querySelectorAll(`[data-tab-item="${tab}"]`).forEach(item => {
            setVisibility(item, item.dataset.page === String(normalizedPage));
        });

        const pageInfo = pageRoot.querySelector(`[data-current-page="${tab}"]`);
        if (pageInfo instanceof HTMLElement) {
            pageInfo.textContent = String(normalizedPage);
        }

        currentPage[tab] = normalizedPage;
    }

    function nextPage(tab) {
        if (currentPage[tab] < totalPages[tab]) {
            showPage(tab, currentPage[tab] + 1);
        }
    }

    function previousPage(tab) {
        if (currentPage[tab] > 1) {
            showPage(tab, currentPage[tab] - 1);
        }
    }

    function activateTab(tabName) {
        tabContents.forEach(content => {
            if (!(content instanceof HTMLElement)) {
                return;
            }

            setVisibility(content, content.id === tabName);
        });

        tabs.forEach(btn => {
            if (!(btn instanceof HTMLElement)) {
                return;
            }

            const isActive = btn.dataset.tab === tabName;
            btn.classList.toggle('border-red-500', isActive);
            btn.classList.toggle('text-red-600', isActive);
            btn.classList.toggle('border-transparent', !isActive);
            btn.classList.toggle('text-gray-600', !isActive);
        });
    }

    tabs.forEach(btn => {
        if (!(btn instanceof HTMLElement)) {
            return;
        }

        const tabName = btn.dataset.tab;
        if (!tabName) {
            return;
        }

        btn.addEventListener('click', () => {
            activateTab(tabName);
            showPage(tabName, 1);
        });
    });

    paginationButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            const tab = btn.dataset.tab;
            const action = btn.dataset.pagination;
            if (!tab || !action) {
                return;
            }

            if (action === 'next') {
                nextPage(tab);
            } else if (action === 'previous') {
                previousPage(tab);
            }
        });
    });

    showPage('all', 1);
    showPage('completed', 1);
    showPage('waiting', 1);
});