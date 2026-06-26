const defaultCity = document.body.dataset.defaultCity ?? '';
const form = document.getElementById('weather-form');
const cityInput = document.getElementById('city');
const submitButton = document.getElementById('submit');
const submitLabel = document.getElementById('submit-label');
const submitStatus = document.getElementById('submit-status');
const statusEl = document.getElementById('status');
const errorEl = document.getElementById('error');
const placeholderEl = document.getElementById('placeholder');
const resultEl = document.getElementById('result');
const weatherPanelEl = document.getElementById('weather-panel');
const chips = Array.from(document.querySelectorAll('[data-city]'));
const weatherIcon = document.getElementById('weather-icon');
const temperatureEl = document.getElementById('temperature');
const resultCityEl = document.getElementById('result-city');
const descriptionEl = document.getElementById('description');
const localTimeEl = document.getElementById('local-time');
const statFeelsLike = document.getElementById('stat-feels-like');
const statHumidity = document.getElementById('stat-humidity');
const statWind = document.getElementById('stat-wind');

let activeController = null;
let rateLimitTimer = null;

const RATE_LIMIT_MAX_REQUESTS = 5;
const RATE_LIMIT_WINDOW_MS = 60_000;
const REQUEST_STORE_KEY = 'weather-api:city-request-log:v1';
const memoryRequestStore = {};

const formatWeatherTimestamp = (timestamp) => {
    if (!timestamp) return '—';

    const date = new Date(timestamp);
    if (Number.isNaN(date.getTime())) return timestamp;

    return new Intl.DateTimeFormat(undefined, {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    }).format(date);
};

const parseLocalIsoLike = (value) => {
    if (!value) return null;

    const match = `${value}`.match(/^(\d{4})-(\d{2})-(\d{2})[T\s](\d{2}):(\d{2})(?::(\d{2}))?/);
    if (!match) return null;

    const [, year, month, day, hour, minute, second = '0'] = match;
    const date = new Date(Number(year), Number(month) - 1, Number(day), Number(hour), Number(minute), Number(second));

    return Number.isNaN(date.getTime()) ? null : date;
};

const formatCityLocalTime = (value) => {
    const date = parseLocalIsoLike(value);
    if (!date) return value || '—';

    return new Intl.DateTimeFormat(undefined, {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    }).format(date);
};

const cityTimeFor = (data) => formatCityLocalTime(data.local_time ?? data.city_time ?? data.native_time ?? data.native_city_time);

const weatherIconFor = (description, temperature) => {
    const text = `${description ?? ''}`.toLowerCase();
    if (text.includes('thunder')) return '⛈️';
    if (text.includes('rain')) return '🌧️';
    if (text.includes('snow')) return '🌨️';
    if (text.includes('mist') || text.includes('fog') || text.includes('haze')) return '🌫️';
    if (text.includes('cloud')) return '☁️';
    if (text.includes('clear')) return temperature >= 30 ? '🌞' : '☀️';
    if (temperature >= 33) return '🌞';
    if (temperature <= 16) return '🧊';
    return '🌤️';
};

const daypartFor = (data) => {
    if (typeof data.is_daytime === 'boolean') return data.is_daytime ? 'day' : 'night';
    if (typeof data.is_day === 'boolean') return data.is_day ? 'day' : 'night';
    if (typeof data.daytime === 'boolean') return data.daytime ? 'day' : 'night';
    return 'day';
};

const themeLabelFor = (theme, daypart) => {
    const labels = {
        warm: 'Warm',
        cold: 'Cool',
        rain: 'Rain',
        clear: 'Clear',
        mild: 'Balanced',
    };

    return `${daypart === 'night' ? 'Night' : 'Day'} · ${labels[theme] ?? 'Balanced'}`;
};

const friendlyMoodFor = (temperature, description) => {
    const text = `${description ?? ''}`.toLowerCase();
    if (text.includes('rain') || text.includes('storm') || text.includes('drizzle')) return 'Rainy and refreshing';
    if (text.includes('snow')) return 'Cold and wintry';
    if (text.includes('cloud')) return 'Cool and cloudy';
    if (text.includes('clear')) return temperature >= 30 ? 'Warm and sunny' : 'Bright and clear';
    if (temperature >= 33) return 'Warm and sunny';
    if (temperature <= 16) return 'Quite chilly';
    return 'Comfortable and calm';
};

const normalizeCityKey = (city) => `${city ?? ''}`
    .trim()
    .replace(/\s+/g, ' ')
    .toLowerCase();

const storageAvailable = () => {
    try {
        if (!window.localStorage) return false;

        const probeKey = `${REQUEST_STORE_KEY}:probe`;
        window.localStorage.setItem(probeKey, '1');
        window.localStorage.removeItem(probeKey);
        return true;
    } catch {
        return false;
    }
};

const canUseLocalStorage = storageAvailable();

const sanitizeRequestStore = (value, now = Date.now()) => {
    if (!value || typeof value !== 'object') {
        return {};
    }

    return Object.entries(value).reduce((carry, [cityKey, timestamps]) => {
        if (!Array.isArray(timestamps)) {
            return carry;
        }

        const prunedTimestamps = timestamps
            .filter((timestamp) => Number.isFinite(timestamp))
            .filter((timestamp) => now - timestamp < RATE_LIMIT_WINDOW_MS)
            .sort((left, right) => left - right);

        if (prunedTimestamps.length) {
            carry[cityKey] = prunedTimestamps;
        }

        return carry;
    }, {});
};

const readRequestStore = (now = Date.now()) => {
    if (!canUseLocalStorage) {
        return sanitizeRequestStore(memoryRequestStore, now);
    }

    try {
        const parsed = JSON.parse(window.localStorage.getItem(REQUEST_STORE_KEY) ?? '{}');
        const sanitized = sanitizeRequestStore(parsed, now);
        window.localStorage.setItem(REQUEST_STORE_KEY, JSON.stringify(sanitized));
        return sanitized;
    } catch {
        return sanitizeRequestStore(memoryRequestStore, now);
    }
};

const writeRequestStore = (store) => {
    const sanitized = sanitizeRequestStore(store);

    if (canUseLocalStorage) {
        try {
            window.localStorage.setItem(REQUEST_STORE_KEY, JSON.stringify(sanitized));
            return sanitized;
        } catch {
            // ponytail: localStorage may fail at runtime, so keep ephemeral fallback behavior.
        }
    }

    Object.keys(memoryRequestStore).forEach((key) => {
        delete memoryRequestStore[key];
    });

    Object.entries(sanitized).forEach(([key, timestamps]) => {
        memoryRequestStore[key] = [...timestamps];
    });

    return sanitized;
};

const formatCountdown = (milliseconds) => {
    const totalSeconds = Math.max(1, Math.ceil(milliseconds / 1000));
    const minutes = Math.floor(totalSeconds / 60);
    const seconds = totalSeconds % 60;

    if (minutes > 0) {
        return `${minutes}m ${seconds}s`;
    }

    return `${seconds}s`;
};

const getRateLimitState = (city) => {
    const now = Date.now();
    const normalizedCity = normalizeCityKey(city);
    const requestStore = readRequestStore(now);
    const cityRequests = requestStore[normalizedCity] ?? [];

    if (cityRequests.length < RATE_LIMIT_MAX_REQUESTS) {
        return {
            limited: false,
            remaining: RATE_LIMIT_MAX_REQUESTS - cityRequests.length,
            retryInMs: 0,
            normalizedCity,
            requestCount: cityRequests.length,
        };
    }

    return {
        limited: true,
        remaining: 0,
        retryInMs: Math.max(0, RATE_LIMIT_WINDOW_MS - (now - cityRequests[0])),
        normalizedCity,
        requestCount: cityRequests.length,
    };
};

const renderRateLimitMessage = (city = cityInput?.value ?? defaultCity) => {
    const cleanCity = `${city ?? ''}`.trim();
    const state = getRateLimitState(cleanCity);
    const cityLabel = cleanCity || 'this city';

    if (state.limited) {
        statusEl.textContent = `Recent checks for ${cityLabel} are temporarily limited. Trying again in about ${formatCountdown(state.retryInMs)}.`;
        if (!rateLimitTimer) {
            rateLimitTimer = window.setInterval(() => {
                const nextState = getRateLimitState(cityInput?.value ?? defaultCity);
                if (!nextState.limited) {
                    window.clearInterval(rateLimitTimer);
                    rateLimitTimer = null;
                }
                renderRateLimitMessage(cityInput?.value ?? defaultCity);
            }, 1000);
        }
        return;
    }

    if (rateLimitTimer) {
        window.clearInterval(rateLimitTimer);
        rateLimitTimer = null;
    }
};

const resolveRequestMode = (city, { countTowardLimit = true } = {}) => {
    const cleanCity = `${city ?? ''}`.trim();
    const state = getRateLimitState(cleanCity);

    if (state.limited) {
        renderRateLimitMessage(cleanCity);
        return true;
    }

    if (countTowardLimit) {
        const requestStore = readRequestStore();
        requestStore[state.normalizedCity] = [...(requestStore[state.normalizedCity] ?? []), Date.now()];
        writeRequestStore(requestStore);
    }

    renderRateLimitMessage(cleanCity);

    return false;
};

const themeFor = (temperature, description) => {
    const text = `${description ?? ''}`.toLowerCase();
    if (text.includes('rain') || text.includes('storm') || text.includes('drizzle')) return 'rain';
    if (temperature >= 32) return 'warm';
    if (temperature <= 18) return 'cold';
    if (text.includes('clear')) return 'clear';
    return 'mild';
};

const setBusy = (busy, message = null) => {
    submitButton.disabled = busy;
    submitButton.setAttribute('aria-busy', busy ? 'true' : 'false');
    submitLabel.textContent = busy ? 'Checking...' : 'Check weather';
    submitStatus.textContent = busy ? 'Loading weather snapshot' : '';
    cityInput.disabled = busy;
    if (message !== null) {
        statusEl.textContent = message;
    }
};

const showError = (message) => {
    errorEl.textContent = message;
    errorEl.style.display = 'block';
    statusEl.textContent = 'We could not load that weather snapshot.';
};

const clearError = () => {
    errorEl.textContent = '';
    errorEl.style.display = 'none';
};

const setActiveChip = (city) => {
    chips.forEach((chip) => {
        const isActive = chip.dataset.city === city;
        chip.setAttribute('aria-pressed', isActive ? 'true' : 'false');
    });
};

const renderResult = (data) => {
    const temperature = Number(data.temperature);
    const description = data.description ?? '—';
    const theme = themeFor(temperature, description);
    const daypart = daypartFor(data);
    const city = data.city ?? 'Unknown city';

    document.body.dataset.weather = theme;
    document.body.dataset.time = daypart;
    weatherPanelEl.dataset.state = 'loaded';
    placeholderEl.classList.add('hidden');
    resultEl.classList.remove('hidden');

    const iconEmoji = weatherIconFor(description, temperature);
    weatherIcon.textContent = iconEmoji;
    weatherIcon.setAttribute('aria-label', `${description} weather icon`);

    temperatureEl.textContent = Number.isFinite(temperature) ? `${Math.round(temperature)}°C` : '—';
    resultCityEl.textContent = city;
    descriptionEl.textContent = description;

    const cityTime = cityTimeFor(data);
    localTimeEl.textContent = cityTime !== '—' ? cityTime : '';
    localTimeEl.setAttribute('aria-label', cityTime !== '—' ? `Local time in ${city}: ${cityTime}` : '');

    // Secondary stats — show what's available from the API response
    statFeelsLike.textContent = data.feels_like != null ? `Feels like ${Math.round(Number(data.feels_like))}°` : '';
    statHumidity.textContent = data.humidity != null ? `Humidity ${data.humidity}%` : '';
    statWind.textContent = data.wind_speed != null ? `Wind ${data.wind_speed} km/h` : '';

    setActiveChip(city);
    statusEl.textContent = `Weather loaded for ${city}.`;
    renderRateLimitMessage(city);
};

const loadWeather = async (city, options = {}) => {
    const { useCached = false, countTowardLimit = true } = options;
    const cleanCity = `${city}`.trim();

    if (!cleanCity) {
        showError('Please enter a city name.');
        return;
    }

    clearError();

    const cached = useCached || resolveRequestMode(cleanCity, { countTowardLimit });
    setBusy(true, `Checking weather for ${cleanCity}...`);

    if (activeController) {
        activeController.abort();
    }

    const controller = new AbortController();
    activeController = controller;
    const requestPath = `/weather/${encodeURIComponent(cleanCity)}${cached ? '/cached' : ''}`;

    try {
        const response = await fetch(requestPath, {
            headers: {
                Accept: 'application/json'
            },
            signal: controller.signal,
        });

        const payload = await response.json().catch(() => null);

        if (!response.ok) {
            throw new Error(payload?.error?.message ?? 'We could not retrieve the weather right now.');
        }

        renderResult(payload);
    } catch (error) {
        if (error.name === 'AbortError') return;

        resultEl.classList.add('hidden');
        placeholderEl.classList.remove('hidden');
        weatherPanelEl.dataset.state = 'idle';
        document.body.dataset.weather = 'mild';
        document.body.dataset.time = 'day';
        setActiveChip(null);
        showError(error.message || 'Unable to load weather.');
    } finally {
        if (activeController === controller) {
            activeController = null;
        }
        setBusy(false);
    }
};

chips.forEach((chip) => {
    chip.setAttribute('aria-pressed', 'false');
    chip.addEventListener('click', () => {
        cityInput.value = chip.dataset.city ?? defaultCity;
        renderRateLimitMessage(cityInput.value);
        loadWeather(cityInput.value);
    });
});

form.addEventListener('submit', (event) => {
    event.preventDefault();
    setActiveChip(null);
    renderRateLimitMessage(cityInput.value);
    loadWeather(cityInput.value);
});

cityInput.addEventListener('input', () => {
    renderRateLimitMessage(cityInput.value);
});

cityInput.value = defaultCity;
cityInput.focus({
    preventScroll: true
});

renderRateLimitMessage(defaultCity || 'Manila');
loadWeather(defaultCity || 'Manila', {
    countTowardLimit: false,
});
