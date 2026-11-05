# Email on Acid pre-send testing

Setup:
1. Copy `.env.example` to `.env` (git-ignored) and add your API key and account password. The password is the one set in the Email on Acid web portal.
2. `./eoa auth` from the repo root to verify credentials.
3. `./eoa clients` to list client IDs, then optionally set `EOA_CLIENTS` in `.env` (comma-separated group names and/or client IDs).

By default every run covers all Gmail, Apple (Apple Mail and iPhone), Outlook (desktop, web, Microsoft 365 and the iPhone app) and Yahoo clients, light and dark mode. The groups are `gmail`, `apple`, `outlook` and `yahoo`; use `--clients outlook` to limit a run to one group.

Run a test (from the repo root):

    ./eoa 078                        # every email whose filename contains "078"
    ./eoa Results-Winner             # any filename fragment works
    ./eoa 2026/CBV/some-email.html   # one exact file

The `./eoa` wrapper finds matching HTML files under the year folders and calls `workflow-automations/email-on-acid/eoa.mjs test` for each one. Fragments can match more than one email, and each match counts as a separate test.

Other commands: `./eoa auth`, `./eoa clients`.

Flags (pass after the fragment): `--clients gmail,outlook` (groups or client IDs), `--image-blocking`, `--keep` (don't delete the test online), `--sandbox` (no real tests, uses sandbox credentials).

Tests are named from the filename, e.g. `CBV-26-078-Costume-Contest-Results-Winner-Oct26.html` becomes "Costume Contest Results - CBV - Winner". Override with `--name "..."`.

Output goes to `.eoa-results/<email-name>/` (report.md plus one PNG per client). The exit code is 2 if any client bounced.

Test limit: run at most 3 tests per email before stopping to review. Batch fixes between runs instead of retesting after every tweak.
