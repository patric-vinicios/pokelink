# Implementation Plan: Application Environment and Delivery

**Prerequisites:**
- Docker Engine 24+ and Compose v2 on the host — no PHP, Node, MySQL, or Redis installation required
- Outbound network access for the initial image pulls and dependency resolution
- An empty project directory containing only `docs/`; every file in this plan is new

---

### Stage 1: Project Scaffold

**1. Laravel 12 Skeleton** - Create the Laravel 12 project in place, keeping the framework's stock migrations for users, sessions, cache, and jobs. These defaults satisfy every schema requirement of this feature, so no migration is authored here.

**2. Breeze Authentication Scaffold** - Install Laravel Breeze on the Livewire stack to produce the login, registration, and profile routes along with the Tailwind and Vite asset pipeline. Only the happy path matters at this stage; the authentication behaviour described in the spec's scope exclusions belongs to a later feature.

**3. Horizon Installation** - Install and publish Laravel Horizon so the Redis-backed queue has a worker and a dashboard. Configure a single supervisor on the default queue, as described in the spec's component overview.

**4. Reverb and Broadcasting Installation** - Install Laravel Reverb and run the framework's broadcasting installer to publish the server configuration and pull in the Echo client packages. The goal is a working transport, not any particular channel — channel definitions arrive with the chat feature.

---

### Stage 2: Container Image

**5. PHP Runtime Image** - Define the final stage of the image on the PHP 8.3-FPM base, installing the extension set the spec enumerates, including the ones Horizon depends on. Layer in the runtime and process-manager configuration files so container environment variables reach the application.

**6. Asset Build Stage** - Add the Node build stage that compiles the Vite and Tailwind output. This stage exists because the service list has no Node container, so compiled assets must be produced at image build time rather than at boot.

**7. Dependency Resolution Stage** - Add the Composer stage that resolves PHP dependencies, retaining development dependencies for the reason recorded in the spec's technical decisions. The final stage copies the resolved dependencies and the compiled assets from the two preceding stages.

**8. Nginx Server Configuration** - Write the server block that serves the application's public directory and forwards PHP requests to the FastCGI listener on the app service. Include the front-controller routing rule that every framework route depends on.

**9. Build Context Filter** - Add the ignore file that keeps dependency directories, runtime storage, version control metadata, local environment files, and documentation out of the build context, so image builds stay fast and never bake a developer's local state.

---

### Stage 3: Boot Orchestration

**10. Container Entrypoint** - Implement the bootstrap script that runs before any service command, following the entrypoint sequence contract in the spec exactly. It must guarantee an environment file and application key exist and that the framework's runtime directories are present and writable before anything else runs.

**11. Database Readiness Probe** - Add the polling loop that waits for the database to accept connections, at the interval and ceiling the spec defines, printing progress on each attempt. On exhaustion it must exit non-zero with the exact Portuguese message from the spec's failure-mode table, so the failure is explicit at boot rather than surfacing later as a server error.

**12. Migration and Seeding Gate** - Branch the bootstrap on the persisted install marker so the first boot migrates and seeds while later boots only apply pending migrations. Abort before seeding if migration fails, leaving the marker unwritten so the next boot retries from a clean state.

**13. Readiness Signalling** - Write the ephemeral readiness marker as the final bootstrap action and expose it, together with a live application-process check, as the app service's healthcheck. This marker is deliberately separate from the install marker because the two have different lifetimes, as the spec's technical decisions explain.

**14. Compose Service Definitions** - Declare the six services in the compose file, with the app, queue, and reverb services sharing the single built image and differing only in the command they run. Publish host ports through the environment overrides the spec lists, so a busy host port is resolved by editing configuration rather than the compose file.

**15. Healthchecks and Dependency Conditions** - Add healthchecks to the database and cache services and chain the startup order so the app waits for both, and the queue and websocket services wait for the app to report healthy. This is what prevents the worker from starting against an unmigrated schema on a cold boot.

**16. Volumes and Source Mounting** - Define the named volumes for database data, cache data, and application storage, and bind-mount the project source into the app, queue, and reverb services. Overlay the two build-artifact paths with named volumes so the image-built output survives the source mount, per the spec's build-strategy decision.

---

### Stage 4: Application Configuration

**17. Environment Contract** - Write the example environment file covering every key group the spec enumerates, with values that work verbatim inside the compose network and require no manual editing. This file is the contract the acceptance criteria check, so a key the application reads and this file omits is a defect.

**18. Horizon Dashboard Gate** - Define the access gate for the queue dashboard so it is reachable during local evaluation and restricted to authenticated users anywhere else. The evaluator needs to reach it during first boot, potentially before signing in.

**19. Echo Client Bootstrap** - Configure the client-side broadcasting bootstrap to read the websocket connection settings from the build-time environment. No channel is subscribed here; the file exists so the chat feature has a configured client to build on.

**20. Documented Account Seeder** - Build the seeder that creates the two accounts the README publishes, written so that re-running it updates rather than duplicates. Register it from the seeder entry point and mark that entry point as the extension slot where the catalog sync will later be dispatched.

---

### Stage 5: Delivery

**21. Delivery Documentation** - Write the README covering prerequisites, the single boot command, the application URL, both credential pairs, the technical decisions taken, the port-conflict overrides, and an explicit statement of what was delivered and what was deliberately left out. Include the development workaround for rebuilding front-end assets, since the service list has no Node container.

**22. Clean-Boot Verification** - Run the stack from a genuinely clean state and confirm every operational item in the spec's verification checklist, including the destructive rebuild path and the deliberate database-unavailable path. Any item that needs a second command or a manual edit to pass is a defect in this feature, not a note for the README.
