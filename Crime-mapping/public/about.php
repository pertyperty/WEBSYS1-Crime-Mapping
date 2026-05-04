<?php
require __DIR__ . '/../api/security.php';
init_secure_session();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>About & FAQ | La Trinidad Crime Mapping</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=IBM+Plex+Sans:wght@300;400;500;600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="../assets/css/site.css" />
</head>
<body class="page-about">
    <div class="page-shell">
        <header class="site-header">
            <div class="brand">
                <span class="brand-mark"></span>
                <div>
                    <div class="brand-title">La Trinidad Crime Mapping</div>
                    <div class="brand-subtitle">About the project</div>
                </div>
            </div>
            <nav class="site-nav">
                <a href="index.php">Dashboard</a>
                <a href="map.php">Map</a>
                <a class="is-active" href="about.php">About & FAQ</a>
                <a href="login.php">Login</a>
                <a class="nav-cta" href="register.php">Register</a>
            </nav>
        </header>

        <main>
            <section class="hero hero-tight">
                <div class="hero-copy">
                    <p class="eyebrow">Project mission</p>
                    <h1>Bringing clarity to community safety.</h1>
                    <p class="lead">This platform consolidates incident reporting, barangay verification, and public awareness in one shared map.</p>
                </div>
            </section>

            <section class="faq-grid">
                <div class="panel">
                    <h2>What data is shown?</h2>
                    <p>Only verified or approved incidents are displayed on the public map. Pending reports stay private until barangay or admin review.</p>
                </div>
                <div class="panel">
                    <h2>How do I report an incident?</h2>
                    <p>Create a registered account, open the map, and submit a report with location, category, and optional media.</p>
                </div>
                <div class="panel">
                    <h2>What is community validation?</h2>
                    <p>Guests and registered users can add a thumbs up or down to help moderators assess report credibility.</p>
                </div>
                <div class="panel">
                    <h2>Who can manage incidents?</h2>
                    <p>Barangay officials verify reports in their area, while admin users manage the full dataset and system settings.</p>
                </div>
            </section>
        </main>

        <footer class="site-footer">
            <div>La Trinidad Crime Mapping &mdash; Community Safety Portal</div>
            <div>Questions? Contact your barangay office.</div>
        </footer>
    </div>
</body>
</html>
