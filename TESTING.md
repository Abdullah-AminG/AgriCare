# AgriCare - Test Record

This file separates three things so nothing is claimed that was not actually executed:

| Result | Meaning |
|---|---|
| **PASS** | The test was executed and behaved as expected. |
| **FAIL** | The test was executed and did NOT behave as expected (describe it in Notes). |
| **NOT RUN** | The test was not executed yet. Do **not** change this to PASS without running it. |

## How to run the tests

1. Set up the application on Ubuntu (see `README.md`), import `database/schema.sql` and then `database/seed.sql`.
2. Run the automated checks (needs only `curl`; see the top of the script for details):

   ```bash
   ./tests/smoke_test.sh | tee smoke_results.txt
   ```

3. Do the manual tests marked "Manual" in a browser.
4. Replace every `NOT RUN` below with `PASS` or `FAIL` according to the real result, fill in the
   environment table, and keep `smoke_results.txt` as your evidence (it is ignored by Git on purpose).

## Environment used for the recorded results

| Item | Value |
|---|---|
| Ubuntu version | _(fill in: `lsb_release -d`)_ |
| Apache version | _(fill in: `apache2 -v`)_ |
| PHP version | _(fill in: `php -v`)_ |
| MySQL version | _(fill in: `mysql --version`)_ |
| Browser | _(fill in)_ |
| Date and tester | _(fill in)_ |

## A. Checks that WERE executed while preparing this version (static checks, no running server)

These were run in a development sandbox that has **no PHP, Apache or MySQL**. They prove only
what the table says. They do **not** prove that the application works.

| Check | What it proves | Result |
|---|---|---|
| `node --check public/assets/js/main.js` | JavaScript has no syntax errors | PASS |
| Bracket/quote balance script over every `.php` file | No unbalanced `()`, `[]`, `{}` or unterminated strings. **This is NOT `php -l`** | PASS |
| Old `agricare.sql` compared with `schema.sql` + `seed.sql` | Same 23 SQL statements apart from the intended changes (no `DROP`, `IF NOT EXISTS`, `CREATE OR REPLACE VIEW`) | PASS |
| `grep` for `DROP`/`DELETE`/`TRUNCATE` in `schema.sql` and `seed.sql` | None present | PASS |
| Sample bcrypt hashes checked with the system `crypt` | `Admin@123` and `Farmer@123` match the stored hashes | PASS |
| `bash -n tests/smoke_test.sh` | The test script has no shell syntax errors | PASS |
| Helper functions of the test script run on sample HTML/headers | Token, ID, statistic and cookie extraction work | PASS |

Not executed in the sandbox: `php -l` on the PHP files, MySQL import of the SQL files, and **every functional test below**.

## B. Environment checklist (run on your Ubuntu machine)

| # | Check | Command / action | Result |
|---|---|---|---|
| E1 | PHP 8.0 or newer | `php -v` | NOT RUN |
| E2 | PHP syntax of all files | `find . -name '*.php' -not -path './.git/*' -exec php -l {} \; \| grep -v 'No syntax errors'` (no output = good) | NOT RUN |
| E3 | Extensions `pdo_mysql` and `mbstring` loaded | `php -m \| grep -E 'pdo_mysql\|mbstring'` | NOT RUN |
| E4 | `schema.sql` imports | `sudo mysql < database/schema.sql` | NOT RUN |
| E5 | `seed.sql` imports (once) | `sudo mysql < database/seed.sql` | NOT RUN |
| E6 | Second `seed.sql` run fails safely and changes nothing | run it again: expect `ERROR 1062 Duplicate entry`; then `sudo mysql -e "SELECT COUNT(*) FROM agricare.users;"` must still be 3 | NOT RUN |
| E7 | Re-running `schema.sql` keeps the data | run it again; user count unchanged | NOT RUN |
| E8 | PDO connects with `config/config.php` | open `http://agricare.local/` (home page shows crop and disease counts from the database) | NOT RUN |
| E9 | Upload folder writable by Apache | `sudo -u www-data test -w public/uploads/reports && echo writable` | NOT RUN |
| E10 | PHP upload limit at least 2 MB | `php -i \| grep -E 'upload_max_filesize\|post_max_size'` | NOT RUN |
| E11 | `AllowOverride All` active (upload folder `.htaccess`) | covered by automated test N19 | NOT RUN |

## C. Functional tests

"Script ID" is the matching line printed by `tests/smoke_test.sh`. "Manual" tests need a browser.

| # | Area | Steps | Expected result | Script ID | Result |
|---|------|-------|-----------------|-----------|--------|
| 1 | Registration | Register with valid data | Account created, redirected to login | T01 | NOT RUN |
| 2 | Registration | Register again with the same email | "Email already exists" error | T02 | NOT RUN |
| 3 | Registration | Mismatched passwords / weak password / bad phone | Validation errors, nothing saved | T03a-c | NOT RUN |
| 4 | Login | Log in with wrong password | Generic "Invalid email or password" | T04 | NOT RUN |
| 5 | Login | Log in as farmer | Farmer Dashboard opens | T05 | NOT RUN |
| 6 | Login | Log in as admin | Admin Dashboard opens | T06 | NOT RUN |
| 7 | Logout | Click Logout, then press Back and open a dashboard URL | Redirected to login; session destroyed | T07a-c (Back button: Manual) | NOT RUN |
| 8 | Authorization | Logged out, open `/farmer/dashboard.php` | Redirect to login | T08 | NOT RUN |
| 9 | Authorization | Farmer opens `/admin/dashboard.php` | 403 Forbidden | T09 | NOT RUN |
| 10 | Authorization | Admin opens `/farmer/dashboard.php` | 403 Forbidden | T10 | NOT RUN |
| 11 | Farms | Add a farm with valid data | Farm appears in My Farms | T11 | NOT RUN |
| 12 | Farms | Add a farm with area 0 or text | Validation error | T12a-b | NOT RUN |
| 13 | Farms | Edit a farm | Changes saved | T13 | NOT RUN |
| 14 | Farms | Delete a farm without reports (confirm dialog appears) | Farm removed | T14 (dialog: Manual) | NOT RUN |
| 15 | Farms | Delete a farm that has reports | Refused with message | T15, T15b | NOT RUN |
| 16 | Crops | Add a crop to your farm with a planting date | Record appears in My Crops | T16 | NOT RUN |
| 17 | Ownership | As Ali open `/farmer/farm_edit.php?id=3` (Bilal's farm) | 404 Not Found | T17 | NOT RUN |
| 18 | Ownership | As Ali open `/farmer/report_view.php?id=3` (Bilal's report) | 404 Not Found | T18 | NOT RUN |
| 19 | Report | Submit a report with symptoms and no photo | Saved with status Pending | T19 | NOT RUN |
| 20 | Report | Submit with a valid JPG/PNG/WEBP under 2 MB | Photo shown on report page | T20 (PNG only) | NOT RUN |
| 21 | Upload | Upload a .txt or .php file renamed to .jpg | Rejected: not a valid image | T21 | NOT RUN |
| 22 | Upload | Upload an image larger than 2 MB | Rejected: too large | T22 | NOT RUN |
| 23 | Report | Submit with empty symptoms | Validation error | T23 | NOT RUN |
| 24 | Report | Send a crop that is not on the selected farm | Rejected by the server | T24 | NOT RUN |
| 25 | Admin review | Open the Pending report | Farmer, farm, crop, symptoms and photo visible | T25 | NOT RUN |
| 26 | Admin review | Select a disease, write advice, set Under Review, save | Advice saved, status changed | T26 | NOT RUN |
| 27 | Admin review | Mark Resolved on a report with no advice | Refused | T27 | NOT RUN |
| 28 | Admin review | Add a second advice record | Both advice records are listed | T28 | NOT RUN |
| 29 | Farmer view | Log in as the farmer | Status, advice and possible disease visible | T29 | NOT RUN |
| 30 | Follow-up | Add a progress update | New note added; older notes remain | T30 | NOT RUN |
| 31 | Filters | Admin filters reports by status, crop, farmer, dates and search | List matches the filters | T31a-e (farmer filter: Manual) | NOT RUN |
| 32 | Crops (admin) | Add a crop; add duplicate name | Added; duplicate rejected | T32 | NOT RUN |
| 33 | Diseases (admin) | Add and edit a disease | Saved and shown in the guide | T33 | NOT RUN |
| 34 | Guide | Search "rust" and filter by crop (not logged in) | Matching diseases shown | T34a-b | NOT RUN |
| 35 | CSRF | POST without a valid `csrf_token` | 403 Forbidden | T35, T35b | NOT RUN |
| 36 | XSS | Add a farm named `<script>alert(1)</script>` | Shown as plain text, no script runs | T36a-b (visual check: Manual) | NOT RUN |
| 37 | Responsive | Resize to mobile width | Menu collapses, tables scroll, layout stacks | Manual only | NOT RUN |
| 38 | Dashboards | Add reports, reload both dashboards | Counts change (they come from the database) | T38a-c | NOT RUN |

## D. Tests for the improvements made in this version

| # | Area | Steps | Expected result | Script ID | Result |
|---|------|-------|-----------------|-----------|--------|
| 39 | Session cookie | Look at the `Set-Cookie` header of the login page | `HttpOnly`, `SameSite=Lax`; `Secure` only when the site is HTTPS | N04-N06 | NOT RUN |
| 40 | Security headers | Look at the response headers | `nosniff`, `X-Frame-Options: DENY`, Content-Security-Policy | N01-N03 | NOT RUN |
| 41 | Cache | Open a logged-in page | `Cache-Control` contains `no-store` | N13 | NOT RUN |
| 42 | Session fixation | Compare PHPSESSID before and after login | The ID changes | N09 | NOT RUN |
| 43 | Logout | Open `/logout.php` by typing the address | You stay logged in (GET does not log out) | N21 | NOT RUN |
| 44 | Logout | POST to `/logout.php` without a token | 403 Forbidden | N22 | NOT RUN |
| 45 | Login limit | 5 wrong passwords for one email, then a 6th try | 6th shows "Too many failed login attempts" | N11, N12 | NOT RUN |
| 46 | Passwords | Register with a password longer than 72 bytes | Refused with the 8-72 characters message | N07 | NOT RUN |
| 47 | Registration | Send an extra `role=admin` field | Account is still a farmer | N08 | NOT RUN |
| 48 | Crop edit | Edit the planting date of your own crop record | Saved and shown in My Crops | N16 | NOT RUN |
| 49 | Crop edit | Open another farmer's crop record by URL | 404 Not Found | N18 | NOT RUN |
| 50 | Planting date | Add or edit a crop with a date in the future | Refused | N15, N17 | NOT RUN |
| 51 | Duplicates | Add the same crop, farm and date twice | Second one refused | N14 | NOT RUN |
| 52 | Diseases | Move a disease that is used in advice to another crop | Refused with a message | N20 | NOT RUN |
| 53 | Uploads | Request a `.php` file inside `uploads/reports/` | 403 Forbidden | N19 | NOT RUN |
| 54 | Idle timeout | Log in, wait 30 minutes, reload a dashboard | Redirected to login with the inactivity message | Manual only | NOT RUN |
| 55 | HTTPS | Serve the site over HTTPS | Session cookie has `Secure`; `Strict-Transport-Security` header sent | Manual (or `BASE_URL=https://...`) | NOT RUN |
| 56 | Back button | Log out, press the browser Back button | Login page, no private data shown | Manual only | NOT RUN |
| 57 | Screenshots | Capture the pages in `docs/screenshots/README.md` | All files present | Manual only | NOT RUN |

## Known limitation (documented, not a test failure)

Uploaded crop photos are served directly by Apache from `/uploads/reports/`. File names are random and
cannot be guessed, but anyone who has the exact link can open the image without logging in.
