import { useState } from 'react';
import { CarFront, Plus, X } from 'lucide-react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import CityField from '../components/CityField.jsx';
import { Empty, Loading, Notice, Pagination, TripCard } from '../components/Ui.jsx';
import { api } from '../services/api.js';
import { dateInputValue } from '../services/format.js';
import { useSession } from '../services/session.jsx';
import { useResource } from '../services/useResource.js';

function PublishTrip({ cities, profile, token, onCancel }) {
  const navigate = useNavigate();
  const [from, setFrom] = useState('');
  const [to, setTo] = useState('');
  const [stops, setStops] = useState([]);
  const [error, setError] = useState(null);
  const [busy, setBusy] = useState(false);

  async function submit(event) {
    event.preventDefault();
    const values = new FormData(event.currentTarget);
    const departure = values.get('departure');
    const date = /\dT\d{2}:\d{2}$/.test(departure) ? `${departure}:00+05:00` : `${departure}+05:00`;
    setBusy(true); setError(null);
    try {
      const { data } = await api('/trips', { method: 'POST', token, body: {
        from, to, stops: stops.map((stop) => stop.city),
        seats: Number(values.get('seats')),
        price_kopecks: Math.round(Number(values.get('price')) * 100),
        departure_at: date, luggage_allowed: values.has('luggage'),
      } });
      navigate(`/trips/${data.id}`);
    } catch (failure) {
      setError(failure);
    } finally {
      setBusy(false);
    }
  }

  return <section className="panel section-panel publish-panel">
    <h2>Новая поездка</h2>
    <form className="stack" onSubmit={submit}>
      <div className="form-columns">
        <CityField label="Откуда" cities={cities} value={from} onChange={setFrom} required />
        <CityField label="Куда" cities={cities} value={to} onChange={setTo} required />
      </div>
      {stops.map((stop, index) => <div className="stop-field" key={stop.key}>
        <CityField label={`Остановка ${index + 1}`} cities={cities} value={stop.city} required
          onChange={(city) => setStops((current) => current.map((item) => item.key === stop.key ? { ...item, city } : item))} />
        <button type="button" className="icon-button" aria-label={`Удалить остановку ${index + 1}`}
          onClick={() => setStops((current) => current.filter((item) => item.key !== stop.key))}><X size={20} /></button>
      </div>)}
      {stops.length < 10 && <button className="button button-outline fit" type="button"
        onClick={() => setStops((current) => [...current, { key: crypto.randomUUID(), city: '' }])}>
        <Plus size={18} /> Добавить остановку
      </button>}
      <div className="form-columns">
        <label className="field">Отправление (ЕКБ)<input name="departure" type="datetime-local"
          min={`${dateInputValue(new Date())}T00:00`} required /></label>
        <label className="field">Цена за место, ₽<input name="price" type="number" min="0" max="1000000" step="0.01" required /></label>
        <label className="field">Свободных мест<input name="seats" type="number" min="1"
          max={profile.vehicle.passenger_seats} defaultValue={profile.vehicle.passenger_seats} required /></label>
      </div>
      <label className="check"><input name="luggage" type="checkbox" defaultChecked /> Разрешить багаж</label>
      <Notice error={error} />
      <div className="button-row">
        <button className="button button-primary" type="submit" disabled={busy}>{busy ? 'Публикуем…' : 'Опубликовать'}</button>
        <button className="button button-outline" type="button" onClick={onCancel}>Отмена</button>
      </div>
    </form>
  </section>;
}

export default function DriverPage() {
  const { session } = useSession();
  const { token } = session;
  const [params, setParams] = useSearchParams();
  const [page, setPage] = useState(1);
  const creating = params.get('create') === '1';
  const profile = useResource('/me', { token });
  const trips = useResource(`/me/trips?page=${page}`, { token });
  const cities = useResource(creating && profile.data?.data.driver ? '/cities' : null);

  return <main className="container content-page" id="main">
    <header className="page-heading section-head">
      <h1>Мои поездки</h1>
      {profile.data?.data.driver && !creating && <button className="button button-primary" type="button"
        onClick={() => setParams({ create: '1' })}><Plus size={20} /> Новая поездка</button>}
    </header>
    <Notice error={profile.error} retry={profile.refresh} />
    {profile.loading && <Loading />}
    {profile.data && !profile.data.data.driver && <Empty title="Заполните профиль водителя" icon={CarFront}>
      <Link className="button button-outline" to="/profile">Добавить автомобиль</Link>
    </Empty>}
    {creating && profile.data?.data.driver && <>
      <Notice error={cities.error} retry={cities.refresh} />
      {cities.loading && <Loading label="Загружаем справочник городов…" />}
      {cities.data && <PublishTrip cities={cities.data.data} token={token} profile={profile.data.data.driver}
        onCancel={() => setParams({})} />}
    </>}
    {profile.data?.data.driver && <section className="stack">
      <Notice error={trips.error} retry={trips.refresh} />
      {trips.loading && <Loading />}
      {trips.data?.data.length === 0 && !creating && <Empty title="У вас пока нет поездок" icon={CarFront}>
        <span>Опубликуйте маршрут и предложите свободные места.</span>
      </Empty>}
      {trips.data?.data.map((trip) => <TripCard key={trip.id} trip={trip} manage />)}
      <Pagination meta={trips.data?.meta} onChange={setPage} />
    </section>}
  </main>;
}
