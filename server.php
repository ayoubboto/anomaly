<?php
$server_ip = "185.230.52.66";
$port = 30120;
$players_info_url = "http://{$server_ip}:{$port}/players.json";

$players_data = [];
$police_count = 0;
$total_players = 0;
$notification_message = '';
$new_player_notifications = [];
$disconnected_notifications = [];
$disconnected_players = [];
$previous_disconnected_players = [];

try {
    $response = file_get_contents($players_info_url);
    $players_data = json_decode($response, true);
    $total_players = count($players_data); 
} catch (Exception $e) {
    $players_data = [];
    $total_players = 0; 
}

$police_pattern = '/\b[lL][sS][pP][dD]\b/';

$previous_players = file_exists('previous_players.json') ? json_decode(file_get_contents('previous_players.json'), true) : [];
$previous_player_ids = array_column($previous_players, 'id');
$previous_police_count = file_exists('previous_police_count.txt') ? (int)file_get_contents('previous_police_count.txt') : 0;

$previous_disconnected_players = file_exists('disconnected_players.json') ? json_decode(file_get_contents('disconnected_players.json'), true) : [];
$previous_disconnected_player_ids = array_column($previous_disconnected_players, 'id');

$current_player_ids = array_column($players_data, 'id');
$new_players = array_filter($players_data, function ($player) use ($previous_player_ids) {
    return !in_array($player['id'], $previous_player_ids);
});

$disconnected_players = array_filter($previous_players, function ($player) use ($current_player_ids) {
    return !in_array($player['id'], $current_player_ids);
});

foreach ($players_data as $player) {
    if (preg_match($police_pattern, $player['name'])) {
        $police_count++;
    }
}

if ($police_count > $previous_police_count) {
    $notification_message = 'A new police officer has joined !';
}

if (!empty($new_players)) {
    foreach ($new_players as $player) {
        $new_player_notifications[] = 'Player joined: ' . htmlspecialchars($player['name']);
    }
}

if (!empty($disconnected_players)) {
    foreach ($disconnected_players as $player) {
        $disconnected_notifications[] = 'Player disconnected: ' . htmlspecialchars($player['name']);
    }
}

$all_disconnected_players = array_merge($previous_disconnected_players, $disconnected_players);
file_put_contents('disconnected_players.json', json_encode($all_disconnected_players));

file_put_contents('previous_police_count.txt', $police_count);
file_put_contents('previous_players.json', json_encode($players_data));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <body oncontextmenu="return false;">
    <title>Server Status</title>
    <style>
/* Importing fonts */
@import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap');

:root {
    --light: #f6f6f9;
    --primary: #d83235;
    --light-primary: #CFE8FF;
    --grey: #eee;
    --dark: #333333;
    --danger: #d83235;
    --light-danger: #FECDD3;
    --warning: #FBC02D;
    --light-warning: #FFF2C6;
    --success: #d83235;
    --light-success: #BBF7D0;
    --button-bg-light: #f0f0f0; 
    --button-bg-dark: #18191C;  
    --grey-hover: #e0e0e0;
}

html {
    scroll-behavior: smooth;
    overflow-x: hidden;
}

body {
    font-family: 'Poppins', sans-serif;
    margin: 20px;
    background-color: #000;
    color: #fff;
}

a {
    color: #1e90ff;
    text-decoration: none;
}

a:hover {
    text-decoration: underline;
}

h1, h2, .status-info {
    text-align: center;
}

.status-info {
    margin: 10px 0;
}

table {
    width: 70%;
    max-width: 100%;
    border-collapse: collapse;
    margin: 20px auto;
    background-color: #222;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
    transition: box-shadow 0.3s, transform 0.3s;
    transform: perspective(5000px) rotateX(2deg);
}

table:hover {
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.4);
    transform: perspective(500px) rotateX(0deg) translateX(-10px);
}

table, th, td {
    border: 1px solid #fff;
}

th, td {
    padding: 4px 8px;
    text-align: left;
    font-size: 14px;
}

th {
    background-color: #333;
}

tbody tr:hover {
    background-color: #444;
}

@media (max-width: 768px) {
    table {
        font-size: 12px;
    }
    th, td {
        padding: 4px;
    }
}

.toast-panel {
    position: fixed;
    top: 10px;
    right: 10px;
    z-index: 1000;
}

.toast-item {
    background: #333;
    color: #fff;
    border-radius: 5px;
    padding: 10px;
    margin-bottom: 10px;
    box-shadow: 0 0 10px rgba(0, 0, 0, 0.5);
    display: flex;
    justify-content: space-between;
    align-items: center;
    opacity: 1;
    transition: opacity 0.5s;
}

.toast-item.success {
    background: #4caf50;
}

.toast-item.error {
    background: #f44336;
}

.toast-item.info {
    background: #1e90ff;
}

.toast-item .close {
    cursor: pointer;
    font-size: 20px;
    line-height: 20px;
}

.toast-item.closed {
    opacity: 0;
}

.disconnected-table {
    background-color: #f44336;
    color: #fff;
    border: 1px solid #fff;
    border-radius: 5px;
    overflow: hidden;
}

.sidebar {
    position: fixed;
    top: 0;
    left: 0;
    background: var(--light);
    width: 230px;
    height: 100%;
    z-index: 2000;
    overflow-x: hidden;
    scrollbar-width: none;
    transition: all 0.3s ease;
}

.sidebar.close ~ .content {
    width: calc(100% - 60px);
    left: 60px;
}

.sidebar.close {
    width: 60px;
}

.sidebar .logo {
    font-size: 24px;
    font-weight: 700;
    height: 56px;
    display: flex;
    align-items: center;
    color: var(--primary);
    z-index: 500;
    padding-bottom: 20px;
    box-sizing: content-box;
}

.sidebar .logo .logo-name span {
    color: var(--dark);
}

.sidebar .logo .bx {
    min-width: 60px;
    display: flex;
    justify-content: center;
    font-size: 2.2rem;
}

.sidebar .side-menu {
    width: 100%;
    margin-top: 48px;
}

.sidebar .side-menu li {
    height: 48px;
    background: transparent;
    margin-left: 6px;
    border-radius: 48px 0 0 48px;
    padding: 4px;
}

.sidebar .side-menu li.active {
    background: var(--grey);
    position: relative;
}

.sidebar .side-menu li.active::before {
    content: "";
    position: absolute;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    top: -40px;
    right: 0;
    box-shadow: 20px 20px 0 var(--grey);
    z-index: -1;
}

.sidebar .side-menu li.active::after {
    content: "";
    position: absolute;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    bottom: -40px;
    right: 0;
    box-shadow: 20px -20px 0 var(--grey);
    z-index: -1;
}

.sidebar .side-menu li a {
    width: 100%;
    height: 100%;
    background: var(--light);
    display: flex;
    align-items: center;
    border-radius: 48px;
    font-size: 16px;
    color: var(--dark);
    white-space: nowrap;
    overflow-x: hidden;
    transition: all 0.3s ease;
}

.sidebar .side-menu li.active a {
    color: var(--success);
}

.sidebar.close .side-menu li a {
    width: calc(48px - (4px * 2));
    transition: all 0.3s ease;
}

.sidebar .side-menu li a .bx {
    min-width: calc(60px - ((4px + 6px) * 2));
    display: flex;
    font-size: 1.6rem;
    justify-content: center;
}

.sidebar .side-menu li a.logout {
    color: var(--danger);
}

/* Content Styles */
.content {
    position: relative;
    width: calc(100% - 230px);
    left: 230px;
    transition: all 0.3s ease;
}

.sidebar.close ~ .content {
    width: calc(100% - 60px);
    left: 60px;
}

.content nav {
    height: 56px;
    background: var(--light);
    padding: 0 24px 0 0;
    display: flex;
    align-items: center;
    grid-gap: 24px;
    position: sticky;
    top: 0;
    left: 0;
    z-index: 1000;
}

.content nav::before {
    content: "";
    position: absolute;
    width: 40px;
    height: 40px;
    bottom: -40px;
    left: 0;
    border-radius: 50%;
    box-shadow: -20px -20px 0 var(--light);
}

.content nav .bx.bx-menu {
    cursor: pointer;
    color: var(--dark);
}

.content nav form {
    max-width: 400px;
    width: 100%;
    margin-right: auto;
}

.content nav .profile img {
    width: 36px;
    height: 36px;
    object-fit: cover;
    border-radius: 50%;
    user-select: none;
    -webkit-user-select: none; 
    -moz-user-select: none; 
    -ms-user-select: none; 
    pointer-events: none;
}

.content nav .theme-toggle {
    display: block;
    min-width: 50px;
    height: 25px;
    background: var(--grey);
    cursor: pointer;
    position: relative;
    border-radius: 25px;
}

.content nav .theme-toggle::before {
    content: "";
    position: absolute;
    top: 2px;
    left: 2px;
    bottom: 2px;
    width: calc(25px - 4px);
    background: var(--primary);
    border-radius: 50%;
    transition: all 0.3s ease;
}

.police-icon img {
    width: 16px;
    height: auto; 
    vertical-align: middle;
    margin-right: 6px; 
}

.total-players-icon img {
    width: 16px;
    height: auto;
    vertical-align: middle; /
    margin-right: 6px; /
}


.content nav #theme-toggle:checked + .theme-toggle::before {
    left: calc(100% - (25px - 4px) - 2px);
}

.content main {
    width: 100%;
    min-height: calc(100vh - 56px);
    padding: 20px;
}
</style>

    <script>
        function reloadPage() {
            setTimeout(function() {
                window.location.reload();
            }, 5000);  
        }

        function showToast(type, message) {
            const toastPanel = document.querySelector('.toast-panel');
            const toast = document.createElement('div');
            toast.className = `toast-item ${type}`;
            toast.innerHTML = `
                <span>${message}</span>
                <span class="close" onclick="this.parentElement.classList.add('closed')">&times;</span>
            `;
            toastPanel.appendChild(toast);

            setTimeout(() => {
                toast.classList.add('closed');
                setTimeout(() => toast.remove(), 500);
            }, 4000);
        }

        window.onload = function() {
            reloadPage();
            const notificationMessage = <?= json_encode($notification_message) ?>;
            if (notificationMessage) {
                showToast('success', notificationMessage);
            }
            const newPlayerNotifications = <?= json_encode($new_player_notifications) ?>;
            newPlayerNotifications.forEach(message => {
                showToast('info', message);
            });
            const disconnectedNotifications = <?= json_encode($disconnected_notifications) ?>;
            disconnectedNotifications.forEach(message => {
                showToast('error', message);
            });
        }
    </script>
</head>
<body>
    <h1>Server Informations</h1>
<p class="status-info">
    <span class="police-icon">
        <img src="https://em-content.zobj.net/source/animated-noto-color-emoji/356/police-car-light_1f6a8.gif" alt="Police Car Light" />
    </span>
    Police Online: <?= htmlspecialchars($police_count) ?>
</p>

<p class="status-info">
    <span class="total-players-icon">
        <img src="https://cdn-icons-png.flaticon.com/512/393/393711.png" alt="Total Players Icon" />
    </span>
    Total Players: <?= htmlspecialchars($total_players) ?>
</p>



    <h2>Online Players</h2>
    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>ID</th>
                <th>Ping</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($players_data as $player): ?>
                <?php
                $discord_id_str = array_filter(
                    $player['identifiers'],
                    function ($id) {
                        return strpos($id, "discord:") === 0;
                    }
                );
                $discord_id = !empty($discord_id_str) ? str_replace("discord:", "", reset($discord_id_str)) : null;
                $discord_url = $discord_id ? "https://discord.com/users/" . htmlspecialchars($discord_id) : null;
                ?>
                <tr>
                    <td>
                        <a href="<?= htmlspecialchars($discord_url) ?>" target="_blank"><?= htmlspecialchars($player['name']) ?></a>
                    </td>
                    <td><?= htmlspecialchars($player['id']) ?></td>
                    <td><?= htmlspecialchars($player['ping']) ?> ms</td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>


    <?php if (!empty($previous_disconnected_players)): ?>
        <h2>Disconnected Players</h2>
        <table class="disconnected-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>ID</th>
                    <th>Last Ping</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($previous_disconnected_players as $player): ?>
                    <?php
                    $discord_id_str = array_filter(
                        $player['identifiers'],
                        function ($id) {
                            return strpos($id, "discord:") === 0;
                        }
                    );
                    $discord_id = !empty($discord_id_str) ? str_replace("discord:", "", reset($discord_id_str)) : null;
                    $discord_url = $discord_id ? "https://discord.com/users/" . htmlspecialchars($discord_id) : null;
                    ?>
                    <tr>
                        <td>
                            <a href="<?= htmlspecialchars($discord_url) ?>" target="_blank"><?= htmlspecialchars($player['name']) ?></a>
                        </td>
                        <td><?= htmlspecialchars($player['id']) ?></td>
                        <td><?= htmlspecialchars($player['ping']) ?> ms</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <div class="toast-panel"></div>
</body>
</html>
