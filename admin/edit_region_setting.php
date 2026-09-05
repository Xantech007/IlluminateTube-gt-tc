<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

require_once '../database/conn.php';
require_once '../inc/countries.php';

date_default_timezone_set('Africa/Lagos');

$uploadDir = '../images/';
$allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$maxFileSize = 5 * 1024 * 1024;

if (!isset($_GET['id']) || !isset($_GET['section']) || !in_array($_GET['section'], ['dashboard', 'verification'])) {
    $_SESSION['error'] = "Invalid request.";
    header("Location: manage_region_settings.php");
    exit;
}

$id = intval($_GET['id']);
$section = $_GET['section'];

try {
    $stmt = $pdo->prepare("
        SELECT id, country, section_header, channel, ch_name, ch_value, withdraw_currency, 
               verify_ch, vc_value, verify_ch_name, verify_ch_value, verify_medium, vcn_value, 
               vcv_value, verify_currency, verify_amount, rate, account_upgrade, images
        FROM region_settings WHERE id = ?
    ");
    $stmt->execute([$id]);
    $setting = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$setting) {
        $_SESSION['error'] = "Region setting not found.";
        header("Location: manage_region_settings.php");
        exit;
    }
} catch (PDOException $e) {
    error_log('Fetch error: ' . $e->getMessage(), 3, '../debug.log');
    $_SESSION['error'] = "Failed to load setting.";
    header("Location: manage_region_settings.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $imageName = $setting['images'];
    $hasError = false;

    try {
        if ($section === 'dashboard') {
            $country = trim($_POST['country'] ?? '');
            $section_header = trim($_POST['section_header'] ?? '');
            $channel = trim($_POST['channel'] ?? '');
            $ch_name = trim($_POST['ch_name'] ?? '');
            $ch_value = trim($_POST['ch_value'] ?? '');
            $withdraw_currency = trim($_POST['withdraw_currency'] ?? '');

            if (empty($country) || empty($section_header) || empty($channel) || empty($ch_name) || empty($ch_value) || empty($withdraw_currency)) {
                $_SESSION['error'] = "All required dashboard fields must be filled.";
                $hasError = true;
            } else {
                $stmt = $pdo->prepare("SELECT id FROM region_settings WHERE country = ? AND id != ?");
                $stmt->execute([$country, $id]);
                if ($stmt->fetch()) {
                    $_SESSION['error'] = "Country configuration already exists.";
                    $hasError = true;
                } else {
                    $stmt = $pdo->prepare("UPDATE region_settings SET country = ?, section_header = ?, channel = ?, ch_name = ?, ch_value = ?, withdraw_currency = ? WHERE id = ?");
                    $stmt->execute([$country, $section_header, $channel, $ch_name, $ch_value, $withdraw_currency, $id]);
                    $_SESSION['success'] = "Dashboard updated successfully.";
                }
            }
        } elseif ($section === 'verification') {
            $verify_ch = trim($_POST['verify_ch'] ?? '');
            $vc_value = trim($_POST['vc_value'] ?? '');
            $verify_ch_name = trim($_POST['verify_ch_name'] ?? '');
            $verify_ch_value = trim($_POST['verify_ch_value'] ?? '');
            $verify_medium = trim($_POST['verify_medium'] ?? '');
            $vcn_value = trim($_POST['vcn_value'] ?? '');
            $vcv_value = trim($_POST['vcv_value'] ?? '');
            $verify_currency = trim($_POST['verify_currency'] ?? '');
            $verify_amount = floatval($_POST['verify_amount'] ?? 0);
            $rate = floatval($_POST['rate'] ?? 0);
            $account_upgrade = isset($_POST['account_upgrade']) ? 1 : 0;

            if (empty($verify_ch) || empty($vc_value) || empty($verify_ch_name) || empty($verify_ch_value) || empty($vcn_value) || empty($vcv_value) || empty($verify_currency) || $verify_amount <= 0 || $rate <= 0) {
                $_SESSION['error'] = "All required verification fields must be completed with valid values.";
                $hasError = true;
            } else {
                $pdo->beginTransaction();

                // Delete current image if requested
                if (isset($_POST['delete_image']) && $_POST['delete_image'] == '1') {
                    if ($imageName && file_exists($uploadDir . $imageName)) {
                        @unlink($uploadDir . $imageName);
                    }
                    $imageName = null;
                }

                // File Upload processing
                if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                    $file = $_FILES['image'];
                    $fileTmp = $file['tmp_name'];
                    $fileSize = $file['size'];

                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $fileType = finfo_file($finfo, $fileTmp);
                    finfo_close($finfo);

                    if (!in_array($fileType, $allowedTypes)) {
                        throw new Exception("Invalid file format. Only JPG, PNG, GIF, and WEBP allowed.");
                    }
                    if ($fileSize > $maxFileSize) {
                        throw new Exception("File size exceeds 5MB limit.");
                    }

                    // Remove old file if replaced
                    if ($setting['images'] && file_exists($uploadDir . $setting['images'])) {
                        @unlink($uploadDir . $setting['images']);
                    }

                    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $imageName = uniqid('img_', true) . '.' . strtolower($ext);

                    if (!move_uploaded_file($fileTmp, $uploadDir . $imageName)) {
                        throw new Exception("Failed to save uploaded file.");
                    }
                }

                $stmt = $pdo->prepare("
                    UPDATE region_settings 
                    SET verify_ch = ?, vc_value = ?, verify_ch_name = ?, verify_ch_value = ?, 
                        verify_medium = ?, vcn_value = ?, vcv_value = ?, verify_currency = ?, 
                        verify_amount = ?, rate = ?, account_upgrade = ?, images = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $verify_ch, $vc_value, $verify_ch_name, $verify_ch_value, $verify_medium,
                    $vcn_value, $vcv_value, $verify_currency, $verify_amount, $rate, $account_upgrade,
                    $imageName, $id
                ]);

                $pdo->commit();
                $_SESSION['success'] = "Verification settings updated successfully.";
            }
        }

        if (!$hasError) {
            header("Location: edit_region_settings.php?id=" . $id . "&section=" . $section);
            exit;
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if (isset($imageName) && $imageName !== $setting['images'] && file_exists($uploadDir . $imageName)) {
            @unlink($uploadDir . $imageName);
        }
        $_SESSION['error'] = "Update failed: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit <?php echo ucfirst($section); ?> Settings</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background-color: #f4f4f4; }
        .dashboard-container { max-width: 800px; margin: 50px auto; padding: 20px; background-color: #fff; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .dashboard-container h2 { color: #333; margin-bottom: 20px; text-align: center; }
        .button-container { display: flex; justify-content: flex-start; margin: 15px 0; }
        .back-link { padding: 8px 16px; color: #fff; background-color: #6c757d; border-radius: 4px; font-size: 13px; text-decoration: none; }
        .back-link:hover { background-color: #5a6268; }
        .edit-form { display: grid; grid-template-columns: 1fr; gap: 14px; width: 100%; box-sizing: border-box; }
        .form-group { display: flex; flex-direction: column; text-align: left; }
        .form-group label { font-size: 12px; font-weight: bold; margin-bottom: 4px; color: #444; }
        .edit-form input[type="text"], .edit-form input[type="number"], .edit-form input[type="file"], .edit-form select { width: 100%; padding: 9px; border: 1px solid #ccc; border-radius: 4px; font-size: 13px; box-sizing: border-box; }
        .edit-form select { background-color: #fff; cursor: pointer; }
        .edit-form label.checkbox-label { display: flex; align-items: center; font-size: 13px; color: #555; cursor: pointer; }
        .edit-form input[type="checkbox"] { width: auto; margin-right: 8px; }
        .edit-form button { width: 220px; justify-self: center; padding: 11px; background-color: #28a745; color: #fff; border: none; border-radius: 4px; font-size: 14px; cursor: pointer; font-weight: bold; margin-top: 10px; }
        .edit-form button:hover { background-color: #218838; }
        .current-image { margin: 8px 0; padding: 10px; background-color: #f8f9fa; border: 1px solid #e9ecef; border-radius: 4px; font-size: 13px; }
        .current-image img { max-width: 120px; max-height: 120px; object-fit: cover; border-radius: 4px; margin-top: 6px; display: block; }
        .error, .success { padding: 10px; border-radius: 4px; margin-bottom: 15px; font-weight: bold; text-align: center; }
        .error { color: #721c24; background-color: #f8d7da; border: 1px solid #f5c6cb; }
        .success { color: #155724; background-color: #d4edda; border: 1px solid #c3e6cb; }
        @media (max-width: 768px) {
            .dashboard-container { margin: 20px 10px; padding: 15px; }
            .current-image img { max-width: 90px; max-height: 90px; }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <h2>Edit <?php echo ucfirst($section); ?> Settings</h2>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="error"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <div class="button-container">
            <a href="manage_region_settings.php" class="back-link">&larr; Back to Management</a>
        </div>

        <?php if ($section === 'dashboard'): ?>
            <form action="" method="POST" class="edit-form">
                <div class="form-group">
                    <label for="country">Country</label>
                    <select id="country" name="country" required>
                        <option value="" disabled>Select Country</option>
                        <?php foreach ($countries as $country): ?>
                            <option value="<?php echo htmlspecialchars($country); ?>" <?php echo $setting['country'] === $country ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($country); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="section_header">Section Header</label>
                    <input type="text" id="section_header" name="section_header" value="<?php echo htmlspecialchars($setting['section_header']); ?>" placeholder="Section Header" required>
                </div>

                <div class="form-group">
                    <label for="channel">Channel</label>
                    <input type="text" id="channel" name="channel" value="<?php echo htmlspecialchars($setting['channel']); ?>" placeholder="Channel" required>
                </div>

                <div class="form-group">
                    <label for="ch_name">Channel Name</label>
                    <input type="text" id="ch_name" name="ch_name" value="<?php echo htmlspecialchars($setting['ch_name']); ?>" placeholder="Channel Name" required>
                </div>

                <div class="form-group">
                    <label for="ch_value">Channel Value</label>
                    <input type="text" id="ch_value" name="ch_value" value="<?php echo htmlspecialchars($setting['ch_value']); ?>" placeholder="Channel Value" required>
                </div>

                <div class="form-group">
                    <label for="withdraw_currency">Withdraw Currency</label>
                    <input type="text" id="withdraw_currency" name="withdraw_currency" value="<?php echo htmlspecialchars($setting['withdraw_currency'] ?? ''); ?>" placeholder="Withdraw Currency" required>
                </div>

                <button type="submit">Update Dashboard</button>
            </form>

        <?php elseif ($section === 'verification'): ?>
            <form action="" method="POST" class="edit-form" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="verify_ch">Verification Channel</label>
                    <input type="text" id="verify_ch" name="verify_ch" value="<?php echo htmlspecialchars($setting['verify_ch'] ?? ''); ?>" placeholder="Verification Channel" required>
                </div>

                <div class="form-group">
                    <label for="vc_value">VC Value</label>
                    <input type="text" id="vc_value" name="vc_value" value="<?php echo htmlspecialchars($setting['vc_value'] ?? ''); ?>" placeholder="VC Value" required>
                </div>

                <div class="form-group">
                    <label for="verify_ch_name">Verification Channel Name</label>
                    <input type="text" id="verify_ch_name" name="verify_ch_name" value="<?php echo htmlspecialchars($setting['verify_ch_name'] ?? ''); ?>" placeholder="Verification Channel Name" required>
                </div>

                <div class="form-group">
                    <label for="verify_ch_value">Verification Channel Value</label>
                    <input type="text" id="verify_ch_value" name="verify_ch_value" value="<?php echo htmlspecialchars($setting['verify_ch_value'] ?? ''); ?>" placeholder="Verification Channel Value" required>
                </div>

                <div class="form-group">
                    <label for="verify_medium">Verification Medium (Optional)</label>
                    <input type="text" id="verify_medium" name="verify_medium" value="<?php echo htmlspecialchars($setting['verify_medium'] ?? ''); ?>" placeholder="Verification Medium">
                </div>

                <div class="form-group">
                    <label for="vcn_value">VCN Value</label>
                    <input type="text" id="vcn_value" name="vcn_value" value="<?php echo htmlspecialchars($setting['vcn_value'] ?? ''); ?>" placeholder="VCN Value" required>
                </div>

                <div class="form-group">
                    <label for="vcv_value">VCV Value</label>
                    <input type="text" id="vcv_value" name="vcv_value" value="<?php echo htmlspecialchars($setting['vcv_value'] ?? ''); ?>" placeholder="VCV Value" required>
                </div>

                <div class="form-group">
                    <label for="verify_currency">Verification Currency</label>
                    <input type="text" id="verify_currency" name="verify_currency" value="<?php echo htmlspecialchars($setting['verify_currency'] ?? ''); ?>" placeholder="Verification Currency" required>
                </div>

                <div class="form-group">
                    <label for="verify_amount">Verification Amount</label>
                    <input type="number" id="verify_amount" name="verify_amount" value="<?php echo htmlspecialchars($setting['verify_amount'] ?? ''); ?>" step="0.01" min="0.01" placeholder="Verification Amount" required>
                </div>

                <div class="form-group">
                    <label for="rate">Rate</label>
                    <input type="number" id="rate" name="rate" value="<?php echo htmlspecialchars($setting['rate'] ?? ''); ?>" step="0.01" min="0.01" placeholder="Rate" required>
                </div>

                <label class="checkbox-label">
                    <input type="checkbox" name="account_upgrade" value="1" <?php echo ($setting['account_upgrade'] ?? 0) == 1 ? 'checked' : ''; ?>> Account Upgrade Enabled
                </label>

                <div class="form-group">
                    <label>Image Settings</label>
                    <?php if (!empty($setting['images'])): ?>
                        <div class="current-image">
                            <strong>Current Image:</strong>
                            <img src="../images/<?php echo htmlspecialchars($setting['images']); ?>" alt="Current Verification Image">
                        </div>
                        <label class="checkbox-label">
                            <input type="checkbox" name="delete_image" value="1"> Delete current image
                        </label>
                    <?php else: ?>
                        <div class="current-image">No image currently attached.</div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="image">Upload New Image</label>
                    <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/gif,image/webp">
                    <small style="color: #666; font-size: 11px; margin-top: 2px;">Uploading a file will replace any existing image.</small>
                </div>

                <button type="submit">Update Verification</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
