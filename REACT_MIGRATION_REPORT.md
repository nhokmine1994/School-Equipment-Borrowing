# Báo Cáo Chuyển Đổi React

**Ngày**: 2026-09-13
**Phạm vi**: Chuyển frontend gốc sang React, đồng thời giữ PHP làm backend an toàn và tương thích

## Tóm Tắt
Giao diện chính của website đã được chuyển sang ứng dụng React ở thư mục `src/`, trong khi PHP vẫn tiếp tục đảm nhiệm dữ liệu backend, session đăng nhập và khả năng tương thích với các luồng cũ. Điểm vào gốc hiện chuyển hướng sang React, còn giao diện vẫn giữ phong cách bố cục ban đầu thay vì thay mới hoàn toàn.

## Những Gì Đã Thay Đổi

### 1. Chuyển điểm vào frontend sang React
- Điểm vào chính của website hiện chuyển từ PHP sang React qua luồng `index.php -> index.html`.
- Ứng dụng React trong `src/` hiện là lớp giao diện đang chạy chính.
- Thư mục `frontend/` chỉ còn là bản tham khảo/kiểm thử, không còn là nguồn chính.

### 2. Kết nối an toàn session và xác thực
- Thêm các endpoint backend cho session hiện tại, đăng nhập và đăng xuất trong `api/seb_api.php`.
- React giờ đọc user hiện tại từ session PHP ngay khi khởi động.
- Truy cập route được bảo vệ bằng cơ chế auth guard của React để người chưa đăng nhập tự chuyển về `/login`.
- Đăng xuất sẽ xóa session PHP và đưa người dùng quay lại luồng đăng nhập của React.

### 3. Thay nội dung demo tĩnh bằng dữ liệu backend chỉ-đọc
- Dashboard giờ đọc dữ liệu tổng hợp thật từ backend.
- Trang Thiết bị giờ đọc danh sách thiết bị từ backend.
- Lịch sử mượn giờ đọc dữ liệu mượn thật của người dùng hiện tại.
- Trang Phòng học giờ đọc dữ liệu đăng ký phòng từ backend.
- Trang Chủ giờ dùng dữ liệu tổng hợp backend và tên người dùng hiện tại thay cho nội dung demo hard-code.
- Trang Hồ sơ hiển thị user đang hoạt động từ Redux/session.

### 4. Thêm các lớp an toàn hệ thống
- Thêm global React error boundary để nếu một component lỗi thì vẫn hiện màn hình fallback thay vì trắng trang.
- Thêm dữ liệu dự phòng cục bộ cho các trang chỉ-đọc để UI vẫn hiển thị nếu backend tạm thời không phản hồi.
- Giữ các luồng ghi dữ liệu ở backend PHP thay vì chuyển sang React.

## Các File Đã Cập Nhật

### Ứng dụng React
- `src/App.jsx`
- `src/routes/AppRoutes.jsx`
- `src/components/ErrorBoundary.jsx`
- `src/components/Header.jsx`
- `src/components/RequireAuth.jsx`
- `src/layouts/AppLayout.jsx`
- `src/pages/HomePage.jsx`
- `src/pages/DashboardPage.jsx`
- `src/pages/DevicesPage.jsx`
- `src/pages/BorrowPage.jsx`
- `src/pages/RoomsPage.jsx`
- `src/pages/ProfilePage.jsx`
- `src/pages/LoginPage.jsx`
- `src/services/api.js`
- `src/store/slices/appSlice.js`
- `src/utils/bootstrap.js`

### Backend PHP và hỗ trợ session
- `api/seb_api.php`
- `components/seb_db.php`
- `components/session_helper.php`
- `index.php`

## Kiểm Tra Đã Thực Hiện
- Build production của React đã chạy thành công với `npm run build`.
- Kiểm tra cú pháp PHP đã pass cho các file backend/session được cập nhật.
- Các thay đổi về route, auth và session đã được áp dụng mà không thay đổi cấu trúc giao diện hiển thị.

## Ghi Chú
- Quá trình chuyển đổi vẫn giữ ngôn ngữ thiết kế và bố cục trang gốc.
- PHP vẫn được dùng cho dữ liệu backend, trạng thái session và hỗ trợ điều hướng legacy.
- React hiện là frontend chính nhưng vẫn giữ cơ chế fallback an toàn khi thiếu dữ liệu.
