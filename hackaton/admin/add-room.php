
<?php
session_start();

if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit;
}

require_once "../config/database.php";

function e($value) {
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}

function getFloorOptions($totalFloors) {
    $floors = [];
    $totalFloors = max(0, min(100, (int)$totalFloors));

    for ($i = 1; $i <= $totalFloors; $i++) {
        if ($i % 100 >= 11 && $i % 100 <= 13) {
            $suffix = "th";
        } else {
            switch ($i % 10) {
                case 1: $suffix = "st"; break;
                case 2: $suffix = "nd"; break;
                case 3: $suffix = "rd"; break;
                default: $suffix = "th";
            }
        }

        $floors[] = $i . $suffix . " Floor";
    }

    return $floors;
}

/* Convert labels such as "2nd Floor" into integer 2 for Supabase. */
function floorLabelToNumber($floorLabel) {
    $floorLabel = trim((string)$floorLabel);

    if (preg_match('/^(\d+)(st|nd|rd|th)\s+Floor$/i', $floorLabel, $matches)) {
        return (int)$matches[1];
    }

    if (ctype_digit($floorLabel)) {
        return (int)$floorLabel;
    }

    return 0;
}

/* Fixed color for each category */
$categoryColors = [
    "Room"       => "#0b3b82",
    "Offices"    => "#2e9e5b",
    "Facilities" => "#e8772e"
];

/* Room name options depend on the selected Type */
$roomNameOptions = [
    "Room" => [
        "Classroom",
        "Computer Laboratory (ComLab)",
        "Faculty Room",
        "Science Laboratory"
    ],
    "Offices" => [
        "Registrar's Office",
        "Guidance Office",
        "Administrative Office",
        "Faculty Office"
    ],
    "Facilities" => [
        "Library",
        "Clinic",
        "Canteen",
        "Comfort Room (CR)",
        "Guardhouse"
    ]
];

$typeOptions = array_keys($roomNameOptions);

$buildingId = (int)($_GET["building_id"] ?? $_POST["building_id"] ?? 0);
$selectedFloor = trim((string)($_GET["floor"] ?? $_POST["floor"] ?? ""));
$message = "";
$error = "";

$buildings = [];

$stmt = $conn->query(
    "SELECT building_id, building_name, total_floors
     FROM buildings
     ORDER BY building_name"
);
$buildings = $stmt->fetchAll(PDO::FETCH_ASSOC);

$selectedBuilding = null;

if ($buildingId > 0) {
    $stmt = $conn->prepare(
        "SELECT building_id, building_name, total_floors
         FROM buildings
         WHERE building_id = :building_id"
    );

    $stmt->execute([
        "building_id" => $buildingId
    ]);

    $selectedBuilding = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$selectedBuilding) {
        $buildingId = 0;
        $selectedFloor = "";
    }
}

$floors = [];

if ($buildingId > 0 && $selectedBuilding) {
    $floors = getFloorOptions($selectedBuilding["total_floors"]);

    if ($selectedFloor !== "" && !in_array($selectedFloor, $floors, true)) {
        $selectedFloor = "";
    }
}

/* ADD ROOM */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["add_room"])) {
    $roomType = trim($_POST["new_room_type"] ?? "");
    $roomNumber = trim($_POST["new_room_number"] ?? "");
    $roomNameChoice = trim($_POST["new_room_name"] ?? "");
    $roomNameOther = trim($_POST["new_room_name_other"] ?? "");
    $selectedFloor = trim($_POST["floor"] ?? "");

    $width = 140;
    $height = 90;
    $shape = "rectangle";
    $rotation = 0;
    $x = 40;
    $y = 65;

    $roomName = ($roomNameChoice === "Others")
        ? $roomNameOther
        : $roomNameChoice;

    if (!$selectedBuilding || $buildingId <= 0) {
        $error = "Please select a building first.";
    } elseif ($selectedFloor === "" || !in_array($selectedFloor, $floors, true)) {
        $error = "Please select a valid floor.";
    } elseif (!in_array($roomType, $typeOptions, true)) {
        $error = "Please select a valid type (Room, Offices, or Facilities).";
    } elseif ($roomType === "Room" && $roomNumber === "") {
        $error = "Room Number is required.";
    } elseif ($roomType === "Room" && (!ctype_digit($roomNumber) || strlen($roomNumber) > 50)) {
        $error = "Room Number must contain numbers only.";
    } elseif ($roomNameChoice === "") {
        $error = "Please select a Room Name.";
    } elseif ($roomNameChoice !== "Others" && !in_array($roomNameChoice, $roomNameOptions[$roomType], true)) {
        $error = "Please select a valid Room Name for the chosen type.";
    } elseif ($roomNameChoice === "Others" && $roomNameOther === "") {
        $error = "Please type the custom Room Name.";
    } elseif (mb_strlen($roomName) > 150) {
        $error = "Room Name must not exceed 150 characters.";
    } else {
        if ($roomType !== "Room") {
            $roomNumber = "";
        }

        try {
            $stmt = $conn->prepare(
                "INSERT INTO rooms
                    (building_id, room_number, room_name, room_type, floor,
                     position_x, position_y, width, height,
                     shape_type, rotation)
                 VALUES
                    (:building_id, :room_number, :room_name, :room_type, :floor,
                     :position_x, :position_y, :width, :height,
                     :shape_type, :rotation)"
            );

            $stmt->execute([
                "building_id" => $buildingId,
                "room_number" => $roomNumber,
                "room_name" => $roomName,
                "room_type" => $roomType,
                "floor" => floorLabelToNumber($selectedFloor),
                "position_x" => $x,
                "position_y" => $y,
                "width" => $width,
                "height" => $height,
                "shape_type" => $shape,
                "rotation" => $rotation
            ]);

            header(
                "Location: add-room.php?building_id=" . $buildingId .
                "&floor=" . urlencode($selectedFloor) . "&added=1"
            );
            exit;

        } catch (Throwable $ex) {
            error_log("Add room failed: " . $ex->getMessage());
            $error = "Unable to add room. Please check your database columns.";
        }
    }
}

/* SAVE ROOM LAYOUT */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["save_layout"])) {
    $ids = $_POST["room_id"] ?? [];
    $xs = $_POST["position_x"] ?? [];
    $ys = $_POST["position_y"] ?? [];
    $widths = $_POST["width"] ?? [];
    $heights = $_POST["height"] ?? [];
    $rotations = $_POST["rotation"] ?? [];
    $names = $_POST["room_name"] ?? [];

    if (!$selectedBuilding || $selectedFloor === "") {
        $error = "Please select a building and floor.";
    } elseif (!in_array($selectedFloor, $floors, true)) {
        $error = "Please select a valid floor.";
    } elseif (!is_array($ids) || count($ids) > 500) {
        $error = "Invalid layout data.";
    } else {
        try {
            $conn->beginTransaction();

            $stmt = $conn->prepare(
                "UPDATE rooms
                 SET position_x = :position_x,
                     position_y = :position_y,
                     width = :width,
                     height = :height,
                     shape_type = :shape_type,
                     rotation = :rotation,
                     room_name = :room_name
                 WHERE room_id = :room_id
                   AND building_id = :building_id
                   AND floor = :floor"
            );

            foreach ($ids as $i => $idValue) {
                $roomId = (int)$idValue;

                if ($roomId <= 0) {
                    continue;
                }

                $x = max(0, min(1100, (float)($xs[$i] ?? 40)));
                $y = max(0, min(550, (float)($ys[$i] ?? 65)));
                $width = max(60, min(600, (float)($widths[$i] ?? 140)));
                $height = max(45, min(400, (float)($heights[$i] ?? 90)));
                $rotation = max(-360, min(360, (float)($rotations[$i] ?? 0)));
                $shape = "rectangle";

                $roomName = mb_substr(
                    trim((string)($names[$i] ?? "")),
                    0,
                    150
                );

                if ($roomName === "") {
                    throw new InvalidArgumentException("Room Name cannot be empty.");
                }

                $stmt->execute([
                    "position_x" => $x,
                    "position_y" => $y,
                    "width" => $width,
                    "height" => $height,
                    "shape_type" => $shape,
                    "rotation" => $rotation,
                    "room_name" => $roomName,
                    "room_id" => $roomId,
                    "building_id" => $buildingId,
                    "floor" => floorLabelToNumber($selectedFloor)
                ]);
            }

            $conn->commit();

            header(
                "Location: add-room.php?building_id=" . $buildingId .
                "&floor=" . urlencode($selectedFloor) . "&saved=1"
            );
            exit;

        } catch (InvalidArgumentException $ex) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }

            $error = $ex->getMessage();

        } catch (Throwable $ex) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }

            error_log("Save room layout failed: " . $ex->getMessage());
            $error = "Unable to save layout. Check your database room layout columns.";
        }
    }
}

if (isset($_GET["added"])) {
    $message = "Room added successfully!";
}

if (isset($_GET["saved"])) {
    $message = "Room layout saved successfully!";
}

/* LOAD ROOMS FOR THE SELECTED FLOOR */
$rooms = [];

if (
    $buildingId > 0 &&
    $selectedBuilding &&
    $selectedFloor !== "" &&
    in_array($selectedFloor, $floors, true)
) {
    $stmt = $conn->prepare(
        "SELECT room_id, room_number, room_name, room_type, floor,
                position_x, position_y, width, height,
                shape_type, rotation
         FROM rooms
         WHERE building_id = :building_id
           AND floor = :floor
         ORDER BY room_number"
    );

    $stmt->execute([
        "building_id" => $buildingId,
        "floor" => floorLabelToNumber($selectedFloor)
    ]);

    $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Room Layout Editor | CMU Campus Navigator</title>

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
    padding: 15px 4%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    background: #fff;
    border-bottom: 1px solid #dce4ef;
}

.logo {
    display: flex;
    align-items: center;
    gap: 14px;
}

.logo img {
    width: 56px;
    height: 56px;
    object-fit: contain;
}

.logo h2 {
    margin: 0 0 5px;
    color: #0b3b82;
    font-size: 21px;
}

.logo span {
    color: #4d5f78;
    font-size: 14px;
    font-weight: bold;
}

.navbar nav {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}

.navbar nav a {
    padding: 12px 18px;
    border-radius: 7px;
    text-decoration: none;
    font-size: 14px;
    font-weight: bold;
}

.view-btn {
    background: #edf3fc;
    color: #0b3b82;
}

.logout-btn {
    background: #b42318;
    color: white;
}

.admin-layout {
    display: flex;
    min-height: calc(100vh - 150px);
}

.sidebar {
    width: 255px;
    flex-shrink: 0;
    background: #0b3b82;
    padding: 30px 18px;
    color: white;
}

.sidebar h3 {
    margin: 0 12px 22px;
    color: #ffdf45;
    font-size: 14px;
    letter-spacing: 1.2px;
}

.sidebar a {
    display: block;
    margin-bottom: 8px;
    padding: 15px 14px;
    border-radius: 7px;
    color: white;
    text-decoration: none;
    font-weight: bold;
}

.sidebar a:hover,
.sidebar a.active {
    background: #24569b;
}

.content {
    flex: 1;
    min-width: 0;
    padding: 35px;
}

.eyebrow {
    color: #805900;
    font-size: 13px;
    font-weight: bold;
    letter-spacing: 1.4px;
}

h1 {
    margin: 10px 0;
    color: #0b3b82;
    font-size: 32px;
}

h2,
h3 {
    color: #0b3b82;
}

.subtitle {
    color: #52627a;
    line-height: 1.6;
    margin: 0 0 20px;
}

.panel {
    background: white;
    border: 1px solid #d8e1ee;
    border-radius: 12px;
    padding: 22px;
    margin-bottom: 20px;
    box-shadow: 0 4px 14px rgba(11, 59, 130, 0.05);
}

.panel h2,
.panel h3 {
    margin-top: 0;
}

.picker {
    display: grid;
    grid-template-columns: 1fr 1fr auto;
    gap: 15px;
    align-items: end;
}

.add-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 15px;
    align-items: end;
}

.field label {
    display: block;
    margin-bottom: 7px;
    color: #43536b;
    font-size: 14px;
    font-weight: bold;
}

.field input,
.field select {
    width: 100%;
    min-height: 42px;
    padding: 10px;
    border: 1px solid #d8e1ee;
    border-radius: 7px;
    background: white;
    color: #172b4d;
    font: inherit;
}

.field input[readonly] {
    background: #f6f8fc;
}

.field select:disabled {
    background: #f1f4f9;
    cursor: not-allowed;
}

.field.full {
    grid-column: 1 / -1;
}

.field.hidden {
    display: none;
}

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

button,
.btn {
    display: inline-block;
    padding: 12px 16px;
    border: 0;
    border-radius: 7px;
    background: #0b3b82;
    color: white;
    text-decoration: none;
    font-size: 14px;
    font-weight: bold;
    cursor: pointer;
}

button:hover,
.btn:hover {
    filter: brightness(.94);
}

button:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    filter: none;
}

.gold {
    background: #d9a900;
    color: #172b4d;
}

.light {
    background: #edf3fc;
    color: #0b3b82;
}

.btn-blue {
    background: #0b3b82;
    color: #fff;
}

.btn-red {
    background: #b42318;
    color: #fff;
}

.notice {
    padding: 13px 16px;
    border-radius: 7px;
    margin-bottom: 18px;
}

.success {
    background: #edf8ef;
    border: 1px solid #b9e2c0;
    color: #216e39;
}

.error {
    background: #fff0ee;
    border: 1px solid #f2c3bd;
    color: #a1261c;
}

.undo-bar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 12px;
    margin-bottom: 14px;
    color: #52627a;
    font-size: 13px;
    line-height: 1.5;
}

.align-toggle {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 14px;
    border: 1px solid #d8e1ee;
    border-radius: 7px;
    background: #f8faff;
    color: #0b3b82;
    font-size: 14px;
    font-weight: bold;
    cursor: pointer;
    user-select: none;
}

.align-toggle input {
    width: 16px;
    height: 16px;
    margin: 0;
    cursor: pointer;
}

.editor-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 270px;
    gap: 18px;
    align-items: start;
}

.canvas-scroll {
    width: 100%;
    overflow: auto;
    border: 1px solid #d8e0ec;
    border-radius: 8px;
    background: white;
}

#roomCanvas {
    position: relative;
    width: 1200px;
    height: 650px;
    overflow: hidden;
    background-color: #fbfcff;
    background-image:
        linear-gradient(#e7ecf4 1px, transparent 1px),
        linear-gradient(90deg, #e7ecf4 1px, transparent 1px);
    background-size: 20px 20px;
}

.canvas-label {
    position: absolute;
    top: 12px;
    left: 15px;
    color: #9aa5b5;
    font-size: 13px;
    pointer-events: none;
}

.align-guide {
    position: absolute;
    z-index: 50;
    background: #d6479b;
    pointer-events: none;
}

.align-guide.vertical {
    top: 0;
    bottom: 0;
    width: 1px;
}

.align-guide.horizontal {
    left: 0;
    right: 0;
    height: 1px;
}

.room-shape {
    position: absolute;
    min-width: 60px;
    min-height: 45px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 6px;
    border: 2px solid #0b3b82;
    border-radius: 0;
    background: #0b3b82;
    color: #ffffff;
    text-align: center;
    cursor: move;
    user-select: none;
    touch-action: none;
}

.room-shape.selected {
    box-shadow: 0 0 0 3px #d9a900;
    z-index: 5;
}

.room-label {
    font-size: 12px;
    font-weight: bold;
    white-space: pre-line;
    overflow-wrap: anywhere;
    pointer-events: none;
}

.resize-handle {
    position: absolute;
    right: -6px;
    bottom: -6px;
    width: 13px;
    height: 13px;
    border: 2px solid white;
    border-radius: 3px;
    background: #d9a900;
    cursor: nwse-resize;
}

.details .field {
    margin-bottom: 12px;
}

.details h3 {
    margin-bottom: 18px;
}

.room-list {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 8px;
}

.room-list button {
    text-align: left;
    background: #edf3fc;
    color: #172b4d;
    border-left: 6px solid #0b3b82;
}

.room-list button.active {
    background: #fff8d8;
    outline: 1px solid #d9a900;
}

footer {
    padding: 20px;
    text-align: center;
    background: white;
    border-top: 1px solid #dce4ef;
    color: #43536b;
    font-size: 14px;
}

@media (max-width: 1100px) {
    .editor-grid {
        grid-template-columns: 1fr;
    }

    .picker {
        grid-template-columns: 1fr 1fr;
    }

    .picker button {
        grid-column: 1 / -1;
    }

    .add-grid {
        grid-template-columns: 1fr 1fr;
    }
}

@media (max-width: 800px) {
    .admin-layout {
        flex-direction: column;
    }

    .sidebar {
        width: 100%;
        padding: 18px;
    }

    .content {
        padding: 22px 15px;
    }

    .navbar {
        flex-direction: column;
        align-items: flex-start;
    }

    .picker,
    .add-grid {
        grid-template-columns: 1fr;
    }
}

.modal-backdrop {
    position: fixed;
    inset: 0;
    z-index: 100000;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 20px;
    background: rgba(23, 43, 77, 0.55);
}

.modal-backdrop.open {
    display: flex;
}

.modal-box {
    width: 100%;
    max-width: 440px;
    padding: 28px;
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 20px 50px rgba(11, 59, 130, 0.3);
}

.modal-icon {
    margin-bottom: 10px;
    font-size: 34px;
}

.modal-box h2 {
    margin: 0 0 10px;
    color: #0b3b82;
    font-size: 22px;
    font-weight: 800;
}

.modal-box p {
    margin: 0 0 22px;
    color: #43536b;
    font-size: 15px;
    line-height: 1.7;
}

.modal-actions {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 10px;
}
</style>
</head>

<body>
<header class="navbar">
    <div class="logo">
        <img src="../cmu logo.jpg" alt="CMU Logo">
        <div>
            <h2>CMU Campus Navigator</h2>
            <span>Administrator Dashboard</span>
        </div>
    </div>

    <nav>
        <a href="../index.php" class="view-btn">View Website</a>
        <a href="logout.php" class="logout-btn">Logout</a>
    </nav>
</header>

<div class="admin-layout">
    <aside class="sidebar">
        <h3>ADMIN MENU</h3>
        <a href="dashboard.php">📊 Dashboard</a>
        <a href="buildings.php">🏢 Buildings</a>
        <a href="rooms.php" class="active">🚪 Rooms</a>
        <a href="offices.php">🏛️ Offices</a>
        <a href="facilities.php">🗺️ Facilities</a>
    </aside>

    <main class="content">
        <span class="eyebrow">ADMINISTRATION</span>
        <h1>Room Layout Editor</h1>
        <p class="subtitle">
            Add rooms and arrange their positions on the selected building floor.
        </p>

        <?php if ($message): ?>
            <div class="notice success"><?= e($message) ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="notice error"><?= e($error) ?></div>
        <?php endif; ?>

        <section class="panel">
            <h2>Select Building and Floor</h2>

            <form method="GET" action="add-room.php" class="picker">
                <div class="field">
                    <label for="building_id">Building *</label>
                    <select name="building_id" id="building_id" required>
                        <option value="">Choose a building</option>
                        <?php foreach ($buildings as $building): ?>
                            <option
                                value="<?= (int)$building["building_id"] ?>"
                                data-floors="<?= (int)$building["total_floors"] ?>"
                                <?= $buildingId === (int)$building["building_id"] ? "selected" : "" ?>
                            >
                                <?= e($building["building_name"]) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="floor">Floor *</label>
                    <select
                        name="floor"
                        id="floor"
                        required
                        <?= $buildingId ? "" : "disabled" ?>
                    >
                        <option value="">Choose a floor</option>
                        <?php foreach ($floors as $floor): ?>
                            <option
                                value="<?= e($floor) ?>"
                                <?= $selectedFloor === $floor ? "selected" : "" ?>
                            >
                                <?= e($floor) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit">Open Layout</button>
            </form>
        </section>

        <?php if (
            $selectedBuilding &&
            $selectedFloor !== "" &&
            in_array($selectedFloor, $floors, true)
        ): ?>

        <section class="panel">
            <h2>
                <?= e($selectedBuilding["building_name"]) ?>
                — <?= e($selectedFloor) ?>
            </h2>

            <p class="subtitle">
                Add room details and arrange the rooms directly on the layout.
            </p>

            <h3>Add New Room</h3>

            <form method="POST" action="add-room.php" class="panel" id="addRoomForm">
                <input type="hidden" name="building_id" value="<?= $buildingId ?>">
                <input type="hidden" name="floor" value="<?= e($selectedFloor) ?>">

                <div class="add-grid">
                    <div class="field">
                        <label for="new_room_type">Type *</label>
                        <select id="new_room_type" name="new_room_type" required>
                            <option value="">Choose a type</option>
                            <?php foreach ($typeOptions as $type): ?>
                                <option value="<?= e($type) ?>"><?= e($type) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field hidden" id="roomNumberField">
                        <label for="new_room_number">Room Number *</label>
                        <input
                            id="new_room_number"
                            name="new_room_number"
                            type="text"
                            inputmode="numeric"
                            pattern="[0-9]*"
                            maxlength="50"
                            placeholder="Example: 101"
                            title="Numbers only"
                        >
                    </div>

                    <div class="field">
                        <label for="new_room_name">Room Name *</label>
                        <select id="new_room_name" name="new_room_name" required disabled>
                            <option value="">Choose a type first</option>
                        </select>
                    </div>

                    <div class="field full hidden" id="otherRoomNameField">
                        <label for="new_room_name_other">Custom Room Name *</label>
                        <input
                            id="new_room_name_other"
                            name="new_room_name_other"
                            type="text"
                            maxlength="150"
                            placeholder="Type the room name"
                        >
                    </div>
                </div>

                <button
                    type="submit"
                    name="add_room"
                    value="1"
                    class="gold"
                    style="margin-top:15px"
                >
                    + Add Room
                </button>
            </form>

            <h3>Room Layout</h3>

            <div class="legend">
                <?php foreach ($categoryColors as $categoryName => $categoryColor): ?>
                    <span class="legend-item">
                        <span
                            class="legend-dot"
                            style="background: <?= e($categoryColor) ?>"
                        ></span>
                        <?= e($categoryName) ?>
                    </span>
                <?php endforeach; ?>
            </div>

            <form method="POST" action="add-room.php" id="layoutForm">
                <input type="hidden" name="building_id" value="<?= $buildingId ?>">
                <input type="hidden" name="floor" value="<?= e($selectedFloor) ?>">

                <div class="undo-bar">
                    <button
                        type="button"
                        class="light"
                        id="undoBtn"
                        title="Undo the last change (Ctrl+Z)"
                        disabled
                    >
                        Undo
                    </button>

                    <label class="align-toggle" title="Snap rooms to each other while moving">
                        <input type="checkbox" id="autoAlign" checked>
                        Auto Align
                    </label>

                    <span>
                        Move, resize, or rename a room, then click Undo to go back one step.
                        With Auto Align on, a room snaps to other rooms and the layout edges
                        (hold Alt while dragging to move freely).
                    </span>
                </div>

                <div class="editor-grid">
                    <div class="canvas-scroll">
                        <div id="roomCanvas">
                            <div class="canvas-label">
                                <?= e($selectedBuilding["building_name"]) ?>
                                — <?= e($selectedFloor) ?>
                            </div>
                        </div>
                    </div>

                    <aside class="panel details">
                        <h3>Room Information</h3>

                        <div class="field">
                            <label for="detailType">Type</label>
                            <input id="detailType" type="text" readonly placeholder="Select a room">
                        </div>

                        <div class="field">
                            <label for="detailNumber">Room Number</label>
                            <input id="detailNumber" type="text" readonly placeholder="Select a room">
                        </div>

                        <div class="field">
                            <label for="detailNameSelect">Room Name</label>
                            <select id="detailNameSelect" disabled>
                                <option value="">Select a room</option>
                            </select>
                        </div>

                        <div class="field hidden" id="detailNameCustomField">
                            <label for="detailName">Custom Room Name</label>
                            <input
                                id="detailName"
                                type="text"
                                maxlength="150"
                                placeholder="Type the room name"
                            >
                        </div>

                        <div class="field">
                            <label for="detailX">Position X</label>
                            <input id="detailX" type="number" readonly>
                        </div>

                        <div class="field">
                            <label for="detailY">Position Y</label>
                            <input id="detailY" type="number" readonly>
                        </div>
                    </aside>
                </div>

                <div class="panel" style="margin-top:20px">
                    <h3>Room List</h3>
                    <div class="room-list" id="roomList"></div>
                </div>

                <button type="submit" name="save_layout" value="1">Save Layout</button>
                <a href="rooms.php" class="btn light">Manage Room Details</a>
            </form>
        </section>
        <?php endif; ?>
    </main>
</div>

<footer>
    © <?= date("Y") ?> CMU Campus Navigator | City of Malabon University
</footer>

<div class="modal-backdrop" id="unsavedModal">
    <div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="unsavedTitle">
        <div class="modal-icon">⚠️</div>
        <h2 id="unsavedTitle">Unsaved Changes</h2>
        <p>
            You have unsaved changes in the room editing section.
            If you leave this page now, your changes will be lost.
        </p>
        <div class="modal-actions">
            <button type="button" class="btn-blue" id="stayBtn">Stay on Page</button>
            <button type="button" class="btn-red" id="leaveBtn">Leave Without Saving</button>
        </div>
    </div>
</div>

<script>
const buildingSelect = document.getElementById("building_id");
const floorSelect = document.getElementById("floor");

if (buildingSelect && floorSelect) {
    buildingSelect.addEventListener("change", () => {
        floorSelect.value = "";
        floorSelect.disabled = true;

        const selectedOption = buildingSelect.options[buildingSelect.selectedIndex];
        const totalFloors = parseInt(selectedOption?.dataset.floors || "0", 10);

        floorSelect.innerHTML = '<option value="">Choose a floor</option>';

        for (let i = 1; i <= totalFloors; i++) {
            let suffix;

            if (i % 100 >= 11 && i % 100 <= 13) {
                suffix = "th";
            } else {
                switch (i % 10) {
                    case 1: suffix = "st"; break;
                    case 2: suffix = "nd"; break;
                    case 3: suffix = "rd"; break;
                    default: suffix = "th";
                }
            }

            const floorName = i + suffix + " Floor";
            const option = document.createElement("option");
            option.value = floorName;
            option.textContent = floorName;
            floorSelect.appendChild(option);
        }

        floorSelect.disabled = !selectedOption?.value || totalFloors <= 0;
    });
}

/* Room type and room name options */
const roomNameOptions = <?= json_encode(
    $roomNameOptions,
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
) ?>;

const CATEGORY_COLORS = <?= json_encode(
    $categoryColors,
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
) ?>;

const typeSelect = document.getElementById("new_room_type");
const numberField = document.getElementById("roomNumberField");
const numberInput = document.getElementById("new_room_number");
const nameSelect = document.getElementById("new_room_name");
const otherField = document.getElementById("otherRoomNameField");
const otherInput = document.getElementById("new_room_name_other");

if (typeSelect && numberField && numberInput && nameSelect && otherField && otherInput) {
    function toggleOtherName() {
        const isOther = nameSelect.value === "Others";
        otherField.classList.toggle("hidden", !isOther);
        otherInput.required = isOther;

        if (isOther) {
            otherInput.focus();
        } else {
            otherInput.value = "";
        }
    }

    function updateTypeFields() {
        const type = typeSelect.value;
        const showNumber = type === "Room";

        numberField.classList.toggle("hidden", !showNumber);
        numberInput.required = showNumber;
        numberInput.disabled = !showNumber;

        if (!showNumber) {
            numberInput.value = "";
        }

        nameSelect.innerHTML = "";

        if (!type || !roomNameOptions[type]) {
            nameSelect.innerHTML = '<option value="">Choose a type first</option>';
            nameSelect.disabled = true;
        } else {
            const placeholder = document.createElement("option");
            placeholder.value = "";
            placeholder.textContent = "Choose a room name";
            nameSelect.appendChild(placeholder);

            roomNameOptions[type].forEach(name => {
                const option = document.createElement("option");
                option.value = name;
                option.textContent = name;
                nameSelect.appendChild(option);
            });

            const other = document.createElement("option");
            other.value = "Others";
            other.textContent = "Others";
            nameSelect.appendChild(other);
            nameSelect.disabled = false;
        }

        toggleOtherName();
    }

    numberInput.addEventListener("input", () => {
        numberInput.value = numberInput.value.replace(/\D/g, "");
    });

    typeSelect.addEventListener("change", updateTypeFields);
    nameSelect.addEventListener("change", toggleOtherName);
    updateTypeFields();
}

const initialRooms = <?= json_encode(
    $rooms,
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
) ?>;

const canvas = document.getElementById("roomCanvas");
const form = document.getElementById("layoutForm");
const list = document.getElementById("roomList");

let layoutHasChanges = () => false;

if (canvas && form && list) {
    const CANVAS_W = 1200;
    const CANVAS_H = 650;
    const SNAP_DISTANCE = 8;

    const detailType = document.getElementById("detailType");
    const detailNumber = document.getElementById("detailNumber");
    const detailNameSelect = document.getElementById("detailNameSelect");
    const detailNameCustomField = document.getElementById("detailNameCustomField");
    const detailName = document.getElementById("detailName");
    const detailX = document.getElementById("detailX");
    const detailY = document.getElementById("detailY");
    const autoAlign = document.getElementById("autoAlign");

    function colorFor(r) {
        return CATEGORY_COLORS[r.room_type] || CATEGORY_COLORS["Room"];
    }

    function hexToRgb(hex) {
        return [
            parseInt(hex.slice(1, 3), 16),
            parseInt(hex.slice(3, 5), 16),
            parseInt(hex.slice(5, 7), 16)
        ];
    }

    function darken(hex, factor) {
        const [r, g, b] = hexToRgb(hex);
        const toHex = n => Math.round(n * factor).toString(16).padStart(2, "0");
        return "#" + toHex(r) + toHex(g) + toHex(b);
    }

    function textColorFor(hex) {
        const [r, g, b] = hexToRgb(hex);
        const luminance = 0.299 * r + 0.587 * g + 0.114 * b;
        return luminance > 150 ? "#172b4d" : "#ffffff";
    }

    function setValue(el, value) {
        const text = String(value);
        if (el.value !== text) {
            el.value = text;
        }
    }

    function nameChoicesFor(r) {
        if (r.room_type && roomNameOptions[r.room_type]) {
            return roomNameOptions[r.room_type];
        }
        return Object.values(roomNameOptions).flat();
    }

    let rooms = initialRooms.map((r, index) => ({
        id: Number(r.room_id),
        room_number: r.room_number || "",
        room_name: r.room_name || "",
        original_name: r.room_name || "",
        room_type: r.room_type || "",
        custom: false,
        x: Number(r.position_x ?? (40 + (index % 5) * 190)),
        y: Number(r.position_y ?? (65 + Math.floor(index / 5) * 125)),
        width: Number(r.width || 140),
        height: Number(r.height || 90),
        rotation: Number(r.rotation || 0)
    }));

    rooms.forEach(r => {
        r.custom = r.room_name !== "" && !nameChoicesFor(r).includes(r.room_name);
    });

    function roomsSnapshot() {
        return JSON.stringify(rooms.map(r => [
            r.id, r.room_name, r.x, r.y, r.width, r.height, r.rotation
        ]));
    }

    const savedRoomsSnapshot = roomsSnapshot();
    layoutHasChanges = () => roomsSnapshot() !== savedRoomsSnapshot;

    let selectedId = rooms.length ? rooms[0].id : null;
    let interaction = null;
    let builtFor = undefined;

    function selectedRoom() {
        return rooms.find(r => r.id === selectedId) || null;
    }

    /* Auto align */
    function snapTargets(movingId) {
        const xs = [0, CANVAS_W / 2, CANVAS_W];
        const ys = [0, CANVAS_H / 2, CANVAS_H];

        rooms.forEach(other => {
            if (other.id === movingId) return;
            xs.push(other.x, other.x + other.width / 2, other.x + other.width);
            ys.push(other.y, other.y + other.height / 2, other.y + other.height);
        });

        return { xs, ys };
    }

    function bestOffset(edges, targets, threshold) {
        let best = null;

        edges.forEach(edge => {
            targets.forEach(target => {
                const difference = target - edge;

                if (
                    Math.abs(difference) <= threshold &&
                    (best === null || Math.abs(difference) < Math.abs(best))
                ) {
                    best = difference;
                }
            });
        });

        return best === null ? 0 : best;
    }

    function snapPosition(room, x, y) {
        const targets = snapTargets(room.id);
        const dx = bestOffset(
            [x, x + room.width / 2, x + room.width],
            targets.xs,
            SNAP_DISTANCE
        );
        const dy = bestOffset(
            [y, y + room.height / 2, y + room.height],
            targets.ys,
            SNAP_DISTANCE
        );

        return {
            x: Math.max(0, Math.min(CANVAS_W - room.width, x + dx)),
            y: Math.max(0, Math.min(CANVAS_H - room.height, y + dy))
        };
    }

    function clearGuides() {
        canvas.querySelectorAll(".align-guide").forEach(guide => guide.remove());
    }

    function drawGuides(room) {
        clearGuides();
        const targets = snapTargets(room.id);
        const drawn = new Set();

        [room.x, room.x + room.width / 2, room.x + room.width].forEach(edge => {
            targets.xs.forEach(target => {
                if (Math.abs(target - edge) < 0.5 && !drawn.has("x" + target)) {
                    drawn.add("x" + target);
                    const guide = document.createElement("div");
                    guide.className = "align-guide vertical";
                    guide.style.left = target + "px";
                    canvas.appendChild(guide);
                }
            });
        });

        [room.y, room.y + room.height / 2, room.y + room.height].forEach(edge => {
            targets.ys.forEach(target => {
                if (Math.abs(target - edge) < 0.5 && !drawn.has("y" + target)) {
                    drawn.add("y" + target);
                    const guide = document.createElement("div");
                    guide.className = "align-guide horizontal";
                    guide.style.top = target + "px";
                    canvas.appendChild(guide);
                }
            });
        });
    }

    /* Undo history */
    const undoHistory = [];
    const UNDO_LIMIT = 50;
    const undoBtn = document.getElementById("undoBtn");

    function cloneState() {
        return JSON.stringify({ rooms: rooms, selectedId: selectedId });
    }

    function updateUndoButton() {
        if (undoBtn) {
            undoBtn.disabled = undoHistory.length === 0;
        }
    }

    function pushHistory(snapshot) {
        undoHistory.push(snapshot || cloneState());

        if (undoHistory.length > UNDO_LIMIT) {
            undoHistory.shift();
        }

        updateUndoButton();
    }

    function undo() {
        if (!undoHistory.length) return;

        const state = JSON.parse(undoHistory.pop());
        rooms = state.rooms;
        selectedId = state.selectedId;
        interaction = null;
        builtFor = undefined;

        clearGuides();
        render();
        updateUndoButton();
    }

    function trackEdits(input) {
        if (!input) return;

        let before = null;
        let pushed = false;

        input.addEventListener("focus", () => {
            before = cloneState();
            pushed = false;
        });

        input.addEventListener("input", () => {
            if (pushed) return;
            pushHistory(before || cloneState());
            pushed = true;
        });
    }

    function roomText(r, separator) {
        return [r.room_number, r.room_name]
            .filter(part => part !== "")
            .join(separator);
    }

    /* Room information panel */
    function buildNameSelect(r) {
        detailNameSelect.innerHTML = "";

        if (!r) {
            detailNameSelect.innerHTML = '<option value="">Select a room</option>';
            detailNameSelect.disabled = true;
            return;
        }

        const placeholder = document.createElement("option");
        placeholder.value = "";
        placeholder.textContent = "Choose a room name";
        detailNameSelect.appendChild(placeholder);

        nameChoicesFor(r).forEach(name => {
            const option = document.createElement("option");
            option.value = name;
            option.textContent = name;
            detailNameSelect.appendChild(option);
        });

        const other = document.createElement("option");
        other.value = "Others";
        other.textContent = "Others";
        detailNameSelect.appendChild(other);
        detailNameSelect.disabled = false;
    }

    function updateInfo() {
        const r = selectedRoom();
        const id = r ? r.id : null;

        if (builtFor !== id) {
            buildNameSelect(r);
            builtFor = id;
        }

        setValue(detailType, r ? (r.room_type || "Room") : "");
        setValue(detailNumber, r ? r.room_number : "");

        if (r) {
            const choices = nameChoicesFor(r);
            detailNameSelect.value = r.custom
                ? "Others"
                : (choices.includes(r.room_name) ? r.room_name : "");
        }

        detailNameCustomField.classList.toggle("hidden", !(r && r.custom));
        setValue(detailName, r ? r.room_name : "");
        setValue(detailX, r ? Math.round(r.x) : "");
        setValue(detailY, r ? Math.round(r.y) : "");
    }

    detailNameSelect.addEventListener("change", () => {
        const r = selectedRoom();
        if (!r) return;

        const value = detailNameSelect.value;
        if (value !== "") pushHistory();

        if (value === "Others") {
            r.custom = true;
            render();
            detailName.focus();
            detailName.select();
        } else if (value !== "") {
            r.custom = false;
            r.room_name = value;
            render();
        } else {
            render();
        }
    });

    detailName.addEventListener("input", () => {
        const r = selectedRoom();
        if (!r || !r.custom) return;
        r.room_name = detailName.value;
        render();
    });

    trackEdits(detailName);

    /* Canvas room shapes */
    function drawRoom(r) {
        let el = document.getElementById("room-" + r.id);

        if (!el) {
            el = document.createElement("div");
            el.id = "room-" + r.id;
            el.className = "room-shape";

            const label = document.createElement("span");
            label.className = "room-label";

            const handle = document.createElement("span");
            handle.className = "resize-handle";

            el.append(label, handle);
            canvas.appendChild(el);

            el.addEventListener("pointerdown", event => {
                const id = Number(el.dataset.id);
                const room = rooms.find(item => item.id === id);
                if (!room) return;

                selectedId = id;
                render();

                interaction = {
                    id,
                    type: event.target.classList.contains("resize-handle") ? "resize" : "move",
                    startX: event.clientX,
                    startY: event.clientY,
                    x: room.x,
                    y: room.y,
                    width: room.width,
                    height: room.height,
                    before: cloneState(),
                    recorded: false
                };

                el.setPointerCapture(event.pointerId);
                event.preventDefault();
            });

            el.addEventListener("pointermove", event => {
                if (!interaction || interaction.id !== Number(el.dataset.id)) return;

                const room = rooms.find(item => item.id === interaction.id);
                if (!room) return;

                const dx = event.clientX - interaction.startX;
                const dy = event.clientY - interaction.startY;

                if (!interaction.recorded) {
                    if (dx === 0 && dy === 0) return;
                    pushHistory(interaction.before);
                    interaction.recorded = true;
                }

                if (interaction.type === "move") {
                    let newX = Math.max(0, Math.min(CANVAS_W - room.width, interaction.x + dx));
                    let newY = Math.max(0, Math.min(CANVAS_H - room.height, interaction.y + dy));

                    const snapping = autoAlign && autoAlign.checked && !event.altKey;

                    if (snapping) {
                        const snapped = snapPosition(room, newX, newY);
                        newX = snapped.x;
                        newY = snapped.y;
                    }

                    room.x = newX;
                    room.y = newY;
                    render();

                    if (snapping) drawGuides(room);
                    else clearGuides();
                } else {
                    room.width = Math.max(60, Math.min(600, interaction.width + dx));
                    room.height = Math.max(45, Math.min(400, interaction.height + dy));
                    render();
                }
            });

            el.addEventListener("pointerup", () => {
                interaction = null;
                clearGuides();
            });

            el.addEventListener("pointercancel", () => {
                interaction = null;
                clearGuides();
            });
        }

        const color = colorFor(r);
        el.dataset.id = r.id;
        el.className = "room-shape" + (r.id === selectedId ? " selected" : "");
        el.style.left = r.x + "px";
        el.style.top = r.y + "px";
        el.style.width = r.width + "px";
        el.style.height = r.height + "px";
        el.style.transform = "rotate(" + r.rotation + "deg)";
        el.style.borderRadius = "0";
        el.style.background = color;
        el.style.borderColor = darken(color, 0.7);
        el.style.color = textColorFor(color);

        el.querySelector(".room-label").textContent = roomText(r, "\n");
        el.querySelector(".resize-handle").style.display =
            r.id === selectedId ? "block" : "none";
    }

    function render() {
        rooms.forEach(drawRoom);
        list.innerHTML = "";

        rooms.forEach(r => {
            const btn = document.createElement("button");
            btn.type = "button";
            btn.className = r.id === selectedId ? "active" : "";
            btn.textContent = roomText(r, " | ");
            btn.style.borderLeftColor = colorFor(r);

            btn.addEventListener("click", () => {
                selectedId = r.id;
                render();
            });

            list.appendChild(btn);
        });

        updateInfo();
    }

    form.addEventListener("submit", () => {
        form.querySelectorAll(".generated-input").forEach(el => el.remove());

        rooms.forEach(r => {
            const values = {
                room_id: r.id,
                room_name: r.room_name.trim() || r.original_name,
                position_x: r.x,
                position_y: r.y,
                width: r.width,
                height: r.height,
                shape_type: "rectangle",
                rotation: r.rotation
            };

            Object.entries(values).forEach(([key, value]) => {
                const input = document.createElement("input");
                input.type = "hidden";
                input.name = key + "[]";
                input.value = value;
                input.className = "generated-input";
                form.appendChild(input);
            });
        });
    });

    if (undoBtn) {
        undoBtn.addEventListener("click", undo);
    }

    document.addEventListener("keydown", event => {
        const tag = (event.target.tagName || "").toLowerCase();
        const typing = tag === "input" || tag === "textarea" || tag === "select";
        const modal = document.getElementById("unsavedModal");
        const modalOpen = modal && modal.classList.contains("open");

        if (
            (event.ctrlKey || event.metaKey) &&
            !event.shiftKey &&
            event.key.toLowerCase() === "z" &&
            !typing &&
            !modalOpen
        ) {
            event.preventDefault();
            undo();
        }
    });

    render();
    updateUndoButton();
}

/* Unsaved changes warning */
function addFormHasChanges() {
    if (!typeSelect || !nameSelect || !numberInput || !otherInput) {
        return false;
    }

    return typeSelect.value !== "" ||
           nameSelect.value !== "" ||
           numberInput.value.trim() !== "" ||
           otherInput.value.trim() !== "";
}

function hasUnsavedChanges() {
    return layoutHasChanges() || addFormHasChanges();
}

const unsavedModal = document.getElementById("unsavedModal");
const stayBtn = document.getElementById("stayBtn");
const leaveBtn = document.getElementById("leaveBtn");

let pendingLink = null;
let allowLeave = false;

function openUnsavedModal(link) {
    pendingLink = link;
    unsavedModal.classList.add("open");
    stayBtn.focus();
}

function closeUnsavedModal() {
    unsavedModal.classList.remove("open");
    pendingLink = null;
}

document.querySelectorAll(".sidebar a").forEach(link => {
    link.addEventListener("click", event => {
        if (allowLeave || !hasUnsavedChanges()) return;

        if (event.ctrlKey || event.metaKey || event.shiftKey || event.button !== 0) {
            return;
        }

        event.preventDefault();
        openUnsavedModal(link);
    });
});

stayBtn.addEventListener("click", closeUnsavedModal);

leaveBtn.addEventListener("click", () => {
    const link = pendingLink;
    allowLeave = true;
    unsavedModal.classList.remove("open");

    if (link) {
        link.click();
    }
});

unsavedModal.addEventListener("click", event => {
    if (event.target === unsavedModal) {
        closeUnsavedModal();
    }
});

document.addEventListener("keydown", event => {
    if (event.key === "Escape" && unsavedModal.classList.contains("open")) {
        closeUnsavedModal();
    }
});
</script>

</body>
</html>
