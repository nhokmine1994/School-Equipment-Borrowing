# BÁO CÁO KỸ THUẬT TOÀN DIỆN: HỆ THỐNG MƯỢN TRẢ THIẾT BỊ SEB (TRƯỜNG THCS LỘC AN)

**Thông tin chung:**
- **Dự án:** SEB - Hệ Thống Mượn Trả Thiết Bị.
- **Đơn vị thụ hưởng:** Trường THCS Lộc An.
- **Nhóm thực hiện:** Phạm Công Hậu, Lò Văn Duẩn, Đặng Bắc Nam.

---

## 1. Đặt vấn đề và Mục tiêu giải pháp

**Phân tích hiện trạng**
Dựa trên khảo sát thực tế và dữ liệu phân tích từ hệ thống cũ [SOURCE_IMAGE_8], việc quản lý thiết bị dạy học tại Trường THCS Lộc An đang đối mặt với các rào cản vận hành nghiêm trọng:
- **Lãng phí nguồn lực:** Tỷ lệ lãng phí thời gian cao do việc tìm kiếm thiết bị và kiểm tra hồ sơ sách thủ công. Việc xử lý nhiều yêu cầu chồng chéo dẫn đến tình trạng "tắc nghẽn vận hành".
- **Dữ liệu rời rạc:** Thông tin về số lượng sẵn có không được cập nhật thời gian thực, gây khó khăn cho giáo viên trong việc chủ động kế hoạch giảng dạy.
- **Quản lý thủ công:** Ghi chép giấy tờ dễ dẫn đến nhầm lẫn và thiếu tính minh bạch trong lịch sử mượn trả.

**Mục tiêu dự án**
- **Tập trung hóa quản lý:** Số hóa toàn bộ quy trình mượn/trả, loại bỏ hoàn toàn việc ghi chép thủ công.
- **Minh bạch hóa dữ liệu:** Cung cấp báo cáo thời gian thực về tình trạng thiết bị (Sẵn sàng, Đang mượn, Bảo trì).
- **Tối ưu hóa quy trình:** Giảm thiểu tối đa thời gian chờ đợi giữa các khâu đăng ký và xét duyệt.

**Lợi ích kỳ vọng**
Hệ thống được thiết kế để giảm sai sót dữ liệu xuống mức thấp nhất, hỗ trợ nhân viên kho xử lý hư hỏng nhanh chóng và hiện đại hóa hạ tầng cơ sở vật chất, hướng tới mô hình trường học thông minh.

---

## 2. Kiến trúc công nghệ hệ thống

**Mô hình kiến trúc Hybrid**
Hệ thống SEB áp dụng cấu trúc Hybrid tối ưu giữa tính linh hoạt của Single Page Application (SPA) và sự ổn định của Backend truyền thống:
- **React Frontend:** Đảm nhiệm xử lý UI/UX, logic điều hướng và trạng thái ứng dụng. Sử dụng dữ liệu backend dạng "chỉ-đọc" (read-only) để hiển thị Dashboard và Lịch sử mượn.
- **PHP Backend:** Đóng vai trò là lớp xử lý an toàn cho session, xác thực người dùng và thực hiện các kết nối SQL Server (MSSQL). Các luồng ghi dữ liệu quan trọng vẫn được duy trì ở phía Backend để đảm bảo tính toàn vẹn.

**Chi tiết Tech Stack**

| Thành phần | Công nghệ sử dụng |
| :--- | :--- |
| **Frontend** | React, HTML5, CSS3, JavaScript, Bootstrap/Tailwind |
| **Backend** | PHP (API & Session helper) |
| **Cơ sở dữ liệu** | SQL Server (MSSQL) |
| **Công cụ hỗ trợ** | Vite (build tool), Apache (.htaccess) |

**Cấu trúc triển khai và Tính tương thích (Legacy Compatibility)**
Để khắc phục lỗi trang trắng (blank page) trên môi trường production, hệ thống sử dụng `index.php` làm entry point chính. PHP sẽ kiểm tra sự tồn tại của bản build trong thư mục `dist/` để phục vụ người dùng. Cơ chế "Fallback an toàn" đã được thiết lập qua `.htaccess` để điều hướng các route SPA và ánh xạ assets chính xác, đảm bảo hệ thống vận hành ổn định ngay cả trên các hạ tầng hosting cũ.

---

## 3. Thiết kế CSDL và Quy trình nghiệp vụ 5 bước

**Thiết kế Cơ sở dữ liệu**
Hệ thống sử dụng bảng `TinhTrangDuyet` để chuẩn hóa quản lý quy trình phê duyệt:
- **Cấu trúc:** Gồm `MaTinhTrang` (PK), `TenTinhTrang`, và `ThuTu`.
- **Ràng buộc kỹ thuật:** Tại bảng `PhieuMuon`, cột `ID` (khóa ngoại) được áp dụng ràng buộc `DF_PhieuMuon_ID` với giá trị mặc định là **3** (Chờ duyệt).
- **Trạng thái chuẩn:** 1 (Đã duyệt), 2 (Từ chối), 3 (Chờ duyệt).

**Quy trình duyệt mượn 5 bước**
1. **Đăng ký/Tìm kiếm:** Giáo viên tra cứu tại "Kho thiết bị", lọc theo danh mục hoặc môn học.
2. **Gửi yêu cầu:** Thực hiện thao tác "Mượn" và điền mục đích sử dụng.
3. **Tiếp nhận & Chờ duyệt:** Yêu cầu được lưu vào SQL với trạng thái mặc định (ID=3).
4. **Xét duyệt Admin:** Quản trị viên sử dụng bảng điều khiển `admin_borrows.php` để cập nhật trạng thái "Đã duyệt" hoặc "Từ chối".
5. **Bàn giao & Ghi nhận:** Hệ thống tự động trừ số lượng tồn kho thực tế và cập nhật vào Kho cá nhân của người dùng.

---

## 4. Báo cáo nghiệm thu chức năng và kiểm thử

**Danh mục chức năng và Phân loại thiết bị**
Hệ thống đã hoàn thiện các module cốt lõi với khả năng hiển thị trực quan:
- **Kho thiết bị:** Phân loại rõ ràng theo 09 danh mục: *Máy tính, Âm thanh, Dụng cụ dạy học, Trình chiếu, Thiết bị mạng, Phụ kiện, Thí nghiệm, Thể thao, Văn phòng*.
- **Trang chủ & Dashboard:** Tổng hợp thông tin thiết bị sẵn có (631 thiết bị), đang mượn (65 thiết bị) và bảo trì (0 thiết bị).
- **Đăng ký phòng học:** Lịch biểu thời gian thực với các trạng thái Trống/Có lịch/Lịch của tôi.
- **Kho cá nhân & Tin tức:** Theo dõi lịch sử mượn và cập nhật quy định mới.

**Kết quả kiểm thử (Hardening & Verification)**
Dữ liệu nghiệm thu từ `VERIFICATION_REPORT.md` xác nhận độ tin cậy của hệ thống:
- **Tham chiếu file:** 130+ liên kết (CSS, JS, Images) hoạt động chính xác.
- **Tỷ lệ lỗi đường dẫn:** 0%. Đã khắc phục lỗi thiếu file `avatar-demo.png`.
- **Smoke test server:** Vận hành ổn định trên XAMPP/Localhost; cú pháp PHP đạt chuẩn.
- **Trải nghiệm người dùng:** Đạt chuẩn Responsive đa thiết bị. Hệ thống sử dụng Spinner và Skeleton trong quá trình tải dữ liệu để tối ưu hóa UX.

---

## 5. Kết luận và Hướng phát triển

**Đánh giá tổng kết**
Hệ thống SEB đã giải quyết triệt để bài toán quản lý thiết bị tại Trường THCS Lộc An, thay thế quy trình thủ công bằng giải pháp số hóa tập trung, bảo mật và hiệu năng cao.

**Lộ trình phát triển (Roadmap)**
1. **Smart Borrowing:** Triển khai QR Code định danh thiết bị để mượn/trả tức thời.
2. **BI Assistant:** Tích hợp Chatbot AI hỗ trợ tra cứu trạng thái thiết bị bằng ngôn ngữ tự nhiên.
3. **Hệ thống thông báo 3 lớp:**
    - *Lớp 1:* Giao diện Admin thực hiện thay đổi trạng thái phiếu mượn.
    - *Lớp 2:* SQL Server ghi nhận sự kiện vào bảng `NotificationQueue`.
    - *Lớp 3:* Worker AI/Email xử lý hàng đợi để gửi thông báo tự động tới người dùng.
4. **Multi-School Platform:** Mở rộng mô hình sang các phòng bộ môn và liên thông với các trường học lân cận trong hệ thống giáo dục.