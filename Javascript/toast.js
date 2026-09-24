(function (global) {
    // Inject styles
    const css = `
    .seb-toast-container{position:fixed;right:20px;bottom:20px;z-index:99999;display:flex;flex-direction:column;gap:10px}
    .seb-toast{min-width:220px;max-width:420px;padding:12px 14px;border-radius:8px;color:#fff;box-shadow:0 8px 20px rgba(0,0,0,0.12);font-weight:600;font-size:14px;opacity:0;transform:translateY(8px) scale(0.98);transition:opacity 240ms ease,transform 240ms ease}
    .seb-toast.show{opacity:1;transform:translateY(0) scale(1)}
    .seb-toast.info{background:linear-gradient(90deg,#0ea5e9,#0284c7)}
    .seb-toast.success{background:linear-gradient(90deg,#10b981,#059669)}
    .seb-toast.warn{background:linear-gradient(90deg,#f59e0b,#d97706)}
    .seb-toast.error{background:linear-gradient(90deg,#ef4444,#dc2626)}
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