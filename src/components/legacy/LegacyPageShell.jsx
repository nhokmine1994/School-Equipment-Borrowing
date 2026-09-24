import LegacyFooter from './LegacyFooter';
import LegacyHeader from './LegacyHeader';
import LegacyNav from './LegacyNav';
import useLegacyCss from './useLegacyCss';

export default function LegacyPageShell({ title, icon, children, className = '', extraCss = [], noHeading = false }) {
  useLegacyCss(extraCss);

  return (
    <div className={`system-container legacy-page ${className}`}>
      <LegacyHeader />
      <LegacyNav />
      {noHeading ? (
        children
      ) : (
        <section className="section-wrapper">
          <div className="section-heading">
            <i className={icon} />
            {title}
          </div>
          {children}
        </section>
      )}
      <LegacyFooter />
    </div>
  );
}

export function PageNotice({ children, tone = 'info' }) {
  return <div className={`page-notice page-notice-${tone}`}>{children}</div>;
}
