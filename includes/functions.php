<?php
// includes/functions.php
// Reusable helpers: flash messages, input reading, validation, display, ownership.

define('APP_NAME', 'AgriCare');

// Same time zone as the database session (config/database.php sets MySQL to +05:00).
date_default_timezone_set('Asia/Karachi');

// Allowed report statuses (must match the ENUM in the reports table).
const REPORT_STATUSES = ['Pending', 'Under Review', 'Resolved'];

/* ===================== Flash messages and redirects ===================== */

/** Store a message for the next page. $type: 'success' or 'error'. */
function setFlash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/** Return the stored message (and remove it), or null. */
function getFlash(): ?array
{
    if (!isset($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function notFound(string $message = 'Record not found.'): void
{
    http_response_code(404);
    exit('404 Not Found: ' . $message);
}

/** Returns 'active' when this navigation key is the current page. */
function activeClass(string $key, string $current): string
{
    return $key === $current ? 'active' : '';
}

/* ===================== Reading input ===================== */

/** Text from $_POST: trimmed, always a string. */
function postValue(string $key): string
{
    $value = $_POST[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}

/** Text from $_POST without trimming (used for passwords). */
function postRaw(string $key): string
{
    $value = $_POST[$key] ?? '';
    return is_string($value) ? $value : '';
}

/** Text from $_GET: trimmed, always a string. */
function getValue(string $key): string
{
    $value = $_GET[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}

/** Positive integer from the URL (?id=5), or null if missing/invalid. */
function getIdParam(string $key): ?int
{
    $id = filter_input(INPUT_GET, $key, FILTER_VALIDATE_INT);
    return ($id !== false && $id !== null && $id > 0) ? $id : null;
}

/** Positive integer from a POST form, or null if missing/invalid. */
function postIdParam(string $key): ?int
{
    $id = filter_input(INPUT_POST, $key, FILTER_VALIDATE_INT);
    return ($id !== false && $id !== null && $id > 0) ? $id : null;
}

/* ===================== Validation ===================== */

function isValidEmail(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/** 7 to 20 characters made of digits, spaces, + and - */
function isValidPhone(string $phone): bool
{
    return preg_match('/^[0-9+\-\s]{7,20}$/', $phone) === 1;
}

// bcrypt (used by password_hash) only reads the first 72 BYTES of a password and
// silently ignores the rest, so longer passwords are rejected instead of being cut.
const MIN_PASSWORD_LENGTH = 8;
const MAX_PASSWORD_BYTES  = 72;

/** 8 to 72 bytes, with at least one letter and one digit. (strlen counts bytes, which is what bcrypt limits.) */
function isStrongPassword(string $password): bool
{
    $bytes = strlen($password);

    return $bytes >= MIN_PASSWORD_LENGTH
        && $bytes <= MAX_PASSWORD_BYTES
        && preg_match('/[A-Za-z]/', $password) === 1
        && preg_match('/[0-9]/', $password) === 1;
}

/** One message used everywhere the password rule is shown. */
function passwordRuleMessage(): string
{
    return 'Password must be 8 to 72 characters long and contain at least one letter and one number.';
}

/** Date written as YYYY-MM-DD (the format of <input type="date">). */
function isValidDate(string $date): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d !== false && $d->format('Y-m-d') === $date;
}

/**
 * Check a planting date. Returns an error message, or null when the date is fine.
 * Rules: real calendar date (YYYY-MM-DD), not before 2000, and NOT in the future
 * (a crop cannot have been planted tomorrow). "Today" uses Pakistan time.
 */
function validatePlantingDate(string $date): ?string
{
    if (!isValidDate($date) || $date < '2000-01-01') {
        return 'Please enter a valid planting date.';
    }
    if ($date > date('Y-m-d')) {
        return 'The planting date cannot be in the future.';
    }
    return null;
}

function isValidStatus(string $status): bool
{
    return in_array($status, REPORT_STATUSES, true);
}

/**
 * Validate name, phone and email (used by registration and account settings).
 * Returns an error message, or null when everything is valid.
 */
function validateProfileFields(string $fullName, string $phone, string $email): ?string
{
    if (mb_strlen($fullName) < 2 || mb_strlen($fullName) > 100) {
        return 'Full name must be between 2 and 100 characters.';
    }
    if (!isValidPhone($phone)) {
        return 'Enter a valid phone number (7 to 20 digits; spaces, + and - are allowed).';
    }
    if (!isValidEmail($email) || strlen($email) > 150) {
        return 'Enter a valid email address.';
    }
    return null;
}

/**
 * Validate farm fields (used when adding and editing a farm).
 * Returns an error message, or null when everything is valid.
 */
function validateFarmFields(string $farmName, string $location, string $area): ?string
{
    if (mb_strlen($farmName) < 2 || mb_strlen($farmName) > 100) {
        return 'Farm name must be between 2 and 100 characters.';
    }
    if (mb_strlen($location) < 2 || mb_strlen($location) > 150) {
        return 'Location must be between 2 and 150 characters.';
    }
    $areaValue = filter_var($area, FILTER_VALIDATE_FLOAT);
    if ($areaValue === false || $areaValue <= 0 || $areaValue > 100000) {
        return 'Area must be a number greater than 0 (in acres).';
    }
    return null;
}

/* ===================== Display helpers ===================== */

/** Coloured status label (CSS classes are in style.css). */
function statusBadge(string $status): string
{
    $classes = [
        'Pending'      => 'badge-pending',
        'Under Review' => 'badge-review',
        'Resolved'     => 'badge-resolved',
    ];
    $class = $classes[$status] ?? 'badge-pending';

    return '<span class="badge ' . $class . '">' . e($status) . '</span>';
}

function formatDate(?string $dateTime): string
{
    if ($dateTime === null || $dateTime === '') {
        return '';
    }
    return date('d M Y', strtotime($dateTime));
}

function formatDateTime(?string $dateTime): string
{
    if ($dateTime === null || $dateTime === '') {
        return '';
    }
    return date('d M Y, H:i', strtotime($dateTime));
}

/** Shorten long text for table rows (adds ... when cut). */
function shortText(string $text, int $length = 80): string
{
    if (mb_strlen($text) <= $length) {
        return $text;
    }
    return mb_substr($text, 0, $length) . '...';
}

/* ===================== Ownership helpers ===================== */

/**
 * Return the farm only if it belongs to the given user, otherwise null.
 * This stops a farmer from opening another farmer's farm by changing the URL.
 */
function findFarmOfUser(PDO $pdo, int $farmId, int $userId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT farm_id, user_id, farm_name, location, area
         FROM farms
         WHERE farm_id = :farm_id AND user_id = :user_id'
    );
    $stmt->execute(['farm_id' => $farmId, 'user_id' => $userId]);
    $farm = $stmt->fetch();

    return $farm ?: null;
}

/**
 * Return one crop record (farm_crops row) only if its farm belongs to the given user.
 * Used by the crop edit page so a farmer cannot open another farmer's record by changing the URL.
 */
function findFarmCropOfUser(PDO $pdo, int $farmCropId, int $userId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT fc.farm_crop_id, fc.farm_id, fc.crop_id, fc.planting_date,
                f.farm_name, c.crop_name
         FROM farm_crops fc
         JOIN farms f ON f.farm_id = fc.farm_id
         JOIN crops c ON c.crop_id = fc.crop_id
         WHERE fc.farm_crop_id = :id AND f.user_id = :user_id'
    );
    $stmt->execute(['id' => $farmCropId, 'user_id' => $userId]);
    $record = $stmt->fetch();

    return $record ?: null;
}

/**
 * Is the same crop already recorded on the same farm with the same planting date?
 * $excludeId lets the edit page ignore the record that is being edited.
 */
function farmCropExists(PDO $pdo, int $farmId, int $cropId, string $plantingDate, int $excludeId = 0): bool
{
    $stmt = $pdo->prepare(
        'SELECT 1
         FROM farm_crops
         WHERE farm_id = :farm_id AND crop_id = :crop_id
           AND planting_date = :planting_date AND farm_crop_id <> :exclude_id
         LIMIT 1'
    );
    $stmt->execute([
        'farm_id'       => $farmId,
        'crop_id'       => $cropId,
        'planting_date' => $plantingDate,
        'exclude_id'    => $excludeId,
    ]);

    return (bool) $stmt->fetchColumn();
}

/* ===================== Secure image upload ===================== */

define('UPLOAD_DIR', dirname(__DIR__) . '/public/uploads/reports/');
define('UPLOAD_URL_PATH', 'uploads/reports/');      // relative path saved in the database
define('MAX_IMAGE_SIZE', 2 * 1024 * 1024);          // 2 MB

/**
 * Validate and save one uploaded crop photo.
 * $file is an entry of $_FILES, e.g. $_FILES['image'].
 * Returns:
 *   ['path' => 'uploads/reports/report_xxx.jpg', 'error' => null]   saved
 *   ['path' => null,                             'error' => null]   no file chosen (optional)
 *   ['path' => null,                             'error' => '...']  validation failed
 */
function handleReportImageUpload(array $file): array
{
    // No file selected: the photograph is optional.
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['path' => null, 'error' => null];
    }

    if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
        return ['path' => null, 'error' => 'The image is too large. Maximum size is 2 MB.'];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['path' => null, 'error' => 'The image could not be uploaded. Please try again.'];
    }

    // Size limit, checked again on the server.
    if ($file['size'] > MAX_IMAGE_SIZE) {
        return ['path' => null, 'error' => 'The image is too large. Maximum size is 2 MB.'];
    }

    // The file must really come from an HTTP upload.
    if (!is_uploaded_file($file['tmp_name'])) {
        return ['path' => null, 'error' => 'Invalid upload.'];
    }

    // getimagesize() reads the file itself, so a renamed .php file fails here.
    $imageInfo = @getimagesize($file['tmp_name']);
    if ($imageInfo === false) {
        return ['path' => null, 'error' => 'The selected file is not a valid image.'];
    }

    // Only these types are accepted. The extension comes from the detected type,
    // NOT from the visitor's file name.
    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    $mimeType = $imageInfo['mime'];
    if (!isset($allowedTypes[$mimeType])) {
        return ['path' => null, 'error' => 'Only JPG, PNG or WEBP images are allowed.'];
    }

    // Safe random file name. The original name is never used.
    $newName     = 'report_' . bin2hex(random_bytes(8)) . '.' . $allowedTypes[$mimeType];
    $destination = UPLOAD_DIR . $newName;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        error_log('AgriCare upload failed: could not move file to ' . $destination);
        return ['path' => null, 'error' => 'The image could not be saved on the server.'];
    }

    return ['path' => UPLOAD_URL_PATH . $newName, 'error' => null];
}

/**
 * Delete an uploaded image (used when saving the report to the database fails
 * after the file was already stored).
 */
function deleteUploadedImage(?string $relativePath): void
{
    if ($relativePath === null || strpos($relativePath, UPLOAD_URL_PATH) !== 0) {
        return;
    }

    $fullPath = UPLOAD_DIR . basename($relativePath);

    if (is_file($fullPath)) {
        unlink($fullPath);
    }
}
