import Alpine from 'alpinejs';
import QRCode from 'qrcode';
import jsQR from 'jsqr';

window.Alpine = Alpine;

const toastTypes = new Set(['success', 'error', 'info', 'warning']);

const formatToastMessage = (message, type) => {
    if (typeof message === 'string') {
        const importantPhrase = type === 'error'
            ? message.match(/(?:masih digunakan oleh|digunakan oleh|masih memiliki|memiliki riwayat)\s+([^.!?]+)([.!?]?)$/i)
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

window.showToast = (message, type = 'success', duration = 2500, title = '') => {
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
        : 2500;

    window.dispatchEvent(new CustomEvent('app-toast', {
        detail: {
            message: validParts,
            type,
            duration: durationMs,
            title: typeof title === 'string' ? title.trim() : '',
        },
    }));
};

Alpine.data('globalToast', () => ({
    visible: false,
    message: '',
    type: 'success',
    title: 'Berhasil!',
    timeout: null,
    toastListener: null,
    init() {
        this.toastListener = (event) => {
            this.message = event.detail.message;
            this.type = event.detail.type;
            this.title = event.detail.title || ({
                error: 'Gagal!',
                warning: 'Perhatian!',
                info: 'Informasi',
                success: 'Berhasil!',
            })[this.type];
            this.visible = true;
            window.clearTimeout(this.timeout);
            this.timeout = window.setTimeout(() => {
                this.visible = false;
            }, event.detail.duration);
        };

        window.addEventListener('app-toast', this.toastListener);
    },
    destroy() {
        window.removeEventListener('app-toast', this.toastListener);
        window.clearTimeout(this.timeout);
    },
    dismiss() {
        this.visible = false;
        window.clearTimeout(this.timeout);
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

Alpine.start();

const renderLoanQr = (element) => {
    if (element.dataset.loanQrRendered === 'true') {
        return;
    }

    element.dataset.loanQrRendered = 'true';
    const canvas = document.createElement('canvas');

    QRCode.toCanvas(canvas, element.dataset.loanQr, {
        errorCorrectionLevel: 'M',
        margin: 4,
        width: 152,
    }).then(() => {
        element.replaceChildren(canvas);
    }).catch((error) => {
        console.error('Failed to render the loan QR code.', error);
        element.textContent = 'QR tidak dapat ditampilkan. Gunakan kode peminjaman di samping.';
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

const iconifyScript = document.createElement('script');
iconifyScript.src = 'https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js';
iconifyScript.async = true;
iconifyScript.dataset.iconifyRuntime = 'true';
iconifyScript.addEventListener('error', () => {
    console.error('Failed to load the Iconify icon runtime.');
});

document.head.append(iconifyScript);
