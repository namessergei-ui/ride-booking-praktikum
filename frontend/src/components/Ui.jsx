import { AlertCircle, ArrowLeft, ArrowRight, CalendarDays, CarFront, CircleHelp, Luggage, MapPin, Users } from 'lucide-react';
import { Link } from 'react-router-dom';
import { bookingLabels, money, tripDate, tripTime } from '../services/format.js';

export function Notice({ error, retry }) {
  if (!error) return null;
  return <div className="notice" role="alert">
    <AlertCircle size={19} /><span>{error.message}</span>
    {retry && <button className="text-button" type="button" onClick={retry}>Повторить</button>}
  </div>;
}

export function Loading({ label = 'Загружаем данные…' }) {
  return <div className="empty" role="status"><span className="spinner" />{label}</div>;
}

export function Empty({ title, children, icon: Icon = CircleHelp }) {
  return <div className="empty panel"><Icon size={24} /><strong>{title}</strong>{children}</div>;
}

export function Pagination({ meta, onChange }) {
  if (!meta || meta.last_page <= 1) return null;
  return <nav className="pagination" aria-label="Страницы результатов">
    <button className="icon-button" type="button" aria-label="Предыдущая страница" disabled={meta.current_page <= 1}
      onClick={() => onChange(meta.current_page - 1)}><ArrowLeft size={18} /></button>
    <span>Страница {meta.current_page} из {meta.last_page}</span>
    <button className="icon-button" type="button" aria-label="Следующая страница" disabled={meta.current_page >= meta.last_page}
      onClick={() => onChange(meta.current_page + 1)}><ArrowRight size={18} /></button>
  </nav>;
}

export function Badge({ status }) {
  return <span className={`badge badge-${status.toLowerCase()}`}>{bookingLabels[status] || status}</span>;
}

export function RouteNames({ trip }) {
  return <>{trip.route[0].name}<span className="route-dash"> — </span>{trip.route.at(-1).name}</>;
}

export function TripCard({ trip, manage = false }) {
  const stops = trip.route.slice(1, -1);
  return <article className="trip-card panel">
    <div className="trip-main">
      <p className="trip-date"><CalendarDays size={16} /> {tripDate(trip.departure_at)}, {tripTime(trip.departure_at)}</p>
      <h3><Link to={`/trips/${trip.id}`}><RouteNames trip={trip} /></Link></h3>
      {stops.length > 0 && <p className="trip-stops"><MapPin size={15} /> Через {stops.map((stop) => stop.name).join(', ')}</p>}
      <div className="trip-meta">
        <span><CarFront size={16} /> {trip.driver.vehicle.make} {trip.driver.vehicle.model}</span>
        <span><Users size={16} /> {trip.available_seats} из {trip.total_seats} мест</span>
        {trip.luggage_allowed && <span><Luggage size={16} /> С багажом</span>}
      </div>
    </div>
    <div className="trip-action">
      <div><strong>{money(trip.price_kopecks)}</strong><small>за место</small></div>
      <Link className="button button-outline" to={`/trips/${trip.id}${manage ? '#decisions' : ''}`}>
        {manage ? 'Управлять' : 'Подробнее'}
      </Link>
    </div>
  </article>;
}
