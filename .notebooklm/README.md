# Hướng Dẫn Sử Dụng NotebookLM CLI & MCP Server (Local Workspace)

Thư mục này chứa toàn bộ môi trường ảo và bộ công cụ cục bộ kết nối với **Google NotebookLM** (Gemini Notebook).
> **Lưu ý**: Toàn bộ thư mục này nằm hoàn toàn ở Local, đã được thêm vào `.git\info\exclude` để tuyệt đối không bị commit hay đẩy lên GitHub và không làm biến đổi bất kỳ mã nguồn nào của dự án.

---

## 1. Đăng nhập Google NotebookLM (Chỉ thực hiện lần đầu)

Chạy file batch:
```cmd
.\login.bat
```
hoặc chạy bằng lệnh:
```powershell
.\nlm.bat login
```
Một cửa sổ trình duyệt sẽ bật lên để bạn đăng nhập tài khoản Google. Sau khi đăng nhập thành công, phiên đăng nhập (cookies/tokens) sẽ được lưu an toàn tại máy của bạn.

Để kiểm tra trạng thái đăng nhập:
```powershell
.\nlm.bat login --check
```

---

## 2. Quản lý Notebook & Thêm tài liệu

### Xem danh sách Notebook:
```powershell
.\nlm.bat notebook list
```

### Tạo Notebook mới:
```powershell
.\nlm.bat notebook create "Báo Cáo Dự Án School Equipment"
```

### Tải tài liệu/file lên Notebook:
```powershell
.\nlm.bat source add <notebook_id> --file ..\README_ADMIN_QUICK.md --wait
```

### Tự động gom & đẩy toàn bộ tài liệu dự án để làm báo cáo:
Sử dụng script tiện ích:
```powershell
# Xem danh sách hoặc tạo mới kèm đẩy tài liệu
.\sync_project.bat --create "Tài Liệu & Báo Cáo Thiết Bị"

# Hoặc đẩy vào notebook đã có:
.\sync_project.bat --notebook <notebook_id>
```
Script sẽ tự động quét các file báo cáo (`CHANGE_REPORT_VI.md`, `REACT_MIGRATION_REPORT.md`, `VERIFICATION_REPORT.md`), schema database `sql/*.sql` và các tài liệu liên quan.

---

## 3. Truy vấn / Hỏi đáp trực tiếp với NotebookLM

Sử dụng lệnh `query`:
```powershell
.\query.bat <notebook_id> "Tóm tắt các chức năng chính của hệ thống mượn trả thiết bị"
```
hoặc qua `nlm`:
```powershell
.\nlm.bat query notebook <notebook_id> "Cấu trúc cơ sở dữ liệu gồm những bảng nào?"
```

---

## 4. MCP Server (Model Context Protocol)

MCP Server đã được đăng ký vào file cấu hình toàn cục của Antigravity (`~/.gemini/config/mcp_config.json`):
- **Tên Server**: `notebooklm`
- **Đường dẫn binary**: `d:\School-Equipment-Borrowing\.notebooklm\venv\Scripts\notebooklm-mcp.exe`
- **Công cụ cung cấp**:
  - `notebook_list`, `notebook_create`, `notebook_get`, `notebook_delete`
  - `source_add`, `source_list_drive`, `source_sync_drive`
  - `notebook_query`
  - `studio_create`, `report`, `quiz`, `flashcards`, v.v.

Agent Antigravity có thể gọi trực tiếp các tool này để hỗ trợ bạn tạo notebook, tra cứu thông tin và soạn thảo báo cáo.
