    <!doctype html>
    <html lang="en">

    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="color-scheme" content="light only">
        <title>{{ config('app.name', 'Weather API') }}</title>
        <link rel="stylesheet" href="{{ asset('css/home.css') }}">
    </head>

    <body data-weather="mild" data-time="day" data-default-city="{{ $defaultCity }}">
        <main class="wrap">
            <header class="intro" id="app-intro" aria-label="About this app">
                <p class="intro-tagline">Live weather, no fuss.</p>
                <p class="intro-sub">Search any city and get the current conditions instantly.</p>
            </header>

            <div class="panel weather-panel" id="weather-panel" data-state="idle" aria-live="polite" aria-atomic="true">
                <div id="placeholder" aria-hidden="true"></div>

                <div id="result" class="hidden">
                    <div class="weather-card">
                        <div class="card-header">
                            <div class="city" id="result-city">—</div>
                            <div class="local-time-inline" id="local-time" aria-label="Local time"></div>
                        </div>

                        <div class="card-main">
                            <div class="temp" id="temperature">--</div>
                            <div class="weather-icon-wrap" id="weather-icon" aria-label="weather icon">🌤️</div>
                        </div>

                        <div class="desc" id="description">—</div>

                        <div class="secondary-stats">
                            <span id="stat-feels-like"></span>
                            <span id="stat-humidity"></span>
                            <span id="stat-wind"></span>
                        </div>
                    </div>
                </div>
            </div>

            <section class="search-stack" aria-label="Search and suggested cities">
                <div class="chips" aria-label="Suggested cities">
                    <button class="chip" type="button" data-city="Manila">Manila</button>
                    <button class="chip" type="button" data-city="Singapore">Singapore</button>
                    <button class="chip" type="button" data-city="New York">New York</button>
                    <button class="chip" type="button" data-city="London">London</button>
                </div>

                <form class="panel form" id="weather-form">
                    <div class="input-row">
                        <input
                                id="city"
                                name="city"
                                type="text"
                                value="{{ $defaultCity }}"
                                autocomplete="off"
                                spellcheck="false"
                                placeholder="City name"
                                inputmode="search"
                                enterkeyhint="search">
                            <button class="submit" id="submit" type="submit" aria-busy="false">
                                <span class="spinner" aria-hidden="true"></span>
                                <span id="submit-label">Check weather</span>
                                <span class="sr-only" id="submit-status"></span>
                            </button>
                        </div>
                        <div class="status sr-only" id="status" aria-live="polite">Enter a city and fetch the weather.</div>
                        <div class="error" id="error" role="alert"></div>
                </form>
            </section>
        </main>

        <script src="{{ asset('js/home.js') }}"></script>
    </body>

    </html>
