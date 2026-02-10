/**
 * Stripe Payment Component
 *
 * Reusable payment component for ParkaLot bookings.
 * Handles Stripe Elements integration, payment processing, and error handling.
 */

class StripePayment {
  constructor(containerId, options = {}) {
    this.containerId = containerId;
    this.container = document.getElementById(containerId);
    this.options = {
      apiEndpoint: '/api/index.php',
      currency: 'gbp',
      onSuccess: () => {},
      onError: () => {},
      onCancel: () => {},
      ...options
    };

    this.stripe = null;
    this.elements = null;
    this.cardElement = null;
    this.paymentIntentId = null;
    this.clientSecret = null;
    this.isProcessing = false;
    this.isInitialized = false;
  }

  /**
   * Initialize Stripe and mount card element
   */
  async init() {
    if (this.isInitialized) return true;

    try {
      // Get Stripe configuration
      const configRes = await fetch(`${this.options.apiEndpoint}?route=payment/config`, {
        credentials: 'same-origin'
      });
      const configData = await configRes.json();

      if (!configData.success || !configData.config.publishableKey) {
        console.warn('Stripe not configured, using demo mode');
        this.isDemoMode = true;
        this.renderDemoMode();
        return true;
      }

      if (!configData.config.configured) {
        this.isDemoMode = true;
        this.renderDemoMode();
        return true;
      }

      // Load Stripe.js if not already loaded
      if (!window.Stripe) {
        await this.loadStripeJS();
      }

      // Initialize Stripe
      this.stripe = Stripe(configData.config.publishableKey);
      this.elements = this.stripe.elements({
        fonts: [
          { cssSrc: 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap' }
        ]
      });

      // Create and mount card element
      this.cardElement = this.elements.create('card', {
        style: {
          base: {
            fontFamily: 'Inter, -apple-system, BlinkMacSystemFont, sans-serif',
            fontSize: '16px',
            fontWeight: '400',
            color: '#1f2937',
            '::placeholder': { color: '#9ca3af' }
          },
          invalid: {
            color: '#ef4444',
            iconColor: '#ef4444'
          }
        },
        hidePostalCode: true
      });

      this.isInitialized = true;
      return true;
    } catch (error) {
      console.error('Failed to initialize Stripe:', error);
      this.isDemoMode = true;
      this.renderDemoMode();
      return true;
    }
  }

  /**
   * Load Stripe.js script
   */
  loadStripeJS() {
    return new Promise((resolve, reject) => {
      if (window.Stripe) {
        resolve();
        return;
      }

      const script = document.createElement('script');
      script.src = 'https://js.stripe.com/v3/';
      script.onload = resolve;
      script.onerror = () => reject(new Error('Failed to load Stripe.js'));
      document.head.appendChild(script);
    });
  }

  /**
   * Render the payment form
   */
  render(bookingDetails) {
    this.bookingDetails = bookingDetails;

    const html = `
      <div class="stripe-payment-container">
        <div class="payment-header">
          <h3>Complete Your Booking</h3>
          <p>Secure payment powered by Stripe</p>
        </div>

        <div class="booking-summary">
          <div class="summary-row">
            <span>${bookingDetails.spaceName || 'Parking Space'}</span>
          </div>
          ${bookingDetails.location ? `
          <div class="summary-row">
            <span class="label">Location</span>
            <span>${bookingDetails.location}</span>
          </div>
          ` : ''}
          <div class="summary-row">
            <span class="label">Duration</span>
            <span>${bookingDetails.duration || '-'}</span>
          </div>
          <div class="summary-row">
            <span class="label">Dates</span>
            <span>${bookingDetails.dates || '-'}</span>
          </div>
          <div class="summary-divider"></div>
          <div class="summary-row subtotal">
            <span class="label">Subtotal</span>
            <span>£${(bookingDetails.subtotal || bookingDetails.amount).toFixed(2)}</span>
          </div>
          ${bookingDetails.serviceFee ? `
          <div class="summary-row">
            <span class="label">Service Fee</span>
            <span>£${bookingDetails.serviceFee.toFixed(2)}</span>
          </div>
          ` : ''}
          <div class="summary-row total">
            <span class="label">Total</span>
            <span>£${bookingDetails.amount.toFixed(2)}</span>
          </div>
        </div>

        <div class="payment-form">
          <div class="form-group">
            <label>Card Details</label>
            <div id="${this.containerId}-card-element" class="card-element"></div>
            <div id="${this.containerId}-card-errors" class="card-errors"></div>
          </div>

          <div class="payment-actions">
            <button type="button" class="btn-cancel" id="${this.containerId}-cancel">Cancel</button>
            <button type="button" class="btn-pay" id="${this.containerId}-submit">
              <span class="btn-text">Pay £${bookingDetails.amount.toFixed(2)}</span>
              <span class="btn-loading" style="display: none;">Processing...</span>
            </button>
          </div>
        </div>

        <div class="payment-footer">
          <div class="secure-badge">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
              <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
            </svg>
            Secured by Stripe
          </div>
          <div class="card-brands">
            <span>Visa</span>
            <span>Mastercard</span>
            <span>Amex</span>
          </div>
        </div>
      </div>
    `;

    this.container.innerHTML = html;
    this.addStyles();

    // Mount card element if not in demo mode
    if (!this.isDemoMode && this.cardElement) {
      this.cardElement.mount(`#${this.containerId}-card-element`);

      this.cardElement.on('change', (event) => {
        const errorEl = document.getElementById(`${this.containerId}-card-errors`);
        if (event.error) {
          errorEl.textContent = event.error.message;
        } else {
          errorEl.textContent = '';
        }
      });
    }

    // Bind events
    document.getElementById(`${this.containerId}-cancel`).addEventListener('click', () => {
      this.options.onCancel();
    });

    document.getElementById(`${this.containerId}-submit`).addEventListener('click', () => {
      this.processPayment();
    });
  }

  /**
   * Render demo mode (when Stripe is not configured)
   */
  renderDemoMode() {
    if (!this.container) return;

    const existingCardEl = document.getElementById(`${this.containerId}-card-element`);
    if (existingCardEl) {
      existingCardEl.innerHTML = `
        <div style="padding: 16px; background: #fffbeb; border-radius: 8px; text-align: center;">
          <p style="font-size: 14px; color: #92400e; margin: 0;">
            <strong>Demo Mode</strong><br>
            Stripe is not configured. Click "Pay" to simulate a successful payment.
          </p>
        </div>
      `;
    }
  }

  /**
   * Process the payment
   */
  async processPayment() {
    if (this.isProcessing) return;
    this.isProcessing = true;

    const submitBtn = document.getElementById(`${this.containerId}-submit`);
    const btnText = submitBtn.querySelector('.btn-text');
    const btnLoading = submitBtn.querySelector('.btn-loading');

    btnText.style.display = 'none';
    btnLoading.style.display = 'inline';
    submitBtn.disabled = true;

    try {
      // Demo mode - simulate success
      if (this.isDemoMode) {
        await new Promise(resolve => setTimeout(resolve, 1500));
        this.options.onSuccess({
          paymentIntentId: 'pi_demo_' + Date.now(),
          status: 'succeeded',
          demo: true
        });
        return;
      }

      // Create payment intent
      const intentRes = await fetch(`${this.options.apiEndpoint}?route=payment/create-intent`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({
          amount: this.bookingDetails.amount,
          booking_type: this.bookingDetails.bookingType || 'garage',
          booking_id: this.bookingDetails.bookingId,
          space_id: this.bookingDetails.spaceId
        })
      });

      const intentData = await intentRes.json();

      if (!intentData.success) {
        throw new Error(intentData.error || 'Failed to create payment');
      }

      this.paymentIntentId = intentData.paymentIntentId;
      this.clientSecret = intentData.clientSecret;

      // If test mode with mock data
      if (intentData.testMode) {
        await new Promise(resolve => setTimeout(resolve, 1000));
        this.options.onSuccess({
          paymentIntentId: intentData.paymentIntentId,
          status: 'succeeded',
          testMode: true
        });
        return;
      }

      // Confirm the payment with Stripe
      const { error, paymentIntent } = await this.stripe.confirmCardPayment(
        this.clientSecret,
        {
          payment_method: {
            card: this.cardElement,
            billing_details: {
              name: this.bookingDetails.customerName || undefined
            }
          }
        }
      );

      if (error) {
        throw new Error(error.message);
      }

      if (paymentIntent.status === 'succeeded') {
        // Confirm with backend
        await fetch(`${this.options.apiEndpoint}?route=payment/confirm`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'same-origin',
          body: JSON.stringify({
            payment_intent_id: paymentIntent.id
          })
        });

        this.options.onSuccess({
          paymentIntentId: paymentIntent.id,
          status: paymentIntent.status
        });
      } else {
        throw new Error('Payment was not completed');
      }

    } catch (error) {
      console.error('Payment error:', error);
      document.getElementById(`${this.containerId}-card-errors`).textContent = error.message;
      this.options.onError(error);

      btnText.style.display = 'inline';
      btnLoading.style.display = 'none';
      submitBtn.disabled = false;
    } finally {
      this.isProcessing = false;
    }
  }

  /**
   * Add component styles
   */
  addStyles() {
    if (document.getElementById('stripe-payment-styles')) return;

    const styles = document.createElement('style');
    styles.id = 'stripe-payment-styles';
    styles.textContent = `
      .stripe-payment-container {
        background: white;
        border-radius: 16px;
        padding: 24px;
        max-width: 420px;
        margin: 0 auto;
      }

      .payment-header {
        text-align: center;
        margin-bottom: 24px;
      }

      .payment-header h3 {
        font-family: 'Poppins', sans-serif;
        font-size: 20px;
        font-weight: 600;
        color: #1f2937;
        margin: 0 0 4px 0;
      }

      .payment-header p {
        font-size: 14px;
        color: #6b7280;
        margin: 0;
      }

      .booking-summary {
        background: #f9fafb;
        border-radius: 12px;
        padding: 16px;
        margin-bottom: 24px;
      }

      .summary-row {
        display: flex;
        justify-content: space-between;
        padding: 8px 0;
        font-size: 14px;
      }

      .summary-row .label {
        color: #6b7280;
      }

      .summary-row.subtotal {
        border-top: 1px solid #e5e7eb;
        margin-top: 8px;
        padding-top: 12px;
      }

      .summary-row.total {
        font-weight: 700;
        font-size: 16px;
        color: #1f2937;
      }

      .summary-divider {
        height: 1px;
        background: #e5e7eb;
        margin: 8px 0;
      }

      .payment-form .form-group {
        margin-bottom: 20px;
      }

      .payment-form label {
        display: block;
        font-size: 14px;
        font-weight: 600;
        color: #374151;
        margin-bottom: 8px;
      }

      .card-element {
        padding: 14px 16px;
        border: 2px solid #e5e7eb;
        border-radius: 10px;
        background: white;
        transition: border-color 0.2s;
      }

      .card-element:focus-within {
        border-color: #2563eb;
      }

      .card-errors {
        color: #ef4444;
        font-size: 13px;
        margin-top: 8px;
        min-height: 20px;
      }

      .payment-actions {
        display: flex;
        gap: 12px;
      }

      .btn-cancel {
        flex: 1;
        padding: 14px 20px;
        background: white;
        border: 2px solid #e5e7eb;
        border-radius: 10px;
        font-size: 15px;
        font-weight: 600;
        color: #374151;
        cursor: pointer;
        transition: all 0.2s;
      }

      .btn-cancel:hover {
        border-color: #d1d5db;
        background: #f9fafb;
      }

      .btn-pay {
        flex: 2;
        padding: 14px 20px;
        background: #2563eb;
        border: none;
        border-radius: 10px;
        font-size: 15px;
        font-weight: 600;
        color: white;
        cursor: pointer;
        transition: all 0.2s;
      }

      .btn-pay:hover:not(:disabled) {
        background: #1d4ed8;
      }

      .btn-pay:disabled {
        opacity: 0.7;
        cursor: not-allowed;
      }

      .payment-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 20px;
        padding-top: 16px;
        border-top: 1px solid #e5e7eb;
      }

      .secure-badge {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 12px;
        color: #6b7280;
      }

      .secure-badge svg {
        color: #10b981;
      }

      .card-brands {
        display: flex;
        gap: 8px;
        font-size: 11px;
        color: #9ca3af;
      }
    `;
    document.head.appendChild(styles);
  }

  /**
   * Destroy the component
   */
  destroy() {
    if (this.cardElement) {
      this.cardElement.destroy();
    }
    if (this.container) {
      this.container.innerHTML = '';
    }
    this.isInitialized = false;
  }
}

// Export for use
if (typeof module !== 'undefined' && module.exports) {
  module.exports = StripePayment;
}
