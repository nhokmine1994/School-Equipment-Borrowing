import { useEffect, useRef } from 'react';
import { Link } from 'react-router-dom';
import LegacyPageShell from '../components/legacy/LegacyPageShell';

const appBase = window.location.pathname.startsWith('/SEB') ? '/SEB' : '';

function Mermaid({ source }) {
  const ref = useRef(null);

  useEffect(() => {
    const node = ref.current;
    let cancelled = false;

    import(/* @vite-ignore */ 'https://cdn.jsdelivr.net/npm/mermaid@10/dist/mermaid.esm.min.mjs')
      .then(async (mod) => {
        if (cancelled || !node) {
          return;
        }
        const mermaid = mod.default;
        mermaid.initialize({ startOnLoad: false, securityLevel: 'loose' });
        await mermaid.run({ nodes: [node] });
      })
      .catch(() => {
        // fallback: giữ nguyên nguồn mermaid dạng text
      });

    return () => {
      cancelled = true;
    };
  }, [source]);

  return (
    <div className="mermaid" ref={ref} style={{ textAlign: 'left' }}>
      {source}
    </div>
  );
}

const trendCards = [
  {
    tag: 'Q1 - Q2',
    title: 'Smart Borrowing',
    text: 'QR mượn nhanh, giảm thao tác tay và rút ngắn thời gian nhận thiết bị.',
  },
  {
    tag: 'Q2 - Q3',
    title: 'AI Assistant',
    text: 'Chatbot trả lời câu hỏi lặp lại, hỗ trợ tra cứu trạng thái thiết bị tức thì.',
  },
  {
    tag: 'Q3 - Q4',
    title: 'Omni Notifications',
    text: 'Gửi thông báo qua Zalo/email cho lịch mượn, trễ hạn và nhắc trả thiết bị.',
  },
  {
    tag: 'Next Stage',
    title: 'Multi-School Platform',
    text: 'Mở rộng liên trường, chuẩn hóa dữ liệu và tạo hệ sinh thái quản lý thiết bị giáo dục.',
  },
];

const layoutDiagram = `graph TD
    A[Header] --> B[Logo + Title + Buttons]
    C[Navigation] --> D[Menu Links + Hamburger]
    E[Main Content] --> F[Search Bar]
    E --> G[Stats Cards]
    E --> H[Equipment Grid]
    E --> I[Maintenance Section]
    J[Footer] --> K[Copyright + Hotline + QR Tip]`;

const architectureDiagram = `graph TD
    A[HTML/CSS/JS Thuần] --> B[Responsive Design]
    A --> C[No Framework]
    D[Frontend Only] --> E[Static Web App]
    D --> F[Client-side Logic]
    G[Database] --> H[JSON Files/Local Storage]
    I[Deployment] --> J[Web Server]
    I --> K[Static Hosting]`;

const flowDiagram = `graph TD
    A[Trang chủ] --> B[Kho thiết bị]
    A --> C[Kho cá nhân]
    A --> D[Tin tức]
    A --> E[Liên hệ]
    E --> F[Về dự án]
    E --> G[Về chúng tôi]
    G --> H[Đội ngũ phát triển]
    B --> I[Xem chi tiết thiết bị]
    C --> J[Lịch sử mượn/trả]
    D --> K[Đọc tin tức]`;

export default function ProjectPage() {
  return (
    <LegacyPageShell noHeading>

      <section className="section-wrapper">
        <div className="section-heading">
          <i className="fas fa-target" />
          Mục đích của dự án
        </div>
        <div className="maintenance-content">
          <p>
            Hiện tại mượn thiết bị vẫn dựa vào ghi chép thủ công, trao đổi trực tiếp hoặc nhắn tin cá nhân.
            Giáo viên không biết thiết bị còn hay đã mượn, dễ trùng lịch và mất thời gian tìm kiếm, còn nhân viên kho phải kiểm tra sổ sách và xử lý nhiều yêu cầu lặp lại.
          </p>
          <div style={{ textAlign: 'center', margin: '20px 0' }}>
            <img
              src={`${appBase}/Images/bieu-do.png`}
              alt="Biểu đồ tỷ lệ sử dụng phương pháp mượn thiết bị"
              style={{ maxWidth: 640, width: '100%', height: 'auto', borderRadius: 12, boxShadow: '0 12px 24px rgba(0,0,0,0.12)' }}
            />
            <p style={{ marginTop: 12, fontSize: '0.94rem', color: '#555' }}>
              Biểu đồ tỷ lệ hiện tại của quy trình mượn thiết bị trong trường học
            </p>
          </div>
          <p>
            Từ những bất cập trên, SEB được xây dựng như một &quot;trợ lý số&quot; cho nhà trường: mọi yêu cầu mượn/trả được ghi nhận tập trung, tra cứu nhanh và theo dõi minh bạch theo thời gian thực.
            Giáo viên biết ngay thiết bị nào đang sẵn sàng, thiết bị nào đã được đặt trước để chủ động kế hoạch giảng dạy.
          </p>
          <p>
            Với bộ phận phụ trách thiết bị, SEB giảm đáng kể thao tác thủ công, hạn chế nhầm lẫn khi kiểm kê, đồng thời hỗ trợ xử lý các trường hợp trễ hạn hoặc thất lạc rõ ràng hơn.
            Mục tiêu cuối cùng là biến quy trình mượn thiết bị thành một trải nghiệm nhanh, gọn và đáng tin cậy cho toàn trường.
          </p>
        </div>
      </section>

      <section className="section-wrapper">
        <div className="section-heading">
          <i className="fas fa-info-circle" />
          Mô tả dự án
        </div>
        <div className="maintenance-content">
          <h3>Phần mềm và công nghệ</h3>
          <p>
            SEB được phát triển bằng HTML, CSS và JavaScript thuần, không sử dụng framework phức tạp để đảm bảo tính đơn giản và dễ bảo trì.
            Hệ thống sử dụng kiến trúc web tĩnh với responsive design, tương thích trên mọi thiết bị (desktop, tablet, mobile).
          </p>

          <h3>Layout và giao diện</h3>
          <p>
            Giao diện sử dụng màu xanh dương làm chủ đạo (--primary-blue), font Inter, và các icon FontAwesome.
            Thiết kế clean, minimal với spacing đều đặn và border-radius cho các element.
          </p>
          <div style={{ textAlign: 'center', margin: '20px 0' }}>
            <h4>Layout của trang web SEB</h4>
            <Mermaid source={layoutDiagram} />
          </div>

          <h3>Kiến trúc phần mềm</h3>
          <div style={{ textAlign: 'center', margin: '20px 0' }}>
            <Mermaid source={architectureDiagram} />
          </div>

          <h3>Flow của trang web</h3>
          <p>
            Flow chính: Trang chủ → Chọn chức năng → Xem chi tiết → Thực hiện hành động (mượn/trả).
          </p>
          <div style={{ textAlign: 'center', margin: '20px 0' }}>
            <h4>Flow của trang web SEB</h4>
            <Mermaid source={flowDiagram} />
          </div>
        </div>
      </section>

      <section className="section-wrapper">
        <div className="section-heading">
          <i className="fas fa-chart-line" />
          Triển vọng dự án
        </div>
        <div className="maintenance-content">
          <p>
            SEB có tiềm năng mở rộng thành nền tảng quản lý thiết bị toàn diện cho nhiều trường học.
            Dự án có thể phát triển thêm tính năng như tích hợp QR code cho mượn nhanh, chatbot hỗ trợ tự động, và hệ thống thông báo qua Zalo.
          </p>
          <div className="trend-roadmap">
            <h4 className="trend-title">Roadmap xu hướng SEB</h4>
            <div className="trend-grid">
              {trendCards.map((card) => (
                <article className="trend-card" key={card.title}>
                  <p className="trend-tag">{card.tag}</p>
                  <h5>{card.title}</h5>
                  <p>{card.text}</p>
                </article>
              ))}
            </div>
          </div>
          <p>
            Với chi phí thấp và kiến trúc đơn giản, SEB dễ dàng duy trì và nâng cấp.
            Triển vọng dài hạn là tạo ra hệ sinh thái số hóa cho các hoạt động quản lý thiết bị trong giáo dục, góp phần hiện đại hóa cơ sở vật chất trường học.
          </p>
          <p>
            Dự án đã được đánh giá cao về hiệu quả, chi phí và trải nghiệm người dùng, với tiềm năng mở rộng sang các trường khác trong hệ thống giáo dục.
          </p>
          <div className="section-link-right">
            <Link to="/ve-chung-toi" className="section-link-btn">
              Sang trang Về chúng tôi <i className="fas fa-arrow-right" />
            </Link>
          </div>
        </div>
      </section>
    </LegacyPageShell>
  );
}
