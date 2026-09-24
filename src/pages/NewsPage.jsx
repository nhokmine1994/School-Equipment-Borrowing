import { useEffect, useState } from 'react';
import LegacyPageShell from '../components/legacy/LegacyPageShell';
import { api } from '../services/api';

const emptyNews = {
  banner: { articles: '42', topics: '5', views: '1.2K' },
  featured: null,
  articles: [],
};

const filters = [
  { key: 'all', label: 'Tất cả', icon: 'fa-th-large' },
  { key: 'thong-bao', label: 'Thông báo', icon: 'fa-bullhorn' },
  { key: 'bao-tri', label: 'Bảo trì', icon: 'fa-tools' },
  { key: 'su-kien', label: 'Sự kiện', icon: 'fa-calendar-alt' },
  { key: 'thiet-bi-moi', label: 'Thiết bị mới', icon: 'fa-box-open' },
];

const badgeMeta = {
  'thong-bao': { cls: 'badge-thong-bao', icon: 'fa-bullhorn', label: 'Thông báo' },
  'bao-tri': { cls: 'badge-bao-tri', icon: 'fa-tools', label: 'Bảo trì' },
  'su-kien': { cls: 'badge-su-kien', icon: 'fa-calendar-alt', label: 'Sự kiện' },
  'thiet-bi-moi': { cls: 'badge-thiet-bi-moi', icon: 'fa-box-open', label: 'Thiết bị mới' },
};

export default function NewsPage() {
  const [news, setNews] = useState(emptyNews);
  const [activeArticle, setActiveArticle] = useState(null);
  const [filter, setFilter] = useState('all');
  const [showExtra, setShowExtra] = useState(false);

  useEffect(() => {
    let active = true;

    api.getNews().then((result) => {
      if (!active || !result?.success) {
        return;
      }

      const data = result.data ?? emptyNews;
      const articles = Array.isArray(data.articles) ? data.articles : [];
      if (articles.length > 0) {
        setNews({ ...emptyNews, ...data, articles });
      }
    });

    return () => {
      active = false;
    };
  }, []);

  const visibleArticles = news.articles.filter((article) =>
    showExtra ? true : !article.extra,
  );
  const filteredArticles = visibleArticles.filter(
    (article) => filter === 'all' || article.category === filter,
  );
  const hasExtra = news.articles.some((article) => article.extra);
  const canLoadMore = hasExtra && !showExtra;
  const featured = news.featured;

  return (
    <LegacyPageShell noHeading extraCss={['/CSS/news.css']}>
      {!activeArticle ? (
      <>
      <div className="news-page-banner">
        <div className="news-banner-icon">
          <i className="fas fa-newspaper" />
        </div>
        <div className="news-banner-text">
          <h2>Tin tức &amp; Thông báo</h2>
          <p>Cập nhật mới nhất về thiết bị, bảo trì và sự kiện nhà trường</p>
        </div>
        <div className="news-banner-stats">
          <div className="news-banner-stat">
            <span className="nbs-value">{news.banner?.articles ?? '42'}</span>
            <span className="nbs-label">Bài viết</span>
          </div>
          <div className="news-banner-stat">
            <span className="nbs-value">{news.banner?.topics ?? '5'}</span>
            <span className="nbs-label">Chủ đề</span>
          </div>
          <div className="news-banner-stat">
            <span className="nbs-value">{news.banner?.views ?? '1.2K'}</span>
            <span className="nbs-label">Lượt xem</span>
          </div>
        </div>
      </div>

      <div className="news-filter-bar">
        {filters.map(({ key, label, icon }) => (
          <button
            key={key}
            type="button"
            className={`news-filter-btn${filter === key ? ' active' : ''}`}
            data-filter={key}
            onClick={() => setFilter(key)}
          >
            <i className={`fas ${icon}`} /> {label}
          </button>
        ))}
      </div>

      {featured ? (
        <section className="news-section" data-category={featured.category}>
          <div className="section-heading">
            <i className="fas fa-star" /> Bài viết nổi bật
          </div>
          <div className="news-featured-wrapper">
            <div className="news-featured-img" style={{ background: 'none', position: 'relative' }}>
              <img
                src={featured.image}
                alt="Bài viết nổi bật"
                style={{ width: '100%', height: '100%', objectFit: 'cover' }}
              />
              {featured.label ? (
                <span
                  className="news-featured-label"
                  style={{ position: 'absolute', top: 15, left: 15, zIndex: 1 }}
                >
                  {featured.label}
                </span>
              ) : null}
            </div>
            <div className="news-featured-content">
              <span className={`news-badge ${badgeMeta[featured.category]?.cls ?? ''}`}>
                <i className={`fas ${badgeMeta[featured.category]?.icon ?? 'fa-newspaper'}`} />{' '}
                {badgeMeta[featured.category]?.label ?? 'Tin tức'}
              </span>
              <h2 className="news-featured-title">{featured.title}</h2>
              <p className="news-featured-excerpt">{featured.excerpt}</p>
              <div className="news-featured-meta">
                <span>
                  <i className="far fa-calendar" /> {featured.date}
                </span>
                <span>
                  <i className="far fa-clock" /> {featured.readTime}
                </span>
                <span>
                  <i className="far fa-eye" /> {featured.views}
                </span>
              </div>
              <button type="button" className="news-read-btn" onClick={() => setActiveArticle(featured)}>
                Đọc bài viết <i className="fas fa-arrow-right" />
              </button>
            </div>
          </div>
        </section>
      ) : null}

      <section className="news-section">
        <div className="section-heading">
          <i className="fas fa-newspaper" /> Tin tức mới nhất
        </div>

        <div className="news-grid-wrapper">
          <div className="news-grid" id="newsGrid">
            {filteredArticles.map((article, index) => (
              <article
                className="news-card"
                data-category={article.category}
                key={`${article.category}-${index}`}
                style={{ cursor: 'pointer' }}
                title="Xem chi tiết bài viết"
                onClick={() => setActiveArticle(article)}
              >
                {article.image ? (
                  <div
                    className="news-card-img"
                    style={{ padding: 0, background: 'none', overflow: 'hidden' }}
                  >
                    <img
                      src={article.image}
                      alt={article.title}
                      style={{
                        width: '100%',
                        height: '100%',
                        objectFit: 'cover',
                        transition: 'transform 0.3s ease',
                      }}
                    />
                  </div>
                ) : (
                  <div className={`news-card-img ${article.gradient ?? ''}`}>
                    <i className={article.icon ?? 'fas fa-file-alt'} />
                  </div>
                )}
                <div className="news-card-body">
                  <span className={`news-badge ${badgeMeta[article.category]?.cls ?? ''}`}>
                    <i className={`fas ${badgeMeta[article.category]?.icon ?? 'fa-newspaper'}`} />{' '}
                    {badgeMeta[article.category]?.label ?? 'Tin tức'}
                  </span>
                  <h3 className="news-card-title">{article.title}</h3>
                  <p className="news-card-excerpt">{article.excerpt}</p>
                  <div className="news-card-meta">
                    <span>
                      <i className="far fa-calendar" /> {article.date}
                    </span>
                    <span>
                      <i className="far fa-clock" /> {article.readTime}
                    </span>
                  </div>
                </div>
                <div className="news-card-footer">
                  <button type="button" className="news-card-btn" onClick={(event) => { event.stopPropagation(); setActiveArticle(article); }}>
                    Đọc thêm <i className="fas fa-chevron-right" />
                  </button>
                </div>
              </article>
            ))}
          </div>

          {filteredArticles.length === 0 ? (
            <div className="news-empty" id="newsEmpty">
              <i className="fas fa-search" />
              <p>Không có bài viết trong chủ đề này.</p>
            </div>
          ) : null}

          {canLoadMore ? (
            <div className="see-more-container" id="loadMoreContainer">
              <button type="button" className="btn-see-more" id="loadMoreBtn" onClick={() => setShowExtra(true)}>
                Xem thêm <i className="fas fa-chevron-down" />
              </button>
            </div>
          ) : null}
        </div>
      </section>

      </>
      ) : null}

      {activeArticle ? (
        (() => {
          const crumbIncome = badgeMeta[activeArticle.category]?.label ?? 'Tin tức';
          const bodyText = String(activeArticle.content || activeArticle.excerpt || '');
          const paragraphs = bodyText.split(/\n\s*\n/).map((p) => p.trim()).filter(Boolean);
          const sideList = [
            ...(() => { try { return featured && featured.title !== activeArticle.title ? [featured] : []; } catch (e) { return []; } })(),
            ...(news.articles ?? []).filter((a) => a.title !== activeArticle.title).slice(0, 4),
          ];
          const detailTime = `${activeArticle.date ?? ''} ${activeArticle.readTime ? '· ' + activeArticle.readTime : ''}`;
          return (
            <div className="news-detail">
              <div className="news-detail-crumb">
                <button type="button" className="crumb-link" onClick={() => setActiveArticle(null)}>Tin tức</button>
                <span className="crumb-sep">›</span>
                <span>{badgeMeta[activeArticle.category]?.label ?? 'Tin tức'}</span>
                <span className="news-detail-time">
                  <i className="far fa-calendar" /> {activeArticle.date} {activeArticle.readTime ? '· ' + activeArticle.readTime : ''}
                </span>
              </div>

              <h1 className="news-detail-title">{activeArticle.title}</h1>

              <div className="news-detail-meta">
                <span>
                  <span className={`news-badge ${badgeMeta[activeArticle.category]?.cls ?? ''}`} style={{ margin: 0 }}>
                    <i className={`fas ${badgeMeta[activeArticle.category]?.icon ?? 'fa-newspaper'}`} /> {badgeMeta[activeArticle.category]?.label ?? 'Tin tức'}
                  </span>
                </span>
                {activeArticle.author ? <span><i className="far fa-user" /> {activeArticle.author}</span> : null}
              </div>

              <div className="news-detail-body" style={{ display: 'flex', gap: 24, flexWrap: 'wrap' }}>
                <div style={{ flex: '1 1 480px', minWidth: 0 }}>
                  {bodyText.trim() !== '' ? (
                    paragraphs.map((pText, idx) => <p key={idx}>{pText}</p>)
                  ) : (
                    <p style={{ color: '#94a3b8' }}>Bài viết chưa có nội dung chi tiết.</p>
                  )}

                  <button type="button" className="news-detail-back" onClick={() => setActiveArticle(null)}>
                    <i className="fas fa-arrow-left" /> Về danh sách tin
                  </button>
                </div>

                <aside className="news-detail-side" style={{ flex: '0 0 220px' }}>
                  <h4 className="news-detail-side-title">Xem nhiều</h4>
                  {sideList.length === 0 ? (
                    <p style={{ fontSize: 13, color: '#94a3b8' }}>Chưa có tin khác.</p>
                  ) : (
                    sideList.map((sideItem, idx) => (
                      <button
                        type="button"
                        key={idx}
                        className="side-news-thumb"
                        onClick={() => { setActiveArticle(null); setTimeout(() => setActiveArticle(sideItem), 10); }}
                      >
                        <span className="side-thumb-icon" style={{ background: 'linear-gradient(135deg, #1565c0, #42a5f5)' }}>
                          <i className={`fas ${badgeMeta[sideItem.category]?.icon ?? 'fa-newspaper'}`} />
                        </span>
                        <span className="side-thumb-body">
                          <strong>{sideItem.title}</strong>
                          <small style={{ color: '#64748b', fontSize: 11.5 }}>{sideItem.date}</small>
                        </span>
                      </button>
                    ))
                  )}
                </aside>
              </div>
            </div>
          );
        })()
      ) : null}
    </LegacyPageShell>
  );
}
