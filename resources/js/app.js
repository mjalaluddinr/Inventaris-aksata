import Alpine from 'alpinejs';
import QRCode from 'qrcode';
import jsQR from 'jsqr';

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

Alpine.data('loanQrScanner', () => ({
    feedbackOpen: false,
    feedbackMessage: '',
    loanListRequest: null,
    loanListLoading: false,
    loanListError: '',
    scannerOpen: false,
    scannerMessage: '',
    cameraSupported: false,
    cameraReady: false,
    cameraAspectRatio: 16 / 9,
    isScanning: false,
    isSubmitting: false,
    manualCode: '',
    stream: null,
    frameRequest: null,
    lastScanAt: 0,
    init() {
        this.feedbackMessage = this.$el.dataset.feedbackMessage || '';
        this.feedbackOpen = Boolean(this.feedbackMessage);
        if (this.feedbackOpen) {
            window.setTimeout(() => {
                this.feedbackOpen = false;
            }, 2500);
        }
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
    async loadLoanList(url) {
        const requestUrl = new URL(url, window.location.href);
        if (requestUrl.origin !== window.location.origin || requestUrl.pathname !== window.location.pathname) {
            return;
        }

        this.loanListRequest?.abort();
        const request = new AbortController();
        this.loanListRequest = request;
        this.loanListLoading = true;
        this.loanListError = '';

        try {
            const response = await fetch(requestUrl, {
                headers: {
                    'Accept': 'text/html',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                signal: request.signal,
            });
            if (!response.ok || response.redirected) {
                throw new Error('Daftar peminjaman tidak dapat dimuat.');
            }

            this.$refs.loanList.innerHTML = await response.text();
            window.history.replaceState({}, '', requestUrl);
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
            // ASVS 2.2.2: Server-side validation remains authoritative for manually entered and scanned codes.
            const response = await fetch(this.$el.dataset.endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.$el.dataset.csrfToken,
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
                    || payload.message
                    || 'QR tidak dapat diproses. Pastikan QR berasal dari pengajuan yang menunggu konfirmasi.';
                return;
            }

            if (!payload.loan_id || typeof payload.message !== 'string') {
                this.scannerMessage = 'Konfirmasi belum dapat dipastikan. Periksa status peminjaman sebelum memindai ulang.';
                return;
            }

            const scanEndpoint = new URL(this.$el.dataset.endpoint, window.location.origin);
            const scanPathname = scanEndpoint.pathname.replace(/\/+$/, '');
            const destinationPathname = scanPathname.replace(/\/scan$/, '');
            if (
                scanEndpoint.origin !== window.location.origin
                || destinationPathname === scanPathname
                || !destinationPathname.endsWith('/admin/peminjaman')
            ) {
                this.scannerMessage = 'Halaman pemindaian peminjaman tidak valid. Muat ulang halaman admin.';
                return;
            }

            const destination = new URL(scanEndpoint.origin);
            destination.pathname = destinationPathname;
            destination.searchParams.set('tab', 'borrowed');
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
        margin: 1,
        width: 144,
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
