<?php

if (!function_exists('render_navbar')) {
    function render_navbar(string $active, string $scope = 'public'): void
    {
        $loggedIn = !empty($_SESSION['user_id']);
        $username = (string) ($_SESSION['username'] ?? '');
        $avatar = trim((string) ($_SESSION['avatar'] ?? ''));

        $items = [];
        switch ($scope) {
            case 'admin':
                $items = [
                    ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => 'admin-dashboard.php'],
                    ['key' => 'map', 'label' => 'Map', 'href' => 'admin-map.php'],
                    ['key' => 'incidents', 'label' => 'Incidents', 'href' => 'admin-incidents.php'],
                    ['key' => 'faq', 'label' => 'FAQs', 'href' => 'admin-faq.php'],
                    ['key' => 'users', 'label' => 'User Management', 'href' => 'admin-users.php'],
                ];
                break;
            case 'barangay':
                $items = [
                    ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => 'barangay-dashboard.php'],
                    ['key' => 'map', 'label' => 'Map', 'href' => 'barangay-map.php'],
                    ['key' => 'incidents', 'label' => 'Incidents', 'href' => 'barangay-incidents.php'],
                    ['key' => 'add-incident', 'label' => 'Add Incident', 'href' => 'barangay-add-incident.php'],
                ];
                break;
            default:
                $items = [
                    ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => 'index.php'],
                    ['key' => 'map', 'label' => 'Map', 'href' => 'map.php'],
                    ['key' => 'about', 'label' => 'About & FAQ', 'href' => 'about.php'],
                ];
                break;
        }

        echo '<nav class="site-nav" aria-label="Primary navigation">';

        foreach ($items as $item) {
            $classes = [];
            if ($item['key'] === $active) {
                $classes[] = 'is-active';
            }

            $classAttribute = $classes ? ' class="' . implode(' ', $classes) . '"' : '';
            echo '<a' . $classAttribute . ' href="' . htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') . '</a>';
        }

        if ($loggedIn) {
            $fallbackInitial = $scope === 'admin' ? 'A' : ($scope === 'barangay' ? 'B' : 'U');
            $initial = strtoupper(substr($username !== '' ? $username : $fallbackInitial, 0, 1));

            echo '<span class="nav-user" aria-label="Signed in user">';
            if ($avatar !== '') {
                echo '<img src="../' . htmlspecialchars($avatar, ENT_QUOTES, 'UTF-8') . '" class="nav-avatar" alt="avatar" />';
            } else {
                echo '<span class="nav-avatar-letter">' . htmlspecialchars($initial, ENT_QUOTES, 'UTF-8') . '</span>';
            }

            echo '<span class="nav-user-name">' . htmlspecialchars($username !== '' ? $username : ucfirst($scope), ENT_QUOTES, 'UTF-8') . '</span>';
            echo '</span>';
            echo '<a href="auth-logout.php">Logout</a>';
        } else {
            $loginClass = $active === 'login' ? ' class="is-active"' : '';
            echo '<a' . $loginClass . ' href="login.php">Login</a>';

            if ($scope === 'public') {
                $registerClass = 'nav-cta' . ($active === 'register' ? ' is-active' : '');
                echo '<a class="' . $registerClass . '" href="register.php">Register</a>';
            }
        }

        echo '</nav>';
    }
}