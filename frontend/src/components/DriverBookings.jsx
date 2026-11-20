import { useState } from 'react';
import { ClipboardList } from 'lucide-react';
import { api } from '../services/api.js';
import { useResource } from '../services/useResource.js';
import { Badge, Loading, Notice, Pagination } from './Ui.jsx';

export default function DriverBookings({ tripId, token, onDecision }) {
  const [page, setPage] = useState(1);
  const [busy, setBusy] = useState(null);
  const [error, setError] = useState(null);
  const path = `/trips/${tripId}/bookings`;
  const bookings = useResource(`${path}?page=${page}`, { token, interval: 12000 });

  async function decide(booking, action) {
    setBusy(booking.id);
    setError(null);
    try {
      await api(`${path}/${booking.id}/${action}`, { method: 'POST', token });
    } catch (failure) {
      setError(failure);
    } finally {
      setBusy(null);
      bookings.refresh();
      onDecision();
    }
  }

  return <section className="panel section-panel" id="decisions" aria-labelledby="decisions-title">
    <h2 className="with-icon" id="decisions-title"><ClipboardList size={22} /> Запросы на бронь</h2>
    <Notice error={bookings.error} retry={bookings.refresh} />
    <Notice error={error} />
    {bookings.loading && <Loading />}
    {bookings.data?.data.length === 0 && <p className="muted">Пока никто не запросил места.</p>}
    {bookings.data?.data.map((booking) => <article className="decision" key={booking.id}>
      <div><strong>Бронь №{booking.id}</strong><span className="muted">Мест: {booking.seats}</span></div>
      <Badge status={booking.status} />
      {booking.status === 'REQUESTED' && <div className="decision-actions">
        <button className="button button-primary" disabled={busy !== null} type="button"
          onClick={() => decide(booking, 'confirm')}>Подтвердить</button>
        <button className="button button-outline" disabled={busy !== null} type="button"
          onClick={() => decide(booking, 'reject')}>Отклонить</button>
      </div>}
    </article>)}
    <Pagination meta={bookings.data?.meta} onChange={setPage} />
  </section>;
}
