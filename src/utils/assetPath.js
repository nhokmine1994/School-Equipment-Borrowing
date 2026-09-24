const appBase = window.location.pathname.startsWith('/SEB') ? '/SEB' : '';

export function assetPath(value) {
  const source = String(value || '').trim();
  if (!source) return '';
  if (/^(https?:|data:|blob:)/i.test(source)) return source;
  if (source.startsWith('../')) return `${appBase}/${source.slice(3)}`;
  if (source.startsWith('/SEB/')) return source;
  if (source.startsWith('/')) return `${appBase}${source}`;
  return `${appBase}/${source}`;
}
