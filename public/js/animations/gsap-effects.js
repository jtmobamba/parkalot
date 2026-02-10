/**
 * GSAP Animation Effects for ParkaLot
 * Simplified version with essential animations only
 */

// Check if GSAP is available
if (typeof gsap !== 'undefined') {

    // Register ScrollTrigger plugin if available
    if (typeof ScrollTrigger !== 'undefined') {
        gsap.registerPlugin(ScrollTrigger);
    }

    /**
     * Simple fade in effect
     */
    gsap.registerEffect({
        name: 'fadeIn',
        effect: (targets, config) => {
            return gsap.from(targets, {
                duration: config.duration,
                opacity: 0,
                ease: 'power2.out'
            });
        },
        defaults: { duration: 0.5 },
        extendTimeline: true
    });

    /**
     * Simple Page Transitions - Minimal animations
     */
    class PageTransitions {
        constructor(options = {}) {
            this.options = {
                enablePageLoad: options.enablePageLoad !== false,
                ...options
            };
            this.init();
        }

        init() {
            if (this.options.enablePageLoad) {
                this.initPageLoad();
            }
        }

        initPageLoad() {
            // Simple page loader fadeout
            const loader = document.querySelector('.page-loader, .loading-overlay');
            if (loader) {
                gsap.to(loader, {
                    opacity: 0,
                    duration: 0.3,
                    onComplete: () => loader.remove()
                });
            }

            // Simple hero fade in
            const heroContent = document.querySelector('.hero-content, .hero h1');
            if (heroContent) {
                gsap.from(heroContent, {
                    opacity: 0,
                    y: 20,
                    duration: 0.6,
                    ease: 'power2.out'
                });
            }
        }

        // Refresh ScrollTrigger
        refresh() {
            if (typeof ScrollTrigger !== 'undefined') {
                ScrollTrigger.refresh();
            }
        }
    }

    // Auto-initialize on DOM ready
    document.addEventListener('DOMContentLoaded', function() {
        window.pageTransitions = new PageTransitions();
    });

    // Export for module usage
    if (typeof module !== 'undefined' && module.exports) {
        module.exports = { PageTransitions };
    }

} else {
    console.warn('GSAP is not loaded.');
}
