
<?php
require_once __DIR__ . "/config/database.php";

function e($value) {
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}

/*
 * Rooms, offices and facilities are stored in the rooms table
 * and distinguished by room_type.
 * Older rooms with no type count as Rooms.
 */
$sql = "
    SELECT
        b.building_id,
        b.building_name,
        b.description,
        b.total_floors,
        b.image,
        b.status,
        l.shape_type,
        l.position_x,
        l.position_y,
        l.width,
        l.height,
        l.rotation,
        (
            SELECT COUNT(*)
            FROM rooms r
            WHERE r.building_id = b.building_id
              AND (
                  r.room_type IS NULL
                  OR r.room_type = ''
                  OR r.room_type = 'Room'
              )
        ) AS room_count,
        (
            SELECT COUNT(*)
            FROM rooms r
            WHERE r.building_id = b.building_id
              AND r.room_type = 'Offices'
        ) AS office_count,
        (
            SELECT COUNT(*)
            FROM rooms r
            WHERE r.building_id = b.building_id
              AND r.room_type = 'Facilities'
        ) AS facility_count
    FROM buildings b
    LEFT JOIN building_layouts l
        ON l.building_id = b.building_id
    ORDER BY b.building_id ASC
";

try {
    $result = $conn->query($sql);
    $rows = $result->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $ex) {
    error_log("Campus map query error: " . $ex->getMessage());
    die("Hindi ma-load ang campus map. Pakitingnan ang database tables at column names.");
}

$buildings = [];

foreach ($rows as $row) {
    $shape = $row["shape_type"] ?? "rectangle";

    if (!in_array($shape, ["rectangle", "square", "rounded"], true)) {
        $shape = "rectangle";
    }

    $buildings[] = [
        "id" => (int)$row["building_id"],
        "name" => $row["building_name"] ?? "Unnamed Building",
        "description" => $row["description"] ?? "",
        "floors" => max(1, (int)($row["total_floors"] ?? 1)),
        "image" => $row["image"] ?? "",
        "status" => $row["status"] ?? "Active",
        "shape" => $shape,
        "x" => (float)($row["position_x"] ?? 50),
        "y" => (float)($row["position_y"] ?? 50),
        "w" => (float)($row["width"] ?? 140),
        "h" => (float)($row["height"] ?? 90),
        "rotation" => (float)($row["rotation"] ?? 0),
        "rooms" => (int)$row["room_count"],
        "offices" => (int)$row["office_count"],
        "facilities" => (int)$row["facility_count"]
    ];
}

/* All rooms, offices and facilities, used by the search bar */
$searchRooms = [];

try {
    $roomResult = $conn->query(
        "SELECT room_id, building_id, room_number, room_name, room_type, floor
         FROM rooms
         ORDER BY room_name ASC"
    );

    $roomRows = $roomResult->fetchAll(PDO::FETCH_ASSOC);

    foreach ($roomRows as $row) {
        $type = trim((string)($row["room_type"] ?? ""));

        if (!in_array($type, ["Offices", "Facilities"], true)) {
            $type = "Room";
        }

        $searchRooms[] = [
            "id" => (int)$row["room_id"],
            "buildingId" => (int)$row["building_id"],
            "number" => trim((string)($row["room_number"] ?? "")),
            "name" => trim((string)($row["room_name"] ?? "")),
            "type" => $type,
            "floor" => trim((string)($row["floor"] ?? ""))
        ];
    }
} catch (PDOException $ex) {
    error_log("Campus map room search query error: " . $ex->getMessage());
    $searchRooms = [];
}

$jsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP;

$buildingsJson = json_encode($buildings, $jsonFlags);

if ($buildingsJson === false) {
    $buildingsJson = "[]";
}

$searchRoomsJson = json_encode($searchRooms, $jsonFlags);

if ($searchRoomsJson === false) {
    $searchRoomsJson = "[]";
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Campus Map | CMU Campus Navigator</title>

<!-- Animations used only by this page (loading screen + entrance effects) -->
<script src="campus.js"></script>

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

.logo {
    display: flex;
    align-items: center;
    gap: 13px;
}

.logo img {
    width: 54px;
    height: 54px;
    object-fit: contain;
}

.logo h2 {
    margin: 0 0 5px;
    color: #0b3b82;
    font-size: 21px;
}

.logo span {
    color: #52627a;
    font-size: 13px;
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

main {
    max-width: 1600px;
    margin: 0 auto;
    padding: 30px;
}

.page-heading {
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
}

.page-heading p {
    margin: 0;
    color: #52627a;
    line-height: 1.7;
}

/* SEARCH */

.search-panel {
    position: relative;
    margin-bottom: 22px;
}

.search-box {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 0 14px;
    border: 1px solid #cbd6e5;
    border-radius: 9px;
    background: #fff;
}

.search-box:focus-within {
    border-color: #0b3b82;
    box-shadow: 0 0 0 3px #d6e5fb;
}

.search-box .search-icon {
    font-size: 18px;
}

.search-box input {
    flex: 1;
    min-width: 0;
    padding: 14px 0;
    border: 0;
    outline: none;
    background: transparent;
    color: #172b4d;
    font: inherit;
    font-size: 15px;
}

.search-clear {
    padding: 6px 10px;
    border: 0;
    border-radius: 6px;
    background: #edf3fc;
    color: #0b3b82;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
}

.search-results {
    display: none;
    max-height: 360px;
    margin-top: 10px;
    overflow-y: auto;
    border: 1px solid #d8e1ee;
    border-radius: 9px;
    background: #fff;
    box-shadow: 0 8px 22px rgba(11, 59, 130, 0.08);
}

.search-results.open {
    display: block;
}

.search-group-title {
    padding: 9px 14px;
    background: #edf3fc;
    color: #0b3b82;
    font-size: 12px;
    font-weight: 800;
    letter-spacing: 1px;
}

.result-row {
    display: flex;
    align-items: center;
    gap: 10px;
    border-bottom: 1px solid #e5eaf2;
}

.result-row:last-child {
    border-bottom: 0;
}

.result-main {
    flex: 1;
    min-width: 0;
    padding: 12px 14px;
    border: 0;
    background: transparent;
    text-align: left;
    cursor: pointer;
    font-family: inherit;
}

.result-main:hover,
.result-main:focus-visible {
    background: #f8faff;
    outline: none;
}

.result-title {
    display: block;
    color: #0b3b82;
    font-size: 14px;
    font-weight: 700;
    overflow-wrap: anywhere;
}

.result-meta {
    display: block;
    margin-top: 3px;
    color: #52627a;
    font-size: 12px;
}

.result-link {
    flex-shrink: 0;
    margin-right: 12px;
    padding: 7px 10px;
    border-radius: 6px;
    background: #0b3b82;
    color: #fff;
    text-decoration: none;
    font-size: 12px;
    font-weight: 700;
    white-space: nowrap;
}

.result-link:hover {
    filter: brightness(.92);
}

.no-results {
    padding: 18px 14px;
    color: #52627a;
    font-size: 14px;
    text-align: center;
}

.map-layout {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 300px;
    gap: 22px;
    align-items: start;
}

.panel {
    min-width: 0;
    padding: 22px;
    margin-bottom: 20px;
    background: #fff;
    border: 1px solid #d8e1ee;
    border-radius: 12px;
    box-shadow: 0 4px 14px rgba(11, 59, 130, 0.04);
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

/* MAP + ZOOM */

.map-scroll {
    width: 100%;
    overflow: auto;
    border: 1px solid #d8e1ee;
    border-radius: 9px;
    background: #f8faff;
}

/* While a building is selected the map is zoomed, so no scrolling */
.map-scroll.zoomed {
    overflow: hidden;
}

#campusCanvas {
    position: relative;
    width: 1200px;
    height: 650px;
    overflow: hidden;
    background-color: #fff;
    transform-origin: 0 0;
    transition: transform .65s cubic-bezier(.22, .61, .36, 1);
    will-change: transform;
}

.building {
    position: absolute;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 7px;
    border: 2px solid #0b3b82;
    background: #dce8fa;
    color: #0b3b82;
    text-align: center;
    font-size: 13px;
    font-weight: 800;
    cursor: pointer;
    user-select: none;
    transition: box-shadow 0.15s, filter 0.15s, opacity 0.3s;
}

.building.active {
    border-color: #198754;
    background: #d1e7dd;
    color: #146c43;
}

.building.inactive {
    border-color: #dc3545;
    background: #f8d7da;
    color: #842029;
}

.building.rectangle,
.building.square {
    border-radius: 2px;
}

.building.rounded {
    border-radius: 18px;
}

.building:hover,
.building:focus-visible {
    z-index: 20 !important;
    filter: brightness(.97);
    box-shadow: 0 0 0 4px rgba(255, 223, 69, .85);
    outline: none;
}

/* Other buildings fade back while one is selected */
#campusCanvas.has-selection .building:not(.selected) {
    opacity: .45;
}

/* First click: the selected building */
.building.selected {
    z-index: 30 !important;
    opacity: 1;
    box-shadow: 0 0 0 4px #ffdf45;
}

/* Building found by the search bar */
.building.found {
    animation: found-pulse 1s ease-in-out 3;
}

@keyframes found-pulse {
    0%, 100% { box-shadow: 0 0 0 4px #d9a900; }
    50% { box-shadow: 0 0 0 10px rgba(217, 169, 0, .35); }
}

.building-label {
    max-width: 100%;
    overflow-wrap: anywhere;
    pointer-events: none;
}

.tooltip {
    position: fixed;
    z-index: 1000;
    display: none;
    width: 260px;
    max-width: calc(100vw - 24px);
    padding: 14px;
    color: #fff;
    background: #102d56;
    border: 1px solid #31547e;
    border-radius: 9px;
    box-shadow: 0 8px 25px rgba(0, 0, 0, .22);
    pointer-events: none;
}

.tooltip h3 {
    margin: 0 0 7px;
    font-size: 15px;
    color: #ffdf45;
}

.tooltip p {
    margin: 0 0 9px;
    color: #e4ecf8;
    font-size: 12px;
    line-height: 1.5;
    overflow-wrap: anywhere;
}

.tooltip-stats {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 6px;
}

.tooltip-stat {
    padding: 8px 4px;
    text-align: center;
    background: rgba(255, 255, 255, .1);
    border-radius: 6px;
}

.tooltip-stat strong {
    display: block;
    color: #ffdf45;
    font-size: 17px;
}

.tooltip-stat span {
    display: block;
    margin-top: 4px;
    font-size: 10px;
    color: #e4ecf8;
}

/* BUILDING INFORMATION PANEL (right side) */

.info-panel[hidden] {
    display: none;
}

.info-panel {
    border-color: #0b3b82;
    border-width: 2px;
}

.detail-name {
    margin: 8px 0 10px;
    color: #0b3b82;
    font-size: 22px;
    overflow-wrap: anywhere;
}

.detail-description {
    margin: 0 0 4px;
    color: #52627a;
    font-size: 14px;
    line-height: 1.7;
    white-space: pre-wrap;
    overflow-wrap: anywhere;
}

.status {
    display: inline-block;
    padding: 5px 9px;
    margin-bottom: 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
}

.status.active {
    background: #d1e7dd;
    color: #146c43;
}

.status.inactive {
    background: #f8d7da;
    color: #b02a37;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
    margin-top: 16px;
}

.stat-card {
    padding: 14px 10px;
    border: 1px solid #d8e1ee;
    border-radius: 8px;
    background: #f8faff;
}

.stat-card strong {
    display: block;
    color: #0b3b82;
    font-size: 24px;
}

.stat-card span {
    display: block;
    margin-top: 5px;
    color: #52627a;
    font-size: 12px;
}

.info-hint {
    margin: 16px 0 0;
    padding: 10px 12px;
    border-radius: 8px;
    background: #fffbea;
    border: 1px solid #f0dc8a;
    color: #684d00;
    font-size: 13px;
    line-height: 1.5;
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

.building-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.building-list-item {
    display: block;
    width: 100%;
    padding: 12px;
    text-align: left;
    border: 1px solid #d8e1ee;
    border-radius: 7px;
    background: #fff;
    color: #0b3b82;
    cursor: pointer;
    font-size: 13px;
    font-weight: 700;
    overflow-wrap: anywhere;
    text-decoration: none;
}

.building-list-item:hover,
.building-list-item:focus-visible {
    border-color: #0b3b82;
    background: #edf3fc;
    outline: none;
}

.building-list-item.selected {
    border-color: #d9a900;
    background: #fff8d8;
}

.list-status {
    display: inline-block;
    margin-top: 5px;
    font-size: 11px;
    font-weight: 700;
}

.list-status.active {
    color: #198754;
}

.list-status.inactive {
    color: #dc3545;
}

.empty-message {
    padding: 20px 10px;
    color: #52627a;
    text-align: center;
    font-size: 14px;
    line-height: 1.7;
}

.legend {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 14px;
    margin-top: 15px;
    color: #52627a;
    font-size: 12px;
}

.legend-item {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.legend-color {
    width: 15px;
    height: 15px;
    border: 2px solid;
    border-radius: 3px;
}

.legend-active {
    background: #d1e7dd;
    border-color: #198754;
}

.legend-inactive {
    background: #f8d7da;
    border-color: #dc3545;
}

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

@media (max-width: 1000px) {
    main {
        padding: 22px;
    }

    .map-layout {
        grid-template-columns: minmax(0, 1fr);
    }

    .details-column {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 16px;
    }

    .info-panel {
        grid-column: 1 / -1;
    }
}

@media (max-width: 650px) {
    .navbar {
        align-items: flex-start;
        flex-direction: column;
        padding: 16px;
    }

    .logo h2 {
        font-size: 18px;
    }

    .nav-links a {
        padding: 10px 12px;
    }

    main {
        padding: 18px 12px;
    }

    .page-heading h1 {
        font-size: 28px;
    }

    .panel {
        padding: 17px;
    }

    .details-column {
        grid-template-columns: minmax(0, 1fr);
        gap: 0;
    }

    .result-row {
        flex-direction: column;
        align-items: stretch;
        gap: 0;
    }

    .result-link {
        margin: 0 14px 12px;
        text-align: center;
    }
}

@media (prefers-reduced-motion: reduce) {
    #campusCanvas {
        transition: none;
    }

    .building.found {
        animation: none;
    }
}
</style>

</head>

<body>

<header class="navbar">
    <div class="logo">
        <img src="cmu%20logo.jpg" alt="CMU Logo">

        <div>
            <h2>CMU Campus Navigator</h2>
            <span>City of Malabon University</span>
        </div>
    </div>

    <nav class="nav-links">
        <a href="index.php" class="nav-home">Home</a>
        <a href="campus-map.php" class="nav-home">Campus Map</a>
        <a href="admin/login.php" class="nav-admin">Admin Login</a>
    </nav>
</header>

<main>
    <section class="page-heading">
        <span class="eyebrow">EXPLORE OUR CAMPUS</span>
        <h1>CMU Campus Map</h1>
        <p>
            Explore the buildings of City of Malabon University.
            Hover over a building to preview it, click it once to zoom in and
            see its information, then click it again to open its floor layout.
            Choosing a result from the search bar zooms in and opens the floor
            layout automatically.
        </p>
    </section>

    <!-- SEARCH -->
    <section class="search-panel">
        <div class="search-box">
            <span class="search-icon" aria-hidden="true">🔎</span>

            <input
                type="search"
                id="campusSearch"
                placeholder="Search a building, room, office, or facility (e.g. Library, 101, Registrar)"
                autocomplete="off"
                aria-label="Search buildings, rooms, offices, and facilities"
            >

            <button type="button" class="search-clear" id="searchClear" hidden>
                Clear
            </button>
        </div>

        <div class="search-results" id="searchResults" role="listbox"></div>
    </section>

    <div class="map-layout">

        <section class="panel">
            <h2>Campus Layout</h2>

            <p class="panel-description">
                Building positions and dimensions come from the saved Campus
                Layout Editor. Scroll horizontally or vertically to explore
                the entire map.
            </p>

            <div class="map-scroll" id="mapScroll">
                <div id="campusCanvas"></div>
            </div>

            <div class="legend">
                <span class="legend-item">
                    <span class="legend-color legend-active"></span>
                    Active Building
                </span>

                <span class="legend-item">
                    <span class="legend-color legend-inactive"></span>
                    Inactive Building
                </span>
            </div>
        </section>

        <aside class="details-column">

            <!-- Filled in when a building is selected -->
            <section class="panel info-panel" id="infoPanel" hidden></section>

            <section class="panel">
                <h2>Select a Building</h2>

                <p class="panel-description">
                    Click any building on the map or use the list below to
                    zoom in. Click it again to open its floor layout.
                </p>

                <div id="buildingList" class="building-list"></div>
            </section>
        </aside>

    </div>
</main>

<div id="buildingTooltip" class="tooltip" role="status"></div>

<footer>
    <p>© <?= date("Y") ?> CMU Campus Navigator | City of Malabon University</p>
</footer>

<script>
const buildings = <?= $buildingsJson ?>;
const searchRooms = <?= $searchRoomsJson ?>;

const canvas = document.getElementById("campusCanvas");
const mapScroll = document.getElementById("mapScroll");
const buildingList = document.getElementById("buildingList");
const tooltip = document.getElementById("buildingTooltip");
const infoPanel = document.getElementById("infoPanel");

/* Two-step click state */
let selectedId = null;      // building currently zoomed in on
let selectedAt = 0;         // when it was selected (ms)
let resetTimer = null;
let searchNavTimer = null;  // pending "zoom, then open floor layout" from search

/* Ignore a second click this soon after the first one (accidental double-click) */
const SECOND_CLICK_DELAY = 400;

/* After a search pick: wait for the zoom animation (.65s) before opening the floor layout */
const SEARCH_ZOOM_WAIT = 1100;

function createElement(tag, className, text) {
    const element = document.createElement(tag);

    if (className) {
        element.className = className;
    }

    if (text !== undefined) {
        element.textContent = text;
    }

    return element;
}

function formatNumber(value) {
    return Number(value || 0).toLocaleString();
}

function getStatusClass(status) {
    return String(status || "Active").trim().toLowerCase() === "inactive"
        ? "inactive"
        : "active";
}

function getStatusLabel(status) {
    return getStatusClass(status) === "inactive"
        ? "Inactive"
        : "Active";
}

function buildingById(id) {
    return buildings.find(item => item.id === Number(id));
}

/* ---------- TWO-STEP CLICK ---------- */

function floorLayoutUrl(buildingId, floor) {
    return "admin/floor-layout.php?building_id=" +
        encodeURIComponent(buildingId) +
        "&floor=" + encodeURIComponent(floor || 1);
}

/* Second click: go to the floor layout page (starts on the 1st floor) */
function openFloorLayout(id) {
    const building = buildingById(id);

    if (!building) {
        return;
    }

    window.location.href = floorLayoutUrl(building.id, 1);
}

/* Smoothly zoom in and center on a building */
function zoomToBuilding(building) {
    clearTimeout(resetTimer);

    /* Keep the current scroll position so the map doesn't jump */
    const scrollLeft = mapScroll.scrollLeft;
    const scrollTop = mapScroll.scrollTop;

    mapScroll.classList.add("zoomed");

    const viewWidth = mapScroll.clientWidth;
    const viewHeight = mapScroll.clientHeight;

    /* Rotation turns around the center, so the center stays the same */
    const centerX = building.x + building.w / 2;
    const centerY = building.y + building.h / 2;

    /* Fit the building to about half of the visible map area */
    let scale = Math.min(
        (viewWidth * 0.5) / Math.max(building.w, 1),
        (viewHeight * 0.5) / Math.max(building.h, 1)
    );

    scale = Math.max(1.2, Math.min(3.5, scale));

    const translateX = viewWidth / 2 + scrollLeft - centerX * scale;
    const translateY = viewHeight / 2 + scrollTop - centerY * scale;

    canvas.style.transform =
        "translate(" + translateX + "px, " + translateY + "px) scale(" + scale + ")";
}

/* Building information panel on the right side */
function renderInfoPanel(building) {
    const statusClass = getStatusClass(building.status);

    infoPanel.replaceChildren();

    infoPanel.appendChild(createElement("span", "eyebrow", "BUILDING INFORMATION"));
    infoPanel.appendChild(createElement("h2", "detail-name", building.name));

    infoPanel.appendChild(
        createElement("span", "status " + statusClass, getStatusLabel(building.status))
    );

    if (building.description && building.description.trim() !== "") {
        infoPanel.appendChild(
            createElement("p", "detail-description", building.description)
        );
    }

    const stats = createElement("div", "stats-grid");

    [
        [building.floors, building.floors === 1 ? "Floor" : "Floors"],
        [building.rooms, "Rooms"],
        [building.offices, "Offices"],
        [building.facilities, "Facilities"]
    ].forEach(([value, label]) => {
        const card = createElement("div", "stat-card");
        card.appendChild(createElement("strong", "", formatNumber(value)));
        card.appendChild(createElement("span", "", label));
        stats.appendChild(card);
    });

    infoPanel.appendChild(stats);

    infoPanel.appendChild(
        createElement(
            "p",
            "info-hint",
            "Click the building again to view its floor layout."
        )
    );

    const back = createElement("button", "back-btn", "← Back / Reset View");
    back.type = "button";
    back.addEventListener("click", resetView);
    infoPanel.appendChild(back);
}

/* Highlight the selected building on the map and in the list */
function updateSelectionUI() {
    const hasSelection = selectedId !== null;

    canvas.classList.toggle("has-selection", hasSelection);
    infoPanel.hidden = !hasSelection;

    canvas.querySelectorAll(".building").forEach(element => {
        element.classList.toggle(
            "selected",
            hasSelection && Number(element.dataset.id) === selectedId
        );
    });

    buildingList.querySelectorAll(".building-list-item").forEach(element => {
        element.classList.toggle(
            "selected",
            hasSelection && Number(element.dataset.id) === selectedId
        );
    });
}

/* First click: select, zoom in, show the information panel */
function selectBuilding(id) {
    const building = buildingById(id);

    if (!building) {
        return;
    }

    selectedId = building.id;
    selectedAt = performance.now();

    hideTooltip();
    renderInfoPanel(building);
    updateSelectionUI();
    zoomToBuilding(building);

    /* Make sure the zoomed map is on screen (useful on phones) */
    mapScroll.scrollIntoView({ behavior: "smooth", block: "nearest" });
}

/* Back / Reset View: zoom out and deselect */
function resetView() {
    /* Cancel a pending automatic jump to the floor layout */
    clearTimeout(searchNavTimer);

    if (selectedId === null) {
        return;
    }

    selectedId = null;

    canvas.style.transform = "";
    updateSelectionUI();

    /* Allow scrolling again once the zoom-out animation is done */
    clearTimeout(resetTimer);
    resetTimer = setTimeout(() => {
        mapScroll.classList.remove("zoomed");
    }, 700);
}

/* One click selects, a second click on the same building navigates */
function handleBuildingClick(id) {
    id = Number(id);

    /* A search pick is already on its way to the floor layout */
    clearTimeout(searchNavTimer);

    if (selectedId === id) {
        if (performance.now() - selectedAt < SECOND_CLICK_DELAY) {
            return;
        }

        openFloorLayout(id);
        return;
    }

    selectBuilding(id);
}

/* ---------- TOOLTIP (hover preview) ---------- */

function showTooltip(building, event) {
    /* The information panel is shown while a building is selected */
    if (selectedId !== null) {
        return;
    }

    tooltip.replaceChildren();

    tooltip.appendChild(
        createElement("h3", "", building.name)
    );

    tooltip.appendChild(
        createElement(
            "p",
            "",
            "Status: " + getStatusLabel(building.status)
        )
    );

    if (building.description && building.description.trim() !== "") {
        tooltip.appendChild(
            createElement("p", "", building.description)
        );
    }

    const stats = createElement("div", "tooltip-stats");

    [
        [building.rooms, "Rooms"],
        [building.offices, "Offices"],
        [building.facilities, "Facilities"]
    ].forEach(([value, label]) => {
        const stat = createElement("div", "tooltip-stat");

        stat.appendChild(
            createElement("strong", "", formatNumber(value))
        );

        stat.appendChild(
            createElement("span", "", label)
        );

        stats.appendChild(stat);
    });

    tooltip.appendChild(stats);
    tooltip.style.display = "block";

    moveTooltip(event);
}

function moveTooltip(event) {
    const margin = 12;
    const offset = 16;

    let x = event.clientX + offset;
    let y = event.clientY + offset;

    const rect = tooltip.getBoundingClientRect();

    if (x + rect.width > window.innerWidth - margin) {
        x = event.clientX - rect.width - offset;
    }

    if (y + rect.height > window.innerHeight - margin) {
        y = event.clientY - rect.height - offset;
    }

    tooltip.style.left = Math.max(margin, x) + "px";
    tooltip.style.top = Math.max(margin, y) + "px";
}

function hideTooltip() {
    tooltip.style.display = "none";
}

/* ---------- RENDER ---------- */

function renderBuildings() {
    canvas.replaceChildren();
    buildingList.replaceChildren();

    if (!buildings.length) {
        buildingList.appendChild(
            createElement(
                "div",
                "empty-message",
                "Wala pang buildings sa database. Mag-add muna ng building sa Campus Layout Editor."
            )
        );

        return;
    }

    buildings.forEach(building => {
        const statusClass = getStatusClass(building.status);
        const statusLabel = getStatusLabel(building.status);

        // Create building on campus map.
        const shape = createElement(
            "div",
            "building " + building.shape + " " + statusClass
        );

        shape.dataset.id = building.id;
        shape.setAttribute("tabindex", "0");
        shape.setAttribute("role", "button");

        shape.setAttribute(
            "aria-label",
            building.name + ", " + statusLabel +
                ". Click to zoom in, click again to open the floor layout."
        );

        shape.setAttribute(
            "title",
            building.name + " — Click to zoom in"
        );

        shape.style.left = building.x + "px";
        shape.style.top = building.y + "px";
        shape.style.width = building.w + "px";
        shape.style.height = building.h + "px";
        shape.style.transform = "rotate(" + building.rotation + "deg)";

        shape.appendChild(
            createElement("span", "building-label", building.name)
        );

        shape.addEventListener("pointerenter", event => {
            showTooltip(building, event);
        });

        shape.addEventListener("pointermove", moveTooltip);
        shape.addEventListener("pointerleave", hideTooltip);
        shape.addEventListener("blur", hideTooltip);

        shape.addEventListener("focus", () => {
            const rect = shape.getBoundingClientRect();

            showTooltip(building, {
                clientX: rect.left + rect.width / 2,
                clientY: rect.top + rect.height / 2
            });
        });

        shape.addEventListener("click", event => {
            event.stopPropagation();
            handleBuildingClick(building.id);
        });

        shape.addEventListener("keydown", event => {
            if (event.key === "Enter" || event.key === " ") {
                event.preventDefault();
                handleBuildingClick(building.id);
            }
        });

        canvas.appendChild(shape);

        // Create corresponding building list item.
        const button = createElement(
            "button",
            "building-list-item"
        );

        button.type = "button";
        button.dataset.id = building.id;

        button.appendChild(
            createElement("div", "", building.name)
        );

        button.appendChild(
            createElement(
                "span",
                "list-status " + statusClass,
                statusLabel + " Building"
            )
        );

        button.addEventListener("click", () => {
            handleBuildingClick(building.id);
        });

        buildingList.appendChild(button);
    });
}

/* Clicking an empty part of the map zooms back out */
canvas.addEventListener("click", event => {
    if (event.target === canvas) {
        resetView();
    }
});

/* Esc zooms back out (and cancels a pending jump to the floor layout) */
document.addEventListener("keydown", event => {
    if (event.key === "Escape") {
        resetView();
    }
});

/* ---------- SEARCH ---------- */

const searchInput = document.getElementById("campusSearch");
const searchResults = document.getElementById("searchResults");
const searchClear = document.getElementById("searchClear");

const MAX_RESULTS = 30;

/* "3rd Floor" -> 3 (floor-layout.php expects the floor number) */
function floorNumber(floorText) {
    const number = parseInt(String(floorText || ""), 10);
    return Number.isFinite(number) && number > 0 ? number : 1;
}

/* Zoom to a building and pulse it */
function locateBuilding(id) {
    canvas.querySelectorAll(".building.found").forEach(element => {
        element.classList.remove("found");
    });

    selectBuilding(id);

    const element = canvas.querySelector('.building[data-id="' + Number(id) + '"]');

    if (element) {
        /* Restart the pulse animation if the same building is picked again */
        void element.offsetWidth;
        element.classList.add("found");
    }
}

/*
 * Search pick: zoom in on the building first, then go directly
 * to its floor layout once the zoom animation has finished.
 */
function zoomThenOpenFloor(buildingId, floor) {
    const building = buildingById(buildingId);

    if (!building) {
        return;
    }

    clearTimeout(searchNavTimer);

    closeResults();
    locateBuilding(building.id);

    const reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

    searchNavTimer = setTimeout(() => {
        window.location.href = floorLayoutUrl(building.id, floor);
    }, reduceMotion ? 150 : SEARCH_ZOOM_WAIT);
}

function closeResults() {
    searchResults.classList.remove("open");
    searchResults.replaceChildren();
}

function makeResultRow(title, meta, onPick, linkHref) {
    const row = createElement("div", "result-row");

    const main = createElement("button", "result-main");
    main.type = "button";
    main.appendChild(createElement("span", "result-title", title));
    main.appendChild(createElement("span", "result-meta", meta));
    main.addEventListener("click", onPick);
    row.appendChild(main);

    if (linkHref) {
        const link = createElement("a", "result-link", "View floor");
        link.href = linkHref;

        /* Zoom in first instead of jumping straight to the page */
        link.addEventListener("click", event => {
            event.preventDefault();
            onPick();
        });

        row.appendChild(link);
    }

    return row;
}

function runSearch() {
    const query = searchInput.value.trim().toLowerCase();

    searchClear.hidden = query === "";

    if (query === "") {
        closeResults();
        return;
    }

    /* Buildings that match by name */
    const buildingMatches = buildings.filter(building =>
        building.name.toLowerCase().includes(query)
    );

    /* Rooms, offices and facilities that match by name, number, or type */
    const roomMatches = searchRooms.filter(room => {
        if (!buildingById(room.buildingId)) {
            return false;
        }

        const text = (room.number + " " + room.name + " " + room.type)
            .toLowerCase();

        return text.includes(query);
    });

    searchResults.replaceChildren();

    if (!buildingMatches.length && !roomMatches.length) {
        searchResults.appendChild(
            createElement("div", "no-results", "No buildings or rooms found for “" + searchInput.value.trim() + "”.")
        );
        searchResults.classList.add("open");
        return;
    }

    if (buildingMatches.length) {
        searchResults.appendChild(
            createElement("div", "search-group-title", "BUILDINGS")
        );

        buildingMatches.slice(0, MAX_RESULTS).forEach(building => {
            searchResults.appendChild(
                makeResultRow(
                    building.name,
                    "Building · " + building.floors + (building.floors === 1 ? " floor" : " floors") +
                        " · Zooms in, then opens its floor layout",
                    () => {
                        zoomThenOpenFloor(building.id, 1);
                    },
                    floorLayoutUrl(building.id, 1)
                )
            );
        });
    }

    if (roomMatches.length) {
        searchResults.appendChild(
            createElement("div", "search-group-title", "ROOMS, OFFICES & FACILITIES")
        );

        roomMatches.slice(0, MAX_RESULTS).forEach(room => {
            const building = buildingById(room.buildingId);
            const floor = floorNumber(room.floor);

            const title = room.number !== ""
                ? room.number + " — " + room.name
                : room.name;

            const meta = room.type + " · " + building.name +
                (room.floor !== "" ? " · " + room.floor : "");

            searchResults.appendChild(
                makeResultRow(
                    title,
                    meta,
                    () => {
                        zoomThenOpenFloor(building.id, floor);
                    },
                    floorLayoutUrl(building.id, floor)
                )
            );
        });
    }

    searchResults.classList.add("open");
}

searchInput.addEventListener("input", runSearch);

searchInput.addEventListener("focus", () => {
    if (searchInput.value.trim() !== "") {
        runSearch();
    }
});

searchInput.addEventListener("keydown", event => {
    if (event.key === "Enter") {
        event.preventDefault();

        const first = searchResults.querySelector(".result-main");

        if (first) {
            first.click();
        }
    }

    if (event.key === "Escape") {
        closeResults();
    }
});

searchClear.addEventListener("click", () => {
    searchInput.value = "";
    searchClear.hidden = true;
    closeResults();

    canvas.querySelectorAll(".building.found").forEach(element => {
        element.classList.remove("found");
    });

    searchInput.focus();
});

/* Close the results when clicking outside the search area */
document.addEventListener("click", event => {
    if (!event.target.closest(".search-panel")) {
        closeResults();
    }
});

window.addEventListener("scroll", hideTooltip, true);

/* Keep the selected building centered if the window is resized */
window.addEventListener("resize", () => {
    hideTooltip();

    const building = selectedId !== null ? buildingById(selectedId) : null;

    if (building) {
        zoomToBuilding(building);
    }
});

renderBuildings();
</script>

</body>
</html>