import { useEffect, useRef, useState } from 'react';
import { MessageCircle, Send } from 'lucide-react';
import { api } from '../services/api.js';
import { tripTime } from '../services/format.js';
import { useResource } from '../services/useResource.js';
import { Loading, Notice } from './Ui.jsx';

export default function Chat({ tripId, session }) {
  const [body, setBody] = useState('');
  const [sending, setSending] = useState(false);
  const [error, setError] = useState(null);
  const path = `/trips/${tripId}/messages`;
  const { token, user } = session;
  const messages = useResource(path, { token, interval: 5000 });
  const end = useRef(null);
  const latestId = messages.data?.data.at(-1)?.id;

  useEffect(() => {
    const list = end.current?.parentElement;
    if (list) list.scrollTop = list.scrollHeight;
  }, [latestId]);

  async function send(event) {
    event.preventDefault();
    const value = body.trim();
    if (!value || sending) return;
    setSending(true);
    setError(null);
    try {
      await api(path, { token, method: 'POST', body: { body: value } });
      setBody('');
      messages.refresh();
    } catch (failure) {
      setError(failure);
    } finally {
      setSending(false);
    }
  }

  return <section className="panel section-panel" aria-labelledby="chat-title">
    <h2 id="chat-title" className="with-icon"><MessageCircle size={22} /> Чат поездки</h2>
    <Notice error={messages.error} retry={messages.refresh} />
    {messages.loading && <Loading />}
    {messages.data && <>
      <div className="messages" role="log" aria-label="Сообщения">
        {messages.data.data.length === 0 && <p className="muted">Напишите первое сообщение.</p>}
        {messages.data.data.map((message) => <article key={message.id}
          className={`message${message.author.id === user.id ? ' message-own' : ''}`}>
          <header><strong>{message.author.name}</strong><time dateTime={message.created_at}>{tripTime(message.created_at)}</time></header>
          <p>{message.body}</p>
        </article>)}
        <div ref={end} />
      </div>
      <form className="chat-form" onSubmit={send}>
        <label className="sr-only" htmlFor="message">Сообщение</label>
        <textarea id="message" rows="2" maxLength={2000} value={body} onChange={(event) => setBody(event.target.value)}
          placeholder="Написать сообщение" required />
        <button className="button button-primary" type="submit" disabled={sending || !body.trim()}>
          <Send size={17} /> Отправить
        </button>
      </form>
      <Notice error={error} />
    </>}
  </section>;
}
