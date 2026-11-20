import { createContext, useContext, useEffect, useState } from 'react';
import { api } from './api.js';

const SessionContext = createContext(null);
const key = 'ride-booking-session';

function readSession() {
  try {
    const session = JSON.parse(sessionStorage.getItem(key));
    return session?.token && session?.user?.id ? session : null;
  } catch {
    return null;
  }
}

export function SessionProvider({ children }) {
  const [session, setSession] = useState(readSession);

  useEffect(() => {
    try {
      if (session) sessionStorage.setItem(key, JSON.stringify(session));
      else sessionStorage.removeItem(key);
    } catch {
      // The current session remains usable in memory when storage is disabled.
    }
  }, [session]);

  useEffect(() => {
    const expire = (event) => setSession((current) => current?.token === event.detail ? null : current);
    window.addEventListener('ride:unauthorized', expire);
    return () => window.removeEventListener('ride:unauthorized', expire);
  }, []);

  async function signIn(mode, values) {
    const { data } = await api(`/auth/${mode}`, { method: 'POST', body: values });
    setSession(data);
  }

  async function signOut() {
    await api('/auth/logout', { method: 'POST', token: session.token }).catch((error) => {
      if (error.status !== 401) throw error;
    });
    setSession(null);
  }

  function updateUser(user) {
    setSession((current) => current ? { ...current, user: { ...current.user, ...user } } : null);
  }

  return <SessionContext.Provider value={{ session, signIn, signOut, updateUser }}>
    {children}
  </SessionContext.Provider>;
}

export function useSession() {
  return useContext(SessionContext);
}
