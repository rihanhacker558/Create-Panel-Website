<?php
session_start();

$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

$userData = json_decode(file_get_contents('users.json'), true);
$found = false;

foreach ($userData as $user) {
  if ($user['username'] === $username && $user['password'] === $password) {
    $found = true;
    $_SESSION['user'] = $username;
    break;
  }
}

if ($found) {
  header("Location: index.html");
  exit;
} else {
  header("Location: login.html?error=Username atau Password salah!");
  exit;
}