// Gọi API PHP → SQL Server (thay localStorage)
(function (global) {
    function apiBase() {
        if (global.__SEB_API_BASE) {
            return String(global.__SEB_API_BASE).replace(/\/$/, '');
        }
        const path = global.location.pathname.replace(/\\/g, '/');
        if (path.includes('/Page/')) {
            return '../api/seb_api.php';
        }
        return 'api/seb_api.php';
    }

    async function callApi(action, options) {
        const opts = options || {};
        const method = (opts.method || 'GET').toUpperCase();
        const params = new URLSearchParams({ action });

        let url = apiBase() + '?' + params.toString();
        const fetchOpts = {
            method,
            credentials: 'same-origin',
            headers: {},
        };

        if (method === 'GET') {
            if (opts.query && typeof opts.query === 'object') {
                Object.entries(opts.query).forEach(([key, value]) => {
                    params.set(key, String(value));
                });
                url = apiBase() + '?' + params.toString();
            }
        } else if (opts.body !== undefined) {
            fetchOpts.headers['Content-Type'] = 'application/json';
            fetchOpts.body = JSON.stringify(opts.body);
        }

        const response = await fetch(url, fetchOpts);
        let data = null;
        try {
            data = await response.json();
        } catch (error) {
            // If server returned non-JSON (HTML error page or plain text), include it in the thrown error
            let text = '';
            try {
                text = await response.text();
            } catch (e) {
                text = '';
            }
            const sample = (text || '').trim().slice(0, 1000);
            const err = new Error(sample !== '' ? sample : 'Phản hồi API không hợp lệ.');
            err.status = response.status;
            err.data = null;
            throw err;
        }

        if (!response.ok || !data || data.ok === false) {
            const message = (data && data.error) ? data.error : ('Lỗi API (' + response.status + ')');
            const err = new Error(message);
            err.status = response.status;
            err.data = data;
            throw err;
        }

        return data;
    }

    global.sebApi = {
        borrowCreate: (body) => callApi('borrow_request', { method: 'POST', body }),
        borrowList: () => callApi('borrow_list'),
        personalList: () => callApi('personal_list'),
        personalAdd: (body) => callApi('personal_add', { method: 'POST', body }),
        personalRemove: (body) => callApi('personal_remove', { method: 'POST', body }),
        roomList: () => callApi('room_list'),
        roomSchedule: (roomType, roomNumber) => callApi('room_schedule', {
            method: 'GET',
            query: { roomType, roomNumber },
        }),
        roomCreate: (body) => callApi('room_create', { method: 'POST', body }),
        roomCancel: (body) => callApi('room_cancel', { method: 'POST', body }),
        register: (body) => callApi('register', { method: 'POST', body }),
    };
})(window);
