/**
 * Trustpilot Widget Component
 *
 * Displays Trustpilot reviews and ratings in a customizable widget.
 * Supports carousel mode, animations, and auto-refresh.
 */

class TrustpilotWidget {
    constructor(containerId, options = {}) {
        this.container = document.getElementById(containerId);
        if (!this.container) {
            console.warn(`TrustpilotWidget: Container #${containerId} not found`);
            return;
        }

        this.options = {
            limit: options.limit || 5,
            showStats: options.showStats !== false,
            showStars: options.showStars !== false,
            showDate: options.showDate !== false,
            showVerified: options.showVerified !== false,
            autoRefresh: options.autoRefresh || false,
            refreshInterval: options.refreshInterval || 300000, // 5 minutes
            carousel: options.carousel || false,
            carouselInterval: options.carouselInterval || 5000, // 5 seconds
            animate: options.animate !== false,
            theme: options.theme || 'light', // 'light' or 'dark'
            maxTextLength: options.maxTextLength || 200,
            apiEndpoint: options.apiEndpoint || '/api/index.php?route=trustpilot/widget',
            ...options
        };

        this.reviews = [];
        this.stats = null;
        this.carouselIndex = 0;
        this.carouselTimer = null;

        this.init();
    }

    async init() {
        this.renderLoading();
        await this.loadData();

        if (this.options.autoRefresh) {
            setInterval(() => this.loadData(), this.options.refreshInterval);
        }
    }

    async loadData() {
        try {
            const url = `${this.options.apiEndpoint}&limit=${this.options.limit}`;
            const response = await fetch(url, { credentials: 'same-origin' });
            const data = await response.json();

            if (data.success && data.data) {
                this.reviews = data.data.reviews?.reviews || [];
                this.stats = data.data.stats || null;
                this.render();
            } else {
                this.renderError('Unable to load reviews');
            }
        } catch (error) {
            console.error('TrustpilotWidget: Failed to load data', error);
            this.renderError('Unable to load reviews');
        }
    }

    render() {
        const theme = this.options.theme === 'dark' ? 'pl-trustpilot--dark' : '';
        const carouselClass = this.options.carousel ? 'pl-trustpilot--carousel' : '';

        this.container.innerHTML = `
            <div class="pl-trustpilot ${theme} ${carouselClass}">
                ${this.options.showStats ? this.renderHeader() : ''}
                ${this.renderReviews()}
            </div>
        `;

        if (this.options.carousel && this.reviews.length > 1) {
            this.startCarousel();
        }

        if (this.options.animate && typeof gsap !== 'undefined') {
            this.animateIn();
        }
    }

    renderHeader() {
        if (!this.stats) return '';

        const stars = this.renderStars(Math.round(this.stats.trustScore || this.stats.starsAverage || 0));
        const totalReviews = this.stats.totalReviews || 0;

        return `
            <div class="pl-trustpilot__header">
                <div class="pl-trustpilot__logo">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="#00b67a">
                        <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                    </svg>
                    <span class="pl-trustpilot__logo-text">Trustpilot</span>
                </div>
                <div class="pl-trustpilot__score">
                    <span class="pl-trustpilot__rating">${(this.stats.trustScore || 0).toFixed(1)}</span>
                    <div>
                        ${stars}
                        <div class="pl-trustpilot__count">${totalReviews.toLocaleString()} reviews</div>
                    </div>
                </div>
            </div>
        `;
    }

    renderReviews() {
        if (!this.reviews.length) {
            return '<div class="pl-trustpilot__empty">No reviews yet</div>';
        }

        const reviewsHtml = this.reviews.map(review => this.renderReview(review)).join('');

        if (this.options.carousel) {
            return `
                <div class="pl-trustpilot__carousel-wrapper">
                    <div class="pl-trustpilot__carousel-track">
                        ${reviewsHtml}
                    </div>
                </div>
            `;
        }

        return `<div class="pl-trustpilot__reviews">${reviewsHtml}</div>`;
    }

    renderReview(review) {
        const rating = review.rating || review.stars || 5;
        const stars = this.options.showStars ? this.renderStars(rating, 'sm') : '';

        const text = review.text || '';
        const truncatedText = text.length > this.options.maxTextLength
            ? text.substring(0, this.options.maxTextLength) + '...'
            : text;

        const date = this.options.showDate && review.dateFormatted
            ? `<span class="pl-trustpilot__date">${review.dateFormatted}</span>`
            : '';

        const verified = this.options.showVerified && review.isVerified
            ? `<div class="pl-trustpilot__verified">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/>
                </svg>
                Verified
               </div>`
            : '';

        const reviewerName = review.consumer?.name || 'Anonymous';
        const title = review.title ? `<div class="pl-trustpilot__review-title">${this.escapeHtml(review.title)}</div>` : '';

        return `
            <div class="pl-trustpilot__review">
                <div class="pl-trustpilot__review-header">
                    <div>
                        <span class="pl-trustpilot__reviewer">${this.escapeHtml(reviewerName)}</span>
                        ${stars}
                    </div>
                    ${date}
                </div>
                ${title}
                <div class="pl-trustpilot__review-text">${this.escapeHtml(truncatedText)}</div>
                ${verified}
            </div>
        `;
    }

    renderStars(rating, size = 'md') {
        const sizes = { sm: 14, md: 20, lg: 28 };
        const starSize = sizes[size] || sizes.md;

        let starsHtml = '<div class="pl-trustpilot__stars">';

        for (let i = 1; i <= 5; i++) {
            const filled = i <= rating;
            const className = filled ? 'pl-trustpilot__star' : 'pl-trustpilot__star pl-trustpilot__star--empty';
            starsHtml += `
                <svg class="${className}" width="${starSize}" height="${starSize}" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                </svg>
            `;
        }

        starsHtml += '</div>';
        return starsHtml;
    }

    renderLoading() {
        this.container.innerHTML = `
            <div class="pl-trustpilot">
                <div class="pl-trustpilot__header">
                    <div class="pl-skeleton" style="width: 120px; height: 24px;"></div>
                    <div class="pl-skeleton" style="width: 100px; height: 20px;"></div>
                </div>
                <div class="pl-trustpilot__reviews">
                    ${Array(3).fill('<div class="pl-trustpilot__review"><div class="pl-skeleton" style="height: 80px;"></div></div>').join('')}
                </div>
            </div>
        `;
    }

    renderError(message) {
        this.container.innerHTML = `
            <div class="pl-trustpilot">
                <div style="text-align: center; padding: 20px; color: #6b7280;">
                    <p>${message}</p>
                    <button class="pl-btn pl-btn--secondary pl-btn--sm" onclick="this.closest('.pl-trustpilot').parentNode.__widget?.loadData()">
                        Retry
                    </button>
                </div>
            </div>
        `;
        this.container.__widget = this;
    }

    startCarousel() {
        if (this.carouselTimer) {
            clearInterval(this.carouselTimer);
        }

        const track = this.container.querySelector('.pl-trustpilot__carousel-track');
        if (!track) return;

        const reviews = track.querySelectorAll('.pl-trustpilot__review');
        if (reviews.length <= 1) return;

        this.carouselTimer = setInterval(() => {
            this.carouselIndex = (this.carouselIndex + 1) % reviews.length;
            const offset = reviews[0].offsetWidth + 16; // 16px gap
            track.style.transform = `translateX(-${this.carouselIndex * offset}px)`;
        }, this.options.carouselInterval);
    }

    animateIn() {
        const reviews = this.container.querySelectorAll('.pl-trustpilot__review');

        gsap.from(reviews, {
            opacity: 0,
            y: 20,
            duration: 0.5,
            stagger: 0.1,
            ease: 'power2.out'
        });
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    destroy() {
        if (this.carouselTimer) {
            clearInterval(this.carouselTimer);
        }
        this.container.innerHTML = '';
    }
}

// Static method for quick initialization
TrustpilotWidget.init = function(containerId, options) {
    return new TrustpilotWidget(containerId, options);
};

// Auto-initialize widgets with data attributes
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('[data-trustpilot-widget]').forEach(container => {
        const options = {
            limit: parseInt(container.dataset.limit) || 5,
            showStats: container.dataset.showStats !== 'false',
            carousel: container.dataset.carousel === 'true',
            theme: container.dataset.theme || 'light'
        };
        new TrustpilotWidget(container.id, options);
    });
});

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = TrustpilotWidget;
}
