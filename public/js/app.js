
async function apiPost(path, payload) {
  // Use query parameter format for Windows/XAMPP compatibility
  const route = path.startsWith('/') ? path.substring(1) : path;
  const res = await fetch(API + "?route=" + route, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    credentials: "same-origin",
    body: JSON.stringify(payload),
  });
  let data = null;
  try {
    data = await res.json();
  } catch {
    data = { error: "Server returned invalid JSON." };
  }
  return { res, data };
}

async function apiGet(path) {
  // Use query parameter format for Windows/XAMPP compatibility
  const route = path.startsWith('/') ? path.substring(1) : path;
  const res = await fetch(API + "?route=" + route, { credentials: "same-origin" });
  let data = null;
  try {
    data = await res.json();
  } catch {
    data = { error: "Server returned invalid JSON." };
  }
  return { res, data };
}

async function loadGarages() {
  const select = document.getElementById("garage_id");
  if (!select) {
    console.log("Garage select element not found");
    return;
  }

  console.log("Loading garages...");

  // Basic loading state
  select.innerHTML = "";
  const optLoading = document.createElement("option");
  optLoading.value = "";
  optLoading.textContent = "Loading garages...";
  select.appendChild(optLoading);
  select.disabled = true;

  try {
    const { res, data } = await apiGet("/garages");
    console.log("Garages API response:", res.status, data);

    const garages = data && data.garages;

    select.innerHTML = "";

    if (!res.ok) {
      console.error("Garages API error:", data);
      const opt = document.createElement("option");
      opt.value = "";
      opt.textContent = "Error loading garages";
      select.appendChild(opt);
      select.disabled = true;
      return;
    }

    if (!Array.isArray(garages) || garages.length === 0) {
      console.log("No garages found");
      const opt = document.createElement("option");
      opt.value = "";
      opt.textContent = "No garages available";
      select.appendChild(opt);
      select.disabled = true;
      return;
    }

    // Add a placeholder option
    const placeholderOpt = document.createElement("option");
    placeholderOpt.value = "";
    placeholderOpt.textContent = "-- Select a garage --";
    select.appendChild(placeholderOpt);

    garages.forEach((g) => {
      const opt = document.createElement("option");
      opt.value = g.garage_id;
      opt.textContent = g.garage_name || `Garage #${g.garage_id}`;
      select.appendChild(opt);
    });

    select.disabled = false;
    console.log("Loaded", garages.length, "garages");

  } catch (err) {
    console.error("Load garages error:", err);
    select.innerHTML = "";
    const opt = document.createElement("option");
    opt.value = "";
    opt.textContent = "Error loading garages";
    select.appendChild(opt);
    select.disabled = true;
  }
}

function login() {
  const emailEl = document.getElementById("login_email");
  const passEl = document.getElementById("login_password");
  const msgEl =
    document.getElementById("loginMessage") ||
    document.getElementById("login_msg");

  apiPost("/login", {
    email: emailEl ? emailEl.value : "",
    password: passEl ? passEl.value : "",
  }).then(({ res, data }) => {
    if (res.ok && data && data.message) window.location = "dashboard.html";
    else if (msgEl) msgEl.innerText = (data && (data.error || data.message)) || "Login failed.";
  });
}

function register() {
  const nameEl = document.getElementById("reg_name");
  const emailEl = document.getElementById("reg_email");
  const passEl = document.getElementById("reg_password");
  const msgEl =
    document.getElementById("registerMessage") ||
    document.getElementById("reg_msg");

  apiPost("/register", {
    name: nameEl ? nameEl.value : "",
    email: emailEl ? emailEl.value : "",
    password: passEl ? passEl.value : "",
  }).then(({ data }) => {
    if (msgEl) msgEl.innerText = (data && (data.message || data.error)) || "Registration failed.";
  });
}

function reserve() {
  console.log("=== RESERVE FUNCTION CALLED ===");

  const msgEl = document.getElementById("reserve_msg");
  const garageEl = document.getElementById("garage_id");
  const startEl = document.getElementById("start_time");
  const endEl = document.getElementById("end_time");

  // Helper function to show message with styling
  function showReserveMessage(message, type) {
    if (msgEl) {
      msgEl.style.display = "block";
      msgEl.innerText = message;
      if (type === "success") {
        msgEl.style.background = "#d4edda";
        msgEl.style.color = "#155724";
        msgEl.style.border = "1px solid #c3e6cb";
      } else if (type === "loading") {
        msgEl.style.background = "#d1ecf1";
        msgEl.style.color = "#0c5460";
        msgEl.style.border = "1px solid #bee5eb";
      } else {
        msgEl.style.background = "#f8d7da";
        msgEl.style.color = "#721c24";
        msgEl.style.border = "1px solid #f5c6cb";
      }
    }
    console.log("Reserve message:", message, type);
  }

  // Check if elements exist
  if (!garageEl || !startEl || !endEl) {
    console.error("Form elements not found:", { garageEl, startEl, endEl });
    showReserveMessage("Error: Form elements not found. Please refresh the page.", "error");
    return;
  }

  // Get values
  const garageId = garageEl.value;
  const startTime = startEl.value;
  const endTime = endEl.value;

  console.log("Form values:", { garageId, startTime, endTime });

  // Validation
  if (!garageId) {
    showReserveMessage("Please select a garage.", "error");
    return;
  }
  if (!startTime) {
    showReserveMessage("Please select a start time.", "error");
    return;
  }
  if (!endTime) {
    showReserveMessage("Please select an end time.", "error");
    return;
  }

  // Validate that start time is in the future
  const startDate = new Date(startTime);
  const endDate = new Date(endTime);
  const now = new Date();

  if (startDate < now) {
    showReserveMessage("Start time cannot be in the past.", "error");
    return;
  }

  // Validate that end time is after start time
  if (startDate >= endDate) {
    showReserveMessage("End time must be after start time.", "error");
    return;
  }

  // Show loading state
  showReserveMessage("Processing reservation...", "loading");

  console.log("Sending reservation request to API...");

  // Make API call
  apiPost("/reserve", {
    garage_id: parseInt(garageId),
    start_time: startTime,
    end_time: endTime,
  }).then(({ res, data }) => {
    console.log("Reserve response:", res.status, data);
    if (res.ok && data && data.success) {
      const priceInfo = data.total_price ? ` Total: £${data.total_price}` : "";
      showReserveMessage("Reservation confirmed!" + priceInfo + " Redirecting to invoice...", "success");
      setTimeout(function() {
        window.location.href = "invoice.html";
      }, 1500);
    } else {
      const errorMsg = (data && (data.error || data.message)) || "Reservation failed. Please try again.";
      showReserveMessage(errorMsg, "error");
    }
  }).catch(function(err) {
    console.error("Reserve error:", err);
    showReserveMessage("Network error. Please check your connection.", "error");
  });
}

// Navigation functions
function goToInvoice() {
  window.location.href = "invoice.html";
}

function goToDashboard() {
  window.location.href = "customer_dashboard.html";
}

function logout() {
  fetch(API + "?route=logout", { credentials: "same-origin" })
    .then(() => {
      window.location.href = "index.html";
    })
    .catch(err => {
      console.error("Logout error:", err);
      window.location.href = "index.html";
    });
}

// Load invoice data with proper API integration
async function loadInvoice() {
  try {
    console.log('Loading invoice data...');
    
    // Check authentication
    const authRes = await fetch(`${API}?route=me`, {
      credentials: "include"
    });
    
    console.log('Auth check response:', authRes.status);
    
    if (!authRes.ok) {
      throw new Error('Not authenticated');
    }
    
    // Get invoice data from the invoice endpoint
    console.log('Fetching invoice from:', `${API}?route=invoice`);
    const invoiceRes = await fetch(`${API}?route=invoice`, {
      credentials: "include"
    });
    
    console.log('Invoice response status:', invoiceRes.status);
    
    if (!invoiceRes.ok) {
      const errorText = await invoiceRes.text();
      console.error('Invoice error response:', errorText);
      throw new Error('Failed to load invoice');
    }
    
    const data = await invoiceRes.json();
    console.log('Invoice data received:', data);
    
    // Check if we have an error
    if (data.error) {
      throw new Error(data.error);
    }
    
    const reservations = data.reservations || [];
    const tbody = document.getElementById('invoiceTableBody');
    
    if (!tbody) {
      console.error('Invoice table body not found');
      return;
    }
    
    // Handle empty reservations
    if (reservations.length === 0) {
      tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:20px;color:#999;">No reservations found. Create your first reservation!</td></tr>';
      const countEl = document.getElementById('count');
      const totalEl = document.getElementById('total');
      if (countEl) countEl.textContent = '0';
      if (totalEl) totalEl.textContent = '0.00';
      return;
    }
    
    // Build reservation table
    let totalCost = 0;
    let html = '';
    
    reservations.forEach(reservation => {
      const start = new Date(reservation.start_time);
      const end = new Date(reservation.end_time);
      const duration = (end - start) / (1000 * 60 * 60); // hours
      const hours = Math.round(duration * 10) / 10;
      const price = parseFloat(reservation.price || 0);
      totalCost += price;
      
      html += `
        <tr>
          <td>${reservation.reservation_id}</td>
          <td>${reservation.garage_name || 'Garage #' + reservation.garage_id}</td>
          <td>${start.toLocaleDateString('en-GB')} ${start.toLocaleTimeString('en-GB', {hour:'2-digit', minute:'2-digit'})}</td>
          <td>${end.toLocaleDateString('en-GB')} ${end.toLocaleTimeString('en-GB', {hour:'2-digit', minute:'2-digit'})}</td>
          <td>${hours}h</td>
          <td style="font-weight:600;color:#27ae60;">£${price.toFixed(2)}</td>
        </tr>
      `;
    });
    
    tbody.innerHTML = html;
    
    // Update totals
    const countEl = document.getElementById('count');
    const totalEl = document.getElementById('total');
    if (countEl) countEl.textContent = reservations.length;
    if (totalEl) totalEl.textContent = totalCost.toFixed(2);
    
  } catch (err) {
    console.error('Load invoice error:', err);
    const tbody = document.getElementById('invoiceTableBody');
    if (tbody) {
      tbody.innerHTML = `
        <tr>
          <td colspan="6" style="text-align:center;padding:20px;color:#e74c3c;">
            <strong>Error loading invoice:</strong> ${err.message}<br>
            <small>Please try logging in again.</small>
          </td>
        </tr>
      `;
    }
  }
}

// Confirm app.js loaded successfully
console.log("=== app.js loaded successfully ===");
console.log("Available functions: apiPost, apiGet, loadGarages, reserve, login, register, logout, loadInvoice");

// Test that reserve function is accessible
if (typeof reserve === "function") {
  console.log("reserve() function is available");
} else {
  console.error("ERROR: reserve() function is NOT defined!");
}
