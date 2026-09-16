import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import LegacyPageShell from '../components/legacy/LegacyPageShell';

const appBase = window.location.pathname.startsWith('/SEB') ? '/SEB' : '';
import { api } from '../services/api';

const teamStyles = `
  .team-intro-tip {
    text-align: center;
    font-size: 14px;
    color: #1a5fa6;
    margin-bottom: 12px;
    font-weight: 600;
  }

  .card-arena {
    display: flex;
    justify-content: center;
    align-items: flex-end;
    overflow: visible;
    padding: 6px 4px 20px 4px;
  }

  .card-arena::-webkit-scrollbar {
    height: 6px;
  }

  .card-arena::-webkit-scrollbar-thumb {
    background-color: #9ec7ec;
    border-radius: 999px;
  }

  .member-card {
    width: 186px;
    min-width: 186px;
    border: 1px solid #97bde4;
    border-radius: 14px;
    background: linear-gradient(180deg, #ffffff 0%, #e8f4ff 100%);
    overflow: hidden;
    cursor: pointer;
    transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
    box-shadow: 0 8px 18px rgba(16, 84, 147, 0.14);
    text-align: left;
    padding: 0;
    position: relative;
    z-index: var(--stack-z, 1);
  }

  .member-card + .member-card {
    margin-left: -52px;
  }

  .member-card:hover {
    transform: translateY(-14px) scale(1.03);
    z-index: 220;
  }

  .member-card.active {
    border-color: #1976d2;
    box-shadow: 0 14px 24px rgba(8, 74, 140, 0.24);
    transform: translateX(-24px) translateY(-18px) scale(1.05);
    z-index: 240;
  }

  .member-card.active:hover {
    transform: translateX(-24px) translateY(-22px) scale(1.06);
  }

  .member-card.add-member {
    border: 2px dashed #7fb1df;
    background: linear-gradient(180deg, #fafdff 0%, #e9f4ff 100%);
  }

  .member-card.add-member .member-role {
    background: linear-gradient(90deg, #2a78c5 0%, #4ba0ef 100%);
  }

  .add-member-body {
    height: 248px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 10px;
    color: #1b67b2;
  }

  .add-member-plus {
    font-size: 64px;
    line-height: 1;
    font-weight: 700;
  }

  .add-member-text {
    font-size: 15px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.3px;
  }

  .member-role {
    display: block;
    background: linear-gradient(90deg, #125cae 0%, #1f8be9 100%);
    color: #ffffff;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.4px;
    text-transform: uppercase;
    padding: 8px 10px;
    text-align: center;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .member-photo {
    width: 100%;
    height: 190px;
    object-fit: cover;
    display: block;
    border-bottom: 1px solid #cde1f5;
  }

  .member-name {
    display: block;
    font-family: var(--font-body);
    font-size: 14px;
    font-weight: 700;
    line-height: 1.35;
    letter-spacing: 0;
    color: #0f4d8f;
    text-transform: uppercase;
    text-align: center;
    padding: 10px 8px;
    min-height: 58px;
    text-rendering: optimizeLegibility;
    -webkit-font-smoothing: antialiased;
  }

  .member-detail {
    margin: 4px auto 0 auto;
    max-width: 760px;
    width: 100%;
    border: 1px solid #cfe4f9;
    border-radius: 14px;
    background: linear-gradient(135deg, #f9fcff 0%, #edf6ff 100%);
    padding: 18px;
  }

  .member-detail.empty {
    opacity: 0.78;
  }

  .member-detail-head {
    text-align: center;
    margin-bottom: 12px;
  }

  .member-detail-name {
    font-size: 22px;
    font-weight: 800;
    color: #0c4a88;
    margin-bottom: 4px;
  }

  .member-detail-role {
    font-size: 13px;
    font-weight: 700;
    color: #1f77cf;
    text-transform: uppercase;
    margin-bottom: 0;
    letter-spacing: 0.3px;
  }

  .detail-grid {
    display: grid;
    grid-template-columns: 170px 1fr;
    gap: 8px 14px;
    align-items: center;
    max-width: 600px;
    margin: 0 auto;
  }

  .detail-label {
    font-size: 14px;
    font-weight: 700;
    color: #14579f;
    text-align: right;
  }

  .detail-value {
    font-size: 14px;
    color: #1d3b5b;
    text-align: left;
    word-break: break-word;
  }

  .detail-value a {
    color: #0f5faa;
    text-decoration: none;
  }

  .detail-value a:hover {
    text-decoration: underline;
  }

  .detail-placeholder {
    text-align: center;
    font-size: 13px;
    color: #3a648d;
    margin-top: 10px;
  }

  .hidden {
    display: none;
  }

  .add-member-form {
    max-width: 640px;
    margin: 0 auto;
  }

  .add-member-form h4 {
    font-size: 18px;
    color: #11589f;
    text-align: center;
    margin-bottom: 12px;
  }

  .form-grid {
    display: grid;
    grid-template-columns: 150px 1fr;
    gap: 10px 12px;
    align-items: center;
  }

  .form-grid label {
    text-align: right;
    font-size: 14px;
    font-weight: 700;
    color: #1b5fa5;
  }

  .form-grid input,
  .form-grid textarea {
    width: 100%;
    border: 1px solid #b9d5f0;
    border-radius: 8px;
    padding: 8px 10px;
    font-size: 14px;
    font-family: var(--font-body);
  }

  .form-grid textarea {
    min-height: 72px;
    resize: vertical;
  }

  .form-actions {
    margin-top: 14px;
    display: flex;
    justify-content: center;
  }

  .form-submit {
    border: none;
    border-radius: 999px;
    padding: 10px 18px;
    font-size: 14px;
    font-weight: 700;
    color: #ffffff;
    background: linear-gradient(90deg, #1665b8 0%, #2c8de3 100%);
    cursor: pointer;
  }

  .section-link-left {
    margin-top: 18px;
    display: flex;
  }

  .section-link-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 18px;
    border-radius: 50px;
    background-color: var(--primary-blue);
    color: #ffffff;
    font-size: 14px;
    font-weight: 600;
    text-decoration: none;
    transition: background-color 0.2s ease, transform 0.2s ease;
  }

  .section-link-btn:hover {
    background-color: #0d6bb2;
    transform: translateY(-2px);
  }

  @media (max-width: 768px) {
    .card-arena {
      justify-content: flex-start;
      overflow-x: auto;
      overflow-y: visible;
      align-items: flex-start;
      padding: 16px 8px 24px 8px;
      -webkit-overflow-scrolling: touch;
    }

    .member-card {
      width: 170px;
      min-width: 170px;
      flex-shrink: 0;
    }

    .member-name {
      font-size: 13px;
      min-height: 52px;
    }

    .member-card + .member-card {
      margin-left: -24px;
    }

    .member-card.active {
      transform: translateX(-10px) translateY(0) scale(1.02);
    }

    .member-card.active:hover {
      transform: translateX(-10px) translateY(0) scale(1.03);
    }

    .member-photo {
      height: 170px;
    }

    .add-member-body {
      height: 228px;
    }

    .member-detail {
      padding: 14px;
    }

    .detail-grid {
      grid-template-columns: 1fr;
      gap: 4px;
    }

    .detail-label,
    .detail-value {
      text-align: center;
    }

    .form-grid {
      grid-template-columns: 1fr;
    }

    .form-grid label {
      text-align: center;
    }
  }
`;

export default function TeamPage() {
  const [intro, setIntro] = useState(
    'Nhóm phát triển SEB gồm các thành viên phụ trách quản lý dự án, phân tích nghiệp vụ, phát triển phần mềm và thiết kế giao diện. Chúng tôi xây dựng giải pháp này để giúp nhà trường số hóa quy trình mượn thiết bị, giảm tải cho giáo viên và nâng cao hiệu quả quản lý kho.',
  );
  const [members, setMembers] = useState([]);
  const [activeIndex, setActiveIndex] = useState(null);
  const [detailMode, setDetailMode] = useState('info');
  const [form, setForm] = useState({
    name: '',
    role: '',
    phone: '',
    email: '',
    image: '',
    note: '',
  });

  useEffect(() => {
    let active = true;

    api.getTeamMembers().then((result) => {
      if (!active || !result?.success) {
        return;
      }

      const data = result.data ?? {};
      const list = Array.isArray(data.members) ? data.members : [];
      if (list.length > 0) {
        setMembers(list);
        setIntro(String(data.intro || ''));
        setActiveIndex(0);
        setDetailMode('info');
      }
    });

    return () => {
      active = false;
    };
  }, []);

  const total = members.length;

  const activeMember = activeIndex !== null ? members[activeIndex] : null;

  const selectCard = (index) => {
    setActiveIndex(index);
    setDetailMode('info');
  };

  const openAddForm = () => {
    setActiveIndex(null);
    setDetailMode('form');
  };

  const handleContainerClick = (event) => {
    if (!event.target.closest('.member-card') && !event.target.closest('#memberDetail')) {
      setActiveIndex(null);
      setDetailMode('empty');
    }
  };

  const handleCardClick = (index) => {
    if (index === members.length) {
      openAddForm();
      return;
    }
    selectCard(index);
  };

  const updateForm = (event) => {
    const { name, value } = event.target;
    setForm((prev) => ({ ...prev, [name]: value }));
  };

  const handleSubmit = (event) => {
    event.preventDefault();

    const memberData = {
      name: form.name.trim(),
      role: form.role.trim(),
      phone: form.phone.trim(),
      email: form.email.trim(),
       image: form.image.trim() || `${appBase}/Images/logo.png`,
      note: form.note.trim(),
    };

    if (!memberData.name || !memberData.role || !memberData.phone || !memberData.email || !memberData.note) {
      return;
    }

    setMembers((prev) => {
      const next = [...prev, memberData];
      setActiveIndex(next.length - 1);
      setDetailMode('info');
      return next;
    });
    setForm({ name: '', role: '', phone: '', email: '', image: '', note: '' });
  };

  return (
    <LegacyPageShell noHeading>
      <style>{teamStyles}</style>

      <section className="section-wrapper">
        <div className="section-heading">
          <i className="fas fa-users" />
          Về chúng tôi
        </div>
        <div className="maintenance-content">
          <p>{intro}</p>
        </div>
      </section>

      <section className="section-wrapper">
        <div className="section-heading">
          <i className="far fa-id-badge" />
          Đội ngũ phát triển
        </div>

        <div className="equipment-container" onClick={handleContainerClick}>
          <p className="team-intro-tip">Nhấn vào một thành viên để xem thông tin</p>

          <div className="card-arena" id="cardArena">
            {members.map((member, index) => (
              <button
                key={`${member.name}-${index}`}
                type="button"
                className={`member-card${activeIndex === index && detailMode === 'info' ? ' active' : ''}`}
                style={{ '--stack-z': Math.max(1, total - index) }}
                onClick={() => handleCardClick(index)}
              >
                <span className="member-role">{member.role}</span>
                <img className="member-photo" src={member.image} alt={member.name} />
                <span className="member-name">{member.name}</span>
              </button>
            ))}

            <button type="button" className="member-card add-member" onClick={() => handleCardClick(members.length)}>
              <span className="member-role">THÊM THÀNH VIÊN</span>
              <div className="add-member-body">
                <span className="add-member-plus">+</span>
                <span className="add-member-text">Mở form nhập</span>
              </div>
            </button>
          </div>

          <div className={`member-detail${detailMode === 'empty' ? ' empty' : ''}`} id="memberDetail">
            {detailMode === 'form' ? (
              <form className="add-member-form" id="addMemberForm" onSubmit={handleSubmit}>
                <h4>Thêm thành viên mới</h4>
                <div className="form-grid">
                  <label htmlFor="newName">Họ và tên</label>
                  <input id="newName" name="name" type="text" value={form.name} onChange={updateForm} required />

                  <label htmlFor="newRole">Vai trò</label>
                  <input id="newRole" name="role" type="text" value={form.role} onChange={updateForm} required />

                  <label htmlFor="newPhone">Số điện thoại</label>
                  <input id="newPhone" name="phone" type="text" value={form.phone} onChange={updateForm} required />

                  <label htmlFor="newEmail">Email</label>
                  <input id="newEmail" name="email" type="email" value={form.email} onChange={updateForm} required />

                  <label htmlFor="newImage">Ảnh (đường dẫn)</label>
                  <input
                    id="newImage"
                    name="image"
                    type="text"
                    placeholder="Images/ten-anh.jpg"
                    value={form.image}
                    onChange={updateForm}
                  />

                  <label htmlFor="newNote">Mô tả</label>
                  <textarea id="newNote" name="note" value={form.note} onChange={updateForm} required />
                </div>
                <div className="form-actions">
                  <button className="form-submit" type="submit">
                    Thêm vào danh sách
                  </button>
                </div>
              </form>
            ) : detailMode === 'empty' || !activeMember ? (
              <p className="detail-placeholder">Nhấn vào một thành viên để xem thông tin chi tiết</p>
            ) : (
              <>
                <div className="member-detail-head" id="detailHead">
                  <p className="member-detail-name" id="detailName">
                    {activeMember.name}
                  </p>
                  <p className="member-detail-role" id="detailRole">
                    {activeMember.role}
                  </p>
                </div>
                <div className="detail-grid" id="detailGrid">
                  <p className="detail-label">Họ và tên</p>
                  <p className="detail-value">{activeMember.name}</p>
                  <p className="detail-label">Vai trò</p>
                  <p className="detail-value">{activeMember.role}</p>
                  <p className="detail-label">Số điện thoại</p>
                  <p className="detail-value">
                    <a href={`tel:${activeMember.phone}`}>{activeMember.phone}</a>
                  </p>
                  <p className="detail-label">Email</p>
                  <p className="detail-value">
                    <a href={`mailto:${activeMember.email}`}>{activeMember.email}</a>
                  </p>
                  <p className="detail-label">Mô tả</p>
                  <p className="detail-value">{activeMember.note}</p>
                </div>
              </>
            )}
          </div>

          <div className="section-link-left">
            <Link to="/ve-du-an" className="section-link-btn">
              <i className="fas fa-arrow-left" /> Sang trang Về dự án
            </Link>
          </div>
        </div>
      </section>
    </LegacyPageShell>
  );
}
