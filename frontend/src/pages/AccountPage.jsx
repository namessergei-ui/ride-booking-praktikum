import { useState } from 'react';
import { CarFront, CircleUserRound, LogIn } from 'lucide-react';
import { Link, Navigate, useLocation, useNavigate } from 'react-router-dom';
import { Loading, Notice } from '../components/Ui.jsx';
import { api } from '../services/api.js';
import { useSession } from '../services/session.jsx';
import { useResource } from '../services/useResource.js';

export function AuthPage({ register = false }) {
  const { session, signIn } = useSession();
  const location = useLocation();
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState(null);
  const from = location.state?.from;
  const destination = typeof from === 'string' && from.startsWith('/') && !from.startsWith('//') ? from : '/';
  if (session) return <Navigate to={destination} replace />;

  async function submit(event) {
    event.preventDefault();
    const values = Object.fromEntries(new FormData(event.currentTarget));
    setBusy(true);
    setError(null);
    try {
      await signIn(register ? 'register' : 'login', values);
    } catch (failure) {
      setError(failure);
    } finally {
      setBusy(false);
    }
  }

  return <main className="container content-page account-page" id="main">
    <section className="panel section-panel auth-panel">
      <div className="profile-avatar"><LogIn size={24} /></div>
      <h1>{register ? 'Создать аккаунт' : 'Войти в аккаунт'}</h1>
      <Notice error={error} />
      <form className="stack" onSubmit={submit}>
        {register && <label className="field">Имя<input name="name" autoComplete="name" maxLength={100} required /></label>}
        <label className="field">Электронная почта<input name="email" type="email" autoComplete="email" maxLength={254} required /></label>
        <label className="field">Пароль
          <input name="password" type="password" autoComplete={register ? 'new-password' : 'current-password'}
            minLength={register ? 8 : undefined} maxLength={72} required />
        </label>
        <button type="submit" className="button button-primary" disabled={busy}>
          {busy ? 'Подождите…' : register ? 'Зарегистрироваться' : 'Войти'}
        </button>
      </form>
      <p className="muted">{register ? 'Уже есть аккаунт? ' : 'Впервые здесь? '}
        <Link className="inline-link" to={register ? '/login' : '/register'} state={location.state}>
          {register ? 'Войти' : 'Зарегистрироваться'}
        </Link>
      </p>
    </section>
  </main>;
}

function ProfileForm({ profile, token, onSaved }) {
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState(null);
  const [saved, setSaved] = useState(false);

  async function submit(event) {
    event.preventDefault();
    setBusy(true); setError(null); setSaved(false);
    try {
      const { data } = await api('/me', {
        method: 'PATCH', token, body: Object.fromEntries(new FormData(event.currentTarget)),
      });
      onSaved(data);
      setSaved(true);
    } catch (failure) {
      setError(failure);
    } finally {
      setBusy(false);
    }
  }

  return <section className="section-panel panel">
    <h2 className="with-icon"><CircleUserRound size={22} /> Личные данные</h2>
    <form className="stack" onSubmit={submit}>
      <label className="field">Имя<input name="name" defaultValue={profile.name} maxLength={100} required /></label>
      <label className="field">Электронная почта<input value={profile.email} type="email" readOnly /></label>
      <Notice error={error} />
      {saved && <p className="success" role="status">Профиль сохранён.</p>}
      <button className="button button-primary fit" disabled={busy} type="submit">Сохранить профиль</button>
    </form>
  </section>;
}

function DriverProfileForm({ driver, token, onSaved }) {
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState(null);
  const [saved, setSaved] = useState(false);
  const navigate = useNavigate();
  const vehicle = driver?.vehicle;

  async function submit(event) {
    event.preventDefault();
    const values = Object.fromEntries(new FormData(event.currentTarget));
    const { experience_years, passenger_seats, ...car } = values;
    setBusy(true); setError(null); setSaved(false);
    try {
      const { data } = await api('/me/driver', { token, method: 'PUT', body: {
        experience_years: Number(experience_years), vehicle: { ...car, passenger_seats: Number(passenger_seats) },
      } });
      onSaved(data);
      setSaved(true);
    } catch (failure) {
      setError(failure);
    } finally {
      setBusy(false);
    }
  }

  return <section className="section-panel panel">
    <h2 className="with-icon"><CarFront size={22} /> Профиль водителя</h2>
    <form className="stack" onSubmit={submit}>
      <div className="form-columns">
        <label className="field">Марка<input name="make" defaultValue={vehicle?.make} maxLength={80} required /></label>
        <label className="field">Модель<input name="model" defaultValue={vehicle?.model} maxLength={80} required /></label>
        <label className="field">Цвет<input name="color" defaultValue={vehicle?.color} maxLength={40} required /></label>
        <label className="field">Мест для пассажиров<input name="passenger_seats" type="number" min="1" max="8"
          defaultValue={vehicle?.passenger_seats || 3} required /></label>
      </div>
      <label className="field">Стаж, лет<input name="experience_years" type="number" min="0" max="80"
        defaultValue={driver?.experience_years ?? ''} required /></label>
      <Notice error={error} />
      {saved && <p className="success" role="status">Данные водителя сохранены.</p>}
      <div className="button-row">
        <button className="button button-primary" disabled={busy} type="submit">Сохранить автомобиль</button>
        {driver && <button className="button button-outline" type="button" onClick={() => navigate('/driver?create=1')}>
          Опубликовать поездку
        </button>}
      </div>
    </form>
  </section>;
}

export function ProfilePage() {
  const { session, updateUser } = useSession();
  const profile = useResource('/me', { token: session.token });
  function onSaved(user) { updateUser(user); profile.refresh(); }
  return <main className="container content-page" id="main">
    <header className="page-heading"><h1>Мой профиль</h1></header>
    <Notice error={profile.error} retry={profile.refresh} />
    {profile.loading && <Loading />}
    {profile.data && <div className="account-grid">
      <ProfileForm profile={profile.data.data} token={session.token} onSaved={onSaved} />
      <DriverProfileForm driver={profile.data.data.driver} token={session.token} onSaved={onSaved} />
    </div>}
  </main>;
}
