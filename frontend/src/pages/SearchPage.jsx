import { useState } from 'react';
import { ArrowLeftRight, CalendarDays, CarFront, Search, SlidersHorizontal, Users } from 'lucide-react';
import { useSearchParams } from 'react-router-dom';
import CityField from '../components/CityField.jsx';
import { Empty, Loading, Notice, Pagination, TripCard } from '../components/Ui.jsx';
import { queryPath } from '../services/api.js';
import { dateInputValue, localTimezone } from '../services/format.js';
import { useResource } from '../services/useResource.js';

function SearchForm({ cities, params, onSearch }) {
  const [from, setFrom] = useState(params.get('from') || '');
  const [to, setTo] = useState(params.get('to') || '');
  const [date, setDate] = useState(params.get('date') || '');
  const [seats, setSeats] = useState(params.get('seats') || '1');

  return <form className="search-form panel" onSubmit={(event) => {
    event.preventDefault();
    onSearch({ from, to, date, seats });
  }}>
    <CityField label="Откуда" placeholder="Город отправления" cities={cities} value={from} onChange={setFrom} />
    <button className="icon-button swap" type="button" aria-label="Поменять города местами"
      onClick={() => { setFrom(to); setTo(from); }}><ArrowLeftRight size={18} /></button>
    <CityField label="Куда" placeholder="Город прибытия" cities={cities} value={to} onChange={setTo} />
    <label className="field">Дата
      <span className="input-wrap"><CalendarDays size={17} aria-hidden="true" />
        <input type="date" value={date} min={dateInputValue(new Date())} onChange={(event) => setDate(event.target.value)} />
      </span>
    </label>
    <label className="field">Пассажиры
      <span className="input-wrap"><Users size={17} aria-hidden="true" />
        <select value={seats} onChange={(event) => setSeats(event.target.value)}>
          {Array.from({ length: 8 }, (_, i) => <option key={i} value={i + 1}>{i + 1}</option>)}
        </select>
      </span>
    </label>
    <button className="button button-primary search-button" type="submit"><Search size={19} /> Найти</button>
  </form>;
}

function Filters({ params, onFilter }) {
  const [price, setPrice] = useState(params.has('max_price_kopecks') ? params.get('max_price_kopecks') / 100 : '');
  const [rating, setRating] = useState(params.get('min_rating') || '');
  const [luggage, setLuggage] = useState(params.get('luggage_allowed') || '');
  function apply(values) { onFilter(values); }

  return <form className="filters panel" onSubmit={(event) => {
    event.preventDefault();
    apply({ max_price_kopecks: price === '' ? '' : Math.round(Number(price) * 100), min_rating: rating, luggage_allowed: luggage });
  }}>
    <h3><SlidersHorizontal size={20} /> Фильтры</h3>
    <label className="field">Цена до, ₽
      <input type="number" min="0" max="1000000" step="0.01" placeholder="Любая" value={price}
        onChange={(event) => setPrice(event.target.value)} />
    </label>
    <label className="field">Рейтинг водителя
      <select value={rating} onChange={(event) => setRating(event.target.value)}>
        <option value="">Любой</option><option value="4">От 4,0</option><option value="4.5">От 4,5</option><option value="5">Только 5,0</option>
      </select>
    </label>
    <label className="field">Багаж
      <select value={luggage} onChange={(event) => setLuggage(event.target.value)}>
        <option value="">Не важно</option><option value="1">Разрешён</option><option value="0">Без багажа</option>
      </select>
    </label>
    <div className="filter-actions">
      <button className="button button-outline" type="submit">Применить</button>
      <button className="text-button" type="button" onClick={() => {
        setPrice(''); setRating(''); setLuggage('');
        apply({ max_price_kopecks: '', min_rating: '', luggage_allowed: '' });
      }}>Сбросить</button>
    </div>
  </form>;
}

export default function SearchPage() {
  const [params, setParams] = useSearchParams();
  const cities = useResource('/cities');
  const results = useResource(queryPath('/trips', { ...Object.fromEntries(params), seats: params.get('seats') || '1', timezone: localTimezone }));
  const routeKey = ['from', 'to', 'date', 'seats'].map((key) => params.get(key)).join(':');
  const filterKey = ['max_price_kopecks', 'min_rating', 'luggage_allowed'].map((key) => params.get(key)).join(':');

  function update(values) {
    const next = new URLSearchParams(params);
    next.delete('page');
    for (const [key, value] of Object.entries(values)) {
      if (value === '') next.delete(key);
      else next.set(key, value);
    }
    setParams(next);
  }

  return <main className="home container" id="main">
    <section className="hero">
      <div className="hero-copy">
        <span className="hero-region"><span /> ПОЕЗДКИ ПО УРАЛУ</span>
        <h1>Из города<br />в город попутно</h1>
        <p>Найдите попутку по своему маршруту или предложите свободные места в машине.</p>
      </div>
    </section>
    <div className="search-block">
      <SearchForm key={routeKey} cities={cities.data?.data || []} params={params} onSearch={update} />
      <Notice error={cities.error} retry={cities.refresh} />
    </div>
    <section className="results" aria-labelledby="results-title">
      <div className="section-head">
        <div><h2 id="results-title">Ближайшие поездки</h2></div>
        {results.data && <span className="results-count">Найдено: {results.data.meta.total}</span>}
      </div>
      <div className="results-grid">
        <Filters key={filterKey} params={params} onFilter={update} />
        <div className="stack">
          <Notice error={results.error} retry={results.refresh} />
          {results.loading && <Loading label="Ищем поездки…" />}
          {results.data?.data.length === 0 && <Empty title="Поездки не найдены" icon={CarFront}>
            <span>Попробуйте другую дату или измените фильтры.</span>
          </Empty>}
          {results.data?.data.map((trip) => <TripCard key={trip.id} trip={trip} />)}
          <Pagination meta={results.data?.meta} onChange={(page) => {
            const next = new URLSearchParams(params); next.set('page', page); setParams(next);
          }} />
        </div>
      </div>
    </section>
  </main>;
}
