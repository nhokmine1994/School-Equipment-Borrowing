const fs = require('fs');
const path = require('path');

// Đường dẫn file mapping
const devicesFile = path.join(__dirname, 'data', 'devices.json');
const imagesDir = path.join(__dirname, 'images', 'devices');

// Chỉ định Map từ khoá tiếng Anh tốt nhất để API sinh ảnh chuẩn xác hơn
const keywordMap = {
    'CNTT': 'laptop,computer',
    'Âm thanh': 'audio,speaker',
    'Trình chiếu': 'projector,presentation',
    'Phòng lab': 'laboratory,microscope',
    'Dụng cụ thực hành': 'measurement,tools',
    'Hóa chất': 'chemical,chemistry',
    'Khác': 'office,equipment'
};

// Đảm bảo thư mục images/devices tồn tại
if (!fs.existsSync(imagesDir)) {
    console.log(`Tạo thư mục mới: ${imagesDir}`);
    fs.mkdirSync(imagesDir, { recursive: true });
}

// Giả lập Fetch tải file
async function downloadImage(url, dest) {
    const res = await fetch(url);
    if (!res.ok) throw new Error(`Network failed: ${res.statusText}`);
    const arrayBuffer = await res.arrayBuffer();
    fs.writeFileSync(dest, Buffer.from(arrayBuffer));
}

async function start() {
    try {
        console.log('Đang đọc file devices.json...');
        const rawData = fs.readFileSync(devicesFile, 'utf-8');
        const devices = JSON.parse(rawData);

        console.log(`Tìm thấy ${devices.length} thiết bị.`);
        console.log('Bắt đầu tải ảnh từ Random Image API (LoremFlickr)...');

        for (let i = 0; i < devices.length; i++) {
            const device = devices[i];
            const fileName = `${device.id}.jpg`;
            const destPath = path.join(imagesDir, fileName);
            
            // Map keyword theo category, fallback về 'equipment'
            const keyword = keywordMap[device.category] || 'equipment';
            
            // lock=i giúp đảm bảo ảnh sẽ được cố định cache ID, không bị lặp hình quá nhiều
            const url = `https://loremflickr.com/400/300/${keyword}?lock=${100 + i}`;

            console.log(`[${i + 1}/${devices.length}] Tải: ${device.id} (${device.name})...`);
            try {
                await downloadImage(url, destPath);
                
                // Thay thế data string text cũ -> Thẻ img HTML trỏ vào kho ảnh vật lý
                device.image = `<img src="../images/devices/${fileName}" alt="${device.name}" style="width: 100%; height: 100%; object-fit: cover; display: block;" />`;
            } catch (dlErr) {
                console.warn(`⚠️ Bỏ qua ${device.id} do lỗi tải:`, dlErr.message);
                // Vẫn cập nhật đường dẫn ảnh mặc cả việc không tải được, sau này có thể chép ảnh thật vào đúng code
                device.image = `<img src="../images/devices/${fileName}" alt="${device.name}" style="width: 100%; height: 100%; object-fit: cover; display: block;" />`;
            }

            // Nghỉ nhẹ 150ms để tránh rate limit của Web API
            await new Promise(resolve => setTimeout(resolve, 150));
        }

        // Cập nhật Database gốc
        fs.writeFileSync(devicesFile, JSON.stringify(devices, null, 4));
        console.log('\n✅ Hoàn tất! Tất cả ảnh đã lưu tại /images/devices/ và json đã cập nhật!');
    } catch (err) {
        console.error('❌ Thất bại nghiêm trọng:', err);
    }
}

start();
