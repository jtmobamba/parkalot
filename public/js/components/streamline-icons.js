/**
 * Streamline Icons Integration
 *
 * Manages icon loading and replacement with Streamline API icons.
 * Falls back to local SVG icons when API is unavailable.
 */

class StreamlineIcons {
    constructor(options = {}) {
        this.options = {
            apiKey: options.apiKey || 'wbGRffzZEiPw9Vun.4d26ca7abb25c59f9c5b5cbfa60b608e',
            baseUrl: options.baseUrl || 'https://api.streamlinehq.com',
            family: options.family || 'regular', // 'regular', 'bold', 'light'
            size: options.size || 24,
            color: options.color || 'currentColor',
            fallbackPath: options.fallbackPath || '/images/icons/',
            cache: options.cache !== false,
            ...options
        };

        this.iconCache = new Map();
        this.pendingRequests = new Map();
    }

    /**
     * Get an icon by name
     * @param {string} name - Icon name (e.g., 'search', 'location', 'car')
     * @param {object} options - Override options for this icon
     * @returns {Promise<string>} SVG markup
     */
    async getIcon(name, options = {}) {
        const opts = { ...this.options, ...options };
        const cacheKey = `${name}-${opts.family}-${opts.size}-${opts.color}`;

        // Check cache first
        if (opts.cache && this.iconCache.has(cacheKey)) {
            return this.iconCache.get(cacheKey);
        }

        // Check for pending request
        if (this.pendingRequests.has(cacheKey)) {
            return this.pendingRequests.get(cacheKey);
        }

        // Create fetch promise
        const fetchPromise = this.fetchIcon(name, opts)
            .then(svg => {
                this.iconCache.set(cacheKey, svg);
                this.pendingRequests.delete(cacheKey);
                return svg;
            })
            .catch(error => {
                this.pendingRequests.delete(cacheKey);
                return this.getFallbackIcon(name, opts);
            });

        this.pendingRequests.set(cacheKey, fetchPromise);
        return fetchPromise;
    }

    /**
     * Fetch icon from Streamline API
     */
    async fetchIcon(name, opts) {
        if (!opts.apiKey) {
            return this.getFallbackIcon(name, opts);
        }

        try {
            // Try Streamline API with proper endpoint
            const url = `${opts.baseUrl}/v3/icons/${opts.family}/${name}`;
            const response = await fetch(url, {
                headers: {
                    'Authorization': `Bearer ${opts.apiKey}`,
                    'Accept': 'application/json'
                }
            });

            if (!response.ok) {
                // Fall back to local icons if API fails
                return this.getFallbackIcon(name, opts);
            }

            const data = await response.json();
            if (data && data.svg) {
                return this.formatSvg(data.svg, opts);
            }
            return this.getFallbackIcon(name, opts);
        } catch (error) {
            // Use local fallback icons on any error
            return this.getFallbackIcon(name, opts);
        }
    }

    /**
     * Get fallback icon from local files
     */
    async getFallbackIcon(name, opts) {
        const mappedName = this.mapIconName(name);
        const url = `${opts.fallbackPath}${mappedName}.svg`;

        try {
            const response = await fetch(url);
            if (!response.ok) {
                return this.getPlaceholderIcon(opts);
            }

            let svg = await response.text();
            return this.formatSvg(svg, opts);
        } catch (error) {
            return this.getPlaceholderIcon(opts);
        }
    }

    /**
     * Map icon names to local file names
     */
    mapIconName(name) {
        const iconMap = {
            // Navigation
            'search': 'search',
            'location': 'location',
            'map': 'location',
            'home': 'building',
            'menu': 'menu',

            // Parking & Vehicles
            'car': 'car',
            'parking': 'parking',
            'garage': 'building',

            // Actions
            'check': 'ok-hand',
            'close': 'close',
            'download': 'download',
            'upload': 'upload',
            'edit': 'edit',
            'delete': 'delete',

            // Communication
            'bell': 'bell',
            'notification': 'notification',
            'chat': 'chat',
            'email': 'email',

            // Status
            'success': 'ok-hand',
            'warning': 'alert',
            'error': 'alert',
            'info': 'info',

            // Features
            'shield': 'shield',
            'star': 'star',
            'payment': 'payment',
            'money': 'money',
            'chart': 'chart',

            // Devices
            'smartphone': 'smartphone',
            'laptop': 'laptop',
            'wifi': 'wifi',

            // Security
            'fingerprint': 'fingerprint',
            'face-scan': 'face-scan',
            'lock': 'lock',

            // Transport
            'plane': 'plane',
            'flight': 'plane',
            'airport': 'plane',

            // Default
            'default': 'sparkle'
        };

        return iconMap[name.toLowerCase()] || iconMap['default'];
    }

    /**
     * Format SVG with options
     */
    formatSvg(svg, opts) {
        // Parse SVG
        const parser = new DOMParser();
        const doc = parser.parseFromString(svg, 'image/svg+xml');
        const svgElement = doc.querySelector('svg');

        if (!svgElement) {
            return this.getPlaceholderIcon(opts);
        }

        // Apply options
        svgElement.setAttribute('width', opts.size);
        svgElement.setAttribute('height', opts.size);

        if (opts.color && opts.color !== 'currentColor') {
            svgElement.setAttribute('fill', opts.color);
        }

        svgElement.classList.add('streamline-icon');

        return svgElement.outerHTML;
    }

    /**
     * Get placeholder icon
     */
    getPlaceholderIcon(opts) {
        return `
            <svg class="streamline-icon" width="${opts.size}" height="${opts.size}" viewBox="0 0 24 24" fill="none" stroke="${opts.color}" stroke-width="2">
                <circle cx="12" cy="12" r="10"/>
                <path d="M12 8v4M12 16h.01"/>
            </svg>
        `;
    }

    /**
     * Replace all icons in the document
     */
    async replaceAllIcons() {
        // Only replace elements with data-streamline-icon attribute
        const elements = document.querySelectorAll('[data-streamline-icon]');
        const promises = Array.from(elements).map(async (el) => {
            const iconName = el.dataset.streamlineIcon;
            const size = el.dataset.iconSize || this.options.size;
            const color = el.dataset.iconColor || this.options.color;

            const svg = await this.getIcon(iconName, { size, color });
            el.innerHTML = svg;
        });

        await Promise.all(promises);
    }

    /**
     * Create an icon element
     */
    async createIconElement(name, options = {}) {
        const svg = await this.getIcon(name, options);
        const wrapper = document.createElement('span');
        wrapper.className = 'streamline-icon-wrapper';
        wrapper.innerHTML = svg;
        return wrapper;
    }

    /**
     * Preload commonly used icons
     */
    async preload(iconNames) {
        const promises = iconNames.map(name => this.getIcon(name));
        await Promise.all(promises);
    }

    /**
     * Clear icon cache
     */
    clearCache() {
        this.iconCache.clear();
    }
}

// Common icon sets for preloading
StreamlineIcons.COMMON_ICONS = [
    'search', 'location', 'car', 'parking', 'check', 'close',
    'bell', 'star', 'shield', 'payment', 'plane', 'user'
];

StreamlineIcons.PARKING_ICONS = [
    'car', 'parking', 'location', 'payment', 'qr-code', 'check'
];

StreamlineIcons.FLIGHT_ICONS = [
    'plane', 'airport', 'departure', 'arrival', 'luggage', 'clock'
];

// Global instance
window.streamlineIcons = new StreamlineIcons();

// Auto-initialize on DOM ready
document.addEventListener('DOMContentLoaded', () => {
    window.streamlineIcons.replaceAllIcons();
});

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = StreamlineIcons;
}
