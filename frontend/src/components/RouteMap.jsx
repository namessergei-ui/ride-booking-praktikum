import { useEffect, useRef } from 'react';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

export default function RouteMap({ route }) {
  const element = useRef(null);
  const geometry = JSON.stringify(route);

  useEffect(() => {
    const map = L.map(element.current, { scrollWheelZoom: false, attributionControl: true });
    const stops = JSON.parse(geometry);
    const points = stops.map(({ lat, lon }) => [lat, lon]);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 18,
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    }).addTo(map);
    const segment = L.polyline(points, { color: '#176043', weight: 4, dashArray: '7 7' }).addTo(map);
    stops.forEach((stop, index) => {
      const name = document.createElement('span');
      name.textContent = stop.name;
      const icon = L.divIcon({
        className: 'map-pin',
        html: String(index + 1),
        iconSize: [26, 26],
        iconAnchor: [13, 13],
      });
      L.marker(points[index], { icon, title: stop.name, alt: stop.name }).addTo(map)
        .bindTooltip(name, { direction: 'top', offset: [0, -13] });
    });
    map.fitBounds(segment.getBounds(), { padding: [47, 35], maxZoom: 12 });
    const observer = new ResizeObserver(() => map.invalidateSize());
    observer.observe(element.current);

    return () => { observer.disconnect(); map.remove(); };
  }, [geometry]);

  return <div className="map" ref={element} role="region" aria-label="Карта маршрута" />;
}
