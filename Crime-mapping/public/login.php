<?php
require __DIR__ . '/../api/security.php';
init_secure_session();
$csrfToken = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Login | La Trinidad Crime Mapping</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=IBM+Plex+Sans:wght@300;400;500;600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="../assets/css/site.css" />
</head>
<body class="page-auth">
    <div class="page-shell auth-shell">
        <header class="site-header">
            <div class="brand">
                <span class="brand-mark"></span>
                <div>
                    <div class="brand-title">La Trinidad Crime Mapping</div>
                    <div class="brand-subtitle">Secure login</div>
                </div>
            </div>
            <nav class="site-nav">
                <a href="index.php">Dashboard</a>
                <a href="map.php">Map</a>
                <a href="about.php">About & FAQ</a>
                <a class="is-active" href="login.php">Login</a>
                <a class="nav-cta" href="register.php">Register</a>
            </nav>
        </header>

        <main class="auth-card">
            <h1>Welcome back</h1>
            <p class="muted">Use your account to report incidents or manage barangay updates.</p>
            <form class="form-grid" id="login-form" action="#" method="post">
                <label>
                    <span>Email or username</span>
                    <input type="text" id="login-identity" name="identity" placeholder="you@domain.com" required />
                </label>
                <label>
                    <span>Password</span>
                    <input type="password" id="login-password" name="password" placeholder="********" required />
                </label>
                <button class="btn-primary" type="submit">Login</button>
                <p class="muted" id="login-status"></p>
                <p class="muted">No account yet? <a href="register.php">Create one here</a>.</p>
            </form>
        </main>
    </div>

    <script>
        const loginForm = document.getElementById("login-form");
        const loginStatus = document.getElementById("login-status");
        const csrfToken = <?php echo json_encode($csrfToken); ?>;

        loginForm.addEventListener("submit", async (event) => {
            event.preventDefault();
            loginStatus.textContent = "Signing in...";

            const payload = {
                identity: document.getElementById("login-identity").value.trim(),
                password: document.getElementById("login-password").value
            };

            try {
                const response = await fetch("../api/auth-login.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "X-CSRF-Token": csrfToken
                    },
                    body: JSON.stringify(payload)
                });
                const result = await response.json();
                if (!result.ok) {
                    loginStatus.textContent = result.error || "Login failed.";
                    return;
                }

                loginStatus.textContent = "Login successful.";
                window.location.href = result.data.redirect;
            } catch (error) {
                console.error("Login failed", error);
                loginStatus.textContent = "Login failed. Please try again.";
            }
        });
    </script>
</body>
</html>
