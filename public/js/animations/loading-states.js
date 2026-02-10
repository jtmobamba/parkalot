/**
 * Loading States Animations
 *
 * Uses Anime.js for loading indicators, skeleton screens,
 * and other loading state animations.
 */

const LoadingStates = {
    /**
     * Skeleton shimmer animation
     * @param {HTMLElement|string} element - Element or selector
     * @returns {Object} Animation instance
     */
    skeleton: function(element) {
        const el = typeof element === 'string' ? document.querySelector(element) : element;
        if (!el) return null;

        if (typeof anime === 'undefined') {
            console.warn('Anime.js not loaded, using CSS fallback');
            el.classList.add('pl-skeleton');
            return null;
        }

        return anime({
            targets: el,
            backgroundPosition: ['200% 0', '-200% 0'],
            easing: 'linear',
            duration: 1500,
            loop: true
        });
    },

    /**
     * Pulse loading animation
     * @param {HTMLElement|string} element - Element or selector
     * @returns {Object} Animation instance
     */
    pulse: function(element) {
        const el = typeof element === 'string' ? document.querySelector(element) : element;
        if (!el) return null;

        if (typeof anime === 'undefined') {
            el.classList.add('pl-pulse');
            return null;
        }

        return anime({
            targets: el,
            opacity: [1, 0.5],
            easing: 'easeInOutSine',
            duration: 800,
            direction: 'alternate',
            loop: true
        });
    },

    /**
     * Spinner rotation animation
     * @param {HTMLElement|string} element - Element or selector
     * @returns {Object} Animation instance
     */
    spinner: function(element) {
        const el = typeof element === 'string' ? document.querySelector(element) : element;
        if (!el) return null;

        if (typeof anime === 'undefined') {
            el.style.animation = 'spin 0.8s linear infinite';
            return null;
        }

        return anime({
            targets: el,
            rotate: 360,
            easing: 'linear',
            duration: 800,
            loop: true
        });
    },

    /**
     * Progress bar fill animation
     * @param {HTMLElement|string} element - Element or selector
     * @param {number} percentage - Target percentage (0-100)
     * @param {number} duration - Animation duration in ms
     * @returns {Object} Animation instance
     */
    progressBar: function(element, percentage = 100, duration = 1500) {
        const el = typeof element === 'string' ? document.querySelector(element) : element;
        if (!el) return null;

        if (typeof anime === 'undefined') {
            el.style.width = `${percentage}%`;
            el.style.transition = `width ${duration}ms ease-out`;
            return null;
        }

        return anime({
            targets: el,
            width: `${percentage}%`,
            easing: 'easeOutExpo',
            duration: duration
        });
    },

    /**
     * Dots loading animation
     * @param {HTMLElement|string} container - Container element or selector
     * @param {number} dotCount - Number of dots
     * @returns {Object} Animation instance
     */
    dots: function(container, dotCount = 3) {
        const el = typeof container === 'string' ? document.querySelector(container) : container;
        if (!el) return null;

        // Create dots
        el.innerHTML = '';
        el.style.display = 'inline-flex';
        el.style.gap = '4px';
        el.style.alignItems = 'center';

        for (let i = 0; i < dotCount; i++) {
            const dot = document.createElement('span');
            dot.style.cssText = `
                width: 8px;
                height: 8px;
                background: currentColor;
                border-radius: 50%;
                opacity: 0.3;
            `;
            el.appendChild(dot);
        }

        const dots = el.querySelectorAll('span');

        if (typeof anime === 'undefined') {
            dots.forEach((dot, i) => {
                dot.style.animation = `bounce 0.6s ease-in-out ${i * 0.1}s infinite alternate`;
            });
            return null;
        }

        return anime({
            targets: dots,
            opacity: [0.3, 1],
            translateY: [-4, 0],
            easing: 'easeInOutSine',
            duration: 400,
            delay: anime.stagger(100),
            direction: 'alternate',
            loop: true
        });
    },

    /**
     * Fade in content after loading
     * @param {HTMLElement|string} element - Element or selector
     * @param {number} duration - Animation duration in ms
     * @returns {Object} Animation instance
     */
    fadeIn: function(element, duration = 500) {
        const el = typeof element === 'string' ? document.querySelector(element) : element;
        if (!el) return null;

        if (typeof anime === 'undefined') {
            el.style.opacity = 0;
            el.style.transition = `opacity ${duration}ms ease-out`;
            requestAnimationFrame(() => {
                el.style.opacity = 1;
            });
            return null;
        }

        return anime({
            targets: el,
            opacity: [0, 1],
            easing: 'easeOutQuad',
            duration: duration
        });
    },

    /**
     * Slide in content
     * @param {HTMLElement|string} element - Element or selector
     * @param {string} direction - 'up', 'down', 'left', 'right'
     * @param {number} distance - Slide distance in px
     * @returns {Object} Animation instance
     */
    slideIn: function(element, direction = 'up', distance = 30) {
        const el = typeof element === 'string' ? document.querySelector(element) : element;
        if (!el) return null;

        const transforms = {
            up: { translateY: [distance, 0] },
            down: { translateY: [-distance, 0] },
            left: { translateX: [distance, 0] },
            right: { translateX: [-distance, 0] }
        };

        if (typeof anime === 'undefined') {
            el.style.opacity = 0;
            el.style.transform = transforms[direction] ?
                `translate${direction === 'up' || direction === 'down' ? 'Y' : 'X'}(${distance}px)` : '';
            el.style.transition = 'all 0.5s ease-out';
            requestAnimationFrame(() => {
                el.style.opacity = 1;
                el.style.transform = 'none';
            });
            return null;
        }

        return anime({
            targets: el,
            opacity: [0, 1],
            ...transforms[direction],
            easing: 'easeOutQuad',
            duration: 500
        });
    }
};

/**
 * Loading Overlay Component
 */
class LoadingOverlay {
    constructor(options = {}) {
        this.options = {
            message: options.message || 'Loading...',
            showSpinner: options.showSpinner !== false,
            backdrop: options.backdrop !== false,
            target: options.target || document.body,
            zIndex: options.zIndex || 9999,
            ...options
        };

        this.overlay = null;
        this.animation = null;
    }

    show() {
        if (this.overlay) return;

        this.overlay = document.createElement('div');
        this.overlay.className = 'loading-overlay-component';
        this.overlay.style.cssText = `
            position: ${this.options.target === document.body ? 'fixed' : 'absolute'};
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: ${this.options.backdrop ? 'rgba(255,255,255,0.9)' : 'transparent'};
            z-index: ${this.options.zIndex};
            opacity: 0;
            transition: opacity 0.3s ease;
        `;

        let content = '';

        if (this.options.showSpinner) {
            content += `
                <div class="loading-spinner" style="
                    width: 40px;
                    height: 40px;
                    border: 3px solid #e5e7eb;
                    border-top-color: #2563eb;
                    border-radius: 50%;
                    margin-bottom: 16px;
                "></div>
            `;
        }

        if (this.options.message) {
            content += `
                <div class="loading-message" style="
                    color: #374151;
                    font-size: 14px;
                    font-weight: 500;
                ">${this.options.message}</div>
            `;
        }

        this.overlay.innerHTML = content;

        const targetEl = typeof this.options.target === 'string'
            ? document.querySelector(this.options.target)
            : this.options.target;

        if (targetEl !== document.body) {
            targetEl.style.position = 'relative';
        }

        targetEl.appendChild(this.overlay);

        // Trigger reflow and fade in
        this.overlay.offsetHeight;
        this.overlay.style.opacity = 1;

        // Start spinner animation
        const spinner = this.overlay.querySelector('.loading-spinner');
        if (spinner) {
            this.animation = LoadingStates.spinner(spinner);
        }
    }

    hide() {
        if (!this.overlay) return;

        this.overlay.style.opacity = 0;

        setTimeout(() => {
            if (this.animation && typeof this.animation.pause === 'function') {
                this.animation.pause();
            }
            this.overlay.remove();
            this.overlay = null;
            this.animation = null;
        }, 300);
    }

    updateMessage(message) {
        if (!this.overlay) return;

        const msgEl = this.overlay.querySelector('.loading-message');
        if (msgEl) {
            msgEl.textContent = message;
        }
    }
}

/**
 * Button Loading State
 */
class ButtonLoader {
    constructor(button, options = {}) {
        this.button = typeof button === 'string' ? document.querySelector(button) : button;
        this.options = {
            loadingText: options.loadingText || 'Loading...',
            spinnerSize: options.spinnerSize || 16,
            disableOnLoad: options.disableOnLoad !== false,
            ...options
        };

        this.originalContent = '';
        this.originalWidth = '';
        this.isLoading = false;
    }

    start() {
        if (this.isLoading || !this.button) return;

        this.isLoading = true;
        this.originalContent = this.button.innerHTML;
        this.originalWidth = this.button.style.width;

        // Lock width to prevent layout shift
        this.button.style.width = `${this.button.offsetWidth}px`;

        if (this.options.disableOnLoad) {
            this.button.disabled = true;
        }

        this.button.innerHTML = `
            <span class="btn-spinner" style="
                display: inline-block;
                width: ${this.options.spinnerSize}px;
                height: ${this.options.spinnerSize}px;
                border: 2px solid currentColor;
                border-top-color: transparent;
                border-radius: 50%;
                margin-right: 8px;
            "></span>
            ${this.options.loadingText}
        `;

        const spinner = this.button.querySelector('.btn-spinner');
        LoadingStates.spinner(spinner);
    }

    stop() {
        if (!this.isLoading || !this.button) return;

        this.isLoading = false;
        this.button.innerHTML = this.originalContent;
        this.button.style.width = this.originalWidth;

        if (this.options.disableOnLoad) {
            this.button.disabled = false;
        }
    }

    toggle() {
        if (this.isLoading) {
            this.stop();
        } else {
            this.start();
        }
    }
}

/**
 * Skeleton Screen Generator
 */
const SkeletonGenerator = {
    /**
     * Generate a skeleton placeholder
     * @param {string} type - 'text', 'avatar', 'card', 'image'
     * @param {object} options - Configuration options
     * @returns {HTMLElement} Skeleton element
     */
    create: function(type, options = {}) {
        const el = document.createElement('div');
        el.className = 'pl-skeleton';

        const styles = {
            text: {
                height: options.height || '16px',
                width: options.width || '100%',
                marginBottom: options.marginBottom || '8px',
                borderRadius: '4px'
            },
            avatar: {
                width: options.size || '48px',
                height: options.size || '48px',
                borderRadius: '50%'
            },
            card: {
                height: options.height || '200px',
                width: options.width || '100%',
                borderRadius: '12px'
            },
            image: {
                height: options.height || '200px',
                width: options.width || '100%',
                borderRadius: options.borderRadius || '8px'
            }
        };

        Object.assign(el.style, styles[type] || styles.text);

        return el;
    },

    /**
     * Replace element content with skeletons
     * @param {HTMLElement|string} container - Container element
     * @param {Array} config - Array of skeleton configs
     */
    replace: function(container, config) {
        const el = typeof container === 'string' ? document.querySelector(container) : container;
        if (!el) return;

        el.innerHTML = '';

        config.forEach(item => {
            const skeleton = this.create(item.type, item);
            el.appendChild(skeleton);
        });
    },

    /**
     * Create a card skeleton
     * @returns {HTMLElement} Card skeleton element
     */
    card: function() {
        const card = document.createElement('div');
        card.className = 'skeleton-card';
        card.style.cssText = 'padding: 16px; border-radius: 12px; background: #f9fafb;';

        card.appendChild(this.create('avatar', { size: '40px' }));
        card.appendChild(this.create('text', { width: '60%', height: '20px' }));
        card.appendChild(this.create('text', { width: '100%' }));
        card.appendChild(this.create('text', { width: '80%' }));

        return card;
    }
};

// Add keyframes for CSS fallbacks
const style = document.createElement('style');
style.textContent = `
    @keyframes spin {
        to { transform: rotate(360deg); }
    }
    @keyframes bounce {
        from { transform: translateY(0); }
        to { transform: translateY(-4px); }
    }
`;
document.head.appendChild(style);

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        LoadingStates,
        LoadingOverlay,
        ButtonLoader,
        SkeletonGenerator
    };
}
