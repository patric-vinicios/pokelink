# Implementation Plan: Authentication and Session Management

**Prerequisites:**
- F01's Laravel 12 project with the Breeze/Livewire scaffold already installed and booting (`docker compose up -d` reaches the login screen)
- `APP_LOCALE=pt_BR` already set in `.env.example`
- No new packages required — Livewire's bundled Alpine covers the client-side countdown

---

### Stage 1: Scope Cleanup

**1. Remove Out-of-Scope Routes** - Strip the `forgot-password`, `reset-password`, `verify-email`, and `confirm-password` route registrations from `routes/auth.php`, leaving only `login` and `register` in place.

**2. Remove Out-of-Scope Volt Pages and Controller** - Delete the four Volt page components and the e-mail verification controller now that nothing routes to them.

**3. Remove Out-of-Scope Test Coverage** - Delete the three Pest test files exercising the removed flows, since they test behavior the product no longer exposes.

---

### Stage 2: Localization and Guest Messaging

**4. Portuguese Auth Strings** - Add the language file overriding the framework's generic-failure and throttle-lockout message keys with the exact Portuguese copy the specification defines.

**5. Throttle Countdown** - Extend the login form object to expose the raw remaining-lockout-seconds value, and extend the login page to render a live countdown driven by that value instead of a static number.

**6. Guest Redirect Message** - Configure the guest-redirect and authenticated-redirect behavior so an unauthenticated visitor reaching a protected route is sent to the login screen with the flashed message, and an authenticated visitor reaching the login or registration screen is sent back into the application.

---

### Stage 3: Session Failure Handling

**7. Session-Expired Redirect** - Register the exception handling that turns an expired CSRF/session token into a redirect to the login screen carrying the flashed session-expired message, covering both a standard form submission and a Livewire component round-trip.

**8. Session-Store-Unreachable Page** - Register the exception handling that recognizes a database connection failure occurring while the session store is read or written, and add the dedicated Portuguese error view it renders.

---

### Stage 4: Test Coverage

**9. Guest Access and Redirect Coverage** - Extend the authentication test file with cases proving every currently-protected route redirects a guest to the login screen with the expected message, and that a successful login restores the originally intended destination.

**10. Login Failure and Throttling Coverage** - Add cases asserting the failure message is identical whether the password is wrong or the e-mail is unregistered, and that a sixth attempt within a minute is blocked with the remaining lockout time surfaced.

**11. Session Lifecycle Coverage** - Add cases covering logout invalidating the session so that revisiting a protected route afterward redirects to login rather than serving cached authenticated content, an authenticated visitor being redirected away from the login and registration screens, and the remember-me cookie's extended lifetime.

**12. Session Failure Coverage** - Create the new test file exercising the session-expired redirect and the session-store-unreachable page introduced in Stage 3.
