# Implementation Plan: User Registration

**Prerequisites:**
- F02's Livewire/Volt auth scaffold already hardened (`LoginForm`, `lang/pt_BR/auth.php`, the guest-redirect flash mechanism) — this feature reuses those patterns directly
- `APP_LOCALE=pt_BR` already set in `.env.example`
- No new packages required

---

### Stage 1: Validation Layer

**1. StoreUserRequest Form Request** - Create the Form Request housing the authoritative rules for name, e-mail, and password, matching the PRD's exact constraints (name length, e-mail format and uniqueness, password minimum length and confirmation). It is never bound to a route — it exists to be the single source of truth other components delegate to.

**2. Portuguese Validation Strings** - Add the `lang/pt_BR` validation language file with generic rule templates, the PRD's exact custom message overrides for the fields this feature validates, and pt-BR labels for each field.

---

### Stage 2: Registration Form and Page

**3. RegisterForm Livewire Form Object** - Create the form object mirroring the login form's pattern: it holds the four registration fields, delegates its validation rules and messages to the Form Request from Stage 1, and encapsulates the transactional creation, event dispatch, and login of the new user.

**4. Registration Volt Page** - Rewrite the registration page to bind against the new form object, add on-blur validation for e-mail and password, disable the submit button with a loading indicator while the request is pending, and flash the welcome message before redirecting into the application.

**5. Temporary Success Feedback on the Landing View** - Render the flashed status message on the current landing view using the existing session-status component, so the welcome message is visible until the application shell replaces that view.

---

### Stage 3: Test Coverage

**6. Happy Path and Persistence Coverage** - Replace the existing registration test file with cases proving that valid registration succeeds, logs the user in automatically, and stores the password exclusively as a bcrypt hash.

**7. Validation and Error Handling Coverage** - Add cases proving duplicate e-mail, an under-length password, a mismatched confirmation, an unexpected payload field, and a failed database write each behave exactly as the specification's error handling describes.

**8. Double-Submission Coverage** - Add a case proving two rapid submissions carrying the same e-mail result in exactly one created account.
