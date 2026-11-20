import { useEffect, useState } from 'react';
import { CarFront, CircleUserRound, Compass, LogIn, LogOut, Menu, Plus, Route as RouteIcon, Ticket, X } from 'lucide-react';
import { Link, NavLink, Navigate, Route, Routes, useLocation, useNavigate } from 'react-router-dom';
import { Notice } from './components/Ui.jsx';
import { AuthPage, ProfilePage } from './pages/AccountPage.jsx';
import BookingsPage from './pages/BookingsPage.jsx';
import DriverPage from './pages/DriverPage.jsx';
import SearchPage from './pages/SearchPage.jsx';
import TripPage from './pages/TripPage.jsx';
import { useSession } from './services/session.jsx';

function Brand() {
  return <Link className="brand" to="/" aria-label="Попутно, на главную">
    <span className="brand-icon"><RouteIcon size={22} strokeWidth={2.5} /></span><span>попутно<span className="brand-period">.</span></span>
  </Link>;
}

function Header() {
  const { session, signOut } = useSession();
  const { pathname } = useLocation();
  const navigate = useNavigate();
  const [open, setOpen] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState(null);

  useEffect(() => { setOpen(false); }, [pathname]);

  async function logout() {
    setBusy(true); setError(null);
    try {
      await signOut();
      navigate('/');
    } catch (failure) {
      setError(failure);
    } finally {
      setBusy(false);
    }
  }

  return <header className="site-header">
    <div className="container header-inner">
      <Brand />
      <button type="button" className="icon-button mobile-toggle" onClick={() => setOpen(!open)} aria-expanded={open}
        aria-controls="main-navigation" aria-label={open ? 'Закрыть меню' : 'Открыть меню'}>{open ? <X /> : <Menu />}</button>
      <div className={`nav-shell${open ? ' nav-open' : ''}`} id="main-navigation">
        <nav className="main-nav" aria-label="Основное меню">
          <NavLink to="/" end><Compass size={17} /> Поиск поездок</NavLink>
          {session && <><NavLink to="/bookings"><Ticket size={17} /> Мои брони</NavLink>
            <NavLink to="/driver"><CarFront size={17} /> Мои поездки</NavLink></>}
        </nav>
        <div className="header-actions">
          {!session ? <Link to="/login" className="button button-quiet"><LogIn size={17} /> Войти</Link>
            : <><Link to="/profile" className="button button-quiet profile-link"><CircleUserRound size={19} />
                <span>{session.user.name.split(',')[0]}</span></Link>
              <button type="button" className="icon-button sign-out" onClick={logout} disabled={busy} aria-label="Выйти">
                <LogOut size={19} /></button></>}
          <Link className="button button-primary" to="/driver?create=1"><Plus size={19} /> Предложить поездку</Link>
        </div>
      </div>
    </div>
    {error && <div className="container"><Notice error={error} /></div>}
  </header>;
}

function Private({ children }) {
  const { session } = useSession();
  const location = useLocation();
  return session ? children : <Navigate to="/login" state={{ from: location.pathname + location.search + location.hash }} replace />;
}

function PageScroll() {
  const { pathname, hash } = useLocation();
  useEffect(() => { if (!hash) window.scrollTo(0, 0); }, [pathname, hash]);
  return null;
}

export default function App() {
  return <>
    <PageScroll />
    <Header />
    <Routes>
      <Route path="/" element={<SearchPage />} />
      <Route path="/trips/:id" element={<TripPage />} />
      <Route path="/login" element={<AuthPage key="login" />} />
      <Route path="/register" element={<AuthPage key="register" register />} />
      <Route path="/bookings" element={<Private><BookingsPage /></Private>} />
      <Route path="/driver" element={<Private><DriverPage /></Private>} />
      <Route path="/profile" element={<Private><ProfilePage /></Private>} />
      <Route path="*" element={<main className="container content-page" id="main">
        <section className="panel section-panel"><h1>Страница не найдена</h1><Link className="button button-primary" to="/">К поиску</Link></section>
      </main>} />
    </Routes>
    <footer className="site-footer">
      <div className="container footer-inner"><Brand /><span>Совместные автопоездки по Уралу</span></div>
    </footer>
  </>;
}
