const endpoint = (import.meta.env.VITE_API_BASE || '/api/v1').replace(/\/$/, '');

const messages = {
  'Invalid credentials.': 'Неверная почта или пароль.',
  'There are not enough available seats.': 'Свободных мест уже недостаточно.',
  'You already have an active booking for this trip.': 'У вас уже есть активная бронь на эту поездку.',
  'This trip has already departed.': 'Поездка уже отправилась.',
  'This booking has already reached a final status.': 'Решение по этой брони уже принято.',
  'Seats exceed the vehicle capacity.': 'Мест в поездке больше, чем в автомобиле.',
  'Intermediate stops must differ from the origin and destination.': 'Остановки должны отличаться от начала и конца маршрута.',
  'The vehicle must accommodate the seats in all upcoming trips.': 'В автомобиле должно хватать мест для всех опубликованных поездок.',
};

const statusMessages = {
  401: 'Войдите в аккаунт, чтобы продолжить.',
  403: 'У вас нет доступа к этому действию.',
  404: 'Запись не найдена.',
  409: 'Данные изменились. Обновите страницу и попробуйте снова.',
  422: 'Проверьте введённые данные.',
  429: 'Слишком много запросов. Попробуйте немного позже.',
};

export class ApiError extends Error {
  constructor(status, payload) {
    const details = Object.values(payload?.errors || {}).flat();
    const translated = details.map((detail) => messages[detail]).filter(Boolean);
    const message = translated[0] || messages[payload?.message] || statusMessages[status]
      || 'Сервис временно недоступен. Попробуйте снова.';
    super(message);
    this.status = status;
    this.fields = Object.keys(payload?.errors || {});
  }
}

export function queryPath(path, params) {
  const search = new URLSearchParams();
  for (const [key, value] of Object.entries(params)) {
    if (value !== '' && value !== undefined && value !== null) search.set(key, String(value));
  }
  return search.size ? `${path}?${search}` : path;
}

export async function api(path, { method = 'GET', body, token, signal } = {}) {
  const headers = { Accept: 'application/json' };
  if (body !== undefined) headers['Content-Type'] = 'application/json';
  if (token) headers.Authorization = `Bearer ${token}`;

  let response;
  try {
    response = await fetch(`${endpoint}${path}`, {
      method, headers, signal,
      body: body === undefined ? undefined : JSON.stringify(body),
      cache: 'no-store',
    });
  } catch (error) {
    if (signal?.aborted) throw error;
    throw new Error('Не удалось связаться с сервером. Проверьте соединение и попробуйте снова.');
  }

  if (response.status === 204) return null;
  const payload = await response.json().catch(() => null);
  if (response.status === 401 && token) {
    window.dispatchEvent(new CustomEvent('ride:unauthorized', { detail: token }));
  }
  if (!response.ok || !payload) throw new ApiError(response.status, payload);
  return payload;
}
