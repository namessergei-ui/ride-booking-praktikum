import { useEffect, useState } from 'react';
import { api } from './api.js';

export function useResource(path, { token, interval = 0 } = {}) {
  const [revision, setRevision] = useState(0);
  const [result, setResult] = useState(null);

  useEffect(() => {
    if (!path) return undefined;

    const controller = new AbortController();
    let inFlight = false;
    async function load() {
      if (inFlight || controller.signal.aborted) return;
      inFlight = true;
      try {
        const data = await api(path, { token, signal: controller.signal });
        if (!controller.signal.aborted) setResult({ path, token, data, error: null });
      } catch (error) {
        if (!controller.signal.aborted) {
          setResult((current) => ({
            path, token, error,
            data: current?.path === path && current?.token === token ? current.data : null,
          }));
        }
      } finally {
        inFlight = false;
      }
    }
    load();
    const timer = interval ? setInterval(() => {
      if (!document.hidden) load();
    }, interval) : null;

    return () => {
      controller.abort();
      if (timer) clearInterval(timer);
    };
  }, [path, token, interval, revision]);

  const current = result?.path === path && result?.token === token ? result : null;
  return {
    data: current?.data ?? null,
    error: current?.error ?? null,
    loading: Boolean(path && !current),
    refresh: () => setRevision((value) => value + 1),
  };
}
