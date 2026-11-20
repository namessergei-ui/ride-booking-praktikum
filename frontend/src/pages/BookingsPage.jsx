import { useState } from 'react';
import { CalendarDays, Ticket, Users } from 'lucide-react';
import { Link } from 'react-router-dom';
import { Badge, Empty, Loading, Notice, Pagination, RouteNames } from '../components/Ui.jsx';
import { api } from '../services/api.js';
import { bookingLabels, tripDate, tripTime } from '../services/format.js';
import { useSession } from '../services/session.jsx';
import { useResource } from '../services/useResource.js';

function BookingCard({ booking, token, onUpdate }) {
  const [expanded, setExpanded] = useState(false);
  const [rating, setRating] = useState('5');
  const [saved, setSaved] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState(null);
  const path = `/trips/${booking.trip_id}/bookings/${booking.id}`;
  const tripLink = `/trips/${booking.trip_id}`;
  const detail = useResource(expanded ? path : null, { token, interval: 10000 });
  const trip = booking.trip;

  async function update(action) {
    setBusy(true); setError(null); setSaved(false);
    try {
      await api(`${path}/${action}`, {
        token, method: action === 'rating' ? 'PUT' : 'POST',
        body: action === 'rating' ? { score: Number(rating) } : undefined,
      });
      if (action === 'rating') setSaved(true);
    } catch (failure) {
      setError(failure);
    } finally {
      setBusy(false); onUpdate(); detail.refresh();
    }
  }

  return <article className="panel booking-card">
    <div className="booking-card-top">
      <div>
        <span className="muted">Бронь №{booking.id}</span>
        <h2><Link to={tripLink}><RouteNames trip={trip} /></Link></h2>
        <div className="trip-meta">
          <span><CalendarDays size={16} /> {tripDate(trip.departure_at)}, {tripTime(trip.departure_at)}</span>
          <span><Users size={16} /> Мест: {booking.seats}</span>
        </div>
      </div>
      <Badge status={booking.status} />
    </div>
    <Notice error={error} />
    <div className="button-row">
      <Link className="button button-outline" to={tripLink}>К поездке</Link>
      {booking.status === 'REQUESTED' && <button className="text-button danger" type="button" disabled={busy}
        onClick={() => update('cancel')}>Отменить запрос</button>}
    </div>
    <details onToggle={(event) => setExpanded(event.currentTarget.open)}>
      <summary>История брони</summary>
      <Notice error={detail.error} retry={detail.refresh} />
      {detail.loading && <Loading />}
      <ol className="history">
        {detail.data?.data.history.map((item, index) => <li key={index}>
          <span>{bookingLabels[item.status]}</span><span className="muted">{tripDate(item.created_at)}, {tripTime(item.created_at)}</span>
        </li>)}
      </ol>
    </details>
    {booking.status === 'CONFIRMED' && Date.parse(trip.departure_at) <= Date.now() && <form className="rating-form"
      onSubmit={(event) => { event.preventDefault(); update('rating'); }}>
      <label className="field">Оценка водителя<select value={rating} onChange={(event) => setRating(event.target.value)}>
        {[5, 4, 3, 2, 1].map((value) => <option key={value}>{value}</option>)}
      </select></label>
      <button className="button button-outline" disabled={busy} type="submit">Сохранить оценку</button>
      {saved && <span role="status" className="success">Оценка сохранена.</span>}
    </form>}
  </article>;
}

export default function BookingsPage() {
  const { session } = useSession();
  const [page, setPage] = useState(1);
  const bookings = useResource(`/bookings?page=${page}`, { token: session.token, interval: 10000 });

  return <main className="container content-page" id="main">
    <header className="page-heading"><h1>Мои брони</h1><p>Здесь можно проверить статус запросов и открыть поездку.</p></header>
    <section className="stack narrow">
      <Notice error={bookings.error} retry={bookings.refresh} />
      {bookings.loading && <Loading />}
      {bookings.data?.data.length === 0 && <Empty title="У вас пока нет броней" icon={Ticket}>
        <Link to="/" className="button button-primary">Найти поездку</Link>
      </Empty>}
      {bookings.data?.data.map((booking) => <BookingCard key={booking.id} booking={booking} token={session.token}
        onUpdate={bookings.refresh} />)}
      <Pagination meta={bookings.data?.meta} onChange={setPage} />
    </section>
  </main>;
}
