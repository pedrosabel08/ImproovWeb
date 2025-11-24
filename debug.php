<?php
echo "<pre>";
echo "session.save_path: " . ini_get('session.save_path') . "\n\n";

$path = ini_get('session.save_path');

foreach (glob("$path/sess_*") as $file) {
    echo basename($file) . " - " . date("Y-m-d H:i:s", filemtime($file)) . "\n";
}
