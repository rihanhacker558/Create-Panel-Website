<?php
session_start();
if (!isset($_SESSION['user'])) {
  header("Location: login.html");
  exit;
}
?>
    
<?php
$panel_url = "https://fino.hyga.site";
$api_key = "ptla_w6OvbdUj3bEpeqoIZQXQDglq90LPiyiYv4KGTetyzZI";
$location_id = 1;
$nest_id = 5;
$egg_linux = 16;
$egg_windows = 15;
$node_id = 1;

$type = $_POST['type'] ?? 'baru';
$username = $_POST['username'] ?? '';
$slot = $_POST['slot'] ?? '20';
$os = $_POST['os'] ?? 'linux';
$admin_user = $_POST['admin_user'] ?? '';
$admin_pass = $_POST['admin_pass'] ?? '';

if ($type === 'admin') {
    if ($admin_user === 'Ozile' && $admin_pass === 'ozile@#1') {
        echo json_encode(["success" => true, "message" => "Login Admin Berhasil"]);
    } else {
        echo json_encode(["success" => false, "message" => "❌ Admin Login Gagal"]);
    }
    exit;
}

if (empty($username) || empty($slot)) {
    echo json_encode(["success" => false, "message" => "Username & Slot wajib diisi."]);
    exit;
}

$egg_id = $os === 'windows' ? $egg_windows : $egg_linux;

$password = strval(rand(100000, 999999));
$email = strtolower($username) . "@gmail.site";

$count_file = __DIR__ . '/server_count.txt';
if (!file_exists($count_file)) file_put_contents($count_file, '0');
$current_count = (int)file_get_contents($count_file);
$new_count = $current_count + 1;
file_put_contents($count_file, $new_count);
$server_name = "Create Otomatis [$new_count]";

if ($type === 'lama') {
    $user_data = get_users($panel_url, $api_key);
    $user_id = null;
    foreach ($user_data as $user) {
        if (strtolower($user['username']) === strtolower($username)) {
            $user_id = $user['id'];
            break;
        }
    }
    if (!$user_id) {
        echo json_encode(["success" => false, "message" => "❌ User tidak ditemukan"]);
        exit;
    }
} else {
    $user_payload = [
        "username" => $username,
        "email" => $email,
        "first_name" => $username,
        "last_name" => "User",
        "password" => $password
    ];
    $user_response = send_curl("$panel_url/api/application/users", $api_key, $user_payload);
    if (!$user_response || !isset($user_response['attributes']['id'])) {
        echo json_encode(["success" => false, "message" => "❌ Gagal membuat user."]);
        exit;
    }
    $user_id = $user_response['attributes']['id'];
}

$allocation_id = get_free_allocation_id($panel_url, $api_key, $node_id);
if (!$allocation_id) {
    echo json_encode(["success" => false, "message" => "❌ Tidak ada allocation kosong tersedia"]);
    exit;
}

$server_payload = [
    "name" => $server_name,
    "user" => $user_id,
    "nest" => $nest_id,
    "egg" => $egg_id,
    "docker_image" => "ghcr.io/parkervcp/games:samp",
    "startup" => "./samp03svr",
    "environment" => [
        "MAX_PLAYERS" => $slot
    ],
    "limits" => [
        "memory" => 0,
        "swap" => 0,
        "disk" => 0,
        "io" => 500,
        "cpu" => 0
    ],
    "feature_limits" => [
        "databases" => 0,
        "allocations" => 1,
        "backups" => 0
    ],
    "allocation" => [
        "default" => $allocation_id
    ],
    "start_on_completion" => true
];

$server_response = send_curl("$panel_url/api/application/servers", $api_key, $server_payload);

if (!$server_response || !isset($server_response['attributes']['identifier'])) {
    file_put_contents("debug_log.txt", print_r($server_response, true));
    echo json_encode([
        "success" => false,
        "message" => $server_response['errors'][0]['detail'] ?? "❌ Gagal membuat server."
    ]);
    exit;
}

echo json_encode([
    "success" => true,
    "username" => $username,
    "password" => $password,
    "domain" => $panel_url,
    "server_name" => $server_name
]);

function send_curl($url, $api_key, $payload) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $api_key",
        "Content-Type: application/json",
        "Accept: Application/vnd.pterodactyl.v1+json"
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

function get_users($panel_url, $api_key) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "$panel_url/api/application/users?per_page=1000");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $api_key",
        "Accept: Application/vnd.pterodactyl.v1+json"
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($response, true);
    return array_map(function($u) {
        return [
            'id' => $u['attributes']['id'],
            'username' => $u['attributes']['username']
        ];
    }, $data['data']);
}

function get_free_allocation_id($panel_url, $api_key, $node_id) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "$panel_url/api/application/nodes/$node_id/allocations");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $api_key",
        "Accept: Application/vnd.pterodactyl.v1+json"
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($response, true);
    foreach ($data['data'] as $alloc) {
        if (!$alloc['attributes']['assigned']) {
            return $alloc['attributes']['id'];
        }
    }
    return null;
}
?>