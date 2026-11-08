#!/usr/bin/env bash
# =============================================================================
# AgriCare smoke test  -  tests/smoke_test.sh
#
# Talks to a RUNNING AgriCare site with curl (no browser) and prints PASS / FAIL
# for each check. It does not touch the database directly.
#
# BEFORE YOU RUN IT
#   1. Import the database:  schema.sql and then seed.sql  (sample accounts + data)
#   2. Apache + PHP + MySQL running, config/config.php created, uploads folder writable.
#
# RUN
#   ./tests/smoke_test.sh                              # uses http://agricare.local
#   BASE_URL=http://localhost:8000 ./tests/smoke_test.sh
#   ./tests/smoke_test.sh | tee smoke_results.txt      # keep the output
#
# NOTES
#   * Each run creates NEW test data (a new farmer, farm, crop record, reports, a test
#     crop and a test disease). To return to a clean state use database/dev_reset.sql,
#     schema.sql, seed.sql (see README). Photos uploaded by the test stay in
#     public/uploads/reports/ - delete them by hand if you want.
#   * Some tests rely on the sample data (Wheat = crop 1, Yellow Rust = disease 1,
#     Bilal's farm id 3, Bilal's report id 3, Bilal's crop record id 4).
#   * Tests that need a real browser (responsive layout, 30-minute idle timeout)
#     are listed at the end as NOT RUN.
# =============================================================================
set -u

BASE="${BASE_URL:-http://agricare.local}"
BASE="${BASE%/}"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

BODY="$TMP/body"
HDRS="$TMP/headers"
RUN_ID="$(date +%s)"
export LC_ALL=C

PASS=0
FAIL=0
FAILED_IDS=""

# ----------------------------------------------------------------- helpers ---

record() {   # record ID "description" EXIT_STATUS
    if [ "$3" -eq 0 ]; then
        PASS=$((PASS + 1))
        printf 'PASS  %-6s %s\n' "$1" "$2"
    else
        FAIL=$((FAIL + 1))
        FAILED_IDS="$FAILED_IDS $1"
        printf 'FAIL  %-6s %s   [HTTP %s]\n' "$1" "$2" "${CODE:-?}"
    fi
}

expect() {   # expect ID "description" command args...   (command succeeds = PASS)
    local id=$1 desc=$2
    shift 2
    if "$@" >/dev/null 2>&1; then record "$id" "$desc" 0; else record "$id" "$desc" 1; fi
}

req() {       # req JAR METHOD PATH [extra curl args]  -> sets CODE, writes $BODY and $HDRS
    local jar=$1 method=$2 path=$3
    shift 3
    CODE=$(curl -sS -m 30 -b "$jar" -c "$jar" -X "$method" -D "$HDRS" -o "$BODY" \
                -w '%{http_code}' "$@" "$BASE$path" 2>"$TMP/curl_err") || CODE="000"
}

body_has()   { grep -qF -- "$1" "$BODY"; }
body_lacks() { ! grep -qF -- "$1" "$BODY"; }
hdr_has()    { grep -qiF -- "$1" "$HDRS"; }
hdr_lacks()  { ! grep -qiF -- "$1" "$HDRS"; }
code_is()    { [ "$CODE" = "$1" ]; }

get_token() {   # get_token JAR PATH  -> sets TOKEN from the first csrf field (or logout form) on the page
    req "$1" GET "$2"
    TOKEN=$(grep -o 'name="csrf_token" value="[^"]*"' "$BODY" | head -1 | sed 's/.*value="//; s/"$//')
}

post() {        # post JAR TOKEN_PAGE POST_PATH "field=value" ...   (url-encoded form + CSRF token)
    local jar=$1 tokpage=$2 path=$3
    shift 3
    get_token "$jar" "$tokpage"
    local args=(--data-urlencode "csrf_token=$TOKEN") f
    for f in "$@"; do args+=(--data-urlencode "$f"); done
    req "$jar" POST "$path" "${args[@]}"
}

post_multipart() {   # post_multipart JAR TOKEN_PAGE POST_PATH "field=value" ...  (for file uploads: pass -F style values)
    local jar=$1 tokpage=$2 path=$3
    shift 3
    get_token "$jar" "$tokpage"
    local args=(-F "csrf_token=$TOKEN") f
    for f in "$@"; do args+=(-F "$f"); done
    req "$jar" POST "$path" "${args[@]}"
}

session_id() {  # session_id JAR -> PHPSESSID value stored in a curl cookie jar
    awk '$6 == "PHPSESSID" { v = $7 } END { print v }' "$1"
}

stat_value() {  # stat_value "Total reports" -> number shown on the dashboard card (reads $BODY)
    grep -o "stat-number\">[0-9]*</span><span class=\"stat-label\">$1" "$BODY" | head -1 \
        | sed -E 's/.*stat-number">([0-9]+)<.*/\1/'
}

first_id() {    # first_id PATTERN -> first number after PATTERN found in $BODY (e.g. 'farm_edit.php?id=')
    grep -o "$1[0-9]*" "$BODY" | head -1 | sed -E 's/[^0-9]//g'
}

max_id() {      # max_id PATTERN -> highest number after PATTERN in $BODY (newest AUTO_INCREMENT id)
    grep -o "$1[0-9]*" "$BODY" | sed -E 's/[^0-9]//g' | sort -n | tail -1
}

today()      { TZ=Asia/Karachi date +%F; }
days_ago()   { TZ=Asia/Karachi date -d "$1 days ago" +%F; }
days_ahead() { TZ=Asia/Karachi date -d "+$1 days" +%F; }

section() { printf '\n--- %s ---\n' "$1"; }

# ------------------------------------------------------------- test data ---

ANON="$TMP/anon.jar"
FARMER="$TMP/farmer.jar"
ADMIN="$TMP/admin.jar"
THROTTLE="$TMP/throttle.jar"
ROLEJAR="$TMP/role.jar"

EMAIL="smoke${RUN_ID}@example.com"
PASSWORD='Smoke1234Pass'
MARK="smoke-symptom-${RUN_ID}"

# tiny valid 1x1 PNG, a fake "image" that is really PHP code, and a 3 MB file
echo 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==' | base64 -d > "$TMP/ok.png"
echo '<?php echo "not an image"; ?>' > "$TMP/fake.jpg"
head -c 3000000 /dev/zero > "$TMP/big.png"

printf 'AgriCare smoke test against %s  (run id %s)\n' "$BASE" "$RUN_ID"

section "Server check"
req "$ANON" GET /login.php
if [ "$CODE" != "200" ]; then
    echo "Cannot reach $BASE/login.php (HTTP $CODE). Is Apache running and BASE_URL correct?"
    cat "$TMP/curl_err" 2>/dev/null
    exit 2
fi
expect T00 "Login page opens (HTTP 200)" code_is 200

# --------------------------------------------------------------- headers ---
section "Headers and session cookie (login page response)"
FRESH="$TMP/fresh.jar"
req "$FRESH" GET /login.php
expect N01 "X-Content-Type-Options: nosniff is sent"      hdr_has "X-Content-Type-Options: nosniff"
expect N02 "X-Frame-Options: DENY is sent"                hdr_has "X-Frame-Options: DENY"
expect N03 "Content-Security-Policy is sent"              hdr_has "Content-Security-Policy:"
expect N04 "Session cookie has HttpOnly"                  hdr_has "Set-Cookie: PHPSESSID"
expect N05 "Session cookie has SameSite=Lax"              hdr_has "SameSite=Lax"
if [[ "$BASE" == https://* ]]; then
    expect N06 "Session cookie has Secure flag (HTTPS site)" hdr_has "secure"
else
    expect N06 "Session cookie has NO Secure flag on plain HTTP (local development)" hdr_lacks "secure"
fi
HDR_COOKIE_LINE=$(grep -i '^set-cookie: PHPSESSID' "$HDRS" | head -1)
case "$HDR_COOKIE_LINE" in *[Hh]ttp[Oo]nly*) record N04b "Session cookie line contains HttpOnly" 0 ;; *) record N04b "Session cookie line contains HttpOnly" 1 ;; esac

# ---------------------------------------------------------- registration ---
section "Registration (tests 1-3)"
post "$ANON" /register.php /register.php "full_name=Smoke Tester" "phone=03001234567" "email=$EMAIL" \
     "password=$PASSWORD" "confirm_password=$PASSWORD"
expect T01 "Register with valid data -> redirect to login" code_is 302
expect T01b "...redirect target is /login.php" hdr_has "Location: /login.php"

post "$ANON" /register.php /register.php "full_name=Smoke Tester" "phone=03001234567" "email=$EMAIL" \
     "password=$PASSWORD" "confirm_password=$PASSWORD"
expect T02 "Duplicate email is refused" body_has "already exists"

post "$ANON" /register.php /register.php "full_name=Bad Pass" "phone=03001234567" "email=other${RUN_ID}@example.com" \
     "password=$PASSWORD" "confirm_password=Different123"
expect T03a "Mismatched passwords are refused" body_has "do not match"

post "$ANON" /register.php /register.php "full_name=Bad Pass" "phone=03001234567" "email=other${RUN_ID}@example.com" \
     "password=abcdefgh" "confirm_password=abcdefgh"
expect T03b "Weak password (no digit) is refused" body_has "Password must be"

post "$ANON" /register.php /register.php "full_name=Bad Phone" "phone=abc" "email=other${RUN_ID}@example.com" \
     "password=$PASSWORD" "confirm_password=$PASSWORD"
expect T03c "Invalid phone number is refused" body_has "valid phone"

LONGPW="A1$(head -c 71 /dev/zero | tr '\0' 'a')"      # 73 bytes
post "$ANON" /register.php /register.php "full_name=Long Pass" "phone=03001234567" "email=long${RUN_ID}@example.com" \
     "password=$LONGPW" "confirm_password=$LONGPW"
expect N07 "Password longer than 72 bytes is refused" body_has "8 to 72 characters"

post "$ROLEJAR" /register.php /register.php "full_name=Role Tester" "phone=03001234567" "email=role${RUN_ID}@example.com" \
     "password=$PASSWORD" "confirm_password=$PASSWORD" "role=admin"
post "$ROLEJAR" /login.php /login.php "email=role${RUN_ID}@example.com" "password=$PASSWORD"
expect N08 "Registration ignores a 'role=admin' field (user lands on FARMER dashboard)" hdr_has "Location: /farmer/dashboard.php"

# ----------------------------------------------------------------- login ---
section "Login (tests 4-6)"
post "$ANON" /login.php /login.php "email=$EMAIL" "password=WrongPassword1"
expect T04 "Wrong password -> generic message" body_has "Invalid email or password"

get_token "$FARMER" /login.php
SID_BEFORE=$(session_id "$FARMER")
req "$FARMER" POST /login.php --data-urlencode "csrf_token=$TOKEN" --data-urlencode "email=$EMAIL" --data-urlencode "password=$PASSWORD"
expect T05 "Farmer login -> farmer dashboard" hdr_has "Location: /farmer/dashboard.php"
SID_AFTER=$(session_id "$FARMER")
if [ -n "$SID_BEFORE" ] && [ -n "$SID_AFTER" ] && [ "$SID_BEFORE" != "$SID_AFTER" ]; then record N09 "Session ID changes after login (session fixation protection)" 0; else record N09 "Session ID changes after login (session fixation protection)" 1; fi

post "$ADMIN" /login.php /login.php "email=admin@agricare.local" "password=Admin@123"
expect T06 "Admin login -> admin dashboard (needs seed.sql)" hdr_has "Location: /admin/dashboard.php"

post "$TMP/ali.jar" /login.php /login.php "email=ali@example.com" "password=Farmer@123"   # sanity: sample farmer works (own cookie jar)
expect N10 "Sample farmer ali@example.com can log in (needs seed.sql)" hdr_has "Location: /farmer/dashboard.php"

# login throttle (separate jar and a made-up email, so real accounts are never locked)
section "Login attempt limit (5 failures / 15 minutes)"
TH_EMAIL="throttle${RUN_ID}@example.com"
for i in 1 2 3 4 5; do
    post "$THROTTLE" /login.php /login.php "email=$TH_EMAIL" "password=WrongPassword$i"
done
expect N11 "5th wrong attempt still shows the normal error" body_has "Invalid email or password"
post "$THROTTLE" /login.php /login.php "email=$TH_EMAIL" "password=WrongPassword6"
expect N12 "6th attempt is blocked: 'Too many failed login attempts'" body_has "Too many failed login attempts"

# ------------------------------------------------- authorization & logout ---
section "Authorization (tests 8-10)"
req "$TMP/anon2.jar" GET /farmer/dashboard.php
expect T08 "Logged out: /farmer/dashboard.php redirects to login" hdr_has "Location: /login.php"
req "$FARMER" GET /admin/dashboard.php
expect T09 "Farmer opening /admin/dashboard.php -> 403" code_is 403
req "$ADMIN" GET /farmer/dashboard.php
expect T10 "Admin opening /farmer/dashboard.php -> 403" code_is 403

section "Dashboards before data (test 38 part 1)"
req "$FARMER" GET /farmer/dashboard.php
expect N13 "Logged-in pages are sent with Cache-Control: no-store" hdr_has "no-store"
FARMER_REPORTS_BEFORE=$(stat_value "Total reports")
req "$ADMIN" GET /admin/dashboard.php
ADMIN_REPORTS_BEFORE=$(stat_value "Total reports")
ADMIN_FARMERS_BEFORE=$(stat_value "Registered farmers")

# ----------------------------------------------------------------- farms ---
section "Farms (tests 11-14, 36)"
FARM_NAME="Smoke Farm $RUN_ID"
post "$FARMER" /farmer/farms.php /farmer/farms.php "action=add" "farm_name=$FARM_NAME" "location=Lahore, Punjab" "area=5.50"
expect T11 "Add farm -> redirect" code_is 302
req "$FARMER" GET /farmer/farms.php
expect T11b "New farm appears in My Farms" body_has "$FARM_NAME"
FARM_ID=$(first_id 'farm_edit.php?id=')

post "$FARMER" /farmer/farms.php /farmer/farms.php "action=add" "farm_name=Zero Area" "location=Lahore" "area=0"
expect T12a "Farm with area 0 is refused" body_has "Area must be"
post "$FARMER" /farmer/farms.php /farmer/farms.php "action=add" "farm_name=Text Area" "location=Lahore" "area=abc"
expect T12b "Farm with text area is refused" body_has "Area must be"

post "$FARMER" "/farmer/farm_edit.php?id=$FARM_ID" "/farmer/farm_edit.php?id=$FARM_ID" "farm_name=$FARM_NAME Edited" "location=Kasur, Punjab" "area=6.00"
expect T13 "Edit farm -> redirect" code_is 302
req "$FARMER" GET /farmer/farms.php
expect T13b "Edited farm name is shown" body_has "$FARM_NAME Edited"

post "$FARMER" /farmer/farms.php /farmer/farms.php "action=add" "farm_name=Disposable $RUN_ID" "location=Lahore" "area=1"
req "$FARMER" GET /farmer/farms.php
DISPOSABLE_ID=$(max_id 'farm_edit.php?id=')
post "$FARMER" /farmer/farms.php /farmer/farms.php "action=delete" "farm_id=$DISPOSABLE_ID"
req "$FARMER" GET /farmer/farms.php
expect T14 "Delete a farm without reports removes it" body_lacks "Disposable $RUN_ID"

XSS='<script>alert(1)</script>'
post "$FARMER" /farmer/farms.php /farmer/farms.php "action=add" "farm_name=$XSS" "location=Lahore" "area=2"
req "$FARMER" GET /farmer/farms.php
expect T36a "XSS: farm name is shown as escaped text" body_has '&lt;script&gt;alert(1)&lt;/script&gt;'
expect T36b "XSS: no raw <script>alert(1)</script> in the page" body_lacks '<script>alert(1)</script>'

# ----------------------------------------------------------------- crops ---
section "Crop records (tests 16, new edit/duplicate/future rules)"
PLANT_DATE=$(days_ago 30)
post "$FARMER" /farmer/crops.php /farmer/crops.php "action=add" "farm_id=$FARM_ID" "crop_id=1" "planting_date=$PLANT_DATE"
expect T16 "Add crop record -> redirect" code_is 302
req "$FARMER" GET /farmer/crops.php
expect T16b "Crop record (Wheat) appears in My Crops" body_has "Wheat"
FARM_CROP_ID=$(first_id 'name="farm_crop_id" value="')

post "$FARMER" /farmer/crops.php /farmer/crops.php "action=add" "farm_id=$FARM_ID" "crop_id=1" "planting_date=$PLANT_DATE"
expect N14 "Duplicate crop record (same farm, crop, date) is refused" body_has "already recorded"

post "$FARMER" /farmer/crops.php /farmer/crops.php "action=add" "farm_id=$FARM_ID" "crop_id=2" "planting_date=$(days_ahead 3)"
expect N15 "Future planting date is refused (add)" body_has "cannot be in the future"

NEW_DATE=$(days_ago 20)
post "$FARMER" "/farmer/crop_edit.php?id=$FARM_CROP_ID" "/farmer/crop_edit.php?id=$FARM_CROP_ID" "planting_date=$NEW_DATE"
expect N16 "Edit crop record planting date -> redirect" code_is 302
req "$FARMER" GET /farmer/crops.php
expect N16b "New planting date is shown in My Crops" body_has "$(TZ=Asia/Karachi date -d "$NEW_DATE" '+%d %b %Y')"

post "$FARMER" "/farmer/crop_edit.php?id=$FARM_CROP_ID" "/farmer/crop_edit.php?id=$FARM_CROP_ID" "planting_date=$(days_ahead 5)"
expect N17 "Future planting date is refused (edit)" body_has "cannot be in the future"

req "$FARMER" GET "/farmer/crop_edit.php?id=4"
expect N18 "Farmer cannot open another farmer's crop record (id 4) -> 404" code_is 404

# ------------------------------------------------------------- ownership ---
section "Access to another farmer's records (tests 17-18)"
req "$FARMER" GET "/farmer/farm_edit.php?id=3"
expect T17 "Open Bilal's farm (id 3) -> 404" code_is 404
req "$FARMER" GET "/farmer/report_view.php?id=3"
expect T18 "Open Bilal's report (id 3) -> 404" code_is 404

# --------------------------------------------------------------- reports ---
section "Reports and uploads (tests 19-24)"
post_multipart "$FARMER" /farmer/report_new.php /farmer/report_new.php \
    "farm_id=$FARM_ID" "farm_crop_id=$FARM_CROP_ID" "symptoms=$MARK yellow stripes on many wheat leaves"
expect T19 "Submit report without photo -> redirect to report page" hdr_has "Location: /farmer/report_view.php?id="
REPORT_ID=$(grep -i '^location:' "$HDRS" | head -1 | sed -E 's/.*id=([0-9]+).*/\1/' | tr -d '\r\n ')
req "$FARMER" GET "/farmer/report_view.php?id=$REPORT_ID"
expect T19b "New report has status Pending" body_has "Pending"

post_multipart "$FARMER" /farmer/report_new.php /farmer/report_new.php \
    "farm_id=$FARM_ID" "farm_crop_id=$FARM_CROP_ID" "symptoms=$MARK second report with a photo" \
    "image=@$TMP/ok.png;type=image/png"
expect T20 "Submit report with a valid PNG -> redirect" hdr_has "Location: /farmer/report_view.php?id="
REPORT2_ID=$(grep -i '^location:' "$HDRS" | head -1 | sed -E 's/.*id=([0-9]+).*/\1/' | tr -d '\r\n ')
req "$FARMER" GET "/farmer/report_view.php?id=$REPORT2_ID"
expect T20b "Photo is shown on the report page" body_has 'class="report-image"'
IMG_PATH=$(grep -o 'src="/uploads/reports/[^"]*"' "$BODY" | head -1 | sed 's/^src="//; s/"$//')
if [ -n "$IMG_PATH" ]; then
    req "$FARMER" GET "$IMG_PATH"
    expect T20c "Uploaded image file can be downloaded (image/png)" hdr_has "image/png"
else
    record T20c "Uploaded image file can be downloaded (image/png)" 1
fi

post_multipart "$FARMER" /farmer/report_new.php /farmer/report_new.php \
    "farm_id=$FARM_ID" "farm_crop_id=$FARM_CROP_ID" "symptoms=$MARK fake image test" \
    "image=@$TMP/fake.jpg;type=image/jpeg"
expect T21 "PHP code renamed to .jpg is rejected" body_has "not a valid image"

post_multipart "$FARMER" /farmer/report_new.php /farmer/report_new.php \
    "farm_id=$FARM_ID" "farm_crop_id=$FARM_CROP_ID" "symptoms=$MARK big image test" \
    "image=@$TMP/big.png;type=image/png"
expect T22 "Image larger than 2 MB is rejected" body_has "too large"

post_multipart "$FARMER" /farmer/report_new.php /farmer/report_new.php \
    "farm_id=$FARM_ID" "farm_crop_id=$FARM_CROP_ID" "symptoms="
expect T23 "Empty symptoms are refused" body_has "describe the symptoms"

post_multipart "$FARMER" /farmer/report_new.php /farmer/report_new.php \
    "farm_id=$FARM_ID" "farm_crop_id=4" "symptoms=$MARK crop of another farmer"
expect T24 "Crop that does not belong to the selected farm is refused" body_has "does not belong"

req "$FARMER" GET "/uploads/reports/test_${RUN_ID}.php"
expect N19 "Uploads folder refuses .php files (needs AllowOverride All) -> 403" code_is 403

# ------------------------------------------------- delete blocked by reports ---
section "Delete rules (test 15)"
post "$FARMER" /farmer/farms.php /farmer/farms.php "action=delete" "farm_id=$FARM_ID"
req "$FARMER" GET /farmer/farms.php
expect T15 "Farm that has reports cannot be deleted" body_has "cannot be deleted"
post "$FARMER" /farmer/crops.php /farmer/crops.php "action=delete" "farm_crop_id=$FARM_CROP_ID"
req "$FARMER" GET /farmer/crops.php
expect T15b "Crop record that has reports cannot be deleted" body_has "cannot be deleted"

# ----------------------------------------------------------- admin review ---
section "Admin review (tests 25-28)"
req "$ADMIN" GET "/admin/review.php?id=$REPORT_ID"
expect T25 "Review page shows farmer, farm, crop and symptoms" body_has "$MARK yellow stripes"
expect T25b "...and the farm name" body_has "$FARM_NAME Edited"

ADVICE1="Smoke advice $RUN_ID: spray the recommended fungicide"
post "$ADMIN" "/admin/review.php?id=$REPORT_ID" "/admin/review.php?id=$REPORT_ID" \
     "status=Under Review" "advice_text=$ADVICE1" "disease_id=1"
expect T26 "Save advice + disease + Under Review -> redirect" code_is 302
req "$ADMIN" GET "/admin/review.php?id=$REPORT_ID"
expect T26b "Advice is listed on the review page" body_has "$ADVICE1"
expect T26c "Possible disease (Yellow Rust) is listed" body_has "Yellow Rust"

post "$ADMIN" "/admin/review.php?id=$REPORT2_ID" "/admin/review.php?id=$REPORT2_ID" "status=Resolved" "advice_text="
expect T27 "Resolved without any advice is refused" body_has "before marking the report as Resolved"

ADVICE2="Second smoke advice $RUN_ID: inspect the whole field again"
post "$ADMIN" "/admin/review.php?id=$REPORT_ID" "/admin/review.php?id=$REPORT_ID" "status=Under Review" "advice_text=$ADVICE2"
req "$ADMIN" GET "/admin/review.php?id=$REPORT_ID"
expect T28 "Both advice records are listed" body_has "$ADVICE1"
expect T28b "...(second advice too)" body_has "$ADVICE2"

section "Farmer sees advice and adds follow-ups (tests 29-30)"
req "$FARMER" GET "/farmer/report_view.php?id=$REPORT_ID"
expect T29 "Farmer sees the advice" body_has "$ADVICE1"
expect T29b "Farmer sees status Under Review" body_has "Under Review"
expect T29c "Farmer sees the possible disease" body_has "Yellow Rust"

post "$FARMER" "/farmer/report_view.php?id=$REPORT_ID" "/farmer/report_view.php?id=$REPORT_ID" "update_note=Follow-up one $RUN_ID sprayed today"
post "$FARMER" "/farmer/report_view.php?id=$REPORT_ID" "/farmer/report_view.php?id=$REPORT_ID" "update_note=Follow-up two $RUN_ID leaves look better"
req "$FARMER" GET "/farmer/report_view.php?id=$REPORT_ID"
expect T30 "New follow-up note is shown" body_has "Follow-up two $RUN_ID"
expect T30b "Older follow-up note is still there" body_has "Follow-up one $RUN_ID"
req "$ADMIN" GET "/admin/review.php?id=$REPORT_ID"
expect T30c "Admin sees the farmer's follow-up notes" body_has "Follow-up two $RUN_ID"

# --------------------------------------------------------------- filters ---
section "Admin filters (test 31)"
req "$ADMIN" GET "/admin/reports.php?q=$MARK"
expect T31a "Search by symptoms text finds the report" body_has "#$REPORT_ID"
req "$ADMIN" GET "/admin/reports.php?q=$MARK&status=Resolved"
expect T31b "Status filter 'Resolved' hides it" body_lacks "#$REPORT_ID"
req "$ADMIN" GET "/admin/reports.php?q=$MARK&crop_id=1"
expect T31c "Crop filter Wheat shows it" body_has "#$REPORT_ID"
req "$ADMIN" GET "/admin/reports.php?q=$MARK&crop_id=2"
expect T31d "Crop filter Rice hides it" body_lacks "#$REPORT_ID"
req "$ADMIN" GET "/admin/reports.php?q=$MARK&date_from=$(days_ahead 2)"
expect T31e "A 'from date' in the future hides it" body_lacks "#$REPORT_ID"

# ------------------------------------------------------- admin crops/diseases ---
section "Admin crops and diseases (tests 32-33, disease guard)"
CROP_NAME="Smoke Crop $RUN_ID"
post "$ADMIN" /admin/crops.php /admin/crops.php "crop_name=$CROP_NAME" "description=Test crop created by the smoke test."
expect T32 "Add crop -> redirect" code_is 302
post "$ADMIN" /admin/crops.php /admin/crops.php "crop_name=$CROP_NAME" "description=Duplicate name."
expect T32b "Duplicate crop name is refused" body_has "already exists"

DIS_NAME="Smoke Disease $RUN_ID"
post "$ADMIN" /admin/diseases.php /admin/diseases.php "crop_id=1" "disease_name=$DIS_NAME" "symptoms=Test symptoms text." "prevention=Test prevention text."
expect T33 "Add disease -> redirect" code_is 302
req "$ADMIN" GET /admin/diseases.php
NEW_DIS_ID=$(max_id 'diseases.php?edit=')
post "$ADMIN" "/admin/diseases.php?edit=$NEW_DIS_ID" /admin/diseases.php "disease_id=$NEW_DIS_ID" "crop_id=1" "disease_name=$DIS_NAME edited" "symptoms=Edited symptoms." "prevention=Edited prevention."
expect T33b "Edit disease -> redirect" code_is 302
req "$ADMIN" GET /admin/diseases.php
expect T33c "Edited disease is listed" body_has "$DIS_NAME edited"
req "$ANON" GET "/guide.php?q=smoke+disease+$RUN_ID"
expect T33d "Edited disease is shown in the public guide" body_has "$DIS_NAME edited"

post "$ADMIN" "/admin/diseases.php?edit=1" /admin/diseases.php "disease_id=1" "crop_id=2" "disease_name=Yellow Rust (Stripe Rust)" "symptoms=Some symptoms." "prevention=Some prevention."
expect N20 "Disease used in advice cannot be moved to another crop" body_has "cannot be moved"

section "Public guide (test 34)"
req "$ANON" GET "/guide.php?q=rust"
expect T34a "Search 'rust' finds Yellow Rust (not logged in)" body_has "Yellow Rust"
req "$ANON" GET "/guide.php?q=rust&crop=2"
expect T34b "Search 'rust' + crop Rice hides Yellow Rust" body_lacks "Yellow Rust"

# -------------------------------------------------------------- CSRF/logout ---
section "CSRF and logout (tests 7, 35)"
req "$FARMER" POST /farmer/farms.php --data-urlencode "action=add" --data-urlencode "farm_name=NoToken" \
    --data-urlencode "location=Lahore" --data-urlencode "area=3"
expect T35 "POST without CSRF token -> 403" code_is 403
req "$ANON" POST /login.php --data-urlencode "email=ali@example.com" --data-urlencode "password=Farmer@123"
expect T35b "Login POST without CSRF token -> 403" code_is 403

req "$FARMER" GET /logout.php
req "$FARMER" GET /farmer/dashboard.php
expect N21 "GET /logout.php does NOT log the user out" code_is 200
req "$FARMER" POST /logout.php
expect N22 "POST /logout.php without CSRF token -> 403" code_is 403

section "Dashboards after data (test 38 part 2)"
req "$FARMER" GET /farmer/dashboard.php
FARMER_REPORTS_AFTER=$(stat_value "Total reports")
if [ "${FARMER_REPORTS_BEFORE:-x}" = "0" ] && [ "${FARMER_REPORTS_AFTER:-x}" = "2" ]; then record T38a "Farmer dashboard 'Total reports' went 0 -> 2" 0; else record T38a "Farmer dashboard 'Total reports' went 0 -> 2 (was '$FARMER_REPORTS_BEFORE', now '$FARMER_REPORTS_AFTER')" 1; fi
req "$ADMIN" GET /admin/dashboard.php
ADMIN_REPORTS_AFTER=$(stat_value "Total reports")
ADMIN_FARMERS_AFTER=$(stat_value "Registered farmers")
if [ -n "${ADMIN_REPORTS_BEFORE:-}" ] && [ "${ADMIN_REPORTS_AFTER:-x}" = "$((ADMIN_REPORTS_BEFORE + 2))" ]; then record T38b "Admin dashboard 'Total reports' increased by 2" 0; else record T38b "Admin dashboard 'Total reports' increased by 2 (was '$ADMIN_REPORTS_BEFORE', now '$ADMIN_REPORTS_AFTER')" 1; fi
if [ -n "${ADMIN_FARMERS_BEFORE:-}" ] && [ "${ADMIN_FARMERS_AFTER:-x}" -ge "$((ADMIN_FARMERS_BEFORE + 1))" ] 2>/dev/null; then record T38c "Admin dashboard 'Registered farmers' increased after registrations" 0; else record T38c "Admin dashboard 'Registered farmers' increased after registrations" 1; fi

section "Logout (test 7)"
cp "$FARMER" "$TMP/farmer_old.jar"
get_token "$FARMER" /farmer/dashboard.php
req "$FARMER" POST /logout.php --data-urlencode "csrf_token=$TOKEN"
expect T07a "POST /logout.php with token -> redirect to login" hdr_has "Location: /login.php"
req "$FARMER" GET /farmer/dashboard.php
expect T07b "After logout the dashboard redirects to login" hdr_has "Location: /login.php"
req "$TMP/farmer_old.jar" GET /farmer/dashboard.php
expect T07c "Re-using the OLD session cookie no longer works (session destroyed)" hdr_has "Location: /login.php"

# ---------------------------------------------------------------- summary ---
printf '\n=============================================\n'
printf 'PASSED: %d   FAILED: %d\n' "$PASS" "$FAIL"
[ -n "$FAILED_IDS" ] && printf 'Failed test IDs:%s\n' "$FAILED_IDS"
printf '\nNOT RUN by this script (do these by hand):\n'
printf '  T37  Responsive layout (needs a browser, resize the window)\n'
printf '  --   30-minute idle timeout (wait 30 minutes, then reload a dashboard)\n'
printf '  --   HTTPS-only behaviour (Secure cookie, HSTS) unless BASE_URL is https://\n'
printf '  --   Screenshots (see docs/screenshots/README.md)\n'
printf '=============================================\n'
[ "$FAIL" -eq 0 ]
