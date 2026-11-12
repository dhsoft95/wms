/**
 * Camera scanning overlay.
 * Uses BarcodeDetector API when available; falls back to @zxing/browser.
 * Exposes window.startCameraScan().
 */

const FORMATS = ['code_128', 'ean_13', 'ean_8', 'qr_code', 'data_matrix'];

function injectBarcode(value) {
    const el = document.querySelector('[data-scan-input]:not([disabled])');
    if (!el) return;
    el.focus();
    // Set value and sync Alpine / Livewire wire:model
    const nativeInputValueSetter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value').set;
    nativeInputValueSetter.call(el, value);
    el.dispatchEvent(new Event('input', { bubbles: true }));
    el.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true, cancelable: true }));
}

function buildOverlay(onCancel) {
    const overlay = document.createElement('div');
    overlay.id = 'camera-scan-overlay';
    overlay.style.cssText = [
        'position:fixed', 'inset:0', 'z-index:9999',
        'background:#000', 'display:flex', 'flex-direction:column',
        'align-items:center', 'justify-content:center',
    ].join(';');

    const video = document.createElement('video');
    video.setAttribute('playsinline', '');
    video.style.cssText = 'width:100%;max-height:70vh;object-fit:cover;';

    const hint = document.createElement('p');
    hint.textContent = 'Point camera at barcode…';
    hint.style.cssText = 'color:#fff;font-size:14px;margin-top:16px;opacity:0.8;';

    const btn = document.createElement('button');
    btn.textContent = 'Cancel';
    btn.style.cssText = [
        'margin-top:20px', 'padding:12px 32px', 'background:#a22631',
        'color:#fff', 'border:none', 'border-radius:12px',
        'font-size:16px', 'font-weight:600', 'cursor:pointer',
    ].join(';');
    btn.addEventListener('click', onCancel);

    overlay.appendChild(video);
    overlay.appendChild(hint);
    overlay.appendChild(btn);
    document.body.appendChild(overlay);
    return { overlay, video };
}

function removeOverlay() {
    const el = document.getElementById('camera-scan-overlay');
    if (el) el.remove();
}

async function startWithBarcodeDetector() {
    const detector = new BarcodeDetector({ formats: FORMATS });
    const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });

    let active = true;
    const { overlay, video } = buildOverlay(() => {
        active = false;
        stream.getTracks().forEach(t => t.stop());
        removeOverlay();
    });

    video.srcObject = stream;
    await video.play();

    const scan = async () => {
        if (!active) return;
        try {
            const codes = await detector.detect(video);
            if (codes.length > 0) {
                active = false;
                stream.getTracks().forEach(t => t.stop());
                removeOverlay();
                injectBarcode(codes[0].rawValue);
                return;
            }
        } catch (_) {}
        requestAnimationFrame(scan);
    };
    requestAnimationFrame(scan);
}

async function startWithZxing() {
    const { BrowserMultiFormatReader } = await import('@zxing/browser');
    const reader = new BrowserMultiFormatReader();
    const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });

    let active = true;
    const { overlay, video } = buildOverlay(() => {
        active = false;
        reader.reset();
        stream.getTracks().forEach(t => t.stop());
        removeOverlay();
    });

    video.srcObject = stream;
    await video.play();

    reader.decodeFromVideoElement(video, (result, err) => {
        if (!active) return;
        if (result) {
            active = false;
            reader.reset();
            stream.getTracks().forEach(t => t.stop());
            removeOverlay();
            injectBarcode(result.getText());
        }
    });
}

window.startCameraScan = async function () {
    if (document.getElementById('camera-scan-overlay')) return; // already open

    if (!navigator.mediaDevices?.getUserMedia) {
        alert('Camera access is not available on this device.');
        return;
    }

    try {
        if (window.BarcodeDetector) {
            await startWithBarcodeDetector();
        } else {
            await startWithZxing();
        }
    } catch (err) {
        removeOverlay();
        if (err.name === 'NotAllowedError') {
            alert('Camera permission denied. Please allow camera access and try again.');
        } else {
            console.error('Camera scan error:', err);
        }
    }
};
