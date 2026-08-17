# Implementation Plan: My Profile

**Prerequisites:**
- F02 (Authentication) and F04 (Shell and Navigation) merged — `auth` middleware, the `/perfil` route, the Breeze/Volt profile scaffold, and the `toast`/`profile-updated` browser event contracts already in place
- No new packages required — `UpdateProfilePolicy`, `Auth::logoutOtherDevices()`, and the `auth.session` middleware alias all ship with the Laravel 12 skeleton already installed

---

### Stage 1: Scope Reduction

**1. Remove the Account-Deletion Form** - Delete the Breeze-scaffolded deletion form and its inclusion on the profile page, along with the test coverage that exercises it, since it is not one of the two forms the specification defines.

**2. Strip the E-mail Field and Verification Flow** - Remove the editable e-mail input, its validation rule, and the now-orphaned verification-notice branch from the account information form, replacing them with a read-only display of the current e-mail.

---

### Stage 2: Authorization and Validation Backend

**3. Name Validation Request** - Add the Form Request holding the name-change validation rule, following the same instantiate-directly-from-Livewire pattern already established for registration.

**4. Profile Update Policy** - Add the policy gating profile mutation to the authenticated user's own record, and register it for the `User` model since its name doesn't follow Laravel's auto-discovery convention.

**5. Portuguese Current-Password Message** - Override the framework's current-password validation message with the specification's exact Portuguese copy.

---

### Stage 3: Form Behavior

**6. Account Information Form** - Rework the form to display the account creation date alongside the existing fields, validate and save only the name through the new Form Request, authorize the write through the new policy, disable its submit button until the field is touched, and replace the existing inline success indicator with the shared toast notification carrying the specification's message.

**7. Password Update Form** - Rework the form to authorize through the new policy, reject a new password identical to the current one, clear all three fields on both success and failure, suppress browser autofill on the current-password field, disable its submit button until a field is touched, and replace the existing inline success indicator with the shared toast notification.

**8. Other-Session Invalidation** - Switch the password update to the session-invalidating authentication call so a successful change logs out every other active session for the user while leaving the current one active, and extend the authenticated route group with the middleware that makes that invalidation take effect the next time another session makes a request.

---

### Stage 4: Test Coverage

**9. Profile Display and Name Update Coverage** - Rewrite the test file's display and account-information cases to match the reduced form set, covering the read-only e-mail, the account creation date, a successful name change, an invalid name, and the navigation bar reflecting the new name.

**10. Password Change Coverage** - Add cases covering a successful password change, an incorrect current password, a new password identical to the current one, and a mismatched confirmation, each asserting the specification's exact field-level behavior.

**11. Authorization and Session Coverage** - Add cases proving the policy denies a mismatched user pair, an injected identifier cannot affect another account, and a successful password change invalidates a second active session for the same user while the changing session stays authenticated.
