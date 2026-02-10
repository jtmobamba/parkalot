/**
 * Flight Tracker Component
 *
 * Displays real-time flight information for London airports.
 * Supports flight search, airport departures/arrivals, and parking linking.
 */

class FlightTracker {
    constructor(containerId, options = {}) {
        this.container = document.getElementById(containerId);
        if (!this.container) {
            console.warn(`FlightTracker: Container #${containerId} not found`);
            return;
        }

        this.options = {
            airport: options.airport || 'LHR',
            type: options.type || 'departures', // 'departures' or 'arrivals'
            limit: options.limit || 10,
            showSearch: options.showSearch !== false,
            autoRefresh: options.autoRefresh || false,
            refreshInterval: options.refreshInterval || 60000, // 1 minute
            animate: options.animate !== false,
            onFlightSelect: options.onFlightSelect || null,
            apiEndpoint: options.apiEndpoint || '/api/index.php?route=',
            ...options
        };

        this.airports = {};
        this.flights = [];
        this.selectedFlight = null;

        this.init();
    }

    async init() {
        this.renderLoading();
        await this.loadAirports();
        await this.loadFlights();

        if (this.options.autoRefresh) {
            setInterval(() => this.loadFlights(), this.options.refreshInterval);
        }
    }

    async loadAirports() {
        try {
            const response = await fetch(`${this.options.apiEndpoint}flights/airports`, {
                credentials: 'same-origin'
            });
            const data = await response.json();

            if (data.success && data.airports) {
                this.airports = data.airports;
            }
        } catch (error) {
            console.error('FlightTracker: Failed to load airports', error);
        }
    }

    async loadFlights() {
        try {
            const url = `${this.options.apiEndpoint}flights/airport&code=${this.options.airport}&type=${this.options.type}&limit=${this.options.limit}`;
            const response = await fetch(url, { credentials: 'same-origin' });
            const data = await response.json();

            if (data.success && data.data) {
                this.flights = data.data.flights || [];
                this.render();
            } else {
                this.renderError(data.error || 'Unable to load flights');
            }
        } catch (error) {
            console.error('FlightTracker: Failed to load flights', error);
            this.renderError('Unable to load flights');
        }
    }

    async searchFlight(flightNumber) {
        if (!flightNumber || flightNumber.length < 3) {
            return null;
        }

        try {
            const url = `${this.options.apiEndpoint}flights/status&flight=${encodeURIComponent(flightNumber)}`;
            const response = await fetch(url, { credentials: 'same-origin' });
            const data = await response.json();

            if (data.success && data.flight) {
                return data.flight;
            }
            return null;
        } catch (error) {
            console.error('FlightTracker: Search failed', error);
            return null;
        }
    }

    render() {
        const airportInfo = this.airports[this.options.airport] || { name: 'Unknown Airport' };

        this.container.innerHTML = `
            <div class="pl-flight-tracker">
                ${this.renderHeader(airportInfo)}
                ${this.options.showSearch ? this.renderSearch() : ''}
                ${this.renderFlights()}
            </div>
        `;

        this.bindEvents();

        if (this.options.animate && typeof gsap !== 'undefined') {
            this.animateIn();
        }
    }

    renderHeader(airportInfo) {
        const tabs = `
            <div class="pl-flight-tracker__tabs">
                <button class="pl-flight-tracker__tab ${this.options.type === 'departures' ? 'active' : ''}" data-type="departures">
                    Departures
                </button>
                <button class="pl-flight-tracker__tab ${this.options.type === 'arrivals' ? 'active' : ''}" data-type="arrivals">
                    Arrivals
                </button>
            </div>
        `;

        return `
            <div class="pl-flight-tracker__header">
                <div>
                    <h3 class="pl-flight-tracker__title">${airportInfo.name}</h3>
                    <span class="pl-flight-tracker__airport-code">${this.options.airport}</span>
                </div>
                ${tabs}
            </div>
        `;
    }

    renderSearch() {
        return `
            <div class="pl-flight-tracker__search">
                <input
                    type="text"
                    placeholder="Search flight (e.g., BA123)"
                    class="pl-flight-tracker__search-input"
                    maxlength="10"
                >
                <button class="pl-btn pl-btn--primary pl-btn--sm pl-flight-tracker__search-btn">
                    Search
                </button>
            </div>
        `;
    }

    renderFlights() {
        if (!this.flights.length) {
            return `
                <div class="pl-flight-tracker__empty">
                    <p>No ${this.options.type} found</p>
                </div>
            `;
        }

        const flightsHtml = this.flights.map(flight => this.renderFlightCard(flight)).join('');

        return `<div class="pl-flight-tracker__list">${flightsHtml}</div>`;
    }

    renderFlightCard(flight) {
        const status = flight.status || 'scheduled';
        const statusClass = `pl-flight-card__status--${status.replace('_', '-')}`;
        const statusLabel = flight.statusLabel || this.formatStatus(status);

        const departure = flight.departure || {};
        const arrival = flight.arrival || {};

        const scheduledTime = flight.scheduledTime
            ? new Date(flight.scheduledTime).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' })
            : '--:--';

        const delayInfo = flight.delayMinutes > 0
            ? `<span class="pl-flight-card__delay">+${flight.delayMinutes}min</span>`
            : '';

        return `
            <div class="pl-flight-card" data-flight="${this.escapeHtml(flight.flightNumber)}">
                <div class="pl-flight-card__header">
                    <div class="pl-flight-card__number">
                        ${flight.airline?.logo ? `<img src="${flight.airline.logo}" alt="${flight.airline.name}" class="pl-flight-card__airline-logo" onerror="this.style.display='none'">` : ''}
                        <div>
                            <div class="pl-flight-card__code">${this.escapeHtml(flight.flightNumber)}</div>
                            <div class="pl-flight-card__airline">${this.escapeHtml(flight.airline?.name || '')}</div>
                        </div>
                    </div>
                    <span class="pl-flight-card__status ${statusClass}">
                        ${statusLabel}
                    </span>
                </div>
                <div class="pl-flight-card__route">
                    <div class="pl-flight-card__airport">
                        <div class="pl-flight-card__airport-code">${this.escapeHtml(departure.airport || '')}</div>
                        <div class="pl-flight-card__airport-name">${this.escapeHtml(departure.city || '')}</div>
                        <div class="pl-flight-card__time">${scheduledTime} ${delayInfo}</div>
                    </div>
                    <div class="pl-flight-card__arrow">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M5 12h14M12 5l7 7-7 7"/>
                        </svg>
                    </div>
                    <div class="pl-flight-card__airport">
                        <div class="pl-flight-card__airport-code">${this.escapeHtml(arrival.airport || '')}</div>
                        <div class="pl-flight-card__airport-name">${this.escapeHtml(arrival.city || '')}</div>
                    </div>
                </div>
                <div class="pl-flight-card__details">
                    ${flight.terminal ? `<span class="pl-flight-card__detail"><span class="pl-flight-card__detail-label">Terminal:</span><span class="pl-flight-card__detail-value">${this.escapeHtml(flight.terminal)}</span></span>` : ''}
                    ${flight.gate ? `<span class="pl-flight-card__detail"><span class="pl-flight-card__detail-label">Gate:</span><span class="pl-flight-card__detail-value">${this.escapeHtml(flight.gate)}</span></span>` : ''}
                </div>
            </div>
        `;
    }

    renderLoading() {
        this.container.innerHTML = `
            <div class="pl-flight-tracker">
                <div class="pl-flight-tracker__header">
                    <div class="pl-skeleton" style="width: 200px; height: 28px;"></div>
                </div>
                <div class="pl-flight-tracker__list">
                    ${Array(3).fill('<div class="pl-flight-card"><div class="pl-skeleton" style="height: 120px;"></div></div>').join('')}
                </div>
            </div>
        `;
    }

    renderError(message) {
        this.container.innerHTML = `
            <div class="pl-flight-tracker">
                <div style="text-align: center; padding: 40px; color: #6b7280;">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin: 0 auto 16px; opacity: 0.5;">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="12" y1="8" x2="12" y2="12"/>
                        <line x1="12" y1="16" x2="12.01" y2="16"/>
                    </svg>
                    <p>${message}</p>
                    <button class="pl-btn pl-btn--primary pl-btn--sm" style="margin-top: 16px;">
                        Retry
                    </button>
                </div>
            </div>
        `;

        this.container.querySelector('button')?.addEventListener('click', () => {
            this.renderLoading();
            this.loadFlights();
        });
    }

    bindEvents() {
        // Tab switching
        this.container.querySelectorAll('.pl-flight-tracker__tab').forEach(tab => {
            tab.addEventListener('click', (e) => {
                const type = e.target.dataset.type;
                if (type && type !== this.options.type) {
                    this.options.type = type;
                    this.renderLoading();
                    this.loadFlights();
                }
            });
        });

        // Flight search
        const searchInput = this.container.querySelector('.pl-flight-tracker__search-input');
        const searchBtn = this.container.querySelector('.pl-flight-tracker__search-btn');

        if (searchInput && searchBtn) {
            const handleSearch = async () => {
                const query = searchInput.value.trim().toUpperCase();
                if (query.length >= 3) {
                    searchBtn.disabled = true;
                    searchBtn.textContent = 'Searching...';

                    const flight = await this.searchFlight(query);

                    searchBtn.disabled = false;
                    searchBtn.textContent = 'Search';

                    if (flight) {
                        this.showFlightResult(flight);
                    } else {
                        this.showSearchError('Flight not found');
                    }
                }
            };

            searchBtn.addEventListener('click', handleSearch);
            searchInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') handleSearch();
            });
        }

        // Flight selection
        this.container.querySelectorAll('.pl-flight-card').forEach(card => {
            card.addEventListener('click', () => {
                const flightNumber = card.dataset.flight;
                const flight = this.flights.find(f => f.flightNumber === flightNumber);

                if (flight) {
                    this.selectFlight(flight);
                }
            });
        });
    }

    showFlightResult(flight) {
        const resultsHtml = `
            <div class="pl-flight-tracker__search-result">
                ${this.renderFlightCard(flight)}
                <button class="pl-btn pl-btn--secondary pl-btn--sm" style="margin-top: 12px;">
                    Back to List
                </button>
            </div>
        `;

        const listEl = this.container.querySelector('.pl-flight-tracker__list');
        if (listEl) {
            listEl.innerHTML = resultsHtml;
            listEl.querySelector('button').addEventListener('click', () => this.loadFlights());

            listEl.querySelector('.pl-flight-card').addEventListener('click', () => {
                this.selectFlight(flight);
            });
        }
    }

    showSearchError(message) {
        const searchEl = this.container.querySelector('.pl-flight-tracker__search');
        if (searchEl) {
            let errorEl = searchEl.querySelector('.pl-flight-tracker__search-error');
            if (!errorEl) {
                errorEl = document.createElement('div');
                errorEl.className = 'pl-flight-tracker__search-error';
                errorEl.style.cssText = 'color: #ef4444; font-size: 14px; margin-top: 8px;';
                searchEl.appendChild(errorEl);
            }
            errorEl.textContent = message;

            setTimeout(() => errorEl.remove(), 3000);
        }
    }

    selectFlight(flight) {
        this.selectedFlight = flight;

        // Highlight selected card
        this.container.querySelectorAll('.pl-flight-card').forEach(card => {
            card.classList.remove('selected');
            if (card.dataset.flight === flight.flightNumber) {
                card.classList.add('selected');
            }
        });

        // Callback for flight selection
        if (typeof this.options.onFlightSelect === 'function') {
            this.options.onFlightSelect(flight);
        }

        // Dispatch custom event
        this.container.dispatchEvent(new CustomEvent('flightSelected', {
            detail: { flight },
            bubbles: true
        }));
    }

    setAirport(airportCode) {
        if (this.airports[airportCode]) {
            this.options.airport = airportCode;
            this.renderLoading();
            this.loadFlights();
        }
    }

    formatStatus(status) {
        const statusMap = {
            scheduled: 'Scheduled',
            boarding: 'Boarding',
            departed: 'Departed',
            in_air: 'In Flight',
            landed: 'Landed',
            arrived: 'Arrived',
            cancelled: 'Cancelled',
            delayed: 'Delayed',
            diverted: 'Diverted'
        };
        return statusMap[status] || status;
    }

    animateIn() {
        const cards = this.container.querySelectorAll('.pl-flight-card');

        gsap.from(cards, {
            opacity: 0,
            x: -20,
            duration: 0.4,
            stagger: 0.08,
            ease: 'power2.out'
        });
    }

    escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    getSelectedFlight() {
        return this.selectedFlight;
    }

    destroy() {
        this.container.innerHTML = '';
    }
}

// Static helper to get London airports
FlightTracker.LONDON_AIRPORTS = {
    LHR: 'London Heathrow',
    LGW: 'London Gatwick',
    STN: 'London Stansted',
    LTN: 'London Luton',
    LCY: 'London City'
};

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = FlightTracker;
}
