import Alpine from 'alpinejs';
import QRCode from 'qrcode';
import jsQR from 'jsqr';
import 'iconify-icon';
import { addCollection, setCustomIconLoader } from 'iconify-icon';
import mdiIcons from './icons/mdi.json';

addCollection(mdiIcons);
addCollection({
    prefix: 'material-symbols',
    icons: {
        shelves: {
            width: 24,
            height: 24,
            body: '<path fill="currentColor" d="M3 23V1h2v2h14V1h2v22h-2v-2H5v2zm2-12h2V7h6v4h6V5H5zm0 8h6v-4h6v4h2v-6H5z"/>',
        },
    },
});
addCollection({
    prefix: 'boxicons',
    icons: {
        'door-open-alt-filled': {
            width: 24,
            height: 24,
            body: '<path fill="currentColor" d="m20.2 4.02l-10-2c-.29-.06-.6.02-.83.21S9 2.7 9 3v1H4c-.55 0-1 .45-1 1v14c0 .55.45 1 1 1h5v1c0 .3.13.58.37.77c.18.15.4.23.63.23c.07 0 .13 0 .2-.02l10-2c.47-.09.8-.5.8-.98V5c0-.48-.34-.89-.8-.98M5 18V6h4v12zm8-5c-.55 0-1-.45-1-1s.45-1 1-1s1 .45 1 1s-.45 1-1 1"/>',
        },
    },
});
for (const prefix of ['mdi', 'material-symbols', 'boxicons']) {
    setCustomIconLoader((iconName) => {
        console.error(`Icon is missing from the bundled ${prefix} collection: ${prefix}:${iconName}`);
        return null;
    }, prefix);
}

window.Alpine = Alpine;

const toastTypes = new Set(['success', 'error', 'info', 'warning']);

const formatToastMessage = (message, type) => {
    if (typeof message === 'string') {
        const namedEntity = message.match(
            /^(Barang|Kategori|Ruangan|Lemari(?:\/Rak)?|Wadah|Akun|Peminjaman)\s+(.+?)\s+(?=(?:berhasil|gagal|tidak|masih|ditolak|diaktifkan|dinonaktifkan)\b)/i,
        );

        if (namedEntity) {
            const entityNameStart = namedEntity.index + namedEntity[1].length + 1;
            const entityNameEnd = entityNameStart + namedEntity[2].length;

            return [
                { text: message.slice(0, entityNameStart), bold: false },
                { text: namedEntity[2], bold: true },
                { text: message.slice(entityNameEnd), bold: false },
            ];
        }

        const importantPhrase = type === 'error'
            ? message.match(/(?:masih digunakan oleh|digunakan oleh|masih memiliki|memiliki riwayat|riwayat peminjaman|riwayat pemakaian|riwayat mutasi)\s+([^.!?]+)([.!?]?)$/i)
            : null;

        if (importantPhrase) {
            const phraseStart = importantPhrase.index + importantPhrase[0].indexOf(importantPhrase[1]);
            return [
                { text: message.slice(0, phraseStart), bold: false },
                { text: importantPhrase[1], bold: true },
                { text: importantPhrase[2], bold: false },
            ].filter((part) => part.text !== '');
        }

        return [{ text: message, bold: false }];
    }

    return Array.isArray(message)
        ? message.map((part) => ({
            text: typeof part?.text === 'string' ? part.text : '',
            bold: part?.bold === true,
        }))
        : [];
};

let toastSequence = 0;

window.showToast = (message, type = 'success', duration = 5000, title = '', action = null) => {
    const messageParts = formatToastMessage(message, type);
    const validParts = messageParts.filter((part) => part.text !== '');

    if (validParts.length === 0) {
        console.error('A toast requires a non-empty message.');
        return;
    }

    if (!toastTypes.has(type)) {
        console.error(`Unsupported toast type: ${type}`);
        type = 'info';
    }

    const durationMs = Number.isFinite(duration)
        ? Math.min(Math.max(duration, 1000), 10000)
        : 5000;

    const toastId = ++toastSequence;
    window.dispatchEvent(new CustomEvent('app-toast', {
        detail: {
            toastId,
            message: validParts,
            type,
            duration: durationMs,
            title: typeof title === 'string' ? title.trim() : '',
            actionLabel: typeof action?.label === 'string' ? action.label.trim() : '',
            onAction: typeof action?.onClick === 'function' ? action.onClick : null,
        },
    }));
    return toastId;
};

Alpine.data('globalToast', () => ({
    visible: false,
    message: '',
    type: 'success',
    title: 'Berhasil!',
    duration: 5000,
    toastId: null,
    actionLabel: '',
    onAction: null,
    timeout: null,
    progressAnimation: null,
    toastListener: null,
    init() {
        this.toastListener = (event) => {
            if (this.visible) this.dismiss();
            this.toastId = event.detail.toastId;
            this.message = event.detail.message;
            this.type = event.detail.type;
            this.duration = event.detail.duration;
            this.actionLabel = event.detail.actionLabel || '';
            this.onAction = event.detail.onAction || null;
            this.title = event.detail.title || ({
                error: 'Gagal!',
                warning: 'Perhatian!',
                info: 'Informasi',
                success: 'Berhasil!',
            })[this.type];
            this.visible = true;
            window.clearTimeout(this.timeout);
            this.progressAnimation?.cancel();
            this.progressAnimation = null;
            this.$nextTick(() => {
                this.progressAnimation = this.$refs.progress.animate(
                    [{ transform: 'scaleX(1)' }, { transform: 'scaleX(0)' }],
                    { duration: this.duration, easing: 'linear', fill: 'forwards' },
                );
            });
            this.timeout = window.setTimeout(() => {
                this.dismiss();
            }, event.detail.duration);
        };

        window.addEventListener('app-toast', this.toastListener);
    },
    destroy() {
        window.removeEventListener('app-toast', this.toastListener);
        window.clearTimeout(this.timeout);
        this.progressAnimation?.cancel();
    },
    dismiss() {
        const toastId = this.toastId;
        this.visible = false;
        this.toastId = null;
        this.actionLabel = '';
        this.onAction = null;
        window.clearTimeout(this.timeout);
        this.progressAnimation?.cancel();
        if (toastId !== null) {
            window.dispatchEvent(new CustomEvent('app-toast-dismissed', {
                detail: { toastId },
            }));
        }
    },
    activateAction() {
        if (typeof this.onAction !== 'function') return;

        const action = this.onAction;
        this.dismiss();
        action();
    },
}));

Alpine.data('notificationDropdown', () => ({
    open: false,
    count: 0,
    notificationAvailable: true,
    notifications: [],
    loading: false,
    error: '',
    countUrl: '',
    readUrl: '',
    historyUrl: '',
    csrfToken: '',
    pollTimer: null,
    visibilityListener: null,
    init() {
        this.countUrl = this.$el.dataset.countUrl || '';
        this.readUrl = this.$el.dataset.readUrl || '';
        this.historyUrl = this.$el.dataset.historyUrl || '';
        this.csrfToken = this.$el.dataset.csrfToken || '';
        this.refreshCount();
        this.pollTimer = window.setInterval(() => {
            this.refreshCount();
        }, 5000);
        this.visibilityListener = () => {
            if (!document.hidden) {
                this.refreshCount();
            }
        };
        document.addEventListener('visibilitychange', this.visibilityListener);
    },
    destroy() {
        window.clearInterval(this.pollTimer);
        document.removeEventListener('visibilitychange', this.visibilityListener);
    },
    async refreshCount() {
        if (document.hidden || !this.countUrl) {
            return;
        }

        try {
            const response = await fetch(this.countUrl, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
                cache: 'no-store',
            });
            if (!response.ok) {
                if (response.status === 503) {
                    this.notificationAvailable = false;
                    this.error = 'Layanan notifikasi sedang tidak tersedia. Coba lagi nanti.';
                    return;
                }

                throw new Error(`Notification count request failed (${response.status}).`);
            }

            const payload = await response.json();
            if (!Number.isInteger(payload.count) || payload.count < 0) {
                throw new Error('Notification count response is invalid.');
            }

            this.count = payload.count;
            this.notificationAvailable = true;
            this.error = '';
        } catch (error) {
            console.error('Failed to refresh notification count.', error);
            this.notificationAvailable = false;
            this.error = 'Notifikasi tidak dapat dimuat. Coba lagi nanti.';
        }
    },
    async toggle() {
        if (this.open) {
            this.open = false;
            return;
        }

        this.open = true;
        this.loading = true;
        this.error = '';

        try {
            const response = await fetch(this.readUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
                credentials: 'same-origin',
                cache: 'no-store',
            });
            if (!response.ok) {
                if (response.status === 503) {
                    this.notificationAvailable = false;
                    this.error = 'Layanan notifikasi sedang tidak tersedia. Coba lagi nanti.';
                    return;
                }

                throw new Error(`Notification read request failed (${response.status}).`);
            }

            const payload = await response.json();
            if (!Array.isArray(payload.notifications)) {
                throw new Error('Notification list response is invalid.');
            }

            this.notifications = payload.notifications;
            this.count = 0;
            this.notificationAvailable = true;
        } catch (error) {
            console.error('Failed to read notifications.', error);
            this.notificationAvailable = false;
            this.error = 'Notifikasi tidak dapat dimuat. Coba lagi nanti.';
        } finally {
            this.loading = false;
        }
    },
    formattedDate(value) {
        return new Intl.DateTimeFormat('id-ID', {
            dateStyle: 'medium',
            timeStyle: 'short',
        }).format(new Date(value));
    },
}));

Alpine.data('loanQrScanner', () => ({
    loanListRequest: null,
    loanListLoading: false,
    loanListError: '',
    loanListRefreshTimer: null,
    loanListVisibilityListener: null,
    scannerOpen: false,
    scannerMessage: '',
    cameraSupported: false,
    cameraReady: false,
    cameraAspectRatio: 16 / 9,
    isScanning: false,
    isSubmitting: false,
    manualCode: '',
    scanEndpoint: '',
    csrfToken: '',
    stream: null,
    frameRequest: null,
    lastScanAt: 0,
    init() {
        const feedbackMessage = this.$el.dataset.feedbackMessage || '';
        if (feedbackMessage) {
            window.showToast(feedbackMessage, 'success');
        }
        this.scanEndpoint = this.$el.dataset.endpoint || '';
        this.csrfToken = this.$el.dataset.csrfToken || '';
        this.loanListRefreshTimer = window.setInterval(() => {
            this.refreshLoanList();
        }, 10000);
        this.loanListVisibilityListener = () => {
            if (!document.hidden) {
                this.refreshLoanList();
            }
        };
        document.addEventListener('visibilitychange', this.loanListVisibilityListener);
    },
    destroy() {
        window.clearInterval(this.loanListRefreshTimer);
        document.removeEventListener('visibilitychange', this.loanListVisibilityListener);
        this.loanListRequest?.abort();
    },
    refreshLoanList() {
        if (document.hidden || this.loanListRequest) {
            return;
        }

        this.loadLoanList(window.location.href, { silent: true });
    },
    loanListSignature(markup) {
        const content = document.createElement('div');
        content.innerHTML = markup;

        return JSON.stringify({
            text: content.textContent.replace(/\s+/g, ' ').trim(),
            navigation: Array.from(content.querySelectorAll('a[href]'), (link) => [
                link.getAttribute('href'),
                link.getAttribute('aria-current'),
            ]),
            forms: Array.from(content.querySelectorAll('form[action]'), (form) => [
                form.getAttribute('action'),
                form.getAttribute('method'),
            ]),
        });
    },
    handleLoanNavigation(event) {
        const target = event.target instanceof Element ? event.target : null;
        const link = target?.closest('a[href]');
        if (
            !link
            || !this.$refs.loanList.contains(link)
            || event.defaultPrevented
            || event.button !== 0
            || event.metaKey
            || event.ctrlKey
            || event.shiftKey
            || event.altKey
        ) {
            return;
        }

        const requestUrl = new URL(link.href, window.location.href);
        if (requestUrl.origin !== window.location.origin || requestUrl.pathname !== window.location.pathname) {
            return;
        }

        event.preventDefault();
        this.loadLoanList(link.href);
    },
    async loadLoanList(url, { silent = false } = {}) {
        const requestUrl = new URL(url, window.location.href);
        if (requestUrl.origin !== window.location.origin || requestUrl.pathname !== window.location.pathname) {
            return;
        }

        this.loanListRequest?.abort();
        const request = new AbortController();
        this.loanListRequest = request;
        this.loanListLoading = !silent;
        this.loanListError = '';

        try {
            const response = await fetch(requestUrl, {
                cache: 'no-store',
                headers: {
                    'Accept': 'text/html',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                signal: request.signal,
            });
            if (!response.ok || response.redirected) {
                throw new Error('Daftar peminjaman tidak dapat dimuat.');
            }

            const markup = await response.text();
            if (this.loanListSignature(this.$refs.loanList.innerHTML) !== this.loanListSignature(markup)) {
                this.$refs.loanList.innerHTML = markup;
            }
            if (requestUrl.href !== window.location.href) {
                window.history.replaceState({}, '', requestUrl);
            }
        } catch (error) {
            if (error.name !== 'AbortError') {
                this.loanListError = error.message || 'Daftar peminjaman tidak dapat dimuat.';
            }
        } finally {
            if (this.loanListRequest === request) {
                this.loanListLoading = false;
                this.loanListRequest = null;
            }
        }
    },
    async openScanner() {
        this.scannerOpen = true;
        this.scannerMessage = '';
        this.manualCode = '';
        await this.$nextTick();

        if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
            this.scannerMessage = 'Kamera perlu dijalankan melalui HTTPS atau localhost. Masukkan kode QR secara manual.';
            return;
        }

        this.cameraSupported = true;
        await this.startCamera();
    },
    async startCamera() {
        this.stopCamera();

        try {
            this.stream = await navigator.mediaDevices.getUserMedia({
                audio: false,
                video: { facingMode: { ideal: 'environment' } },
            });
            const video = this.$refs.cameraVideo;
            video.srcObject = this.stream;
            this.cameraReady = true;
            await this.$nextTick();
            await video.play();
            if (video.videoWidth > 0 && video.videoHeight > 0) {
                this.cameraAspectRatio = video.videoWidth / video.videoHeight;
            }
            this.isScanning = true;
            this.lastScanAt = 0;
            this.scannerMessage = '';
            this.detectFrame();
        } catch (error) {
            console.error('Failed to access a camera for loan QR scanning.', error);
            this.stopCamera();
            this.scannerMessage = error.name === 'NotAllowedError'
                ? 'Izin kamera ditolak. Izinkan kamera di browser lalu coba lagi.'
                : 'Kamera tidak dapat dibuka. Periksa kamera atau masukkan kode QR secara manual.';
        }
    },
    async detectFrame() {
        if (!this.isScanning) {
            return;
        }

        const now = performance.now();
        if (now - this.lastScanAt < 120) {
            this.frameRequest = window.requestAnimationFrame(() => this.detectFrame());
            return;
        }
        this.lastScanAt = now;

        try {
            const video = this.$refs.cameraVideo;
            if (video.readyState < HTMLMediaElement.HAVE_CURRENT_DATA || video.videoWidth === 0) {
                this.frameRequest = window.requestAnimationFrame(() => this.detectFrame());
                return;
            }

            const canvas = this.$refs.scanCanvas;
            const width = Math.min(video.videoWidth, 720);
            const height = Math.round(width * video.videoHeight / video.videoWidth);
            if (canvas.width !== width || canvas.height !== height) {
                canvas.width = width;
                canvas.height = height;
            }

            const context = canvas.getContext('2d', { willReadFrequently: true });
            if (!context) {
                throw new Error('Unable to create a canvas context for QR scanning.');
            }

            context.drawImage(video, 0, 0, width, height);
            const image = context.getImageData(0, 0, width, height);
            const result = jsQR(image.data, width, height, { inversionAttempts: 'attemptBoth' });
            if (result) {
                this.stopCamera();
                await this.submitCode(result.data);
                return;
            }
        } catch (error) {
            console.error('Failed to read a QR code from the camera stream.', error);
            this.stopCamera();
            this.scannerMessage = 'QR tidak dapat dibaca. Coba arahkan kamera kembali atau masukkan kode manual.';
            return;
        }

        this.frameRequest = window.requestAnimationFrame(() => this.detectFrame());
    },
    stopCamera() {
        this.isScanning = false;
        if (this.frameRequest !== null) {
            window.cancelAnimationFrame(this.frameRequest);
            this.frameRequest = null;
        }
        this.stream?.getTracks().forEach((track) => track.stop());
        this.stream = null;
        if (this.$refs.cameraVideo) {
            this.$refs.cameraVideo.srcObject = null;
        }
        this.cameraReady = false;
    },
    closeScanner() {
        this.stopCamera();
        this.scannerOpen = false;
    },
    async submitCode(code = this.manualCode) {
        const normalizedCode = code.trim().toUpperCase();
        if (!normalizedCode) {
            this.scannerMessage = 'Masukkan kode dari QR peminjaman.';
            return;
        }

        this.isSubmitting = true;
        this.scannerMessage = 'Memeriksa kode dan mengonfirmasi peminjaman...';

        try {
            if (!this.scanEndpoint || !this.csrfToken) {
                this.scannerMessage = 'Alamat pemindaian tidak tersedia. Muat ulang halaman admin lalu coba lagi.';
                return;
            }

            const scanEndpoint = new URL(this.scanEndpoint, window.location.href);
            const scanPathname = scanEndpoint.pathname.replace(/\/+$/, '');
            if (
                scanEndpoint.origin !== window.location.origin
                || !scanPathname.endsWith('/admin/peminjaman/scan')
            ) {
                this.scannerMessage = 'Alamat pemindaian tidak valid. Muat ulang halaman admin lalu coba lagi.';
                return;
            }

            // ASVS 2.2.2: Server-side validation remains authoritative for manually entered and scanned codes.
            const response = await fetch(scanEndpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
                body: JSON.stringify({ code: normalizedCode }),
            });
            if (response.redirected || new URL(response.url).origin !== window.location.origin) {
                this.scannerMessage = 'Sesi admin berakhir. Masuk kembali lalu coba pindai QR.';
                return;
            }

            if (!response.headers.get('content-type')?.includes('application/json')) {
                this.scannerMessage = 'Server tidak mengirim hasil pemindaian yang valid. Coba pindai QR kembali.';
                return;
            }

            const payload = await response.json();

            if (!response.ok) {
                this.scannerMessage = payload.errors?.code?.[0]
                    || payload.errors?.peminjaman?.[0]
                    || (response.status === 404
                        ? 'Layanan pemindaian tidak ditemukan. Muat ulang halaman admin lalu coba lagi.'
                        : 'QR tidak dapat diproses. Pastikan QR berasal dari pengajuan yang menunggu konfirmasi.');
                return;
            }

            if (
                !payload.loan_id
                || typeof payload.message !== 'string'
                || typeof payload.redirect_url !== 'string'
            ) {
                this.scannerMessage = 'Konfirmasi belum dapat dipastikan. Periksa status peminjaman sebelum memindai ulang.';
                return;
            }

            const destinationPathname = scanPathname.replace(/\/scan$/, '');
            // ASVS 1.2.2: constrain server-provided redirects to the expected same-origin loan page.
            const destination = new URL(payload.redirect_url, scanEndpoint.origin);
            if (
                destination.origin !== scanEndpoint.origin
                || destination.pathname !== destinationPathname
                || destination.searchParams.get('tab') !== 'borrowed'
            ) {
                this.scannerMessage = 'Halaman pemindaian peminjaman tidak valid. Muat ulang halaman admin.';
                return;
            }

            this.closeScanner();
            window.setTimeout(() => window.location.assign(destination.href), 900);
        } catch (error) {
            console.error('Failed to submit the scanned loan QR code.', error);
            this.scannerMessage = 'Koneksi gagal. Periksa jaringan lalu coba pindai QR kembali.';
        } finally {
            this.isSubmitting = false;
        }
    },
}));

const adminFieldSelector = 'input, select, textarea';
const ignoredIndicatorTypes = new Set(['hidden', 'button', 'submit', 'reset', 'image']);
const adminFieldIndicators = new WeakMap();

const findAdminFieldLabel = (field) => {
    const directLabel = field.closest('label') || field.labels?.[0];
    if (directLabel) {
        return directLabel;
    }

    const form = field.closest('form');
    let ancestor = field.parentElement;

    while (ancestor && ancestor !== form) {
        const labels = Array.from(ancestor.children).filter((child) => child.tagName === 'LABEL');
        const controls = ancestor.querySelectorAll(adminFieldSelector);
        if (labels.length === 1 && controls.length === 1) {
            return labels[0];
        }

        ancestor = ancestor.parentElement;
    }

    return null;
};

const adminFieldHasValue = (field) => {
    if (field.type === 'checkbox' || field.type === 'radio') {
        return field.checked;
    }

    if (field.type === 'file') {
        return (field.files?.length ?? 0) > 0;
    }

    return String(field.value ?? '').trim() !== '';
};

const isAdminFilterOrSearchField = (field, label) => (
    field.type === 'search'
    || field.name.toLocaleLowerCase() === 'search'
    || field.closest('form')?.method === 'get'
    || /\b(filter|cari|pencarian)\b/i.test(label.textContent)
);

const updateAdminFieldIndicator = (field) => {
    if (
        !(field instanceof HTMLInputElement || field instanceof HTMLSelectElement || field instanceof HTMLTextAreaElement)
        || ignoredIndicatorTypes.has(field.type)
    ) {
        return;
    }

    const label = findAdminFieldLabel(field);
    if (!label) {
        return;
    }

    const requiredGroup = field.type === 'radio'
        ? field.closest('fieldset')?.querySelector(':scope > legend [data-admin-field-indicator], :scope > legend span')
        : null;
    if (requiredGroup?.textContent.trim() === '*') {
        return;
    }

    const required = field.required || field.getAttribute('aria-required') === 'true';
    if (!required && isAdminFilterOrSearchField(field, label)) {
        return;
    }

    let indicator = adminFieldIndicators.get(field);

    if (!indicator) {
        indicator = Array.from(label.children).find((child) => (
            child.dataset.adminFieldIndicator !== undefined
            || child.textContent.trim() === '*'
        ));

        if (!indicator && !required) {
            return;
        }

        if (!indicator) {
            indicator = document.createElement('span');
            const firstControl = label.querySelector(adminFieldSelector);
            if (firstControl) {
                label.insertBefore(indicator, firstControl);
            } else {
                label.append(indicator);
            }
        }

        indicator.dataset.adminFieldIndicator = '';
        indicator.setAttribute('aria-hidden', 'true');
        adminFieldIndicators.set(field, indicator);
    }

    const indicatorText = required ? '*' : '';
    if (indicator.textContent !== indicatorText) {
        indicator.textContent = indicatorText;
    }

    const indicatorClass = 'ml-1 text-rose-500';
    if (indicator.className !== indicatorClass) {
        indicator.className = indicatorClass;
    }

    indicator.hidden = field.disabled || !required || adminFieldHasValue(field);
};

const updateAdminFieldsetIndicators = () => {
    document.querySelectorAll('fieldset > legend span').forEach((indicator) => {
        if (indicator.textContent.trim() !== '*') {
            return;
        }

        const fieldset = indicator.closest('fieldset');
        const selected = fieldset?.querySelector('input[type="radio"]:checked, input[type="checkbox"]:checked');
        indicator.hidden = Boolean(selected);
    });
};

const updateAdminFieldIndicators = () => {
    if (!document.body.hasAttribute('data-admin-field-indicators')) {
        return;
    }

    document.querySelectorAll(adminFieldSelector).forEach(updateAdminFieldIndicator);
    updateAdminFieldsetIndicators();
};

if (document.body.hasAttribute('data-admin-field-indicators')) {
    const adminFieldIndicatorObserver = new MutationObserver(updateAdminFieldIndicators);
    adminFieldIndicatorObserver.observe(document.body, {
        attributes: true,
        attributeFilter: ['aria-required', 'disabled', 'required'],
        childList: true,
        subtree: true,
    });

    document.addEventListener('input', (event) => {
        updateAdminFieldIndicator(event.target);
        updateAdminFieldsetIndicators();
    });
    document.addEventListener('change', (event) => {
        updateAdminFieldIndicator(event.target);
        updateAdminFieldsetIndicators();
    });
}

Alpine.start();
updateAdminFieldIndicators();

const renderLoanQr = (element) => {
    if (element.dataset.loanQrRendered === 'true') {
        return;
    }

    element.dataset.loanQrRendered = 'true';
    const canvas = document.createElement('canvas');

    QRCode.toCanvas(canvas, element.dataset.loanQr, {
        errorCorrectionLevel: 'M',
        margin: 4,
        width: 240,
    }).then(() => {
        element.replaceChildren(canvas);
    }).catch((error) => {
        console.error('Failed to render the loan QR code.', error);
        element.textContent = 'QR tidak dapat ditampilkan.';
    });
};

const renderLoanQrs = (node) => {
    if (!(node instanceof Element)) {
        return;
    }

    if (node.matches('[data-loan-qr]')) {
        renderLoanQr(node);
    }

    node.querySelectorAll('[data-loan-qr]').forEach(renderLoanQr);
};

const loanQrObserver = new MutationObserver((mutations) => {
    for (const mutation of mutations) {
        mutation.addedNodes.forEach(renderLoanQrs);
    }
});
loanQrObserver.observe(document.body, { childList: true, subtree: true });
renderLoanQrs(document.body);
