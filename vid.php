<?php

// Remote video directory
$remoteURL = "https://illumatetube.gt.tc/vid/";

// Local folder (must exist and be writable)
$localFolder = __DIR__ . "/users/videos/";

// Ensure local folder exists
if (!is_dir($localFolder)) {
    mkdir($localFolder, 0777, true);
}

// Fetch the HTML of the remote directory
$html = @file_get_contents($remoteURL);

if (!$html) {
    die("❌ Could not access remote directory.");
}

// Extract video links (mp4, mov, avi, mkv, webm)
preg_match_all('/href=["\']([^"\']+\.(mp4|mov|avi|mkv|webm))["\']/i', $html, $matches);

$files = $matches[1];

if (empty($files)) {
    die("❌ No video files found in the directory.");
}

echo "✅ Found " . count($files) . " videos.<br><br>";

foreach ($files as $file) {

    $remoteFile = $remoteURL . $file;
    $localFile  = $localFolder . $file;

    echo "Downloading: $file ... ";

    // Download file using curl
    $ch = curl_init($remoteFile);
    $fp = fopen($localFile, 'w+');

    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

    $success = curl_exec($ch);
    curl_close($ch);
    fclose($fp);

    if ($success) {
        echo "✔ Saved to /users/videos/$file<br>";
    } else {
        echo "❌ Failed<br>";
    }
}

echo "<br>🎉 DONE — All videos downloaded.";

?>
