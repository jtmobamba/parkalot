/* ================================
   ParkaLot System - auth.js
   Handles:
   - Login
   - Register
   - Logout
   - Email Verification (OTP)
   - Inline error messaging
================================ */

/* ---------- Helper Functions ---------- */

function showMessage(element, message, type = "error") {
  if (!element) return;
  element.style.display = "block";
  element.textContent = message;
  
  if (type === "error") {
    element.style.color = "#c0392b";
    element.style.backgroundColor = "#fadbd8";
    element.style.border = "1px solid #e74c3c";
  } else if (type === "success") {
    element.style.color = "#27ae60";
    element.style.backgroundColor = "#d5f4e6";
    element.style.border = "1px solid #27ae60";
  } else if (type === "info") {
    element.style.color = "#2980b9";
    element.style.backgroundColor = "#d6eaf8";
    element.style.border = "1px solid #3498db";
  }
}

function hideMessage(element) {
  if (!element) return;
  element.style.display = "none";
  element.textContent = "";
}

/* ---------- Email Verification Modal ---------- */

function showVerificationModal() {
  const modal = document.getElementById("verificationModal");
  if (modal) {
    modal.style.display = "flex";
    document.getElementById("otpInput").value = "";
    document.getElementById("otpInput").focus();
  }
}

function hideVerificationModal() {
  const modal = document.getElementById("verificationModal");
  if (modal) {
    modal.style.display = "none";
  }
}

/* ---------- LOGIN ---------- */

const loginForm = document.getElementById("loginForm");
// Support current HTML ids (and keep backward compatibility)
const loginMsg =
  document.getElementById("loginMessage") ||
  document.getElementById("login_msg");

if (loginForm) {
  loginForm.addEventListener("submit", async (e) => {
    e.preventDefault();

    hideMessage(loginMsg);

    const email = document.getElementById("login_email").value.trim();
    const password = document.getElementById("login_password").value.trim();

    /* Client-side validation */
    if (!email || !password) {
      showMessage(
        loginMsg,
        "Please enter both email and password."
      );
      return;
    }

    // Email format validation
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
      showMessage(loginMsg, "Please enter a valid email address.");
      return;
    }

    try {
      const res = await fetch("/api/index.php?route=login", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ email, password }),
        credentials: "include",
      });

      const data = await res.json();

      // Check if email verification is required (even if status is 401)
      if (data && data.code === "email_not_verified") {
        showMessage(loginMsg, data.error, "info");
        // Show verification modal and send OTP
        showVerificationModal();
        await sendOTP();
        return;
      }

      if (!res.ok) {
        // Check for rate limiting
        if (data && data.code === "rate_limited") {
          showMessage(loginMsg, data.error || "Too many login attempts. Please try again later.");
          return;
        }
        
        // Other errors
        const msg =
          (data && (data.error || data.message)) ||
          "Invalid email or password.";
        showMessage(loginMsg, msg);
        return;
      }

      /* Successful login */
      window.location.href = "dashboard.html";
    } catch (err) {
      showMessage(loginMsg, "Server error. Please try again.");
      console.error(err);
    }
  });
}

/* ---------- REGISTER ---------- */

const registerForm = document.getElementById("registerForm");
const registerMsg =
  document.getElementById("registerMessage") ||
  document.getElementById("register_msg");

if (registerForm) {
  registerForm.addEventListener("submit", async (e) => {
    e.preventDefault();

    hideMessage(registerMsg);

    const name =
      (document.getElementById("reg_name") ||
        document.getElementById("register_name"))?.value.trim() || "";
    const email =
      (document.getElementById("reg_email") ||
        document.getElementById("register_email"))?.value.trim() || "";
    const password =
      (document.getElementById("reg_password") ||
        document.getElementById("register_password"))?.value.trim() || "";

    /* Client-side validation */
    if (!name || !email || !password) {
      showMessage(
        registerMsg,
        "Please fill in all fields."
      );
      return;
    }

    // Name validation
    if (name.length < 2) {
      showMessage(registerMsg, "Full name must be at least 2 characters.");
      return;
    }

    // Email validation
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
      showMessage(registerMsg, "Please enter a valid email address.");
      return;
    }

    // Password validation
    if (password.length < 8) {
      showMessage(registerMsg, "Password must be at least 8 characters.");
      return;
    }
    if (!/[A-Z]/.test(password)) {
      showMessage(registerMsg, "Password must contain at least one uppercase letter.");
      return;
    }
    if (!/[a-z]/.test(password)) {
      showMessage(registerMsg, "Password must contain at least one lowercase letter.");
      return;
    }
    if (!/[0-9]/.test(password)) {
      showMessage(registerMsg, "Password must contain at least one number.");
      return;
    }

    try {
      const res = await fetch("/api/index.php?route=register", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ name, email, password }),
        credentials: "include",
      });

      const data = await res.json();

      // Check if email verification is required (can be 200 or 400 status)
      if (data && (data.requires_verification || data.code === "verification_required")) {
        showMessage(
          registerMsg,
          "Registration successful! Please verify your email.",
          "success"
        );
        
        // Show verification modal and send OTP
        setTimeout(() => {
          showVerificationModal();
          sendOTP();
        }, 1500);
        return;
      }

      if (!res.ok) {
        // Backend uses `code: account_exists` + `error: "Account already exists."`
        if (data && data.code === "account_exists") {
          showMessage(registerMsg, "This Account Already Exists.");
        } else if (data && data.code === "validation") {
          // Show validation errors
          const msg = data.error || "Please check your input.";
          showMessage(registerMsg, msg);
        } else {
          const msg =
            (data && (data.error || data.message)) ||
            "Server error. Please try again.";
          showMessage(registerMsg, msg);
        }
        return;
      }

      /* Successful registration without verification */
      showMessage(
        registerMsg,
        "Registration successful. You may now login.",
        "success"
      );
      registerForm.reset();
    } catch (err) {
      showMessage(registerMsg, "Server error. Please try again.");
      console.error(err);
    }
  });
}

/* ---------- EMAIL VERIFICATION ---------- */

async function sendOTP() {
  const verificationMsg = document.getElementById("verificationMessage");
  
  try {
    showMessage(verificationMsg, "Sending verification code...", "info");
    
    const res = await fetch("/api/index.php?route=verify_email/send", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        credentials: "include",
      });

    const data = await res.json();

    if (!res.ok || data.error) {
      showMessage(
        verificationMsg,
        data.error || "Failed to send verification code.",
        "error"
      );
      return;
    }

    showMessage(
      verificationMsg,
      "Verification code sent! Check your email.",
      "success"
    );
    
    // Hide success message after 3 seconds
    setTimeout(() => hideMessage(verificationMsg), 3000);
  } catch (err) {
    showMessage(verificationMsg, "Failed to send code. Please try again.", "error");
    console.error(err);
  }
}

async function verifyOTP() {
  const otpInput = document.getElementById("otpInput");
  const verificationMsg = document.getElementById("verificationMessage");
  const otpCode = otpInput.value.trim();

  hideMessage(verificationMsg);

  if (!otpCode || otpCode.length !== 6) {
    showMessage(verificationMsg, "Please enter a valid 6-digit code.", "error");
    return;
  }

  try {
    const res = await fetch("/api/index.php?route=verify_email/confirm", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        credentials: "include",
        body: JSON.stringify({ otp_code: otpCode }),
      });

    const data = await res.json();

    if (!res.ok || data.error) {
      showMessage(
        verificationMsg,
        data.error || "Invalid verification code.",
        "error"
      );
      return;
    }

    if (data.success) {
      showMessage(
        verificationMsg,
        "Email verified successfully! Redirecting...",
        "success"
      );
      
      // Redirect to dashboard after successful verification
      setTimeout(() => {
        window.location.href = "dashboard.html";
      }, 1500);
    }
  } catch (err) {
    showMessage(verificationMsg, "Verification failed. Please try again.", "error");
    console.error(err);
  }
}

// Attach event listeners for verification modal
document.addEventListener("DOMContentLoaded", () => {
  const verifyButton = document.getElementById("verifyButton");
  const resendButton = document.getElementById("resendOtpButton");
  const otpInput = document.getElementById("otpInput");

  if (verifyButton) {
    verifyButton.addEventListener("click", verifyOTP);
  }

  if (resendButton) {
    resendButton.addEventListener("click", async () => {
      const verificationMsg = document.getElementById("verificationMessage");
      showMessage(verificationMsg, "Resending code...", "info");
      await sendOTP();
    });
  }

  if (otpInput) {
    // Allow verification on Enter key
    otpInput.addEventListener("keypress", (e) => {
      if (e.key === "Enter") {
        verifyOTP();
      }
    });
    
    // Only allow numbers
    otpInput.addEventListener("input", (e) => {
      e.target.value = e.target.value.replace(/[^0-9]/g, "");
    });
  }
});

/* ---------- LOGOUT ---------- */

async function logout() {
  try {
    await fetch("/api/index.php?route=logout", { credentials: "include" });
    window.location.href = "index.html";
  } catch (err) {
    console.error("Logout failed:", err);
  }
}
