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
    <title>Register | La Trinidad Crime Mapping</title>
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
                    <div class="brand-subtitle">Create an account</div>
                </div>
            </div>
            <?php require_once __DIR__ . '/_navbar.php'; render_navbar('register', 'public'); ?>
        </header>

        <main class="auth-card">
            <h1>Create your account</h1>
            <p class="muted">Registered users can submit reports and track updates.</p>
            <form class="form-grid" id="register-form" action="#" method="post">
                <label>
                    <span>Full name</span>
                    <input type="text" id="register-name" name="name" placeholder="Juan Dela Cruz" required />
                </label>
                <label>
                    <span>Email</span>
                    <input type="email" id="register-email" name="email" placeholder="you@domain.com" required />
                </label>
                <label>
                    <span>Contact number</span>
                    <input type="text" id="register-contact" name="contact" placeholder="+63900 000 0000" required />
                </label>
                <label>
                    <span>Address</span>
                    <input type="text" id="register-address" name="address" placeholder="Street, Barangay, City" />
                </label>
                <label>
                    <span>Password</span>
                    <input type="password" id="register-password" name="password" placeholder="Create a password" required />
                </label>
                <button class="btn-primary" type="submit">Register</button>
                <p class="muted" id="register-status"></p>
                <p class="muted">Already registered? <a href="login.php">Login here</a>.</p>
            </form>
        </main>
    </div>

    <script>
        const registerForm = document.getElementById("register-form");
        const registerStatus = document.getElementById("register-status");
        const csrfToken = <?php echo json_encode($csrfToken); ?>;
        const nextPage = new URLSearchParams(window.location.search).get("next");

        // Propagate next param to login link so users can sign in and resume
        (function propagateNextToLogin() {
            try {
                const loginLink = document.querySelector('a[href="login.php"]');
                if (loginLink && nextPage) {
                    loginLink.href = `login.php?next=${encodeURIComponent(nextPage)}`;
                }
            } catch (e) {}
        })();

        registerForm.addEventListener("submit", async (event) => {
            event.preventDefault();
            registerStatus.textContent = "Creating account...";

            const payload = {
                name: document.getElementById("register-name").value.trim(),
                email: document.getElementById("register-email").value.trim(),
                contact: document.getElementById("register-contact").value.trim(),
                address: document.getElementById("register-address").value.trim(),
                password: document.getElementById("register-password").value,
                next: nextPage || null
            };

            try {
                const response = await fetch("../api/auth-register.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "X-CSRF-Token": csrfToken
                    },
                    body: JSON.stringify(payload)
                });
                const result = await response.json();
                if (!result.ok) {
                    registerStatus.textContent = result.error || "Registration failed.";
                    return;
                }

                registerStatus.textContent = "Account created.";
                window.location.href = result.data.redirect;
            } catch (error) {
                console.error("Registration failed", error);
                registerStatus.textContent = "Registration failed. Please try again.";
            }
        });
    </script>
</body>
</html>
