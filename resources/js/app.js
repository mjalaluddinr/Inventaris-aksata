import Alpine from 'alpinejs';
import 'iconify-icon';
import { addCollection, setCustomIconLoader } from 'iconify-icon';
import mdiIcons from './icons/mdi.json';

let qrDecoderPromise = null;
let qrCodeModulePromise = null;

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
    countRequestInProgress: false,
    init() {
        this.countUrl = this.$el.dataset.countUrl || '';
        this.readUrl = this.$el.dataset.readUrl || '';
        this.historyUrl = this.$el.dataset.historyUrl || '';
        this.csrfToken = this.$el.dataset.csrfToken || '';
        this.refreshCount();
        this.pollTimer = window.setInterval(() => {
            this.refreshCount();
        }, 30000);
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
        if (document.hidden || !this.countUrl || this.countRequestInProgress) {
            return;
        }

        this.countRequestInProgress = true;
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
        } finally {
            this.countRequestInProgress = false;
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
            timeZone: 'Asia/Jakarta',
        }).format(new Date(value));
    },
}));

Alpine.data('loanQrScanner', () => ({
    loanListRequest: null,
    loanListLoading: false,
    loanListError: '',
    loanActionSubmitting: false,
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
    qrFrame: null,
    init() {
        const feedbackMessage = this.$el.dataset.feedbackMessage || '';
        if (feedbackMessage) {
            window.showToast(feedbackMessage, 'success');
        }
        this.scanEndpoint = this.$el.dataset.endpoint || '';
        this.csrfToken = this.$el.dataset.csrfToken || '';
        this.loanListRefreshTimer = window.setInterval(() => {
            this.refreshLoanList();
        }, 30000);
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
        this.stopCamera();
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
    async submitLoanAction(event) {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || this.loanActionSubmitting) {
            return;
        }

        this.loanActionSubmitting = true;
        const submitButton = form.querySelector('button[type="submit"]');
        const loadingIcon = submitButton?.querySelector('[data-loan-action-spinner]');
        const idleIcon = submitButton?.querySelector('[data-loan-action-icon]');
        const buttonLabel = submitButton?.querySelector('[data-loan-action-label]');
        const idleLabel = buttonLabel?.textContent;
        if (submitButton) {
            submitButton.disabled = true;
            submitButton.setAttribute('aria-busy', 'true');
        }
        if (loadingIcon) {
            loadingIcon.classList.remove('hidden');
        }
        if (idleIcon) {
            idleIcon.classList.add('hidden');
        }
        if (buttonLabel) {
            buttonLabel.textContent = 'Memproses...';
        }

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: new FormData(form),
            });
            let result;
            try {
                result = await response.json();
            } catch {
                throw new Error('Respons peminjaman tidak dapat dibaca.');
            }

            if (!response.ok) {
                const validationMessage = result?.errors
                    ? Object.values(result.errors).flat().join(' ')
                    : '';
                throw new Error(validationMessage || result?.message || 'Peminjaman gagal diproses.');
            }

            if (typeof result?.message !== 'string' || typeof result?.tab !== 'string') {
                throw new Error('Respons peminjaman tidak lengkap.');
            }

            window.showToast(result.message, 'success');
            await this.loadLoanList(window.location.href);
        } catch (error) {
            window.showToast(error.message || 'Peminjaman gagal diproses. Coba lagi.', 'error');
        } finally {
            this.loanActionSubmitting = false;
            if (submitButton?.isConnected) {
                submitButton.disabled = false;
                submitButton.removeAttribute('aria-busy');
            }
            if (loadingIcon?.isConnected) {
                loadingIcon.classList.add('hidden');
            }
            if (idleIcon?.isConnected) {
                idleIcon.classList.remove('hidden');
            }
            if (buttonLabel?.isConnected && idleLabel !== undefined) {
                buttonLabel.textContent = idleLabel;
            }
        }
    },
    async openScanner() {
        this.scannerOpen = true;
        this.scannerMessage = '';
        this.manualCode = '';
        this.qrFrame = null;
        await this.$nextTick();

        if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
            this.scannerMessage = 'Kamera perlu dijalankan melalui HTTPS atau localhost. Masukkan kode QR secara manual.';
            return;
        }

        this.cameraSupported = true;
        try {
            qrDecoderPromise ??= import('jsqr').then(({ default: decodeQr }) => decodeQr);
            await qrDecoderPromise;
        } catch (error) {
            qrDecoderPromise = null;
            console.error('Failed to load the QR scanner.', error);
            this.scannerMessage = 'Pemindai QR tidak dapat dimuat. Masukkan kode QR secara manual.';
            return;
        }

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
            const decodeQr = await qrDecoderPromise;
            const result = decodeQr(image.data, width, height, { inversionAttempts: 'attemptBoth' });
            if (result) {
                const { topLeftCorner, topRightCorner, bottomRightCorner, bottomLeftCorner } = result.location;
                this.qrFrame = {
                    width,
                    height,
                    points: [topLeftCorner, topRightCorner, bottomRightCorner, bottomLeftCorner]
                        .map(({ x, y }) => `${x},${y}`)
                        .join(' '),
                };
                this.stopCamera({ preservePreview: true });
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
    stopCamera({ preservePreview = false } = {}) {
        this.isScanning = false;
        if (this.frameRequest !== null) {
            window.cancelAnimationFrame(this.frameRequest);
            this.frameRequest = null;
        }
        this.stream?.getTracks().forEach((track) => track.stop());
        this.stream = null;
        if (!preservePreview && this.$refs.cameraVideo) {
            this.$refs.cameraVideo.srcObject = null;
        }
        if (!preservePreview) {
            this.cameraReady = false;
            this.qrFrame = null;
        }
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
                let insertionPoint = firstControl;
                while (insertionPoint.parentElement !== label) {
                    insertionPoint = insertionPoint.parentElement;
                }
                label.insertBefore(indicator, insertionPoint);
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

const isAdminPageUrl = (url) => url.pathname === '/admin' || url.pathname.startsWith('/admin/');
const executedAdminPageScripts = new Set();

const adminPageScriptKey = (script) => `${script.type}|${script.src || script.textContent}`;

const rememberAdminPageScripts = (page) => {
    page?.querySelectorAll('script').forEach((script) => {
        executedAdminPageScripts.add(adminPageScriptKey(script));
    });
};

const runAdminPageScripts = (page) => {
    for (const script of page.querySelectorAll('script')) {
        const key = adminPageScriptKey(script);
        if (executedAdminPageScripts.has(key)) {
            script.remove();
            continue;
        }

        if (script.src) {
            throw new Error('Admin page has an external script and requires a full navigation.');
        }

        const executableScript = document.createElement('script');
        for (const attribute of script.attributes) {
            executableScript.setAttribute(attribute.name, attribute.value);
        }
        executableScript.textContent = script.textContent;
        script.replaceWith(executableScript);
        executedAdminPageScripts.add(key);
    }
};

const initAdminAjaxNavigation = () => {
    const main = document.getElementById('admin-main-content');
    const loadingIndicator = document.getElementById('admin-navigation-progress');
    const currentPage = main?.querySelector('.admin-page-enter');
    if (!main || !currentPage) {
        return;
    }

    rememberAdminPageScripts(currentPage);
    window.history.replaceState({
        ...(window.history.state || {}),
        adminNavigation: true,
        adminScrollTop: main.scrollTop,
    }, '', window.location.href);

    let activeNavigation = null;
    let loadingIndicatorTimeout = null;
    let loadingIndicatorFrame = null;
    let loadingProgress = 0;
    const loadingBar = loadingIndicator?.querySelector('span');
    const startLoadingIndicator = () => {
        if (!loadingIndicator) {
            return;
        }

        window.clearTimeout(loadingIndicatorTimeout);
        if (loadingIndicatorFrame !== null) {
            window.cancelAnimationFrame(loadingIndicatorFrame);
        }
        loadingProgress = 0;
        const progressStartedAt = performance.now();
        loadingIndicator.dataset.complete = 'false';
        loadingIndicator.dataset.loading = 'true';
        if (loadingBar) {
            loadingBar.style.transform = 'scaleX(0)';
        }
        const advanceProgress = () => {
            if (!activeNavigation) {
                return;
            }

            const elapsed = performance.now() - progressStartedAt;
            loadingProgress = 0.9 * (1 - Math.exp(-elapsed / 4000));
            if (loadingBar) {
                loadingBar.style.transform = `scaleX(${loadingProgress})`;
            }
            loadingIndicatorFrame = window.requestAnimationFrame(advanceProgress);
        };
        loadingIndicatorFrame = window.requestAnimationFrame(advanceProgress);
    };
    const finishLoadingIndicator = () => {
        if (!loadingIndicator) {
            return;
        }

        if (loadingIndicatorFrame !== null) {
            window.cancelAnimationFrame(loadingIndicatorFrame);
            loadingIndicatorFrame = null;
        }
        loadingProgress = 1;
        if (loadingBar) {
            loadingBar.style.transform = 'scaleX(1)';
        }
        loadingIndicator.dataset.complete = 'true';
        loadingIndicatorTimeout = window.setTimeout(() => {
            loadingIndicator.dataset.loading = 'false';
            loadingIndicator.dataset.complete = 'false';
            if (loadingBar) {
                loadingBar.style.transform = '';
            }
        }, 180);
    };
    const navigate = async (destination, { pushHistory = false, scrollTop = 0 } = {}) => {
        const request = new AbortController();
        activeNavigation?.abort();
        activeNavigation = request;
        startLoadingIndicator();
        main.setAttribute('aria-busy', 'true');

        try {
            const response = await fetch(destination, {
                headers: {
                    'Accept': 'text/html',
                },
                credentials: 'same-origin',
                cache: 'no-store',
                signal: request.signal,
            });
            const responseUrl = new URL(response.url, window.location.href);
            const contentType = response.headers.get('content-type') || '';
            // ASVS V4.1.1: accept only HTML navigation responses with a matching content type.
            if (
                !response.ok
                || responseUrl.origin !== window.location.origin
                || !isAdminPageUrl(responseUrl)
                || !contentType.includes('text/html')
            ) {
                throw new Error(`Admin page navigation failed with status ${response.status}.`);
            }

            const responseDocument = new DOMParser().parseFromString(await response.text(), 'text/html');
            const nextPage = responseDocument.querySelector('#admin-main-content .admin-page-enter');
            const currentNavigation = document.getElementById('admin-navigation');
            const nextNavigation = responseDocument.getElementById('admin-navigation');
            if (!nextPage || !currentNavigation || !nextNavigation) {
                throw new Error('Admin navigation response did not contain the expected page layout.');
            }

            nextPage.querySelectorAll('[data-admin-persistent][id]').forEach((element) => {
                if (document.getElementById(element.id)) {
                    element.remove();
                }
            });

            const previousPage = main.querySelector('.admin-page-enter');
            if (!previousPage) {
                throw new Error('The current admin page is no longer available.');
            }
            previousPage.replaceWith(nextPage);
            currentNavigation.replaceChildren(...Array.from(nextNavigation.childNodes));

            runAdminPageScripts(nextPage);
            window.Alpine.initTree(currentNavigation);
            window.Alpine.initTree(nextPage);
            document.title = responseDocument.title;
            main.scrollTop = scrollTop;
            updateAdminFieldIndicators();
            window.dispatchEvent(new CustomEvent('admin-sidebar-close'));

            if (pushHistory) {
                window.history.pushState({
                    adminNavigation: true,
                    adminScrollTop: scrollTop,
                }, '', responseUrl.href);
            }
        } catch (error) {
            if (error.name !== 'AbortError') {
                console.error('Failed to navigate between admin pages.', error);
                window.location.assign(destination.href);
            }
        } finally {
            if (activeNavigation === request) {
                activeNavigation = null;
                main.removeAttribute('aria-busy');
                finishLoadingIndicator();
            }
        }
    };

    document.addEventListener('click', (event) => {
        const link = event.target instanceof Element ? event.target.closest('a[href]') : null;
        if (
            !link
            || event.defaultPrevented
            || event.button !== 0
            || event.metaKey
            || event.ctrlKey
            || event.shiftKey
            || event.altKey
            || link.target
            || link.hasAttribute('download')
            || link.closest('form')
            || link.hasAttribute('data-admin-ajax-ignore')
        ) {
            return;
        }

        // ASVS V3.5.1/V8.3.1: intercept only same-origin admin GET navigation; server middleware still authorizes each request.
        const destination = new URL(link.href, window.location.href);
        if (
            destination.origin !== window.location.origin
            || !isAdminPageUrl(destination)
            || (destination.pathname === window.location.pathname
                && destination.search === window.location.search
                && destination.hash !== '')
            || /\/(?:export|print)(?:\/|$)/.test(destination.pathname)
        ) {
            return;
        }

        event.preventDefault();
        window.history.replaceState({
            ...(window.history.state || {}),
            adminNavigation: true,
            adminScrollTop: main.scrollTop,
        }, '', window.location.href);
        navigate(destination, { pushHistory: true });
    });

    window.addEventListener('user:page-navigation-requested', (event) => {
        const requestedUrl = event.detail?.url;
        if (typeof requestedUrl !== 'string') {
            return;
        }

        let destination;
        try {
            destination = new URL(requestedUrl, window.location.href);
        } catch (error) {
            console.error('Rejected invalid borrower page navigation URL.', error);
            return;
        }

        if (destination.origin !== window.location.origin || !userPagePaths.has(destination.pathname)) {
            console.error('Rejected borrower page navigation outside the allowed portal paths.');
            return;
        }

        navigate(destination, { pushHistory: true });
    });

    window.addEventListener('popstate', (event) => {
        const destination = new URL(window.location.href);
        if (!isAdminPageUrl(destination)) {
            window.location.reload();
            return;
        }

        navigate(destination, {
            scrollTop: Number.isFinite(event.state?.adminScrollTop) ? event.state.adminScrollTop : 0,
        });
    });
};

const initUserAjaxNavigation = () => {
    const pageContent = document.getElementById('user-page-content');
    const loadingIndicator = document.getElementById('user-navigation-progress');
    if (!pageContent || !loadingIndicator) {
        return;
    }

    const userPagePaths = new Set((pageContent.dataset.userPaths || '').split('|').filter(Boolean));
    const pageScriptNames = new Set(['home-reveals', 'catalog-reveals']);
    const executedPageScripts = new Set(
        Array.from(pageContent.querySelectorAll('script[data-user-page-script]'), (script) => script.dataset.userPageScript),
    );
    let activeNavigation = null;
    let loadingIndicatorTimeout = null;
    let currentPath = window.location.pathname;
    let currentUrl = new URL(window.location.href);

    const runUserPageScripts = (root) => {
        for (const script of root.querySelectorAll('script[data-user-page-script]')) {
            const scriptName = script.dataset.userPageScript;
            if (!pageScriptNames.has(scriptName)) {
                script.remove();
                continue;
            }
            if (executedPageScripts.has(scriptName)) {
                script.remove();
                continue;
            }

            const executableScript = document.createElement('script');
            for (const attribute of script.attributes) {
                executableScript.setAttribute(attribute.name, attribute.value);
            }
            executableScript.textContent = script.textContent;
            script.replaceWith(executableScript);
            executedPageScripts.add(scriptName);
        }
    };

    const finishLoadingIndicator = () => {
        window.clearTimeout(loadingIndicatorTimeout);
        loadingIndicator.dataset.complete = 'true';
        loadingIndicator.dataset.loading = 'false';
        loadingIndicatorTimeout = window.setTimeout(() => {
            loadingIndicator.dataset.complete = 'false';
        }, 180);
    };

    const navigate = async (destination, { pushHistory = false, scrollTop = 0 } = {}) => {
        if (destination.origin !== window.location.origin || !userPagePaths.has(destination.pathname)) {
            return;
        }

        activeNavigation?.abort();
        const request = new AbortController();
        activeNavigation = request;
        window.clearTimeout(loadingIndicatorTimeout);
        loadingIndicator.dataset.complete = 'false';
        loadingIndicator.dataset.loading = 'true';
        pageContent.setAttribute('aria-busy', 'true');
        window.dispatchEvent(new CustomEvent('user:page-navigating'));

        try {
            // ASVS V1.2.2: fetch only explicitly allowed, same-origin borrower page URLs.
            const response = await fetch(destination, {
                headers: {
                    'Accept': 'text/html',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                cache: 'no-store',
                signal: request.signal,
            });
            const responseUrl = new URL(response.url, window.location.href);
            const contentType = response.headers.get('content-type') || '';
            if (
                !response.ok
                || responseUrl.origin !== window.location.origin
                || !userPagePaths.has(responseUrl.pathname)
                || !contentType.includes('text/html')
            ) {
                if (response.redirected && responseUrl.origin === window.location.origin) {
                    window.location.assign(responseUrl.href);
                    return;
                }
                throw new Error(`User page navigation failed with status ${response.status}.`);
            }

            const responseDocument = new DOMParser().parseFromString(await response.text(), 'text/html');
            const nextPageContent = responseDocument.querySelector('#user-page-content[data-user-page-content]');
            if (!nextPageContent) {
                throw new Error('User page response did not contain the expected content.');
            }

            if (pushHistory) {
                window.history.replaceState({
                    ...(window.history.state || {}),
                    userNavigation: true,
                    userScrollY: window.scrollY,
                }, '', window.location.href);
            }

            window.Alpine.destroyTree(pageContent);
            pageContent.replaceChildren(...Array.from(nextPageContent.childNodes));
            runUserPageScripts(pageContent);
            window.Alpine.initTree(pageContent);
            document.title = responseDocument.title;
            currentPath = responseUrl.pathname;
            currentUrl = new URL(responseUrl.href);

            const catalog = pageContent.querySelector('#catalog-content');
            if (catalog) {
                window.dispatchEvent(new CustomEvent('catalog:updated', { detail: { root: catalog } }));
            }
            window.dispatchEvent(new CustomEvent('user:page-updated'));

            if (pushHistory) {
                window.history.pushState({ userNavigation: true, userScrollY: 0 }, '', responseUrl.href);
                window.scrollTo(0, 0);
            } else {
                window.scrollTo(0, scrollTop);
            }

            const main = pageContent.querySelector('main');
            if (main) {
                main.tabIndex = -1;
                main.focus({ preventScroll: true });
                if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                    main.animate([
                        { opacity: 0, transform: 'translateY(4px)' },
                        { opacity: 1, transform: 'translateY(0)' },
                    ], { duration: 150, easing: 'ease-out' });
                }
            }
        } catch (error) {
            if (error.name !== 'AbortError') {
                console.error('Failed to navigate between user pages.', error);
                window.showToast('Halaman gagal dimuat. Periksa koneksi lalu coba lagi.', 'error');
            }
        } finally {
            if (activeNavigation === request) {
                activeNavigation = null;
                pageContent.removeAttribute('aria-busy');
                finishLoadingIndicator();
            }
        }
    };

    window.history.replaceState({
        ...(window.history.state || {}),
        userNavigation: true,
        userScrollY: window.scrollY,
    }, '', window.location.href);

    document.addEventListener('click', (event) => {
        const target = event.target instanceof Element ? event.target : null;
        const link = target?.closest('a[href]');
        if (
            !link
            || (!pageContent.contains(link) && !link.hasAttribute('data-user-navigation-link'))
            || event.defaultPrevented
            || event.button !== 0
            || event.metaKey
            || event.ctrlKey
            || event.shiftKey
            || event.altKey
            || link.target
            || link.hasAttribute('download')
            || link.closest('form')
        ) {
            return;
        }

        const destination = new URL(link.href, window.location.href);
        const samePage = destination.pathname === currentPath;
        const handledByPage = link.matches('[data-catalog-ajax], [data-history-ajax]');
        if (
            destination.origin !== window.location.origin
            || !userPagePaths.has(destination.pathname)
            || (samePage && (
                destination.search === window.location.search
                || handledByPage
            ))
        ) {
            return;
        }

        event.preventDefault();
        navigate(destination, { pushHistory: true });
    });

    window.addEventListener('popstate', (event) => {
        const destination = new URL(window.location.href);
        if (!userPagePaths.has(destination.pathname)) {
            window.location.reload();
            return;
        }
        if (destination.pathname === currentPath) {
            const pageHasOwnAjaxNavigation = pageContent.querySelector('#catalog-content, #history-content');
            if (destination.search !== currentUrl.search && !pageHasOwnAjaxNavigation) {
                navigate(destination, {
                    scrollTop: Number.isFinite(event.state?.userScrollY) ? event.state.userScrollY : 0,
                });
            }
            return;
        }

        navigate(destination, {
            scrollTop: Number.isFinite(event.state?.userScrollY) ? event.state.userScrollY : 0,
        });
    });
};

Alpine.start();
updateAdminFieldIndicators();
initAdminAjaxNavigation();
initUserAjaxNavigation();

const renderLoanQr = async (element) => {
    if (element.dataset.loanQrRendered === 'true') {
        return;
    }

    element.dataset.loanQrRendered = 'true';
    const canvas = document.createElement('canvas');

    try {
        qrCodeModulePromise ??= import('qrcode').then(({ default: QRCode }) => QRCode);
        const QRCode = await qrCodeModulePromise;
        await QRCode.toCanvas(canvas, element.dataset.loanQr, {
            errorCorrectionLevel: 'M',
            margin: 4,
            width: 240,
        });
        element.replaceChildren(canvas);
    } catch (error) {
        console.error('Failed to render the loan QR code.', error);
        element.textContent = 'QR tidak dapat ditampilkan.';
    }
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
