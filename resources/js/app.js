import Alpine from 'alpinejs';

window.Alpine = Alpine;

const modalStateKeys = [
    'modalOpen',
    'openModal',
    'deleteModalOpen',
    'openImport',
    'privilegeDialogOpen',
    'openLogDetail',
];

const feedbackObserver = new MutationObserver((mutations) => {
    for (const mutation of mutations) {
        if (!(mutation.target instanceof Element)) {
            continue;
        }

        const toast = mutation.target.closest('[x-show="feedbackOpen"]');
        if (!toast || getComputedStyle(toast).display === 'none') {
            continue;
        }

        const state = Alpine.$data(toast);
        const isError = state.feedbackType === 'error' || state.feedbackStatus === 'error';
        const hasOpenModal = modalStateKeys.some((key) => state[key] === true);

        if (!state.feedbackOpen || !isError || !hasOpenModal) {
            continue;
        }

        if (state.deleteModalOpen && typeof state.cancelDelete === 'function') {
            state.cancelDelete();
        }

        for (const key of modalStateKeys) {
            if (key in state) {
                state[key] = false;
            }
        }
    }
});

feedbackObserver.observe(document.documentElement, {
    attributes: true,
    attributeFilter: ['style'],
    subtree: true,
});

Alpine.start();

const iconifyScript = document.createElement('script');
iconifyScript.src = 'https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js';
iconifyScript.async = true;
iconifyScript.dataset.iconifyRuntime = 'true';
iconifyScript.addEventListener('error', () => {
    console.error('Failed to load the Iconify icon runtime.');
});

document.head.append(iconifyScript);
