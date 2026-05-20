<?php
$hash = password_hash('Admin@1234', PASSWORD_DEFAULT);

echo "Copy this hash:<br><br>";
echo "<textarea style='width:100%;height:80px;font-size:13px;padding:10px;'>" . $hash . "</textarea>";
echo "<br><br>";
echo "Then run this SQL in phpMyAdmin:<br><br>";
echo "<textarea style='width:100%;height:140px;font-size:13px;padding:10px;'>INSERT INTO admins (full_name, username, password)
VALUES (
    'System Administrator',
    'admin',
    '" . $hash . "'
);</textarea>";
echo "<br><br><b style='color:red;'>DELETE THIS FILE after you are done!</b>";
?>