import { useEffect } from 'react';

export default function useLegacyCss(extraCss = []) {
  const extraKey = Array.isArray(extraCss) ? extraCss.join('|') : String(extraCss ?? '');

  useEffect(() => {
    const nodes = [];

    const appendNode = (tagName, attributes) => {
      const node = document.createElement(tagName);
      Object.entries(attributes).forEach(([key, value]) => {
        if (value === undefined || value === null) {
          return;
        }
        if (key === 'crossOrigin') {
          node.setAttribute('crossorigin', value);
          return;
        }
        node.setAttribute(key, value);
      });
      node.dataset.sebLegacy = '1';
      document.head.appendChild(node);
      nodes.push(node);
    };

    const appRoot = window.location.pathname.startsWith('/SEB') ? '/SEB' : '';
    appendNode('link', { rel: 'stylesheet', href: `${appRoot}/CSS/main.css` });
    appendNode('link', { rel: 'preconnect', href: 'https://fonts.googleapis.com' });
    appendNode('link', { rel: 'preconnect', href: 'https://fonts.gstatic.com', crossOrigin: 'anonymous' });
    appendNode('link', {
      rel: 'stylesheet',
      href: 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap',
    });
    appendNode('link', {
      rel: 'stylesheet',
      href: 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
    });

    extraCss.forEach((href) => {
      if (typeof href === 'string' && href !== '') {
        const normalizedHref = href.startsWith('/') && !href.startsWith('//')
          ? `${appRoot}${href}`
          : href;
        appendNode('link', { rel: 'stylesheet', href: normalizedHref });
      }
    });

    return () => {
      nodes.forEach((node) => node.remove());
    };
  }, [extraKey]); // eslint-disable-line react-hooks/exhaustive-deps
}
