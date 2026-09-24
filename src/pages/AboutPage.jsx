import { Link } from 'react-router-dom';
import LegacyPageShell from '../components/legacy/LegacyPageShell';

const appBase = window.location.pathname.startsWith('/SEB') ? '/SEB' : '';

const aboutStyles = `
  .team-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    justify-content: center;
    gap: 25px;
  }

  .team-card .equipment-info {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: space-between;
    height: 100%;
  }

  .team-card {
    border-radius: 14px;
    overflow: hidden;
  }

  .team-card .equipment-time {
    width: 100%;
    margin-top: 0;
    margin-bottom: 10px;
    font-size: 16px;
    font-weight: 700;
    text-transform: uppercase;
    background-color: var(--primary-blue);
    color: #ffffff;
    letter-spacing: 0.5px;
    border-radius: 10px;
  }

  .team-card .equipment-img-box {
    margin: 10px 0;
    border-radius: 12px;
    overflow: hidden;
  }

  .team-card .equipment-name {
    margin-top: 10px;
  }

  .team-contact {
    width: 100%;
    margin-top: 8px;
    padding: 8px 10px;
    border: 1px dashed var(--container-border);
    border-radius: 8px;
    font-size: 13px;
    text-align: center;
    line-height: 1.5;
    color: var(--text-dark);
    background: #f8fcff;
  }

  .system-container .equipment-grid.team-grid {
    justify-content: center !important;
    max-width: 720px;
    margin: 0 auto;
  }
  @media (max-width: 768px) {
    .team-grid {
      grid-template-columns: 1fr;
    }
  }
`;

export default function AboutPage() {
  return (
    <LegacyPageShell noHeading>
      <style>{aboutStyles}</style>

      <section className="section-wrapper">
        <div className="section-heading">
          <i className="fas fa-address-book" />
          Liên hệ
        </div>

        <div className="equipment-container">
          <div className="equipment-grid team-grid">
            <Link to="/ve-du-an" style={{ textDecoration: 'none', color: 'inherit', display: 'block' }}>
              <div className="equipment-card team-card">
                <div className="equipment-info">
                  <div className="equipment-time">Về dự án</div>
                  <div className="equipment-img-box">
                    <img src={`${appBase}/Images/logo.png`} alt="Về dự án" />
                  </div>
                  <div className="equipment-name">SEB - Hệ thống mượn/trả thiết bị</div>
                  <div className="team-contact">
                    Tìm hiểu về mục đích, triển vọng và giải pháp của dự án SEB.
                  </div>
                </div>
              </div>
            </Link>

            <Link to="/ve-chung-toi" style={{ textDecoration: 'none', color: 'inherit', display: 'block' }}>
              <div className="equipment-card team-card">
                <div className="equipment-info">
                  <div className="equipment-time">Về chúng tôi</div>
                  <div className="equipment-img-box">
                    <img src={`${appBase}/Images/conghau-avatar.jpg`} alt="Về chúng tôi" />
                  </div>
                  <div className="equipment-name">Đội ngũ phát triển</div>
                  <div className="team-contact">
                    Làm quen với đội ngũ đằng sau dự án SEB.
                  </div>
                </div>
              </div>
            </Link>
          </div>
        </div>
      </section>

      <section className="section-wrapper">
        <div className="section-heading">
          <i className="fas fa-circle-info" />
          Ghi chú liên hệ
        </div>
        <div className="maintenance-content">
          <p>
            Bạn có thể bổ sung SĐT, Email hoặc các kênh liên hệ khác (Zalo, Facebook, GitHub) cho từng thành viên.
          </p>
          <p>
            Khi cần, mình có thể giúp bạn thêm nút gọi nhanh hoặc mailto để bấm là liên hệ trực tiếp.
          </p>
        </div>
      </section>
    </LegacyPageShell>
  );
}
