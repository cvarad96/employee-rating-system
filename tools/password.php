<?php
$password = 'wifi123#'; // Replace with your desired password
$hash = password_hash($password, PASSWORD_DEFAULT);
echo "Password: $password\n";
echo "Bcrypt Hash: $hash\n";
?>
