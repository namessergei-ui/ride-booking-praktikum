import { lazy, Suspense, useEffect, useState } from 'react';
import { ArrowLeft, CalendarDays, CarFront, Clock3, Luggage, MapPin, Star, Users } from 'lucide-react';
import { Link, useLocation, useParams } from 'react-router-dom';
import Chat from '../components/Chat.jsx';
import DriverBookings from '../components/DriverBookings.jsx';
import { Badge, Loading, Notice, RouteNames } from '../components/Ui.jsx';
import { api } from '../services/api.js';
import { money, tripDate, tripTime } from '../services/format.js';
import { useSession } from '../services/session.jsx';
import { useResource } from '../services/useResource.js';

const RouteMap = lazy(() => import('../components/RouteMap.jsx'));

function BookingPanel({ trip, session, active, lookup, onUpdate, driver }) {
  const [seats, setSeats] = useState(1);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState(null);
  const isPast = Date.parse(trip.departure_at) <= Date.now();
  const count = Math.min(seats, trip.available_seats);

  async function request(event) {
    event.preventDefault();
    if (count < 1 || busy) return;
    setBusy(true);
    setError(null);
    try {
      await api(`/trips/${trip.id}/bookings`, { method: 'POST', token: session.token, body: { seats: count } });
    } catch (failure) {
      setError(failure);
    } finally {
      onUpdate();
      setBusy(false);
    }
  }

  return <aside className="booking-sidebar panel">
    <div className="booking-price"><strong>{money(trip.price_kopecks)}</strong><span>за место</span></div>
    <div className="availability"><Users size={18} /> Свободно {trip.available_seats} из {trip.total_seats} мест</div>
    <Notice error={error} />
    {driver ? <p className="muted">Это ваша поездка.</p>
      : active ? <div className="stack compact"><Badge status={active.status} />
        <Link className="button button-outline" to="/bookings">Мои брони</Link></div>
      : lookup.loading ? <Loading label="Проверяем бронь…" />
      : lookup.error ? <Notice error={lookup.error} retry={lookup.refresh} />
      : isPast ? <p className="muted">Поездка уже отправилась.</p>
      : !trip.available_seats ? <p className="muted">Все места забронированы.</p>
      : !session ? <Link className="button button-primary" to="/login" state={{ from: `/trips/${trip.id}` }}>
        Войти для бронирования
      </Link>
      : <form className="stack compact" onSubmit={request}>
        <label className="field">Сколько мест нужно
          <select value={count} onChange={(event) => setSeats(Number(event.target.value))}>
            {Array.from({ length: trip.available_seats }, (_, index) => <option key={index}>{index + 1}</option>)}
          </select>
        </label>
        <button className="button button-primary" type="submit" disabled={busy}>{busy ? 'Отправляем…' : 'Запросить бронь'}</button>
      </form>}
    <div className="driver-info">
      <div className="profile-avatar"><CarFront size={22} /></div>
      <div><strong>{trip.driver.name}</strong><p>{trip.driver.vehicle.make} {trip.driver.vehicle.model} · {trip.driver.vehicle.color}</p></div>
      {trip.driver.rating !== null && <span className="rating"><Star size={16} fill="currentColor" />
        {trip.driver.rating.toLocaleString('ru-RU', { minimumFractionDigits: 1 })}</span>}
    </div>
  </aside>;
}

function Participants({ tripId, token }) {
  const people = useResource(`/trips/${tripId}/participants`, { token, interval: 12000 });
  if (!people.data?.data.passengers.length) return null;
  return <section className="panel section-panel">
    <h2 className="with-icon"><Users size={21} /> Участники поездки</h2>
    <ul className="participants">{people.data.data.passengers.map(({ id, name, seats }) =>
      <li key={id}><span>{name}</span><span className="muted">Мест: {seats}</span></li>)}</ul>
  </section>;
}

function TripDetail({ trip, refreshTrip }) {
  const { hash } = useLocation();
  const { session } = useSession();
  const [revision, setRevision] = useState(0);
  const isDriver = session?.user.id === trip.driver.id;
  const booking = useResource(session && !isDriver ? `/bookings?trip_id=${trip.id}` : null, {
    token: session?.token, interval: 10000,
  });
  const active = booking.data?.data.find(({ status }) => status === 'REQUESTED' || status === 'CONFIRMED');
  const canChat = isDriver || active?.status === 'CONFIRMED';

  function update() {
    booking.refresh();
    refreshTrip();
    setRevision((value) => value + 1);
  }

  useEffect(() => {
    if (hash === '#decisions' && isDriver) {
      document.getElementById('decisions')?.scrollIntoView({ block: 'start' });
    }
  }, [hash, isDriver]);

  return <>
    <header className="page-heading trip-heading">
      <p className="eyebrow"><MapPin size={16} /> ПОЕЗДКА ПО УРАЛУ</p>
      <h1><RouteNames trip={trip} /></h1>
      <div className="heading-meta">
        <span><CalendarDays size={19} /> {tripDate(trip.departure_at)}</span>
        <span><Clock3 size={19} /> {tripTime(trip.departure_at)} (ЕКБ)</span>
        <span><Luggage size={19} /> {trip.luggage_allowed ? 'Багаж разрешён' : 'Поездка без багажа'}</span>
      </div>
    </header>
    <div className="detail-grid">
      <div className="stack detail-main">
        <section className="panel section-panel route-panel">
          <h2 className="with-icon"><MapPin size={22} /> Маршрут</h2>
          <Suspense fallback={<Loading label="Загружаем карту…" />}><RouteMap route={trip.route} /></Suspense>
          <ol className="route-timeline">
            {trip.route.map((point, index) => <li key={point.position}>
              <span className="point-number">{index + 1}</span><strong>{point.name}</strong>
              <span className="muted">{index === 0 ? 'Отправление' : index === trip.route.length - 1 ? 'Прибытие' : 'Остановка'}</span>
            </li>)}
          </ol>
        </section>
        {isDriver && <DriverBookings tripId={trip.id} token={session.token} onDecision={update} />}
        {canChat && <><Participants key={revision} tripId={trip.id} token={session.token} />
          <Chat tripId={trip.id} session={session} /></>}
        {active?.status === 'REQUESTED' && <section className="panel section-panel">
          <h2>Чат поездки</h2><p className="muted">Чат откроется после подтверждения брони водителем.</p>
        </section>}
      </div>
      <BookingPanel key={session?.token || 'guest'} trip={trip} session={session} active={active}
        lookup={booking} onUpdate={update} driver={isDriver} />
    </div>
  </>;
}

export default function TripPage() {
  const { id } = useParams();
  const trip = useResource(`/trips/${encodeURIComponent(id)}`, { interval: 12000 });
  return <main className="container content-page" id="main">
    <Link className="back-link" to="/"><ArrowLeft size={17} /> К поиску</Link>
    <Notice error={trip.error} retry={trip.refresh} />
    {trip.loading && <Loading label="Загружаем поездку…" />}
    {trip.data && <TripDetail key={id} trip={trip.data.data} refreshTrip={trip.refresh} />}
  </main>;
}
