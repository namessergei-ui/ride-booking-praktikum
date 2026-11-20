import { createRoot } from 'react-dom/client';
import { HashRouter } from 'react-router-dom';
import App from './App.jsx';
import { SessionProvider } from './services/session.jsx';
import './styles.css';

createRoot(document.getElementById('root')).render(
  <HashRouter><SessionProvider><App /></SessionProvider></HashRouter>,
);
