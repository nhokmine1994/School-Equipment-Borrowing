// ĐĂNG KÝ PHÒNG HỌC JS
document.addEventListener('DOMContentLoaded', function () {
    const ROOM_BOOKING_KEY = 'seb_room_bookings';
    const AUTH_STORAGE_KEY = 'seb_demo_auth_user';

    function formatDate(date) {
        const day = String(date.getDate()).padStart(2, '0');
        const month = String(date.getMonth() + 1).padStart(2, '0');
        return day + '/' + month;
    }

    function getSchoolWeekMonday(baseDate) {
        const current = new Date(baseDate);
        current.setHours(0, 0, 0, 0);

        const dayOfWeek = current.getDay();
        const monday = new Date(current);

        if (dayOfWeek === 0) {
            // Chủ nhật: hiển thị lịch tuần mới bắt đầu từ ngày mai.
            monday.setDate(current.getDate() + 1);
        } else if (dayOfWeek === 6) {
            // Thứ 7: hiển thị lịch tuần mới bắt đầu từ thứ 2 tới.
            monday.setDate(current.getDate() + 2);
        } else {
            monday.setDate(current.getDate() - (dayOfWeek - 1));
        }

        return monday;
    }

    function syncTimetableHeaderWithRealDates() {
        const headerCells = document.querySelectorAll('.timetable thead th');
        if (!headerCells || headerCells.length < 6) {
            return;
        }

        const monday = getSchoolWeekMonday(new Date());

        for (let i = 0; i < 5; i++) {
            const date = new Date(monday);
            date.setDate(monday.getDate() + i);
            headerCells[i + 1].textContent = `T${i + 2} ${formatDate(date)}`;
        }
    }

    syncTimetableHeaderWithRealDates();

    const timetable = document.querySelector('.timetable');
    const submitBtn = document.querySelector('.btn-submit-booking');
    const roomTypeSelect = document.querySelector('select[name="room-type"]');
    const roomNumberSelect = document.querySelector('select[name="room-number"]');
    const userNameSelect = document.querySelector('select[name="user-name"]');
    const purposeInput = document.querySelector('.textarea-field textarea');
    const draftSelectionsByRoom = {};
    const SUBMIT_TEXT_DEFAULT = 'Gửi Yêu Cầu';
    const SUBMIT_TEXT_REQUIRE_AUTH = 'Đăng nhập/Đăng ký';

    function getStoredBookings() {
        try {
            const raw = localStorage.getItem(ROOM_BOOKING_KEY);
            const parsed = raw ? JSON.parse(raw) : [];
            return Array.isArray(parsed) ? parsed : [];
        } catch (error) {
            console.warn('Không thể đọc dữ liệu đăng ký phòng:', error);
            return [];
        }
    }

    function setStoredBookings(bookings) {
        localStorage.setItem(ROOM_BOOKING_KEY, JSON.stringify(bookings));
    }

    function getAuthUsername() {
        try {
            const raw = localStorage.getItem(AUTH_STORAGE_KEY);
            if (!raw) return '';
            const parsed = JSON.parse(raw);
            return parsed && parsed.username ? String(parsed.username).trim() : '';
        } catch (error) {
            return '';
        }
    }

    function updateSubmitButtonAuthState() {
        if (!submitBtn) {
            return;
        }

        const loggedIn = Boolean(getAuthUsername());
        submitBtn.textContent = loggedIn ? SUBMIT_TEXT_DEFAULT : SUBMIT_TEXT_REQUIRE_AUTH;
        submitBtn.title = loggedIn
            ? 'Gửi yêu cầu đăng ký phòng học'
            : 'Vui lòng đăng nhập hoặc đăng ký để gửi yêu cầu';
    }

    function promptAuthForBookingAction() {
        window.dispatchEvent(new CustomEvent('seb:require-auth'));
        updateSubmitButtonAuthState();
    }

    function getSlotMeta(button) {
        const row = button.closest('tr');
        const cell = button.closest('td');
        if (!row || !cell) return null;

        const rowCells = Array.from(row.children);
        const colIndex = rowCells.indexOf(cell);
        if (colIndex <= 0) return null;

        const headerCells = document.querySelectorAll('.timetable thead th');
        const dayLabel = headerCells[colIndex] ? headerCells[colIndex].textContent.trim() : '';
        const timeLabelCell = row.querySelector('.time-label');
        const timeLabel = timeLabelCell ? timeLabelCell.textContent.trim() : '';

        if (!dayLabel || !timeLabel) return null;

        return {
            dayLabel,
            timeLabel,
            slotKey: dayLabel + '__' + timeLabel,
        };
    }

    function getCurrentRoomKey() {
        const roomType = roomTypeSelect ? roomTypeSelect.value : '';
        const roomNumber = roomNumberSelect ? roomNumberSelect.value : '';
        if (!roomType || !roomNumber) {
            return '';
        }
        return roomType + '__' + roomNumber;
    }

    function saveCurrentDraftSelection() {
        const roomKey = getCurrentRoomKey();
        if (!roomKey) {
            return;
        }

        const selectedSlotKeys = Array.from(document.querySelectorAll('.slot-btn.selected'))
            .map((button) => getSlotMeta(button))
            .filter((meta) => Boolean(meta && meta.slotKey))
            .map((meta) => meta.slotKey);

        draftSelectionsByRoom[roomKey] = selectedSlotKeys;
    }

    const slotSnapshots = Array.from(document.querySelectorAll('.timetable tbody .slot-btn')).map((button) => ({
        button,
        baseClass: button.classList.contains('busy') ? 'busy' : 'empty',
        baseText: button.textContent.trim(),
    }));

    function resetSlotsToBaseState() {
        slotSnapshots.forEach((snapshot) => {
            const button = snapshot.button;
            button.classList.remove('selected', 'my-booked', 'empty', 'busy');
            button.classList.add(snapshot.baseClass);
            button.textContent = snapshot.baseText;
            button.disabled = false;
        });
    }

    function applyMyBookingsForCurrentRoom() {
        resetSlotsToBaseState();

        const roomType = roomTypeSelect ? roomTypeSelect.value : '';
        const roomNumber = roomNumberSelect ? roomNumberSelect.value : '';
        if (!roomType || !roomNumber) {
            return;
        }

        const authUsername = getAuthUsername().toLowerCase();
        const bookings = getStoredBookings().filter((item) => item.roomType === roomType && item.roomNumber === roomNumber);
        const myBookedSlotKeys = new Set();
        const occupiedSlotKeys = new Set();

        bookings.forEach((booking) => {
            const slots = Array.isArray(booking.slots) ? booking.slots : [];
            const isMine = authUsername && String(booking.createdBy || '').toLowerCase() === authUsername;
            slots.forEach((slot) => {
                if (slot && slot.slotKey) {
                    if (isMine) {
                        myBookedSlotKeys.add(slot.slotKey);
                    } else {
                        occupiedSlotKeys.add(slot.slotKey);
                    }
                }
            });
        });

        slotSnapshots.forEach((snapshot) => {
            const button = snapshot.button;
            const meta = getSlotMeta(button);
            if (!meta) return;

            if (myBookedSlotKeys.has(meta.slotKey)) {
                button.classList.remove('selected', 'empty', 'busy');
                button.classList.add('my-booked');
                button.textContent = 'Lịch của tôi';
                button.disabled = true;
                return;
            }

            if (occupiedSlotKeys.has(meta.slotKey)) {
                button.classList.remove('selected', 'empty', 'my-booked');
                button.classList.add('busy');
                button.textContent = 'Có lịch';
                button.disabled = true;
            }
        });

        const roomKey = getCurrentRoomKey();
        if (!roomKey) {
            return;
        }

        const draftSlotKeys = new Set(draftSelectionsByRoom[roomKey] || []);
        if (!draftSlotKeys.size) {
            return;
        }

        slotSnapshots.forEach((snapshot) => {
            const button = snapshot.button;
            const meta = getSlotMeta(button);
            if (!meta || !draftSlotKeys.has(meta.slotKey)) {
                return;
            }

            if (button.classList.contains('empty') && !button.disabled) {
                button.classList.add('selected');
                button.textContent = 'Đã chọn';
            }
        });
    }

    if (timetable) {
        timetable.addEventListener('click', function (event) {
            const button = event.target.closest('.slot-btn');
            if (!button || !button.classList.contains('empty')) {
                return;
            }

            if (button.classList.contains('selected')) {
                button.classList.remove('selected');
                button.textContent = 'Trống';
            } else {
                button.classList.add('selected');
                button.textContent = 'Đã chọn';
            }

            saveCurrentDraftSelection();
        });
    }

    if (roomTypeSelect) {
        roomTypeSelect.addEventListener('change', function () {
            saveCurrentDraftSelection();
            applyMyBookingsForCurrentRoom();
        });
    }

    if (roomNumberSelect) {
        roomNumberSelect.addEventListener('change', function () {
            saveCurrentDraftSelection();
            applyMyBookingsForCurrentRoom();
        });
    }

    applyMyBookingsForCurrentRoom();
    updateSubmitButtonAuthState();

    window.addEventListener('storage', function (event) {
        if (event.key === AUTH_STORAGE_KEY) {
            updateSubmitButtonAuthState();
            applyMyBookingsForCurrentRoom();
        }
    });

    window.addEventListener('focus', updateSubmitButtonAuthState);

    window.addEventListener('seb:auth-changed', function () {
        updateSubmitButtonAuthState();
        applyMyBookingsForCurrentRoom();
    });

    // Xử lý gửi yêu cầu
    if (submitBtn) {
        submitBtn.addEventListener('click', async function () {
            const selectedSlots = Array.from(document.querySelectorAll('.slot-btn.selected'));
            const roomType = roomTypeSelect ? roomTypeSelect.value : '';
            const roomNumber = roomNumberSelect ? roomNumberSelect.value : '';
            const authUsername = getAuthUsername();

            if (!authUsername) {
                promptAuthForBookingAction();
                return;
            }

            if (selectedSlots.length === 0) {
                alert('Vui lòng chọn ít nhất một tiết học trống!');
                return;
            }

            if (!roomType || !roomNumber) {
                alert('Vui lòng chọn đầy đủ thông tin phòng học!');
                return;
            }

            const roomTypeLabel = roomTypeSelect && roomTypeSelect.selectedOptions[0]
                ? roomTypeSelect.selectedOptions[0].textContent.trim()
                : roomType;
            const roomNumberLabel = roomNumberSelect && roomNumberSelect.selectedOptions[0]
                ? roomNumberSelect.selectedOptions[0].textContent.trim()
                : roomNumber;
            const userNameLabel = userNameSelect && userNameSelect.selectedOptions[0] && userNameSelect.value
                ? userNameSelect.selectedOptions[0].textContent.trim()
                : 'Người dùng demo';
            const bookingOwner = authUsername || userNameLabel;
            const purpose = purposeInput && purposeInput.value.trim() ? purposeInput.value.trim() : 'Không có ghi chú';

            const slotDetails = selectedSlots
                .map((button) => getSlotMeta(button))
                .filter((item) => Boolean(item));

            if (slotDetails.length === 0) {
                alert('Không thể xác định khung giờ đã chọn. Vui lòng thử lại.');
                return;
            }

            const accepted = typeof window.sebConfirm === 'function'
                ? await window.sebConfirm('Bạn có chắc muốn gửi yêu cầu đăng ký phòng học này không?', 'Xác nhận gửi yêu cầu')
                : window.confirm('Bạn có chắc muốn gửi yêu cầu đăng ký phòng học này không?');
            if (!accepted) {
                return;
            }

            const bookings = getStoredBookings();
            bookings.push({
                bookingId: 'RB-' + Date.now(),
                roomType,
                roomTypeLabel,
                roomNumber,
                roomNumberLabel,
                createdBy: bookingOwner,
                userNameLabel,
                purpose,
                createdAt: new Date().toISOString(),
                slots: slotDetails,
            });
            setStoredBookings(bookings);

            const roomKey = getCurrentRoomKey();
            if (roomKey) {
                draftSelectionsByRoom[roomKey] = [];
            }

            applyMyBookingsForCurrentRoom();

            if (purposeInput) {
                purposeInput.value = '';
            }

            alert('Yêu cầu đăng ký phòng ' + roomNumber + ' đã được gửi thành công!');
        });
    }
});
