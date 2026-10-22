<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CMU Campus Navigator</title>
    <link rel="stylesheet" href="style.css">

    <style>
        /* NAVBAR LINKS */
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

        .navbar nav a:hover {
            filter: brightness(0.95);
            transform: translateY(-1px);
        }

        /* MAP PREVIEW */
        .map-preview-link {
            display: block;
            width: 100%;
            height: 100%;
            border-radius: 12px;
            text-decoration: none;
            color: inherit;
            cursor: pointer;
            outline-offset: 4px;
        }

        .map-preview-link .map-preview {
            transition: transform 0.35s ease, box-shadow 0.35s ease;
        }

        .map-preview-link:hover .map-preview,
        .map-preview-link:focus-visible .map-preview {
            transform: scale(1.045);
            box-shadow: 0 12px 25px rgba(11, 59, 130, 0.18);
        }

        .map-preview-link:active .map-preview {
            transform: scale(1.015);
        }

        .map-preview-link .map-grid,
        .map-preview-link .map-pin,
        .map-preview-link .map-center {
            pointer-events: none;
        }

        /* CMU LOADING SCREEN */
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
            from {
                transform: scale(0.95);
            }

            to {
                transform: scale(1.05);
            }
        }

        @keyframes cmu-dot-bounce {
            from {
                transform: translateY(0);
                opacity: 0.5;
            }

            to {
                transform: translateY(-8px);
                opacity: 1;
            }
        }

        @media (max-width: 650px) {
            .navbar nav {
                gap: 8px;
            }

            .navbar nav a {
                padding: 10px 12px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            #cmu-loader *,
            #cmu-loader {
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

        <img src="cmu logo.jpg" alt="CMU Logo">

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
                <img src="cmu logo.jpg" alt="CMU Logo">
            </div>

            <div>
                <h2>CMU Campus Navigator</h2>
                <span>City of Malabon University</span>
            </div>

        </div>

        <nav>
            <a href="index.php#home">Home</a>
            <a href="campus-map.php">Campus Map</a>
            <a href="admin/University-info.php">University Info</a>
            <a href="index.php#about">About</a>
            <a href="admin/login.php" class="admin-btn">Admin Login</a>
        </nav>

    </header>

    <main>

        <div class="main-background"></div>

        <!-- HERO SECTION -->
        <section class="hero" id="home">

            <div class="hero-content">

                <span class="tag">CITY OF MALABON UNIVERSITY</span>

                <h1>
                    Explore the CMU
                    <span>Campus</span>
                </h1>

                <p>
                    Find buildings, rooms, offices, facilities, and other
                    important locations around the City of Malabon University.
                </p>

                <div class="hero-buttons">
                    <a href="campus-map.php" class="primary-btn">
                        Explore Campus
                    </a>

                    <a href="#about" class="secondary-btn">
                        Learn More
                    </a>
                </div>

            </div>

            <div class="hero-card">

                <a href="campus-map.php"
                   class="map-preview-link"
                   aria-label="Open the interactive CMU Campus Map">

                    <div class="map-preview">

                        <div class="map-grid"></div>

                        <div class="map-pin pin-one">📍</div>
                        <div class="map-pin pin-two">📍</div>
                        <div class="map-pin pin-three">📍</div>

                        <div class="map-center">
                            <strong>CMU</strong>
                            <span>Campus</span>
                        </div>

                    </div>

                </a>

            </div>

        </section>

        <!-- CAMPUS FEATURES -->
        <section class="features" id="map">

            <div class="section-title">

                <span>EXPLORE</span>

                <h2>Campus Information</h2>

                <p>
                    Easily find the places and information you need inside CMU.
                </p>

            </div>

            <div class="feature-grid">

                <div class="feature-card">
                    <div class="feature-icon">🗺️</div>
                    <h3>Interactive Map</h3>
                    <p>
                        Explore campus locations through an interactive map
                        and easily locate different buildings.
                    </p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">🏢</div>
                    <h3>Buildings</h3>
                    <p>
                        View building information, rooms, offices, and
                        other details available in each location.
                    </p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">🔎</div>
                    <h3>Search &amp; Find</h3>
                    <p>
                        Search for rooms, offices, facilities, departments,
                        and other campus information.
                    </p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">📷</div>
                    <h3>360° Virtual Tour</h3>
                    <p>
                        Explore supported campus locations through
                        available 360° virtual tour links.
                    </p>
                </div>

            </div>

        </section>

        <!-- ABOUT SECTION -->
        <section class="about" id="about">

            <div class="about-content">

                <span>ABOUT THE SYSTEM</span>

                <h2>Navigate CMU with ease.</h2>

                <p>
                    CMU Campus Navigator is a web-based campus navigation
                    and information system designed to help students,
                    visitors, faculty, and other users locate important
                    places within City of Malabon University.
                </p>

            </div>

        </section>

    </main>

    <!-- FOOTER -->
    <footer>
        <p>© 2026 CMU Campus Navigator | City of Malabon University</p>
    </footer>

    <!-- EXISTING JAVASCRIPT -->
    <script src="index.js"></script>

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
    </script>

</body>
</html>