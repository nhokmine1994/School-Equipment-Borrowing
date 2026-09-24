export const formatCurrency = (value = 0) =>
  new Intl.NumberFormat('vi-VN', {
    style: 'currency',
    currency: 'VND',
    maximumFractionDigits: 0,
  }).format(value);

export const formatDate = (value) => {
  if (!value) return '—';

  return new Date(value).toLocaleDateString('vi-VN');
};
