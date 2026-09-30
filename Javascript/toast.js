(function (global) {
    // Inject styles
    const css = `
    .seb-toast-container{position:fixed;inset:0;z-index:99999;display:flex;align-items:center;justify-content:center;padding:16px;pointer-events:none;background:rgba(15,23,42,0);transition:background 180ms ease}
    .seb-toast-container:has(.seb-toast.show){background:rgba(15,23,42,.42);pointer-events:auto}
    .seb-toast{position:relative;width:min(420px,100%);padding:30px 28px 24px;border-radius:16px;color:#334155;background:#fff;box-shadow:0 24px 70px rgba(15,23,42,.28);font-weight:600;font-size:15px;line-height:1.55;text-align:center;opacity:0;transform:translateY(12px) scale(.96);transition:opacity 240ms ease,transform 240ms ease}
    .seb-toast.show{opacity:1;transform:translateY(0) scale(1)}
    .seb-toast::before{display:block;margin-bottom:10px;font-size:40px;line-height:1;color:#0284c7;content:'ⓘ'}
    .seb-toast.success::before{color:#059669;content:'✓'}
    .seb-toast.warn::before{color:#d97706;content:'!'}
    .seb-toast.error::before{color:#dc2626;content:'!'}
    .seb-toast::after{content:'×';position:absolute;right:12px;top:6px;color:#64748b;font-size:26px;font-weight:400;line-height:1}
    .seb-toast-progress{position:absolute;left:0;right:0;bottom:0;height:4px;border-radius:0 0 16px 16px;background:#0284c7;transform-origin:left;animation:sebToastCountdown 4500ms linear forwards}
    .seb-toast.success .seb-toast-progress{background:#059669}.seb-toast.warn .seb-toast-progress{background:#d97706}.seb-toast.error .seb-toast-progress{background:#dc2626}
    @keyframes sebToastCountdown{from{transform:scaleX(1)}to{transform:scaleX(0)}}
    `;
    const style = document.createElement('style');
    style.setAttribute('data-seb-toast','1');
    style.appendChild(document.createTextNode(css));
    document.head.appendChild(style);

    // Create container
    let container = document.querySelector('.seb-toast-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'seb-toast-container';
        document.body.appendChild(container);
    }

    function makeToast(message, type = 'info', timeout = 4000) {
        const el = document.createElement('div');
        el.className = 'seb-toast ' + (type || 'info');
        el.textContent = String(message || '');
        const progress = document.createElement('span');
        progress.className = 'seb-toast-progress';
        el.appendChild(progress);
        container.appendChild(el);
        // force reflow to enable transition
        void el.offsetWidth;
        el.classList.add('show');
        const id = setTimeout(() => {
            el.classList.remove('show');
            setTimeout(() => { try { container.removeChild(el); } catch (e) {}
            }, 260);
        }, timeout);
        el.addEventListener('click', () => {
            clearTimeout(id);
            try { el.classList.remove('show'); container.removeChild(el);} catch (e) {}
        });
        return el;
    }

    global.sebToast = makeToast;
    global.sebShowMessage = function (message, type) {
        if (typeof global.sebToast === 'function') {
            global.sebToast(message, type || 'info', 4500);
        } else {
            alert(message);
        }
    };
})(window);
