<?php
/*
 * University Info (public page)
 * Location: hackaton/admin/University-info.php
 * No login check, so anyone can open it from the main navigation.
 */

function h($value) {
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}

$universityName = "City of Malabon University";

$overview = "City of Malabon University (CMU) is a local university serving "
          . "the community of Malabon City. Replace this paragraph with the "
          . "official description of the university.";

$sealItems = [
    "The Shield" => "A symbol for solidarity and honor, this design element rendered in an emblematic shape of a notched shield, encloses the other elements within bold blue lines that connote emphasis and significance.",
    "The Torch" => "The lighted torch symbolizes the burning passion one must have for acquiring knowledge and for honing skills.",
    "The Globe" => "The globe with grids represents the ambition for global standards for education and production of globally competitive students. Its orbiting illusion stands for its continuous desire to do so.",
    "The Book and Academic Cap" => "An icon assembled from a combined stencil of an academic cap and a book set to remind one to hold education and achievement in high regard.",
    "The School of Fish" => "The School of Fish represents one of Malabon City’s main industry. It also symbolizes the roots of our students and the foundation of their existence."
];

$vision = "A leading university that is committed for the advancement of human lives and global competitiveness through academic excellence, research and community linkages.";

$mission = "CMU aims to provide transformative and globally competitive higher education, holistic formation, research and innovation, excellent delivery of services, and sustainable community extension and linkages.";

// Core values: CMU group (first three) and AHON group (last four)
$coreValues = [
    ["letter" => "C", "word" => "Competent",       "group" => "cmu"],
    ["letter" => "M", "word" => "Morally upright", "group" => "cmu"],
    ["letter" => "U", "word" => "United",          "group" => "cmu"],
    ["letter" => "A", "word" => "Adaptable",       "group" => "ahon"],
    ["letter" => "H", "word" => "Honest",          "group" => "ahon"],
    ["letter" => "O", "word" => "Optimistic",      "group" => "ahon"],
    ["letter" => "N", "word" => "Nationalistic",   "group" => "ahon"]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>University Info | CMU Campus Navigator</title>
    <link rel="stylesheet" href="../style.css">

    <style>
        /* =========================================
           NAVBAR LINKS (same as index.php)
           ========================================= */
        .navbar nav {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }

        .navbar nav a {
            display: inline-block;
            padding: 11px 16px;
            border-radius: 7px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 700;
            transition: filter 0.2s ease, transform 0.2s ease;
        }

        .navbar nav a:not(.admin-btn) {
            color: #0b3b82;
            background: #edf3fc;
        }

        .navbar nav a.admin-btn {
            color: #ffffff;
            background: #0b3b82;
        }

        .navbar nav a.active {
            color: #ffffff;
            background: #0b3b82;
        }

        .navbar nav a:hover {
            filter: brightness(0.95);
            transform: translateY(-1px);
        }

        /* =========================================
           PAGE LAYOUT
           ========================================= */
        .ui-page {
            background: #f3f6fb;
            color: #172b4d;
            font-family: Arial, Helvetica, sans-serif;
        }

        /* Content always sits above any overlay layer from style.css */
        .ui-page {
            position: relative;
            z-index: 50;
            isolation: isolate;
        }

        body::before,
        body::after {
            content: none !important;
            display: none !important;
        }

        /* Text is always fully visible and sharp: no fades, filters,
           blur, reveal animations or hover-dependent effects. */
        .ui-page,
        .ui-page * {
            opacity: 1 !important;
            filter: none !important;
            -webkit-backdrop-filter: none !important;
            backdrop-filter: none !important;
            animation: none !important;
            transition: none !important;
            text-shadow: none !important;
        }

        .ui-container {
            max-width: 1300px;
            margin: 0 auto;
            padding: 0 5%;
        }

        .ui-eyebrow {
            display: inline-block;
            color: #805900;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 1.6px;
        }

        /* =========================================
           TOP SECTION: INTRO + SEAL
           ========================================= */

        /* CMU building picture as the background (same look as the
           login page and Home page): light overlay, cover, centered */
        .ui-top {
            padding: 60px 0 56px;
            background-color: #dfe7f3;
            background-image:
                linear-gradient(rgba(255, 255, 255, 0.58), rgba(255, 255, 255, 0.58)),
                url("../cmu-new-building-inspection.webp");
            background-size: cover;
            background-position: center center;
            background-repeat: no-repeat;
        }

        .ui-top-grid {
            display: grid;
            grid-template-columns: minmax(0, 4fr) minmax(0, 8fr);
            gap: 24px;
            align-items: start;
        }

        /* Intro card */
        .ui-intro {
            overflow: hidden;
            color: #ffffff;
            background: #061c42;
            border: 1px solid rgba(255, 255, 255, 0.22);
            border-radius: 16px;
            box-shadow: 0 14px 34px rgba(0, 0, 0, 0.3);
        }

        .ui-photo {
            display: block;
            width: 100%;
            height: 210px;
            object-fit: cover;
            object-position: center;
            image-rendering: auto;
        }

        .ui-intro-body {
            padding: 30px;
        }

        .ui-logo {
            display: block;
            width: 92px;
            height: 92px;
            margin-bottom: 20px;
            padding: 6px;
            object-fit: contain;
            background: #ffffff;
            border: 3px solid #ffdf45;
            border-radius: 50%;
        }

        .ui-intro .ui-eyebrow {
            padding: 5px 12px;
            margin-bottom: 18px;
            color: #172b4d;
            background: #ffdf45;
            border-radius: 20px;
            font-size: 11px;
        }

        .ui-intro h1 {
            margin: 0 0 16px;
            color: #ffffff;
            font-size: 32px;
            line-height: 1.25;
            font-weight: 800;
            overflow-wrap: anywhere;
        }

        .ui-intro p {
            margin: 0 0 26px;
            color: #ffffff;
            font-size: 15.5px;
            line-height: 1.8;
        }

        .ui-btn {
            display: inline-block;
            padding: 13px 22px;
            color: #172b4d;
            background: #ffdf45;
            border-radius: 8px;
            text-decoration: none;
            font-size: 14.5px;
            font-weight: 800;
        }

        /* Seal panel */
        .ui-seal {
            padding: 28px;
            background: #ffffff;
            border: 1px solid #d8e1ee;
            border-radius: 16px;
            box-shadow: 0 8px 24px rgba(11, 59, 130, 0.07);
        }

        .ui-heading {
            display: flex;
            align-items: center;
            gap: 14px;
            margin: 0 0 20px;
        }

        .ui-heading h2 {
            margin: 0;
            color: #0b3b82;
            font-size: 22px;
            font-weight: 800;
            white-space: nowrap;
        }

        .ui-heading::after {
            content: "";
            flex: 1;
            height: 1px;
            background: #e1e8f2;
        }

        .ui-heading-sub {
            margin: -12px 0 20px;
            color: #34435a;
            font-size: 14.5px;
            line-height: 1.6;
        }

        /* 3 cards on the first row, 2 wider cards on the second */
        .seal-grid {
            display: grid;
            grid-template-columns: repeat(6, minmax(0, 1fr));
            gap: 14px;
        }

        .seal-card {
            grid-column: span 2;
            min-width: 0;
            padding: 20px 18px;
            background: #f8faff;
            border: 1px solid #dce6f4;
            border-top: 3px solid #c39400;
            border-radius: 12px;
        }

        .seal-card:nth-child(n+4) {
            grid-column: span 3;
        }

        .seal-number {
            display: inline-block;
            margin-bottom: 12px;
            padding: 4px 10px;
            color: #ffffff;
            background: #0b3b82;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 1px;
        }

        .seal-card h3 {
            margin: 0 0 8px;
            color: #0b3b82;
            font-size: 16px;
            font-weight: 800;
            line-height: 1.35;
        }

        .seal-card p {
            margin: 0;
            color: #2b3a52;
            font-size: 14px;
            line-height: 1.7;
        }

        /* =========================================
           BOTTOM FULL-WIDTH SECTION
           ========================================= */
        .ui-bottom {
            padding: 50px 0 60px;
            background: #ffffff;
            border-top: 1px solid #dce4ef;
        }

        .ui-block + .ui-block {
            margin-top: 40px;
        }

        /* Vision + Mission */
        .vm-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px;
        }

        .vm-card {
            min-width: 0;
            padding: 32px;
            border-radius: 16px;
        }

        .vm-card.vision {
            color: #ffffff;
            background: linear-gradient(145deg, #0b3b82, #1a5bb8);
            box-shadow: 0 12px 28px rgba(11, 59, 130, 0.18);
        }

        .vm-card.mission {
            background: #fff8dc;
            border: 1px solid #efdca0;
        }

        .vm-card h3 {
            margin: 0 0 16px;
            padding-bottom: 14px;
            font-size: 15px;
            font-weight: 800;
            letter-spacing: 2px;
            border-bottom: 1px solid;
        }

        .vm-card.vision h3 {
            color: #ffdf45;
            border-color: rgba(255, 255, 255, 0.25);
        }

        .vm-card.mission h3 {
            color: #805900;
            border-color: #efdca0;
        }

        .vm-card p {
            margin: 0;
            font-size: 16px;
            line-height: 1.85;
        }

        .vm-card.vision p {
            color: #ffffff;
        }

        .vm-card.mission p {
            color: #2b3a52;
        }

        /* Core values */
        .values-legend {
            display: flex;
            flex-wrap: wrap;
            gap: 18px;
            margin: -8px 0 20px;
            color: #2b3a52;
            font-size: 13.5px;
            font-weight: 700;
        }

        .legend-item {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .legend-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
        }

        .legend-dot.cmu {
            background: #0b3b82;
        }

        .legend-dot.ahon {
            background: #805900;
        }

        .values-grid {
            display: grid;
            grid-template-columns: repeat(7, minmax(0, 1fr));
            gap: 14px;
        }

        .value-tile {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
            min-width: 0;
            padding: 26px 10px 22px;
            text-align: center;
            background: #f8faff;
            border: 1px solid #dce6f4;
            border-radius: 14px;
        }

        .value-letter {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 54px;
            height: 54px;
            color: #ffffff;
            border-radius: 50%;
            font-size: 24px;
            font-weight: 800;
        }

        .value-tile.cmu .value-letter {
            background: #0b3b82;
        }

        .value-tile.ahon .value-letter {
            background: #805900;
        }

        .value-word {
            min-width: 0;
            color: #172b4d;
            font-size: 14.5px;
            font-weight: 700;
            line-height: 1.35;
            overflow-wrap: anywhere;
        }

        /* =========================================
           CMU LOADING SCREEN (same as index.php)
           ========================================= */
        #cmu-loader {
            position: fixed;
            inset: 0;
            z-index: 99999;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            background: #ffffff;
            opacity: 1;
            visibility: visible;
            transition: opacity 0.4s ease, visibility 0.4s ease;
        }

        #cmu-loader.loader-hidden {
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
        }

        #cmu-loader img {
            width: 100px;
            height: 100px;
            object-fit: contain;
            animation: cmu-logo-pulse 1s ease-in-out infinite alternate;
        }

        #cmu-loader p {
            margin: 16px 0 12px;
            color: #0b3b82;
            font-size: 18px;
            font-weight: 700;
        }

        .cmu-loading-dots {
            display: flex;
            gap: 8px;
        }

        .cmu-loading-dots span {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #d9a900;
            animation: cmu-dot-bounce 0.6s infinite alternate;
        }

        .cmu-loading-dots span:nth-child(2) {
            animation-delay: 0.2s;
        }

        .cmu-loading-dots span:nth-child(3) {
            animation-delay: 0.4s;
        }

        @keyframes cmu-logo-pulse {
            from { transform: scale(0.95); }
            to   { transform: scale(1.05); }
        }

        @keyframes cmu-dot-bounce {
            from { transform: translateY(0); opacity: 0.5; }
            to   { transform: translateY(-8px); opacity: 1; }
        }

        /* =========================================
           RESPONSIVE
           ========================================= */
        @media (max-width: 1100px) {
            /* 7 tiles: wrap and center instead of one row */
            .values-grid {
                display: flex;
                flex-wrap: wrap;
                justify-content: center;
            }

            .value-tile {
                flex: 0 0 calc(25% - 11px);
            }
        }

        @media (max-width: 1000px) {
            .ui-top-grid {
                grid-template-columns: minmax(0, 1fr);
            }
        }

        @media (max-width: 800px) {
            .seal-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .seal-card,
            .seal-card:nth-child(n+4) {
                grid-column: auto;
            }

            .seal-card:last-child:nth-child(odd) {
                grid-column: 1 / -1;
            }

            .vm-grid {
                grid-template-columns: minmax(0, 1fr);
            }

            .value-tile {
                flex: 0 0 calc(33.333% - 10px);
            }
        }

        @media (max-width: 650px) {
            .navbar nav {
                gap: 8px;
            }

            .navbar nav a {
                padding: 10px 12px;
            }

            .ui-top {
                padding: 30px 0 28px;
            }

            .ui-container {
                padding: 0 4%;
            }

            .ui-intro-body {
                padding: 24px 20px;
            }

            .ui-photo {
                height: 170px;
            }

            .ui-intro h1 {
                font-size: 26px;
            }

            .ui-seal {
                padding: 20px 14px;
            }

            .seal-grid {
                grid-template-columns: minmax(0, 1fr);
            }

            .ui-bottom {
                padding: 36px 0 44px;
            }

            .vm-card {
                padding: 24px 20px;
            }

            .value-tile {
                flex: 0 0 calc(50% - 7px);
            }
        }

        @media (prefers-reduced-motion: reduce) {
            #cmu-loader *,
            #cmu-loader,
            .seal-card,
            .value-tile,
            .ui-btn {
                animation: none;
                transition: none;
            }
        }
    </style>
</head>

<body>

    <!-- CMU LOADING SCREEN -->
    <div id="cmu-loader" role="status"
         aria-label="Loading CMU Campus Navigator">

        <img src="../cmu logo.jpg" alt="CMU Logo">

        <p>CMU Campus Navigator</p>

        <div class="cmu-loading-dots" aria-hidden="true">
            <span></span>
            <span></span>
            <span></span>
        </div>

    </div>

    <!-- NAVBAR -->
    <header class="navbar">

        <div class="logo">

            <div class="logo-icon">
                <img src="../cmu logo.jpg" alt="CMU Logo">
            </div>

            <div>
                <h2>CMU Campus Navigator</h2>
                <span>City of Malabon University</span>
            </div>

        </div>

        <nav>
            <a href="../index.php#home">Home</a>
            <a href="../campus-map.php">Campus Map</a>
            <a href="University-info.php" class="active">University Info</a>
            <a href="../index.php#about">About</a>
            <a href="login.php" class="admin-btn">Admin Login</a>
        </nav>

    </header>

    <div class="ui-page" role="main">

        <!-- TOP SECTION: INTRO + UNIVERSITY SEAL -->
        <div class="ui-top" id="home">
            <div class="ui-container">
                <div class="ui-top-grid">

                    <!-- INTRO -->
                    <div class="ui-intro">
                        <img src="../cmu-new-building-inspection.webp"
                             alt="CMU campus building" class="ui-photo">

                        <div class="ui-intro-body">
                        <img src="../cmu logo.jpg" alt="City of Malabon University seal" class="ui-logo">

                        <span class="ui-eyebrow">UNIVERSITY INFORMATION</span>

                        <h1>About <?= h($universityName) ?></h1>

                        <p><?= h($overview) ?></p>

                        <a href="../campus-map.php" class="ui-btn">
                            Explore Campus
                        </a>
                        </div>
                    </div>

                    <!-- UNIVERSITY SEAL -->
                    <div class="ui-seal">
                        <div class="ui-heading">
                            <h2>University Seal</h2>
                        </div>

                        <p class="ui-heading-sub">
                            The meaning behind each symbol of the CMU seal.
                        </p>

                        <div class="seal-grid">
                            <?php $n = 1; foreach ($sealItems as $title => $text): ?>
                                <article class="seal-card">
                                    <span class="seal-number">
                                        <?= str_pad((string)$n++, 2, "0", STR_PAD_LEFT) ?>
                                    </span>
                                    <h3><?= h($title) ?></h3>
                                    <p><?= h($text) ?></p>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <!-- BOTTOM FULL-WIDTH SECTION -->
        <div class="ui-bottom">
            <div class="ui-container">

                <!-- VISION + MISSION -->
                <div class="ui-block">
                    <div class="ui-heading">
                        <h2>Vision &amp; Mission</h2>
                    </div>

                    <div class="vm-grid">
                        <article class="vm-card vision">
                            <h3>VISION</h3>
                            <p><?= h($vision) ?></p>
                        </article>

                        <article class="vm-card mission">
                            <h3>MISSION</h3>
                            <p><?= h($mission) ?></p>
                        </article>
                    </div>
                </div>

                <!-- CORE VALUES -->
                <div class="ui-block">
                    <div class="ui-heading">
                        <h2>Core Values</h2>
                    </div>

                    <div class="values-legend">
                        <span class="legend-item">
                            <span class="legend-dot cmu"></span> CMU
                        </span>
                        <span class="legend-item">
                            <span class="legend-dot ahon"></span> AHON
                        </span>
                    </div>

                    <div class="values-grid">
                        <?php foreach ($coreValues as $value): ?>
                            <div class="value-tile <?= h($value["group"]) ?>">
                                <span class="value-letter"><?= h($value["letter"]) ?></span>
                                <span class="value-word"><?= h($value["word"]) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            </div>
        </div>

    </div>

    <!-- FOOTER -->
    <footer>
        <p>© <?= date("Y") ?> CMU Campus Navigator | City of Malabon University</p>
    </footer>

    <!-- HIDE LOADING SCREEN AFTER PAGE LOAD -->
    <script>
        window.addEventListener("load", function () {
            setTimeout(function () {
                const loader = document.getElementById("cmu-loader");

                if (loader) {
                    loader.classList.add("loader-hidden");
                }
            }, 1200);
        });

        // Safety: always remove the loader even if a resource is slow
        setTimeout(function () {
            const loader = document.getElementById("cmu-loader");

            if (loader) {
                loader.classList.add("loader-hidden");
            }
        }, 4000);
    </script>

</body>
</html>