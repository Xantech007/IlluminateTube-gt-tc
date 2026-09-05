<?php
session_start();

// Check if the user is logged in as an admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

require_once '../database/conn.php';
require_once '../inc/countries.php'; // Include the countries file

// Set time zone to WAT
date_default_timezone_set('Africa/Lagos');

// Define upload directory and URL
$upload_dir = '../images/';
$upload_url = '../images/'; // Adjust if needed for display

// Handle region setting actions (add_dashboard, add_verification, delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    try {
        $pdo->beginTransaction();

        if ($action === 'add_dashboard') {
            $country = trim($_POST['country']);
            $section_header = trim($_POST['section_header']);
            $channel = trim($_POST['channel']);
            $channel_options = trim($_POST['channel_options']);
            $ch_name = trim($_POST['ch_name']);
            $ch_value = trim($_POST['ch_value']);
            $withdraw_currency = trim($_POST['withdraw_currency']);

            // Validation
            if (empty($country) || empty($section_header) || empty($channel) || empty($ch_name) || empty($ch_value) || empty($withdraw_currency)) {
                $_SESSION['error'] = "All Dashboard fields, including Currency, are required.";
            } else {
                // Check if country already exists
                $stmt = $pdo->prepare("SELECT id FROM region_settings WHERE country = ?");
                $stmt->execute([$country]);
                if ($stmt->fetch()) {
                    $_SESSION['error'] = "Region settings for this country already exist.";
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO region_settings (country, section_header, channel, channel_options, ch_name, ch_value, withdraw_currency)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$country, $section_header, $channel, $channel_options, $ch_name, $ch_value, $withdraw_currency]);
                    $_SESSION['success'] = "Dashboard settings added successfully.";
                }
            }
        } 
        elseif ($action === 'add_verification') {
            $country = trim($_POST['country']);
            $verify_ch = trim($_POST['verify_ch']);
            $vc_value = trim($_POST['vc_value']);
            $verify_ch_name = trim($_POST['verify_ch_name']);
            $verify_ch_value = trim($_POST['verify_ch_value']);
            $verify_medium = trim($_POST['verify_medium']);
            $vcn_value = trim($_POST['vcn_value']);
            $vcv_value = trim($_POST['vcv_value']);
            $verify_currency = trim($_POST['verify_currency']);
            $verify_amount = floatval($_POST['verify_amount']);
            $rate = floatval($_POST['rate']);
            $account_upgrade = isset($_POST['account_upgrade']) ? 1 : 0;

            // Handle image upload
            $image_filename = null;
            if (isset($_FILES['verification_image']) && $_FILES['verification_image']['error'] === UPLOAD_ERR_OK) {
                $file_tmp = $_FILES['verification_image']['tmp_name'];
                $file_name = basename($_FILES['verification_image']['name']);
                $file_size = $_FILES['verification_image']['size'];
                $file_type = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

                // Validate file
                $allowed = ['jpg', 'jpeg', 'png', 'gif'];
                if (!in_array($file_type, $allowed)) {
                    $_SESSION['error'] = "Only JPG, JPEG, PNG & GIF files are allowed.";
                    $pdo->rollBack();
                    header("Location: manage_region_settings.php");
                    exit;
                }
                if ($file_size > 2 * 1024 * 1024) { // 2MB
                    $_SESSION['error'] = "Image must be less than 2MB.";
                    $pdo->rollBack();
                    header("Location: manage_region_settings.php");
                    exit;
                }

                // Generate unique filename
                $image_filename = uniqid('img_', true) . '.' . $file_type;
                $destination = $upload_dir . $image_filename;

                if (!move_uploaded_file($file_tmp, $destination)) {
                    $_SESSION['error'] = "Failed to upload image.";
                    $pdo->rollBack();
                    header("Location: manage_region_settings.php");
                    exit;
                }
            }

            // Validation
            if (empty($country) || empty($verify_ch) || empty($vc_value) || empty($verify_ch_name) || 
                empty($verify_ch_value) || empty($vcn_value) || empty($vcv_value) || 
                empty($verify_currency) || $verify_amount <= 0 || $rate <= 0) {
                // Delete uploaded image if validation fails
                if ($image_filename && file_exists($upload_dir . $image_filename)) {
                    unlink($upload_dir . $image_filename);
                }
                $_SESSION['error'] = "All Verification fields except Verify Medium are required, Amount and Rate must be greater than 0.";
            } else {
                // Update existing row for the country
                $stmt = $pdo->prepare("
                    UPDATE region_settings 
                    SET verify_ch = ?, vc_value = ?, verify_ch_name = ?, verify_ch_value = ?, 
                        verify_medium = ?, vcn_value = ?, vcv_value = ?, verify_currency = ?, 
                        verify_amount = ?, rate = ?, account_upgrade = ?, images = ?
                    WHERE country = ?
                ");
                $result = $stmt->execute([
                    $verify_ch, $vc_value, $verify_ch_name, $verify_ch_value, $verify_medium, 
                    $vcn_value, $vcv_value, $verify_currency, $verify_amount, $rate, 
                    $account_upgrade, $image_filename, $country
                ]);

                if ($stmt->rowCount() === 0) {
                    // Delete image if no row was updated
                    if ($image_filename && file_exists($upload_dir . $image_filename)) {
                        unlink($upload_dir . $image_filename);
                    }
                    $_SESSION['error'] = "No Dashboard settings found for this country. Add Dashboard settings first.";
                } else {
                    $_SESSION['success'] = "Verification/Upgrade settings updated successfully.";
                }
            }
        } 
        elseif ($action === 'delete') {
            $id = intval($_POST['id']);
            
            // Get image filename before deleting
            $stmt = $pdo->prepare("SELECT images FROM region_settings WHERE id = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            
            $stmt = $pdo->prepare("DELETE FROM region_settings WHERE id = ?");
            $stmt->execute([$id]);

            // Delete image file if exists
            if ($row && !empty($row['images']) && file_exists($upload_dir . $row['images'])) {
                unlink($upload_dir . $row['images']);
            }

            $_SESSION['success'] = "Region setting deleted successfully.";
        }

        $pdo->commit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log('Region setting action error: ' . $e->getMessage(), 3, '../debug.log');
        $_SESSION['error'] = "Database error: " . $e->getMessage();
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log('Upload error: ' . $e->getMessage(), 3, '../debug.log');
        $_SESSION['error'] = "Upload failed: " . $e->getMessage();
    }
    
    header("Location: manage_region_settings.php");
    exit;
}

// Fetch all region settings
try {
    $stmt = $pdo->prepare("
        SELECT id, country, section_header, channel, channel_options, ch_name, ch_value, withdraw_currency, 
               verify_ch, vc_value, verify_ch_name, verify_ch_value, verify_medium, vcn_value, 
               vcv_value, verify_currency, verify_amount, rate, account_upgrade, images
        FROM region_settings
        ORDER BY country
    ");
    $stmt->execute();
    $region_settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Region settings fetch error: ' . $e->getMessage(), 3, '../debug.log');
    $region_settings = [];
    $error = 'Failed to load region settings: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Tube - Manage Region Settings</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            background-color: #f4f4f4;
        }

        .dashboard-container {
            max-width: 1400px;
            margin: 50px auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .dashboard-container h2 {
            color: #333;
            margin-bottom: 20px;
        }

        .dashboard-container h3 {
            color: #333;
            margin: 30px 0 15px;
            font-size: 18px;
            border-bottom: 2px solid #ddd;
            padding-bottom: 5px;
        }

        .dashboard-container p {
            color: #555;
            font-size: 16px;
        }

        .button-container {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 10px;
            margin: 20px 0;
        }

        .back-link, .action-btn {
            box-sizing: border-box;
            padding: 7px 14px;
            color: #fff;
            text-decoration: none;
            border-radius: 4px;
            font-size: 12px;
            transition: background-color 0.3s ease;
        }

        .back-link {
            background-color: #6c757d;
        }

        .back-link:hover {
            background-color: #5a6268;
        }

        .action-btn.add {
            background-color: #28a745;
        }

        .action-btn.add:hover {
            background-color: #218838;
        }

        .action-btn.delete {
            background-color: #dc3545;
        }

        .action-btn.delete:hover {
            background-color: #c82333;
        }

        .action-btn.edit {
            background-color: #007bff;
        }

        .action-btn.edit:hover {
            background-color: #0056b3;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .add-form {
            margin-bottom: 20px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
            justify-items: center;
            max-width: 100%;
            margin-left: auto;
            margin-right: auto;
        }

        .add-form input[type="text"],
        .add-form input[type="number"],
        .add-form input[type="file"],
        .add-form textarea,
        .add-form select,
        .add-form input[type="checkbox"] {
            width: 100%;
            padding: 6px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 13px;
            box-sizing: border-box;
            font-family: inherit;
        }

        .add-form textarea {
            resize: vertical;
            min-height: 38px;
        }

        .add-form input[type="file"] {
            padding: 3px;
        }

        .add-form select {
            background-color: #fff;
            cursor: pointer;
        }

        .add-form label.checkbox-label {
            display: flex;
            align-items: center;
            font-size: 13px;
            color: #555;
            justify-self: start;
        }

        .add-form input[type="checkbox"] {
            width: auto;
            margin-right: 8px;
        }

        .add-form button {
            width: 200px;
            grid-column: 1 / -1;
            justify-self: center;
        }

        .table-container {
            max-width: 100%;
            overflow-x: auto;
            overflow-y: auto;
            max-height: 400px;
            margin-top: 10px;
            margin-left: auto;
            margin-right: auto;
        }

        .region-settings-table {
            width: 100%;
            border-collapse: collapse;
        }

        .region-settings-table th,
        .region-settings-table td {
            padding: 8px;
            border: 1px solid #ddd;
            text-align: left;
            font-size: 13px;
        }

        .region-settings-table th {
            background-color: #f8f9fa;
            color: #333;
            position: sticky;
            top: 0;
            z-index: 1;
        }

        .region-settings-table td {
            color: #555;
        }

        .preview-img {
            max-width: 60px;
            max-height: 60px;
            object-fit: cover;
            border-radius: 4px;
            margin: 2px;
        }

        .error, .success {
            color: red;
            margin-bottom: 15px;
        }

        .success {
            color: green;
        }

        @media (max-width: 768px) {
            .dashboard-container {
                margin: 20px;
                padding: 15px;
            }

            .add-form {
                grid-template-columns: 1fr;
            }

            .add-form input,
            .add-form textarea,
            .add-form select,
            .add-form button,
            .add-form label.checkbox-label {
                width: 100%;
            }

            .add-form input[type="checkbox"] {
                width: auto;
            }

            .region-settings-table th,
            .region-settings-table td {
                font-size: 12px;
                padding: 6px;
            }

            .action-btn, .back-link {
                width: 100%;
                max-width: 180px;
                margin: 6px 0;
                padding: 6px 12px;
                font-size: 11px;
            }

            .action-buttons {
                flex-direction: column;
                align-items: center;
            }

            .preview-img {
                max-width: 40px;
                max-height: 40px;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <h2>Manage Region Settings</h2>
        <p>Hello, <?php echo htmlspecialchars($_SESSION['admin_email']); ?>!</p>

        <?php if (isset($error)): ?>
            <p class="error"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>
        <?php if (isset($_SESSION['success'])): ?>
            <p class="success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></p>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <p class="error"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></p>
        <?php endif; ?>

        <div class="button-container">
            <a href="dashboard.php" class="back-link">Back to Dashboard</a>
        </div>

        <h3>Dashboard Section</h3>
        <form action="manage_region_settings.php" method="POST" class="add-form">
            <select name="country" required>
                <option value="" disabled selected>Select Country</option>
                <?php foreach ($countries as $country): ?>
                    <option value="<?php echo htmlspecialchars($country); ?>"><?php echo htmlspecialchars($country); ?></option>
                <?php endforeach; ?>
            </select>
            <input type="text" name="section_header" placeholder="Section Heading (e.g., Withdraw with bank)" required>
            <input type="text" name="channel" placeholder="Channel (e.g., Bank)" required>
            <textarea name="channel_options" placeholder="Channel Options (one per line, e.g. Telecel&#10;MTN)" rows="2"></textarea>
            <input type="text" name="ch_name" placeholder="Channel Name (e.g., Bank Name)" required>
            <input type="text" name="ch_value" placeholder="Channel Number (e.g., Account Number)" required>
            <input type="text" name="withdraw_currency" placeholder="Currency (e.g., NGN)" required>
            <input type="hidden" name="action" value="add_dashboard">
            <button type="submit" class="action-btn add">Add Dashboard Settings</button>
        </form>

        <?php if (empty($region_settings)): ?>
            <p>No Dashboard settings available.</p>
        <?php else: ?>
            <div class="table-container">
                <table class="region-settings-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Country</th>
                            <th>Section Heading</th>
                            <th>Channel</th>
                            <th>Channel Options</th>
                            <th>Channel Name</th>
                            <th>Channel Number</th>
                            <th>Currency</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($region_settings as $setting): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($setting['id']); ?></td>
                                <td><?php echo htmlspecialchars($setting['country']); ?></td>
                                <td><?php echo htmlspecialchars($setting['section_header']); ?></td>
                                <td><?php echo htmlspecialchars($setting['channel']); ?></td>
                                <td><?php echo !empty($setting['channel_options']) ? nl2br(htmlspecialchars($setting['channel_options'])) : 'N/A'; ?></td>
                                <td><?php echo htmlspecialchars($setting['ch_name']); ?></td>
                                <td><?php echo htmlspecialchars($setting['ch_value']); ?></td>
                                <td><?php echo htmlspecialchars($setting['withdraw_currency'] ?? 'N/A'); ?></td>
                                <td class="action-buttons">
                                    <a href="edit_region_setting.php?id=<?php echo $setting['id']; ?>&section=dashboard" class="action-btn edit">Edit</a>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="id" value="<?php echo $setting['id']; ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <button type="submit" class="action-btn delete" onclick="return confirm('Are you sure you want to delete this region setting? This will remove both Dashboard and Verification settings.');">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <h3>Verification/Upgrade Section</h3>
        <form action="manage_region_settings.php" method="POST" class="add-form" enctype="multipart/form-data">
            <select name="country" required>
                <option value="" disabled selected>Select Country</option>
                <?php foreach ($region_settings as $setting): ?>
                    <option value="<?php echo htmlspecialchars($setting['country']); ?>"><?php echo htmlspecialchars($setting['country']); ?></option>
                <?php endforeach; ?>
            </select>
            <input type="text" name="verify_ch" placeholder="Channel (e.g., Bank)" required>
            <input type="text" name="vc_value" placeholder="Name (e.g., Obi Mikel)" required>
            <input type="text" name="verify_ch_name" placeholder="Channel Name (e.g., Bank Name)" required>
            <input type="text" name="verify_ch_value" placeholder="Channel Number (e.g., Account Number)" required>
            <input type="text" name="verify_medium" placeholder="Verify Medium (e.g., Payment Method)">
            <input type="text" name="vcn_value" placeholder="Channel Name Value (e.g., MOMO PSB)" required>
            <input type="text" name="vcv_value" placeholder="Channel Number Value (e.g., 8012345678)" required>
            <input type="text" name="verify_currency" placeholder="Currency (e.g., NGN)" required>
            <input type="number" name="verify_amount" placeholder="Charges (e.g., 15000)" step="0.01" required>
            <input type="number" name="rate" placeholder="Conversion Rate (e.g., 1000 for 1 USD = 1000 NGN)" step="0.01" required>
            <label class="checkbox-label">
                <input type="checkbox" name="account_upgrade" value="1"> Upgrade (checked) / Verification (unchecked)
            </label>
            <div style="grid-column: 1 / -1; text-align: left; font-size: 13px; color: #666;">
                <label>Upload Image (JPG, PNG, GIF - max 2MB):</label>
                <input type="file" name="verification_image" accept="image/*">
            </div>
            <input type="hidden" name="action" value="add_verification">
            <button type="submit" class="action-btn add">Update Verification/Upgrade Settings</button>
        </form>

        <?php if (empty($region_settings)): ?>
            <p>No Verification/Upgrade settings available.</p>
        <?php else: ?>
            <div class="table-container">
                <table class="region-settings-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Country</th>
                            <th>Type</th>
                            <th>Channel</th>
                            <th>Name</th>
                            <th>Channel Name</th>
                            <th>Channel Number</th>
                            <th>Verify Medium</th>
                            <th>Channel Name Value</th>
                            <th>Channel Number Value</th>
                            <th>Currency</th>
                            <th>Charges</th>
                            <th>Rate</th>
                            <th>Image</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($region_settings as $setting): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($setting['id']); ?></td>
                                <td><?php echo htmlspecialchars($setting['country']); ?></td>
                                <td><?php echo $setting['account_upgrade'] == 1 ? 'Upgrade' : 'Verification'; ?></td>
                                <td><?php echo htmlspecialchars($setting['verify_ch'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($setting['vc_value'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($setting['verify_ch_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($setting['verify_ch_value'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($setting['verify_medium'] ?? 'Payment Method'); ?></td>
                                <td><?php echo htmlspecialchars($setting['vcn_value'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($setting['vcv_value'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($setting['verify_currency'] ?? 'N/A'); ?></td>
                                <td><?php echo isset($setting['verify_amount']) ? number_format($setting['verify_amount'], 2) : 'N/A'; ?></td>
                                <td><?php echo isset($setting['rate']) ? number_format($setting['rate'], 2) : 'N/A'; ?></td>
                                <td>
                                    <?php if (!empty($setting['images']) && file_exists($upload_dir . $setting['images'])): ?>
                                        <img src="<?php echo htmlspecialchars($upload_url . $setting['images']); ?>" alt="Verification Image" class="preview-img">
                                    <?php else: ?>
                                        No Image
                                    <?php endif; ?>
                                </td>
                                <td class="action-buttons">
                                    <a href="edit_region_setting.php?id=<?php echo $setting['id']; ?>&section=verification" class="action-btn edit">Edit</a>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="id" value="<?php echo $setting['id']; ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <button type="submit" class="action-btn delete" onclick="return confirm('Are you sure you want to delete this region setting? This will remove both Dashboard and Verification settings.');">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
