# Implementation Plan: Real-time Chat

**Prerequisites:**
- F01 (Reverb/Echo wiring, `laravel-echo`/`pusher-js` already in `package.json`, `resources/js/echo.js`) merged and available
- F02 (`users` table, `auth` middleware) merged and available
- F04 (application shell, `card`/`badge`/`empty-state`/`toast` primitives, `/chat` placeholder route) merged and available
- Horizon worker and Redis queue connection already running (F01) — not used for broadcast delivery in this feature, but still the connection F06's sync job depends on

---

### Stage 1: Schema and Domain Models

**1. Conversations Table and Model** - Create the migration and Eloquent model for a deterministic, ordered-pair conversation between two users, including the database-level guarantee that the pair stays canonically ordered, per the spec's data model.

**2. Messages Table and Model** - Create the migration and Eloquent model for individual chat messages, indexed for both chronological history retrieval and per-recipient unread counting, per the spec's data model.

**3. Chat Configuration** - Add the dedicated configuration file holding the message length limit, page sizes, and unread-badge cap, following this codebase's established per-integration config-file convention.

**4. Model Factories** - Add factories for both new models, including states for pre-seeded read/unread messages, so this feature's own tests and any future feature can generate conversation data without a real chat session.

---

### Stage 2: Broadcasting Infrastructure

**5. MessageSent Broadcast Event** - Implement the event that carries a persisted message onto its two delivery channels, following the spec's dual-channel design for keeping the sidebar reactive without subscribing to every conversation.

**6. Channel Authorization Routes** - Register the private conversation channel and the presence channel in the channels file, each resolving through the policy added in the next step, per the spec's exposed-interfaces contracts.

**7. Conversation Policy** - Add the policy gating participancy in a conversation, used both by the channel authorization callback and as a defense-in-depth check inside the Livewire actions that read or write conversation data.

**8. Presence Timing Configuration** - Tune the Reverb application's ping interval and activity timeout so an ungraceful disconnect is reflected within the PRD's 5-second convergence target, documenting the trade-off from the spec's technical decisions.

---

### Stage 3: Chat Livewire Components

**9. Chat Page Orchestrator Component** - Implement the top-level component that tracks which conversation is currently selected and composes the user-list and conversation components around that selection, per the spec's component responsibilities.

**10. User List Component** - Implement the left-column component: every other registered user, sorted by recency of contact then name, filterable and paginated as described in the spec, with per-row unread counts and a presence-store binding for the online indicator.

**11. Conversation Component — History and Read State** - Implement the right-column component's history loading (most recent page on open, older pages via the keyset scroll-up fetch) and the single-query read-marking that runs when a conversation is opened.

**12. Conversation Component — Composer and Send** - Implement message composition and sending, including the character-count warning and limit enforcement, the deterministic conversation creation on first send, and dispatching the broadcast event after the persisting transaction commits.

---

### Stage 4: Real-time Frontend Wiring

**13. Chat Page Route Wiring** - Replace the F04 placeholder body on `/chat` with the new orchestrator component, keeping the existing shell and header slot untouched.

**14. Presence and Connection-State Client Stores** - Add the client-side script that joins the presence channel to maintain an online-user roster and observes the Reverb connection state to drive the reconnecting banner described in the spec's failure modes.

**15. Infinite Scroll and Pending-Bubble Interactions** - Wire the scroll-to-top trigger for loading older history with scroll-position anchoring, and the composer's immediate pending-bubble feedback that reconciles once the server confirms a send.

---

### Stage 5: Verification

**16. Chat Test Suite** - Write the feature tests enumerated in the spec's Testing Strategy: user-list ordering/filtering/pagination/unread display, conversation history/send/read-marking/infinite-scroll/validation/escaping, the broadcast event's channels and payload, and channel authorization for both participants and non-participants.

**17. Placeholder Test Cleanup** - Remove the F04-authored chat-placeholder assertions from the shared placeholder test file now that `/chat` renders real content, leaving the favoritos placeholder coverage (owned by F10) untouched.
