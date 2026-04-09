# Hướng dẫn: Tạo Database cho dự án (Google Sheets + App Script)

Quy trình này hướng dẫn cách thiết lập một cơ sở dữ liệu miễn phí, trực tuyến cho dự án Web sử dụng HTML, CSS và JavaScript thuần.

## 1. Chuẩn bị Google Sheets
- Tạo một file Google Sheets mới.
- Đổi tên Sheet thành `Thiết_bị`.
- Hàng 1 (Tiêu đề): `ID`, `TenThietBi`, `SoLuong`, `TinhTrang`.
- Nhập thử một vài dòng dữ liệu mẫu.

## 2. Thiết lập Google App Script (The API)
- Trong Google Sheets, vào **Extensions (Tiện ích mở rộng)** > **Apps Script**.
- Xóa hết mã hiện có và dán mã sau:

```javascript
const sheetName = 'Thiết_bị';

function doGet(e) {
  const sheet = SpreadsheetApp.getActiveSpreadsheet().getSheetByName(sheetName);
  const data = sheet.getDataRange().getValues();
  const headers = data[0];
  const rows = data.slice(1);
  const result = rows.map(row => {
    let obj = {};
    headers.forEach((header, index) => {
      obj[header] = row[index];
    });
    return obj;
  });
  return ContentService.createTextOutput(JSON.stringify(result)).setMimeType(ContentService.MimeType.JSON);
}

function doPost(e) {
  const sheet = SpreadsheetApp.getActiveSpreadsheet().getSheetByName(sheetName);
  const requestData = JSON.parse(e.postData.contents);
  sheet.appendRow([requestData.ID, requestData.TenThietBi, requestData.SoLuong, requestData.TinhTrang]);
  return ContentService.createTextOutput(JSON.stringify({"status": "success"})).setMimeType(ContentService.MimeType.JSON);
}
```

## 3. Triển khai API
- Bấm **Deploy** > **New Deployment**.
- Chọn loại là **Web App**.
- Ở mục "Who has access", chọn **Anyone** (Bắt buộc).
- Copy link URL nhận được.

## 4. Cách sử dụng trong JavaScript (Frontend)
Sử dụng hàm `fetch()` để gọi API:

```javascript
const API_URL = 'LINK_URL_CỦA_BẠN';

// Lấy dữ liệu
async function getData() {
  const res = await fetch(API_URL);
  const data = await res.json();
  console.log(data);
}
```
