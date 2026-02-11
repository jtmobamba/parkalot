/**
 * Stripe Payment Component
 *
 * Reusable payment component for ParkaLot bookings.
 * Handles Stripe Elements integration, payment processing, and error handling.
 * Uses individual card input fields (Card Number, Expiry, CVC) for better UX.
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
    // Individual card elements
    this.cardNumberElement = null;
    this.cardExpiryElement = null;
    this.cardCvcElement = null;
    this.paymentIntentId = null;
    this.clientSecret = null;
    this.isProcessing = false;
    this.isInitialized = false;
  }

  /**
   * Initialize Stripe and create card elements
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

      // Shared style for all card elements
      const elementStyle = {
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
      };

      // Create individual card elements
      this.cardNumberElement = this.elements.create('cardNumber', {
        style: elementStyle,
        placeholder: '1234 5678 9012 3456',
        showIcon: true
      });

      this.cardExpiryElement = this.elements.create('cardExpiry', {
        style: elementStyle,
        placeholder: 'MM / YY'
      });

      this.cardCvcElement = this.elements.create('cardCvc', {
        style: elementStyle,
        placeholder: 'CVC'
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
   * Render the payment form with individual card input fields
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
          <div class="payment-section-title">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/>
              <line x1="1" y1="10" x2="23" y2="10"/>
            </svg>
            Card Payment Details
          </div>

          <!-- Cardholder Name -->
          <div class="form-group">
            <label for="${this.containerId}-cardholder-name">Cardholder Name</label>
            <input
              type="text"
              id="${this.containerId}-cardholder-name"
              class="card-input-field"
              placeholder="Name on card"
              autocomplete="cc-name"
            />
          </div>

          <!-- Card Number -->
          <div class="form-group">
            <label for="${this.containerId}-card-number">Card Number</label>
            <div id="${this.containerId}-card-number" class="card-element card-number-element"></div>
            <div id="${this.containerId}-card-number-errors" class="card-errors"></div>
          </div>

          <!-- Expiry and CVC in a row -->
          <div class="form-row">
            <div class="form-group form-group-half">
              <label for="${this.containerId}-card-expiry">Expiry Date</label>
              <div id="${this.containerId}-card-expiry" class="card-element card-expiry-element"></div>
              <div id="${this.containerId}-card-expiry-errors" class="card-errors"></div>
            </div>
            <div class="form-group form-group-half">
              <label for="${this.containerId}-card-cvc">CVC / CVV</label>
              <div id="${this.containerId}-card-cvc" class="card-element card-cvc-element"></div>
              <div id="${this.containerId}-card-cvc-errors" class="card-errors"></div>
            </div>
          </div>

          <!-- General error display -->
          <div id="${this.containerId}-card-errors" class="card-errors general-error"></div>

          <div class="payment-actions">
            <button type="button" class="btn-cancel" id="${this.containerId}-cancel">Cancel</button>
            <button type="button" class="btn-pay" id="${this.containerId}-submit">
              <span class="btn-text">Pay £${bookingDetails.amount.toFixed(2)}</span>
              <span class="btn-loading" style="display: none;">
                <span class="spinner"></span> Processing...
              </span>
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
            <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='32' height='20' viewBox='0 0 32 20'%3E%3Crect fill='%231A1F71' width='32' height='20' rx='2'/%3E%3Ctext x='16' y='14' font-size='8' fill='white' text-anchor='middle' font-family='Arial'%3EVISA%3C/text%3E%3C/svg%3E" alt="Visa" title="Visa">
            <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='32' height='20' viewBox='0 0 32 20'%3E%3Crect fill='%23EB001B' width='32' height='20' rx='2'/%3E%3Ccircle cx='12' cy='10' r='6' fill='%23EB001B'/%3E%3Ccircle cx='20' cy='10' r='6' fill='%23F79E1B'/%3E%3Cpath d='M16 5.5a6 6 0 0 0 0 9' fill='%23FF5F00'/%3E%3C/svg%3E" alt="Mastercard" title="Mastercard">
            <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='32' height='20' viewBox='0 0 32 20'%3E%3Crect fill='%23006FCF' width='32' height='20' rx='2'/%3E%3Ctext x='16' y='13' font-size='6' fill='white' text-anchor='middle' font-family='Arial'%3EAMEX%3C/text%3E%3C/svg%3E" alt="Amex" title="American Express">
          </div>
        </div>
      </div>
    `;

    this.container.innerHTML = html;
    this.addStyles();

    // Mount individual card elements if not in demo mode
    if (!this.isDemoMode && this.cardNumberElement && this.cardExpiryElement && this.cardCvcElement) {
      // Mount card number element
      this.cardNumberElement.mount(`#${this.containerId}-card-number`);
      this.cardNumberElement.on('change', (event) => {
        const errorEl = document.getElementById(`${this.containerId}-card-number-errors`);
        errorEl.textContent = event.error ? event.error.message : '';
      });

      // Mount expiry element
      this.cardExpiryElement.mount(`#${this.containerId}-card-expiry`);
      this.cardExpiryElement.on('change', (event) => {
        const errorEl = document.getElementById(`${this.containerId}-card-expiry-errors`);
        errorEl.textContent = event.error ? event.error.message : '';
      });

      // Mount CVC element
      this.cardCvcElement.mount(`#${this.containerId}-card-cvc`);
      this.cardCvcElement.on('change', (event) => {
        const errorEl = document.getElementById(`${this.containerId}-card-cvc-errors`);
        errorEl.textContent = event.error ? event.error.message : '';
      });
    }

    // Bind events
    document.getElementById(`${this.containerId}-cancel`).addEventListener('click', () => {
      this.options.onCancel();
    });

    document.getElementById(`${this.containerId}-submit`).addEventListener('click', () => {
      this.processPayment();
    });

    // Allow Enter key to submit payment
    const cardholderInput = document.getElementById(`${this.containerId}-cardholder-name`);
    if (cardholderInput) {
      cardholderInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
          this.processPayment();
        }
      });
    }
  }

  /**
   * Render demo mode (when Stripe is not configured)
   */
  renderDemoMode() {
    if (!this.container) return;

    // Show demo notice in card number field
    const cardNumberEl = document.getElementById(`${this.containerId}-card-number`);
    if (cardNumberEl) {
      cardNumberEl.innerHTML = `
        <input type="text" class="demo-card-input" placeholder="4242 4242 4242 4242" value="4242 4242 4242 4242" readonly style="width: 100%; padding: 12px; border: none; background: transparent; font-size: 16px; color: #1f2937;">
      `;
    }

    const cardExpiryEl = document.getElementById(`${this.containerId}-card-expiry`);
    if (cardExpiryEl) {
      cardExpiryEl.innerHTML = `
        <input type="text" class="demo-card-input" placeholder="12/28" value="12/28" readonly style="width: 100%; padding: 12px; border: none; background: transparent; font-size: 16px; color: #1f2937;">
      `;
    }

    const cardCvcEl = document.getElementById(`${this.containerId}-card-cvc`);
    if (cardCvcEl) {
      cardCvcEl.innerHTML = `
        <input type="text" class="demo-card-input" placeholder="123" value="123" readonly style="width: 100%; padding: 12px; border: none; background: transparent; font-size: 16px; color: #1f2937;">
      `;
    }

    // Show demo mode banner
    const generalErrorEl = document.getElementById(`${this.containerId}-card-errors`);
    if (generalErrorEl) {
      generalErrorEl.innerHTML = `
        <div style="padding: 12px 16px; background: #fffbeb; border: 1px solid #fcd34d; border-radius: 8px; text-align: center; margin-top: 12px;">
          <p style="font-size: 13px; color: #92400e; margin: 0;">
            <strong>Demo Mode</strong> - Stripe API not configured. Click "Pay" to simulate a successful payment.
          </p>
        </div>
      `;
    }
  }

  /**
   * Process the payment using individual card elements
   */
  async processPayment() {
    if (this.isProcessing) return;
    this.isProcessing = true;

    const submitBtn = document.getElementById(`${this.containerId}-submit`);
    const btnText = submitBtn.querySelector('.btn-text');
    const btnLoading = submitBtn.querySelector('.btn-loading');
    const generalErrorEl = document.getElementById(`${this.containerId}-card-errors`);

    // Clear previous errors
    generalErrorEl.textContent = '';

    // Get cardholder name
    const cardholderNameInput = document.getElementById(`${this.containerId}-cardholder-name`);
    const cardholderName = cardholderNameInput ? cardholderNameInput.value.trim() : '';

    // Validate cardholder name
    if (!cardholderName && !this.isDemoMode) {
      generalErrorEl.textContent = 'Please enter the cardholder name';
      this.isProcessing = false;
      return;
    }

    btnText.style.display = 'none';
    btnLoading.style.display = 'inline-flex';
    submitBtn.disabled = true;

    try {
      // Demo mode - simulate success
      if (this.isDemoMode) {
        await new Promise(resolve => setTimeout(resolve, 1500));
        this.options.onSuccess({
          paymentIntentId: 'pi_demo_' + Date.now(),
          status: 'succeeded',
          demo: true,
          cardholderName: cardholderName || 'Demo User'
        });
        return;
      }

      // Create payment intent on backend
      const intentRes = await fetch(`${this.options.apiEndpoint}?route=payment/create-intent`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({
          amount: this.bookingDetails.amount,
          booking_type: this.bookingDetails.bookingType || 'garage',
          booking_id: this.bookingDetails.bookingId,
          space_id: this.bookingDetails.spaceId,
          cardholder_name: cardholderName
        })
      });

      const intentData = await intentRes.json();

      if (!intentData.success) {
        throw new Error(intentData.error || 'Failed to create payment');
      }

      this.paymentIntentId = intentData.paymentIntentId;
      this.clientSecret = intentData.clientSecret;

      // If test mode with mock data (API keys not configured)
      if (intentData.testMode) {
        await new Promise(resolve => setTimeout(resolve, 1000));
        this.options.onSuccess({
          paymentIntentId: intentData.paymentIntentId,
          status: 'succeeded',
          testMode: true,
          cardholderName: cardholderName
        });
        return;
      }

      // Confirm the payment with Stripe using the card number element
      const { error, paymentIntent } = await this.stripe.confirmCardPayment(
        this.clientSecret,
        {
          payment_method: {
            card: this.cardNumberElement,  // Use cardNumberElement for individual elements
            billing_details: {
              name: cardholderName || undefined
            }
          }
        }
      );

      if (error) {
        throw new Error(error.message);
      }

      if (paymentIntent.status === 'succeeded') {
        // Confirm with backend to update payment record in database
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
          status: paymentIntent.status,
          cardholderName: cardholderName
        });
      } else if (paymentIntent.status === 'requires_action') {
        // 3D Secure authentication required - Stripe handles this automatically
        throw new Error('Additional authentication required. Please complete the verification.');
      } else {
        throw new Error('Payment was not completed. Status: ' + paymentIntent.status);
      }

    } catch (error) {
      console.error('Payment error:', error);
      generalErrorEl.textContent = error.message;
      this.options.onError(error);

      btnText.style.display = 'inline';
      btnLoading.style.display = 'none';
      submitBtn.disabled = false;
    } finally {
      this.isProcessing = false;
    }
  }

  /**
   * Add component styles for individual card input fields
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
        max-width: 460px;
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

      .payment-section-title {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 16px;
        font-weight: 600;
        color: #1f2937;
        margin-bottom: 20px;
        padding-bottom: 12px;
        border-bottom: 1px solid #e5e7eb;
      }

      .payment-section-title svg {
        color: #2563eb;
      }

      .payment-form .form-group {
        margin-bottom: 16px;
      }

      .payment-form .form-row {
        display: flex;
        gap: 12px;
      }

      .payment-form .form-group-half {
        flex: 1;
        min-width: 0;
      }

      .payment-form label {
        display: block;
        font-size: 13px;
        font-weight: 600;
        color: #374151;
        margin-bottom: 6px;
      }

      /* Text input for cardholder name */
      .card-input-field {
        width: 100%;
        padding: 12px 14px;
        border: 2px solid #e5e7eb;
        border-radius: 10px;
        font-size: 16px;
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        color: #1f2937;
        background: white;
        transition: border-color 0.2s, box-shadow 0.2s;
      }

      .card-input-field:focus {
        outline: none;
        border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
      }

      .card-input-field::placeholder {
        color: #9ca3af;
      }

      /* Stripe Element containers */
      .card-element {
        padding: 12px 14px;
        border: 2px solid #e5e7eb;
        border-radius: 10px;
        background: white;
        transition: border-color 0.2s, box-shadow 0.2s;
        min-height: 48px;
      }

      .card-element:focus-within {
        border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
      }

      .card-number-element {
        /* Extra padding for card icon */
      }

      .card-errors {
        color: #ef4444;
        font-size: 12px;
        margin-top: 4px;
        min-height: 16px;
      }

      .card-errors.general-error {
        text-align: center;
        margin-top: 8px;
        min-height: auto;
      }

      .payment-actions {
        display: flex;
        gap: 12px;
        margin-top: 20px;
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
        background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
        border: none;
        border-radius: 10px;
        font-size: 15px;
        font-weight: 600;
        color: white;
        cursor: pointer;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
      }

      .btn-pay:hover:not(:disabled) {
        background: linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
      }

      .btn-pay:disabled {
        opacity: 0.7;
        cursor: not-allowed;
        transform: none;
        box-shadow: none;
      }

      .btn-pay .btn-loading {
        display: inline-flex;
        align-items: center;
        gap: 8px;
      }

      .btn-pay .spinner {
        width: 16px;
        height: 16px;
        border: 2px solid rgba(255, 255, 255, 0.3);
        border-top-color: white;
        border-radius: 50%;
        animation: spin 0.8s linear infinite;
      }

      @keyframes spin {
        to { transform: rotate(360deg); }
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
        gap: 6px;
        align-items: center;
      }

      .card-brands img {
        height: 20px;
        width: auto;
        border-radius: 3px;
      }

      /* Responsive adjustments */
      @media (max-width: 480px) {
        .stripe-payment-container {
          padding: 16px;
        }

        .payment-form .form-row {
          flex-direction: column;
          gap: 16px;
        }

        .payment-actions {
          flex-direction: column-reverse;
        }

        .btn-cancel, .btn-pay {
          flex: none;
          width: 100%;
        }
      }
    `;
    document.head.appendChild(styles);
  }

  /**
   * Destroy the component and clean up all card elements
   */
  destroy() {
    // Destroy individual card elements
    if (this.cardNumberElement) {
      this.cardNumberElement.destroy();
      this.cardNumberElement = null;
    }
    if (this.cardExpiryElement) {
      this.cardExpiryElement.destroy();
      this.cardExpiryElement = null;
    }
    if (this.cardCvcElement) {
      this.cardCvcElement.destroy();
      this.cardCvcElement = null;
    }

    // Clear container
    if (this.container) {
      this.container.innerHTML = '';
    }

    // Reset state
    this.isInitialized = false;
    this.isProcessing = false;
    this.paymentIntentId = null;
    this.clientSecret = null;
    this.bookingDetails = null;
  }

  /**
   * Get the cardholder name from the form
   */
  getCardholderName() {
    const input = document.getElementById(`${this.containerId}-cardholder-name`);
    return input ? input.value.trim() : '';
  }
}

// Export for use
if (typeof module !== 'undefined' && module.exports) {
  module.exports = StripePayment;
}
