
<?php
require_once __DIR__ . '/../config/database.php';

function h($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/* Same category colors as add-room.php */
$categoryColors = [
    'Room'       => '#0b3b82',
    'Offices'    => '#2e9e5b',
    'Facilities' => '#e8772e'
];

/* Same color helpers as the editor's JavaScript */
function darkenHex($hex, $factor) {
    $hex = ltrim($hex, '#');

    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));

    return sprintf(
        '#%02x%02x%02x',
        (int)round($r * $factor),
        (int)round($g * $factor),
        (int)round($b * $factor)
    );
}

function textColorFor($hex) {
    $hex = ltrim($hex, '#');

    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));

    $luminance = 0.299 * $r + 0.587 * $g + 0.114 * $b;

    return $luminance > 150 ? '#172b4d' : '#ffffff';
}

/* Get building ID and selected floor */
$building_id = isset($_GET['building_id'])
    ? (int)$_GET['building_id']
    : 0;

$floorInput = trim((string)($_GET['floor'] ?? '1'));

/* Convert labels such as "1st Floor" to numeric floor values */
$floor = '1';

if (preg_match('/^(\d+)(?:st|nd|rd|th)?(?:\s+Floor)?$/i', $floorInput, $matches)) {
    $floor = $matches[1];
}

/* Validate building ID */
if ($building_id <= 0) {
    header('Location: ../campus-map.php');
    exit;
}

/* Get building information using PDO */
$stmt = $conn->prepare("
    SELECT building_id, building_name, total_floors, status
    FROM buildings
    WHERE building_id = :building_id
    LIMIT 1
");

$stmt->execute([
    'building_id' => $building_id
]);

$building = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$building) {
    header('Location: ../campus-map.php');
    exit;
}

$totalFloors = max(1, (int)($building['total_floors'] ?? 1));

/* Validate selected floor */
if (
    !ctype_digit($floor) ||
    (int)$floor < 1 ||
    (int)$floor > $totalFloors
) {
    $floor = '1';
}

/* Convert numeric floor to its display label */
$floorNumber = (int)$floor;

$ordinal = match ($floorNumber) {
    1 => '1st',
    2 => '2nd',
    3 => '3rd',
    default => $floorNumber . 'th'
};

$selectedFloorLabel = $ordinal . ' Floor';

/* Get rooms for this building and floor */
$stmt = $conn->prepare("
    SELECT
        room_id,
        room_number,
        room_name,
        room_type,
        floor,
        position_x,
        position_y,
        width,
        height,
        shape_type,
        rotation
    FROM rooms
    WHERE building_id = :building_id
      AND (
          TRIM(CAST(floor AS TEXT)) = :numeric_floor
          OR TRIM(CAST(floor AS TEXT)) = :label_floor
      )
    ORDER BY room_number ASC
");

$stmt->execute([
    'building_id' => $building_id,
    'numeric_floor' => $floor,
    'label_floor' => $selectedFloorLabel
]);

$rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* Close the connection only if this page does not use it later */
$conn = null;

$floorNames = [
    '1' => '1st',
    '2' => '2nd',
    '3' => '3rd',
    '4' => '4th',
    '5' => '5th'
];

$floorLabel = $floorNames[$floor] ?? ($floor . 'th');

/* Floor name shown on the canvas */
$canvasFloorLabel = $floorLabel . ' Floor';

/* Pass room data safely to JavaScript */
$roomsJson = json_encode(
    $rooms,
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
);

if ($roomsJson === false) {
    $roomsJson = '[]';
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>
    <?= h($building['building_name']) ?> - Floor Layout | CMU Campus Navigator
</title>

<!-- Loading screen + entrance animation, same style as campus.js (must stay in <head>, no defer) -->
<script src="../floor.js"></script>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, Helvetica, sans-serif;
    background: #f3f6fb;
    color: #172b4d;
}

/* NAVIGATION - MATCH CAMPUS-MAP.PHP */

.navbar {
    min-height: 86px;
    padding: 14px 4%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    background: #fff;
    border-bottom: 1px solid #dce4ef;
    box-shadow: 0 2px 8px rgba(20, 45, 80, 0.06);
}

.brand {
    display: flex;
    align-items: center;
    gap: 13px;
    color: #0b3b82;
    text-decoration: none;
}

.brand img {
    width: 54px;
    height: 54px;
    object-fit: contain;
}

.brand-text {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.brand-text strong {
    color: #0b3b82;
    font-size: 21px;
}

.brand-text span {
    color: #52627a;
    font-size: 13px;
    font-weight: normal;
}

.nav-links {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
}

.nav-links a {
    padding: 11px 16px;
    border-radius: 7px;
    text-decoration: none;
    font-size: 14px;
    font-weight: 700;
}

.nav-home {
    color: #0b3b82;
    background: #edf3fc;
}

.nav-admin {
    color: #fff;
    background: #0b3b82;
}

.nav-links a:hover {
    filter: brightness(.95);
}

/* PAGE */

.container {
    max-width: 1600px;
    padding: 30px;
    margin: 0 auto;
}

.back-link {
    display: inline-block;
    margin-bottom: 20px;
    color: #0b3b82;
    text-decoration: none;
    font-size: 14px;
    font-weight: bold;
}

.back-link:hover {
    color: #c39400;
}

.page-heading {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
    margin-bottom: 24px;
}

.eyebrow {
    color: #805900;
    font-size: 12px;
    font-weight: 800;
    letter-spacing: 1.5px;
}

.page-heading h1 {
    margin: 9px 0;
    color: #0b3b82;
    font-size: 34px;
    overflow-wrap: anywhere;
}

.page-heading p {
    margin: 0;
    color: #52627a;
    line-height: 1.7;
}

.floor-badge {
    padding: 10px 16px;
    border-radius: 8px;
    background: #ffdf45;
    color: #172b4d;
    font-weight: bold;
    white-space: nowrap;
}

/* FLOOR SELECTOR */

.floor-selector {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 10px;
    margin-bottom: 22px;
}

.floor-selector a {
    display: inline-block;
    padding: 11px 16px;
    border: 1px solid #d8e1ee;
    border-radius: 7px;
    color: #0b3b82;
    background: #fff;
    font-size: 14px;
    font-weight: 700;
    text-decoration: none;
    transition: .15s;
}

.floor-selector a.active,
.floor-selector a:hover {
    border-color: #0b3b82;
    background: #0b3b82;
    color: #fff;
}

/* MAIN PANEL (same as .panel in add-room.php) */

.panel {
    min-width: 0;
    padding: 22px;
    margin-bottom: 20px;
    background: #fff;
    border: 1px solid #d8e1ee;
    border-radius: 12px;
    box-shadow: 0 4px 14px rgba(11, 59, 130, 0.05);
}

.panel h2 {
    margin: 0 0 10px;
    color: #0b3b82;
    font-size: 21px;
}

.panel-description {
    margin: 0 0 18px;
    color: #52627a;
    font-size: 14px;
    line-height: 1.6;
}

/*
 * SAME GRID AS .editor-grid IN add-room.php:
 * flexible layout column + 270px side panel, 18px gap.
 */

.floor-layout-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 270px;
    gap: 18px;
    align-items: start;
}

.floor-layout-column {
    min-width: 0;
}

/* CATEGORY LEGEND (same as .legend in add-room.php) */

.legend {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    margin: 0 0 14px;
    color: #43536b;
    font-size: 14px;
    font-weight: bold;
}

.legend-item {
    display: inline-flex;
    align-items: center;
    gap: 7px;
}

.legend-dot {
    width: 16px;
    height: 16px;
    border-radius: 4px;
    border: 1px solid rgba(0, 0, 0, 0.2);
}

/* OUTER BOX (same as .canvas-scroll in add-room.php) */

.layout-scroll {
    width: 100%;
    overflow: auto;
    border: 1px solid #d8e0ec;
    border-radius: 8px;
    background: #fff;
}

/* While a room is selected the plan is zoomed, so no scrolling */
.layout-scroll.zoomed {
    overflow: hidden;
}

/*
 * CANVAS (same as #roomCanvas in add-room.php):
 * 1200 x 650, 20px grid. Room coordinates are relative to it.
 * The zoom animation transforms this element (same idea as #campusCanvas
 * in campus-map.php).
 */

.floor-plan {
    position: relative;
    width: 1200px;
    height: 650px;
    overflow: hidden;
    background-color: #fbfcff;
    background-image:
        linear-gradient(#e7ecf4 1px, transparent 1px),
        linear-gradient(90deg, #e7ecf4 1px, transparent 1px);
    background-size: 20px 20px;
    transform-origin: 0 0;
    transition: transform .65s cubic-bezier(.22, .61, .36, 1);
    will-change: transform;
}

.canvas-label {
    position: absolute;
    top: 12px;
    left: 15px;
    color: #9aa5b5;
    font-size: 13px;
    pointer-events: none;
}

/*
 * ROOMS - same box model as .room-shape in add-room.php.
 * Colors are set per room from its category (inline style).
 */

.room {
    position: absolute;
    box-sizing: border-box;
    min-width: 60px;
    min-height: 45px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 6px;
    margin: 0;
    overflow: hidden;
    text-align: center;
    cursor: pointer;
    border: 2px solid #0b3b82;
    border-radius: 0;
    background: #0b3b82;
    color: #ffffff;
    font-family: Arial, Helvetica, sans-serif;
    line-height: normal;
    transition: box-shadow .15s, filter .15s, opacity .3s;
}

/* Same gold outline as the selected room in the editor */
.room:hover,
.room.selected,
.room:focus-visible {
    z-index: 5;
    box-shadow: 0 0 0 3px #d9a900;
    outline: none;
}

/* Other rooms fade back while one is selected (same as campus-map.php) */
.floor-plan.has-selection .room:not(.selected) {
    opacity: .45;
}

.room.selected {
    z-index: 30;
    opacity: 1;
}

/* Same label style as .room-label in add-room.php */
.room-label {
    font-size: 12px;
    font-weight: bold;
    white-space: pre-line;
    overflow-wrap: anywhere;
    pointer-events: none;
}

.legend-hint {
    margin-top: 12px;
    color: #52627a;
    font-size: 12px;
}

/* ROOM DETAILS ON THE RIGHT */

.room-details-panel {
    position: sticky;
    top: 20px;
    min-width: 0;
    padding: 18px;
    background: #fff;
    border: 1px solid #d8e1ee;
    border-radius: 10px;
    box-shadow: 0 4px 14px rgba(11, 59, 130, 0.04);
}

.room-details-panel.has-room {
    border-color: #0b3b82;
    border-width: 2px;
}

.room-details-panel h2 {
    margin: 0 0 6px;
    color: #0b3b82;
    font-size: 19px;
}

.details-hint {
    margin: 0 0 18px;
    color: #52627a;
    font-size: 13px;
    line-height: 1.6;
}

.detail-item {
    padding: 13px 0;
    border-bottom: 1px solid #e5ebf4;
}

.detail-item:last-child {
    border-bottom: none;
}

.detail-label {
    display: block;
    margin-bottom: 6px;
    color: #52627a;
    font-size: 12px;
    font-weight: 700;
}

.detail-value {
    display: block;
    color: #0b3b82;
    font-size: 15px;
    font-weight: 700;
    overflow-wrap: anywhere;
    line-height: 1.5;
}

.room-info-placeholder {
    padding: 16px 12px;
    color: #52627a;
    background: #f8faff;
    border: 1px dashed #cbd6e5;
    border-radius: 8px;
    font-size: 13px;
    line-height: 1.6;
    text-align: center;
}

.room-selected-label {
    display: inline-block;
    padding: 5px 9px;
    margin-bottom: 12px;
    color: #146c43;
    background: #d1e7dd;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
}

/* Details slide in each time a room is selected */
#roomDetails.show {
    animation: details-in .45s cubic-bezier(.2, .8, .3, 1);
}

@keyframes details-in {
    from {
        opacity: 0;
        transform: translateX(16px);
    }

    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.back-btn {
    display: block;
    width: 100%;
    margin-top: 14px;
    padding: 12px 16px;
    border: 0;
    border-radius: 7px;
    background: #0b3b82;
    color: #fff;
    font-family: inherit;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
}

.back-btn:hover {
    background: #082d63;
}

/* EMPTY STATE */

.empty-state {
    padding: 40px 20px;
    color: #52627a;
    text-align: center;
    font-size: 14px;
    line-height: 1.7;
}

.empty-state strong {
    display: block;
    margin-bottom: 8px;
    color: #0b3b82;
    font-size: 18px;
}

/* FOOTER */

footer {
    padding: 22px;
    text-align: center;
    background: #fff;
    border-top: 1px solid #dce4ef;
}

footer p {
    margin: 0;
    color: #52627a;
    font-size: 13px;
}

/* RESPONSIVE (same 1100px breakpoint as add-room.php) */

@media (max-width: 1100px) {
    .floor-layout-grid {
        grid-template-columns: minmax(0, 1fr);
    }

    .room-details-panel {
        position: static;
    }
}

@media (max-width: 650px) {
    .navbar {
        align-items: flex-start;
        flex-direction: column;
        padding: 16px;
    }

    .brand-text strong {
        font-size: 18px;
    }

    .nav-links a {
        padding: 10px 12px;
    }

    .container {
        padding: 18px 12px;
    }

    .page-heading h1 {
        font-size: 28px;
    }

    .panel {
        padding: 17px;
    }
}

@media (prefers-reduced-motion: reduce) {
    .floor-plan {
        transition: none;
    }

    #roomDetails.show {
        animation: none;
    }
}
</style>
</head>

<!-- data-no-bg: this page uses a light solid background, so no slideshow -->
<body data-no-bg>

<header class="navbar">
    <a class="brand" href="../campus-map.php">
        <img src="../cmu%20logo.jpg" alt="CMU Logo">

        <div class="brand-text">
            <strong>CMU Campus Navigator</strong>
            <span>City of Malabon University</span>
        </div>
    </a>

    <nav class="nav-links">
        <a href="../index.php" class="nav-home">Home</a>
        <a href="../campus-map.php" class="nav-home">Campus Map</a>
        <a href="login.php" class="nav-admin">Admin Login</a>
    </nav>
</header>

<main class="container">

    <a class="back-link" href="../campus-map.php">
        &larr; Back to Campus Map
    </a>

    <div class="page-heading">
        <div>
            <span class="eyebrow">BUILDING INFORMATION</span>

            <h1><?= h($building['building_name']) ?></h1>

            <p>
                View the rooms inside this building.
                Select a floor and click a room to zoom in and see its details.
            </p>
        </div>

        <div class="floor-badge">
            <?= h($floorLabel) ?> Floor
        </div>
    </div>

    <nav class="floor-selector" aria-label="Choose floor">
        <?php for ($i = 1; $i <= $totalFloors; $i++): ?>
            <?php
                $label = $floorNames[(string)$i] ?? ($i . 'th');
            ?>

            <a
                class="<?= (string)$i === $floor ? 'active' : '' ?>"
                href="floor-layout.php?building_id=<?= (int)$building_id ?>&floor=<?= $i ?>"
            >
                <?= h($label) ?> Floor
            </a>
        <?php endfor; ?>
    </nav>

    <section class="panel">
        <h2>Floor Layout</h2>

        <p class="panel-description">
            Room positions and dimensions follow the values saved in the
            database. Click a room to zoom in and view its information on
            the right.
        </p>

        <?php if (empty($rooms)): ?>

            <div class="empty-state">
                <strong>No room layout found for this floor.</strong>

                No rooms were found for the selected floor.

                <br>

                Make sure the rooms are saved under this building and floor
                in the admin room layout editor.
            </div>

        <?php else: ?>

            <div class="legend">
                <?php foreach ($categoryColors as $categoryName => $categoryColor): ?>
                    <span class="legend-item">
                        <span
                            class="legend-dot"
                            style="background: <?= h($categoryColor) ?>"
                        ></span>
                        <?= h($categoryName) ?>
                    </span>
                <?php endforeach; ?>
            </div>

            <div class="floor-layout-grid">

                <div class="floor-layout-column">

                    <div class="layout-scroll" id="layoutScroll">
                        <div class="floor-plan" id="floorPlan">

                            <div class="canvas-label">
                                <?= h($building['building_name']) ?>
                                — <?= h($canvasFloorLabel) ?>
                            </div>

                            <?php foreach ($rooms as $room): ?>
                                <?php
                                    /*
                                     * Saved coordinates, with the same size limits
                                     * add-room.php applies when saving:
                                     * width 60-600, height 45-400.
                                     */
                                    $x = (float)($room['position_x'] ?? 40);
                                    $y = (float)($room['position_y'] ?? 65);
                                    $width = max(60, min(600, (float)($room['width'] ?? 140)));
                                    $height = max(45, min(400, (float)($room['height'] ?? 90)));
                                    $rotation = (float)($room['rotation'] ?? 0);

                                    /* Category color (rooms with no type use the Room color) */
                                    $type = (string)($room['room_type'] ?? '');

                                    if (!isset($categoryColors[$type])) {
                                        $type = 'Room';
                                    }

                                    $color = $categoryColors[$type];
                                    $borderColor = darkenHex($color, 0.7);
                                    $textColor = textColorFor($color);

                                    /* Same label as the editor: number, then name */
                                    $labelParts = array_filter(
                                        [
                                            trim((string)($room['room_number'] ?? '')),
                                            trim((string)($room['room_name'] ?? ''))
                                        ],
                                        function ($part) {
                                            return $part !== '';
                                        }
                                    );

                                    $labelText = implode("\n", $labelParts);
                                ?>

                                <button
                                    type="button"
                                    class="room"
                                    style="
                                        left: <?= $x ?>px;
                                        top: <?= $y ?>px;
                                        width: <?= $width ?>px;
                                        height: <?= $height ?>px;
                                        transform: rotate(<?= $rotation ?>deg);
                                        background: <?= h($color) ?>;
                                        border-color: <?= h($borderColor) ?>;
                                        color: <?= h($textColor) ?>;
                                    "
                                    data-id="<?= (int)$room['room_id'] ?>"
                                    data-type="<?= h($type) ?>"
                                    data-number="<?= h($room['room_number']) ?>"
                                    data-name="<?= h($room['room_name']) ?>"
                                    data-floor="<?= h($room['floor']) ?>"
                                    data-x="<?= $x ?>"
                                    data-y="<?= $y ?>"
                                    data-w="<?= $width ?>"
                                    data-h="<?= $height ?>"
                                    onclick="showRoom(this, event)"
                                    title="Click to zoom in and view room details"
                                    aria-label="<?= h(trim(($room['room_number'] ?? '') . ' ' . ($room['room_name'] ?? ''))) ?>"
                                >
                                    <span class="room-label"><?= h($labelText) ?></span>
                                </button>

                            <?php endforeach; ?>

                        </div>
                    </div>

                    <div class="legend-hint">
                        Click a room to zoom in. Click an empty area, press Esc,
                        or use the Back button to zoom out.
                    </div>

                </div>

                <aside class="room-details-panel" id="roomDetailsPanel">

                    <h2>Room Details</h2>

                    <p class="details-hint">
                        Select a room on the floor layout to see its
                        information here.
                    </p>

                    <div class="room-info-placeholder" id="roomPlaceholder">
                        No room selected yet.
                    </div>

                    <div id="roomDetails" hidden>

                        <span class="room-selected-label">
                            SELECTED ROOM
                        </span>

                        <div class="detail-item">
                            <span class="detail-label">Type</span>
                            <span class="detail-value" id="detailType">—</span>
                        </div>

                        <div class="detail-item">
                            <span class="detail-label">Room Number</span>
                            <span class="detail-value" id="detailNumber">—</span>
                        </div>

                        <div class="detail-item">
                            <span class="detail-label">Room Name</span>
                            <span class="detail-value" id="detailName">—</span>
                        </div>

                        <div class="detail-item">
                            <span class="detail-label">Floor</span>
                            <span class="detail-value" id="detailFloor">—</span>
                        </div>

                        <button type="button" class="back-btn" id="resetViewBtn">
                            ← Back / Reset View
                        </button>

                    </div>

                </aside>

            </div>

        <?php endif; ?>

    </section>

</main>

<footer>
    <p>
        &copy; <?= date('Y') ?> CMU Campus Navigator |
        City of Malabon University
    </p>
</footer>

<script>
const rooms = <?= $roomsJson ?>;

const floorPlan = document.getElementById('floorPlan');
const layoutScroll = document.getElementById('layoutScroll');
const roomDetailsPanel = document.getElementById('roomDetailsPanel');
const roomPlaceholder = document.getElementById('roomPlaceholder');
const roomDetails = document.getElementById('roomDetails');
const resetViewBtn = document.getElementById('resetViewBtn');

let selectedRoomButton = null;
let resetTimer = null;

/* Smoothly zoom in and center on a room (same idea as campus-map.php) */
function zoomToRoom(button) {
    clearTimeout(resetTimer);

    /* Keep the current scroll position so the plan doesn't jump */
    const scrollLeft = layoutScroll.scrollLeft;
    const scrollTop = layoutScroll.scrollTop;

    layoutScroll.classList.add('zoomed');

    const viewWidth = layoutScroll.clientWidth;
    const viewHeight = layoutScroll.clientHeight;

    const x = parseFloat(button.dataset.x) || 0;
    const y = parseFloat(button.dataset.y) || 0;
    const w = parseFloat(button.dataset.w) || 140;
    const h = parseFloat(button.dataset.h) || 90;

    /* Rotation turns around the center, so the center stays the same */
    const centerX = x + w / 2;
    const centerY = y + h / 2;

    /* Fit the room to about half of the visible area */
    let scale = Math.min(
        (viewWidth * 0.5) / Math.max(w, 1),
        (viewHeight * 0.5) / Math.max(h, 1)
    );

    scale = Math.max(1.2, Math.min(3.5, scale));

    const translateX = viewWidth / 2 + scrollLeft - centerX * scale;
    const translateY = viewHeight / 2 + scrollTop - centerY * scale;

    floorPlan.style.transform =
        'translate(' + translateX + 'px, ' + translateY + 'px) scale(' + scale + ')';
}

/* Click a room: select it, zoom in, show its information on the right */
function showRoom(button, event) {
    if (event) {
        event.stopPropagation();
    }

    document.querySelectorAll('.room').forEach(function(room) {
        room.classList.remove('selected');
        room.setAttribute('aria-pressed', 'false');
    });

    button.classList.add('selected');
    button.setAttribute('aria-pressed', 'true');
    selectedRoomButton = button;

    const type = button.dataset.type || 'Room';
    const number = button.dataset.number || 'N/A';
    const name = button.dataset.name || 'No room name provided';
    const floor = button.dataset.floor || 'N/A';

    document.getElementById('detailType').textContent = type;
    document.getElementById('detailNumber').textContent = number;
    document.getElementById('detailName').textContent = name;
    document.getElementById('detailFloor').textContent = floor;

    roomPlaceholder.hidden = true;
    roomDetails.hidden = false;
    roomDetailsPanel.classList.add('has-room');

    /* Restart the slide-in animation of the details */
    roomDetails.classList.remove('show');
    void roomDetails.offsetWidth;
    roomDetails.classList.add('show');

    floorPlan.classList.add('has-selection');
    zoomToRoom(button);

    /* Make sure the zoomed plan is on screen (useful on phones) */
    layoutScroll.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

/* Back / Reset View: zoom out and deselect */
function resetView() {
    if (!selectedRoomButton) {
        return;
    }

    selectedRoomButton.classList.remove('selected');
    selectedRoomButton.setAttribute('aria-pressed', 'false');
    selectedRoomButton = null;

    floorPlan.style.transform = '';
    floorPlan.classList.remove('has-selection');

    roomDetails.hidden = true;
    roomDetails.classList.remove('show');
    roomPlaceholder.hidden = false;
    roomDetailsPanel.classList.remove('has-room');

    /* Allow scrolling again once the zoom-out animation is done */
    clearTimeout(resetTimer);
    resetTimer = setTimeout(function() {
        layoutScroll.classList.remove('zoomed');
    }, 700);
}

if (floorPlan && layoutScroll) {
    /* Clicking an empty part of the plan zooms back out */
    floorPlan.addEventListener('click', function(event) {
        if (event.target === floorPlan || event.target.classList.contains('canvas-label')) {
            resetView();
        }
    });

    resetViewBtn.addEventListener('click', resetView);

    /* Esc zooms back out */
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            resetView();
        }
    });

    /* Keep the selected room centered if the window is resized */
    window.addEventListener('resize', function() {
        if (selectedRoomButton) {
            zoomToRoom(selectedRoomButton);
        }
    });
}
</script>

</body>
</html>