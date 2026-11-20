const zone = 'Asia/Yekaterinburg';
export const localTimezone = zone;

export function money(kopecks) {
  return new Intl.NumberFormat('ru-RU', {
    style: 'currency', currency: 'RUB', maximumFractionDigits: 2, minimumFractionDigits: 0,
  }).format(kopecks / 100);
}

export function tripDate(value) {
  return new Intl.DateTimeFormat('ru-RU', {
    day: 'numeric', month: 'long', weekday: 'short', timeZone: zone,
  }).format(new Date(value));
}

export function tripTime(value) {
  return new Intl.DateTimeFormat('ru-RU', {
    hour: '2-digit', minute: '2-digit', timeZone: zone,
  }).format(new Date(value));
}

export function dateInputValue(value) {
  const parts = new Intl.DateTimeFormat('en-CA', {
    year: 'numeric', month: '2-digit', day: '2-digit', timeZone: zone,
  }).formatToParts(value);
  const { year, month, day } = Object.fromEntries(parts.map((part) => [part.type, part.value]));
  return `${year}-${month}-${day}`;
}

export const bookingLabels = {
  REQUESTED: 'На рассмотрении',
  CONFIRMED: 'Подтверждена',
  REJECTED: 'Отклонена',
  CANCELLED: 'Отменена',
};
