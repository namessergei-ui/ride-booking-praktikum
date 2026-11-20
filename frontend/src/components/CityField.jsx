import { useEffect, useId, useRef, useState } from 'react';
import { MapPin } from 'lucide-react';

export default function CityField({ label, cities, value, onChange, placeholder, required = false }) {
  const id = useId();
  const input = useRef(null);
  const [query, setQuery] = useState('');
  const [open, setOpen] = useState(false);
  const [active, setActive] = useState(-1);
  const selected = cities.find((city) => city.id === value);
  useEffect(() => {
    if (selected) input.current?.setCustomValidity('');
  }, [selected]);

  const match = query.trim().toLocaleLowerCase('ru');
  const options = cities.filter((city) => !match || city.name.toLocaleLowerCase('ru').includes(match)
    || city.id.includes(match)).slice(0, 10);

  function select(city) {
    setQuery('');
    input.current.setCustomValidity('');
    onChange(city.id);
    setOpen(false);
    setActive(-1);
  }

  function handleKey(event) {
    if ((event.key === 'ArrowDown' || event.key === 'ArrowUp') && options.length) {
      event.preventDefault();
      setOpen(true);
      const offset = event.key === 'ArrowDown' ? 1 : -1;
      setActive((index) => index < 0 ? (offset > 0 ? 0 : options.length - 1)
        : (index + offset + options.length) % options.length);
    } else if (event.key === 'Enter' && open && options[active]) {
      event.preventDefault();
      select(options[active]);
    } else if (event.key === 'Escape' && open) {
      event.preventDefault();
      setOpen(false);
    }
  }

  return <div className="field city-field">
    <label htmlFor={id}>{label}</label>
    <div className="input-wrap">
      <MapPin size={17} aria-hidden="true" />
      <input id={id} ref={input} role="combobox" aria-expanded={open} aria-controls={`${id}-options`}
        aria-autocomplete="list" aria-activedescendant={open && options[active] ? `${id}-${active}` : undefined}
        value={selected?.name ?? query} placeholder={placeholder} required={required} autoComplete="off"
        onFocus={() => { setOpen(true); setActive(-1); }} onBlur={() => setOpen(false)}
        onKeyDown={handleKey} onChange={(event) => {
          const next = event.currentTarget.value;
          onChange('');
          setQuery(next);
          setActive(-1);
          setOpen(true);
          event.currentTarget.setCustomValidity(next ? 'Выберите город из списка.' : '');
        }} />
    </div>
    {open && <ul className="city-options" role="listbox" id={`${id}-options`} aria-label={label}>
      {options.map((city, index) => <li key={city.id} id={`${id}-${index}`} role="option"
        aria-selected={index === active} onMouseDown={(event) => event.preventDefault()}
        onClick={() => select(city)}>{city.name}</li>)}
      {!options.length && <li className="muted" role="presentation">Город не найден</li>}
    </ul>}
  </div>;
}
