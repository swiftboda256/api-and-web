<script>
    window.tripMap = window.tripMap || function (data, config) {
        const loadGoogleMaps = () => {
            if (window.google?.maps?.importLibrary) {
                return Promise.resolve();
            }

            window.__googleMapsLoading = window.__googleMapsLoading || new Promise((resolve, reject) => {
                window.__onGoogleMapsLoaded = resolve;

                const params = new URLSearchParams({
                    key: config.apiKey,
                    v: 'weekly',
                    loading: 'async',
                    callback: '__onGoogleMapsLoaded',
                });

                const script = document.createElement('script');
                script.src = `https://maps.googleapis.com/maps/api/js?${params}`;
                script.async = true;
                script.onerror = reject;
                document.head.appendChild(script);
            });

            return window.__googleMapsLoading;
        };

        const dot = (color, size, text = '') => {
            const el = document.createElement('div');
            el.style.cssText = `width:${size}px;height:${size}px;border-radius:9999px;background:${color};border:2px solid #fff;box-shadow:0 1px 3px rgba(0,0,0,.4);transform:translateY(50%);display:flex;align-items:center;justify-content:center;color:#fff;font:600 11px/1 sans-serif;`;
            el.textContent = text;

            return el;
        };

        // Pickup/destination pins come pre-rendered from map-pin.blade.php (our own markup); the SVG's
        // bottom tip sits on the coordinate, which is how AdvancedMarkerElement anchors its content.
        const pin = (svg) => {
            const el = document.createElement('div');
            el.innerHTML = svg;

            return el;
        };

        const markerContent = (stop, index) => {
            if (config.pins?.[stop.role]) {
                return pin(config.pins[stop.role]);
            }

            return dot('#8b5cf6', 22, String(index));
        };

        // Keep the Google objects out of Alpine's reactive state: Alpine wraps anything stored on `this`
        // in a Proxy, and AdvancedMarkerElement silently refuses to attach to a proxied Map.
        let map = null;

        return {
            error: null,

            async init() {
                // Google calls this global when the key is invalid, not enabled for Maps JS, or referrer-blocked.
                window.gm_authFailure = () => {
                    this.error = 'Google Maps rejected the API key. Check the key, its HTTP referrer restrictions, and that the Maps JavaScript API is enabled.';
                };

                try {
                    await loadGoogleMaps();
                } catch (e) {
                    this.error = 'Google Maps failed to load.';

                    return;
                }

                const { Map, InfoWindow, Polyline } = await google.maps.importLibrary('maps');
                const { AdvancedMarkerElement } = await google.maps.importLibrary('marker');
                const { ColorScheme, LatLngBounds } = await google.maps.importLibrary('core');

                // Start on the trip itself (first stop, else first GPS point) so the map never opens elsewhere.
                const firstStop = data.stops[0];
                const initialCenter = firstStop
                    ? { lat: firstStop.lat, lng: firstStop.lng }
                    : { lat: data.path[0][0], lng: data.path[0][1] };

                map = new Map(this.$refs.map, {
                    mapId: config.mapId || 'DEMO_MAP_ID',
                    center: initialCenter,
                    zoom: 15,
                    gestureHandling: 'cooperative',
                    streetViewControl: false,
                    mapTypeControl: false,
                    colorScheme: document.documentElement.classList.contains('dark') ? ColorScheme.DARK : ColorScheme.LIGHT,
                });

                const infoWindow = new InfoWindow();
                const bounds = new LatLngBounds();
                const path = data.path.map(([lat, lng]) => ({ lat, lng }));

                if (path.length > 1) {
                    new Polyline({
                        map: map,
                        path,
                        strokeColor: '#f59e0b',
                        strokeOpacity: 0.9,
                        strokeWeight: 4,
                    });
                }

                path.forEach((point) => bounds.extend(point));

                data.stops.forEach((stop, index) => {
                    const position = { lat: stop.lat, lng: stop.lng };
                    const heading = `${stop.label} · ${stop.name}`;
                    const marker = new AdvancedMarkerElement({
                        map: map,
                        position,
                        title: stop.address ? `${heading}\n${stop.address}` : heading,
                        content: markerContent(stop, index),
                        gmpClickable: true,
                        zIndex: 500 + index,
                    });

                    marker.addListener('click', () => {
                        const content = document.createElement('div');
                        const title = document.createElement('strong');
                        title.textContent = heading;
                        content.appendChild(title);
                        [stop.address, stop.time ?? 'Not reached yet'].filter(Boolean).forEach((line) => {
                            content.appendChild(document.createElement('br'));
                            content.appendChild(document.createTextNode(line));
                        });

                        infoWindow.setContent(content);
                        infoWindow.open({ map: map, anchor: marker });
                    });

                    bounds.extend(position);
                });

                if (data.active && path.length > 0) {
                    new AdvancedMarkerElement({
                        map: map,
                        position: path[path.length - 1],
                        title: 'Rider last position',
                        content: dot('#0ea5e9', 14),
                        zIndex: 1000,
                    });
                }

                if (! bounds.isEmpty()) {
                    map.fitBounds(bounds, 40);

                    // fitBounds on a single point (or very close points) zooms all the way in.
                    google.maps.event.addListenerOnce(map, 'idle', () => {
                        if (map.getZoom() > 17) {
                            map.setZoom(17);
                        }
                    });
                }
            },
        };
    };
</script>
