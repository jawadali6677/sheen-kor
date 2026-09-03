(function (window) {
    'use strict';

    var defaultCenter = [36.1911, 44.0092];
    var defaultZoom = 8;

    function nominatimHeaders() {
        return {
            'Accept': 'application/json'
        };
    }

    function reverseGeocode(lat, lng, onDone) {
        fetch('https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=' + encodeURIComponent(lat) + '&lon=' + encodeURIComponent(lng), {
            headers: nominatimHeaders()
        })
            .then(function (response) {
                return response.json();
            })
            .then(function (data) {
                onDone(data.display_name || '');
            })
            .catch(function () {
                onDone('');
            });
    }

    function searchPlace(query, onDone) {
        fetch('https://nominatim.openstreetmap.org/search?format=jsonv2&q=' + encodeURIComponent(query), {
            headers: nominatimHeaders()
        })
            .then(function (response) {
                return response.json();
            })
            .then(function (results) {
                onDone(Array.isArray(results) ? results[0] : null);
            })
            .catch(function () {
                onDone(null);
            });
    }

    window.initLocationMap = function (options) {
        if (!window.L || !options || !options.mapId) {
            return;
        }

        var mapEl = document.getElementById(options.mapId);

        if (!mapEl) {
            return;
        }

        var latInput = options.latSelector ? document.querySelector(options.latSelector) : null;
        var lngInput = options.lngSelector ? document.querySelector(options.lngSelector) : null;
        var nameInput = options.nameSelector ? document.querySelector(options.nameSelector) : null;
        var searchInput = options.searchSelector ? document.querySelector(options.searchSelector) : null;
        var searchButton = options.searchButtonSelector ? document.querySelector(options.searchButtonSelector) : null;
        var locateButton = options.locateSelector ? document.querySelector(options.locateSelector) : null;
        var readonly = Boolean(options.readonly);

        var hasPoint = (latInput && lngInput && latInput.value && lngInput.value)
            || (options.initialLat && options.initialLng);
        var startLat = hasPoint
            ? parseFloat((latInput && latInput.value) || options.initialLat)
            : defaultCenter[0];
        var startLng = hasPoint
            ? parseFloat((lngInput && lngInput.value) || options.initialLng)
            : defaultCenter[1];
        var zoom = hasPoint ? 14 : defaultZoom;

        var map = L.map(options.mapId).setView([startLat, startLng], zoom);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap'
        }).addTo(map);

        var marker = null;

        function setPoint(lat, lng, updateName) {
            if (latInput) {
                latInput.value = Number(lat).toFixed(7);
            }

            if (lngInput) {
                lngInput.value = Number(lng).toFixed(7);
            }

            if (marker) {
                marker.setLatLng([lat, lng]);
            } else {
                marker = L.marker([lat, lng]).addTo(map);
            }

            map.setView([lat, lng], Math.max(map.getZoom(), 14));

            if (updateName && nameInput) {
                reverseGeocode(lat, lng, function (name) {
                    if (name) {
                        nameInput.value = name;
                    }
                });
            }
        }

        if (hasPoint) {
            setPoint(startLat, startLng, false);
        }

        if (!readonly) {
            map.on('click', function (event) {
                setPoint(event.latlng.lat, event.latlng.lng, true);
            });

            if (locateButton) {
                locateButton.addEventListener('click', function () {
                    if (!navigator.geolocation) {
                        return;
                    }

                    locateButton.disabled = true;

                    navigator.geolocation.getCurrentPosition(function (position) {
                        setPoint(position.coords.latitude, position.coords.longitude, true);
                        locateButton.disabled = false;
                    }, function () {
                        locateButton.disabled = false;
                    });
                });
            }

            function runSearch() {
                if (!searchInput || !searchInput.value.trim()) {
                    return;
                }

                searchPlace(searchInput.value.trim(), function (result) {
                    if (!result) {
                        return;
                    }

                    setPoint(result.lat, result.lon, false);

                    if (nameInput) {
                        nameInput.value = result.display_name || searchInput.value.trim();
                    }
                });
            }

            if (searchButton) {
                searchButton.addEventListener('click', function (event) {
                    event.preventDefault();
                    runSearch();
                });
            }

            if (searchInput) {
                searchInput.addEventListener('keydown', function (event) {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        runSearch();
                    }
                });
            }
        }

        setTimeout(function () {
            map.invalidateSize();
        }, 200);
    };

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.js-location-map').forEach(function (el) {
            window.initLocationMap({
                mapId: el.id,
                latSelector: el.getAttribute('data-lat-selector') || null,
                lngSelector: el.getAttribute('data-lng-selector') || null,
                nameSelector: el.getAttribute('data-name-selector') || null,
                searchSelector: el.getAttribute('data-search-selector') || null,
                searchButtonSelector: el.getAttribute('data-search-button-selector') || null,
                locateSelector: el.getAttribute('data-locate-selector') || null,
                initialLat: el.getAttribute('data-initial-lat') || null,
                initialLng: el.getAttribute('data-initial-lng') || null,
                readonly: el.getAttribute('data-readonly') === '1'
            });
        });
    });
})(window);
