<?php
session_start();

// Include configuration
require_once 'config.php';

// Initialize variables
$errors = [];
$formData = [
    'name' => '',
    'email' => '',
    'phone' => '',
    'serial_number' => '',
    'product_model' => '',
    'purchase_date' => '',
];
$productModels = [];

// --- Database Connection ---
$link = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
if ($link === false) {
    // This is a critical error, so we might not be able to display it nicely in HTML.
    // For a real application, log this error and show a generic error page.
    die("ERROR: Could not connect to database. " . mysqli_connect_error());
}

// --- Fetch Product Models for Dropdown ---
$sql_products = "SELECT model FROM products ORDER BY model ASC";
$result_products = mysqli_query($link, $sql_products);
if ($result_products) {
    while ($row = mysqli_fetch_assoc($result_products)) {
        $productModels[] = $row['model'];
    }
    mysqli_free_result($result_products);
} else {
    $errors['db_products'] = "Error fetching product models: " . mysqli_error($link);
    // Potentially log this error more formally
}


// --- Form Processing ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Store submitted values for repopulation
    $formData['name'] = trim($_POST['name'] ?? '');
    $formData['email'] = trim($_POST['email'] ?? '');
    $formData['phone'] = trim($_POST['phone'] ?? '');
    $formData['serial_number'] = trim($_POST['serial_number'] ?? '');
    $formData['product_model'] = trim($_POST['product_model'] ?? '');
    $formData['purchase_date'] = trim($_POST['purchase_date'] ?? '');

    // --- Validation ---

    // Name
    if (empty($formData['name'])) {
        $errors['name'] = "Name is required.";
    }

    // Email
    if (empty($formData['email'])) {
        $errors['email'] = "Email is required.";
    } elseif (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Invalid email format.";
    }

    // Phone
    if (empty($formData['phone'])) {
        $errors['phone'] = "Phone number is required.";
    } elseif (!preg_match('/^[0-9\-\+\(\) ]{7,20}$/', $formData['phone'])) { // Basic validation
        $errors['phone'] = "Invalid phone number format (allow numbers, spaces, +, -, ()).";
    }

    // Serial Number
    if (empty($formData['serial_number'])) {
        $errors['serial_number'] = "Serial number is required.";
    } else {
        // Check for uniqueness
        $sql_check_serial = "SELECT id FROM warranty_registration WHERE serial_numbers = ?";
        if ($stmt_check_serial = mysqli_prepare($link, $sql_check_serial)) {
            mysqli_stmt_bind_param($stmt_check_serial, "s", $formData['serial_number']);
            mysqli_stmt_execute($stmt_check_serial);
            mysqli_stmt_store_result($stmt_check_serial);
            if (mysqli_stmt_num_rows($stmt_check_serial) > 0) {
                $errors['serial_number'] = "This serial number is already registered.";
            }
            mysqli_stmt_close($stmt_check_serial);
        } else {
            $errors['serial_number'] = "Error checking serial number: " . mysqli_error($link);
        }
    }

    // Product Model
    if (empty($formData['product_model'])) {
        $errors['product_model'] = "Product model is required.";
    } elseif (!in_array($formData['product_model'], $productModels)) {
        $errors['product_model'] = "Invalid product model selected.";
    }

    // Purchase Date
    if (empty($formData['purchase_date'])) {
        $errors['purchase_date'] = "Purchase date is required.";
    } else {
        // Basic validation for YYYY-MM-DD format
        $date_parts = explode('-', $formData['purchase_date']);
        if (count($date_parts) !== 3 || !checkdate((int)$date_parts[1], (int)$date_parts[2], (int)$date_parts[0])) {
            $errors['purchase_date'] = "Invalid date format. Please use YYYY-MM-DD.";
        }
    }

    // Receipt Image
    $targetPath = ""; // Will be set if image is valid
    if (!isset($_FILES['receipt_image']) || $_FILES['receipt_image']['error'] == UPLOAD_ERR_NO_FILE) {
        $errors['receipt_image'] = "Receipt image is required.";
    } else {
        $imageFile = $_FILES['receipt_image'];
        // Check for other upload errors
        if ($imageFile['error'] !== UPLOAD_ERR_OK) {
            $uploadErrors = [
                UPLOAD_ERR_INI_SIZE   => "The uploaded file exceeds the upload_max_filesize directive in php.ini.",
                UPLOAD_ERR_FORM_SIZE  => "The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.",
                UPLOAD_ERR_PARTIAL    => "The uploaded file was only partially uploaded.",
                UPLOAD_ERR_NO_TMP_DIR => "Missing a temporary folder.",
                UPLOAD_ERR_CANT_WRITE => "Failed to write file to disk.",
                UPLOAD_ERR_EXTENSION  => "A PHP extension stopped the file upload.",
            ];
            $errors['receipt_image'] = $uploadErrors[$imageFile['error']] ?? "Unknown upload error.";
        } else {
            // Validate file type
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            $fileMimeType = mime_content_type($imageFile['tmp_name']); // More reliable than $_FILES['type']
            if (!in_array($fileMimeType, $allowedTypes)) {
                $errors['receipt_image'] = "Invalid file type. Only JPG, PNG, GIF are allowed. (Detected: " . htmlspecialchars($fileMimeType) . ")";
            }

            // Validate file size (e.g., max 2MB)
            $maxFileSize = 2 * 1024 * 1024; // 2MB
            if ($imageFile['size'] > $maxFileSize) {
                $errors['receipt_image'] = "File is too large. Maximum size is 2MB.";
            }

            // If image is valid so far, prepare for upload
            if (empty($errors['receipt_image'])) {
                $uploadDir = 'uploads/';
                // Create uploads directory if it doesn't exist
                if (!is_dir($uploadDir)) {
                    if (!mkdir($uploadDir, 0755, true)) { // Create recursively with permissions
                        $errors['receipt_image'] = "Failed to create upload directory.";
                    } elseif (!is_writable($uploadDir)) {
                         $errors['receipt_image'] = "Upload directory is not writable.";
                    }
                } elseif (!is_writable($uploadDir)) {
                    $errors['receipt_image'] = "Upload directory is not writable.";
                }


                if (empty($errors['receipt_image'])) { // Check again if directory creation/permission was ok
                    $imageFileName = time() . '_' . basename($imageFile['name']);
                    $targetPath = $uploadDir . $imageFileName;
                }
            }
        }
    }
    
    // --- Database Insertion (if no errors) ---
    if (empty($errors)) {
        // Move uploaded file
        if (!move_uploaded_file($_FILES['receipt_image']['tmp_name'], $targetPath)) {
            $errors['receipt_image'] = "Failed to move uploaded file. Check permissions.";
        } else {
            // All good, proceed to insert into database
            $sql_insert = "INSERT INTO warranty_registration (name, email, phone, serial_numbers, model, purchase_date, images) 
                           VALUES (?, ?, ?, ?, ?, ?, ?)";
            
            if ($stmt_insert = mysqli_prepare($link, $sql_insert)) {
                mysqli_stmt_bind_param(
                    $stmt_insert, 
                    "sssssss",
                    $formData['name'],
                    $formData['email'],
                    $formData['phone'],
                    $formData['serial_number'],
                    $formData['product_model'],
                    $formData['purchase_date'],
                    $targetPath // Store the path to the image
                );

                if (mysqli_stmt_execute($stmt_insert)) {
                    $_SESSION['last_warranty_id'] = mysqli_insert_id($link);
                    mysqli_stmt_close($stmt_insert);
                    mysqli_close($link);
                    header("Location: warranty_card.php");
                    exit;
                } else {
                    $errors['db_insert'] = "Database error during registration: " . mysqli_stmt_error($stmt_insert);
                    // Potentially delete the uploaded file if DB insert fails
                    if (file_exists($targetPath)) {
                        unlink($targetPath);
                    }
                }
                if(isset($stmt_insert)) mysqli_stmt_close($stmt_insert); // Ensure statement is closed
            } else {
                $errors['db_insert'] = "Database error preparing registration: " . mysqli_error($link);
                 // Potentially delete the uploaded file if DB prepare fails
                if (file_exists($targetPath) && !empty($targetPath)) { // ensure targetPath was set
                    unlink($targetPath);
                }
            }
        }
    }
    // If errors occurred, $errors array will be populated and displayed in HTML
}

// Close connection if it's still open (e.g., if not POST request or POST failed before closing)
if ($link && !($formData && empty($errors))) { // Avoid closing if redirect happened
   // mysqli_close($link); // Let's close it at the very end of the script for GET requests
}

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Register Your Warranty - Jellyfish</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="css/style.css">
    <style>
        /* Optional: Custom styles for better error message display */
        .form-text.text-danger {
            font-size: 0.875em;
        }
    </style>
</head>
<body>
    <div class="container mt-5 mb-5">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-7">
                <h2 class="mb-4 text-center">Register Your Product Warranty</h2>

                <?php if (!empty($errors) && isset($errors['db_insert'])): ?>
                    <div class="alert alert-danger">
                        <p class="mb-0"><strong>Registration Failed!</strong></p>
                        <p class="mb-0"><?php echo htmlspecialchars($errors['db_insert']); ?></p>
                    </div>
                <?php endif; ?>
                <?php if (!empty($errors) && isset($errors['db_products'])): ?>
                    <div class="alert alert-warning">
                        <p class="mb-0"><?php echo htmlspecialchars($errors['db_products']); ?></p>
                         <p class="mb-0">Product selection might be unavailable. Please try again later.</p>
                    </div>
                <?php endif; ?>


                <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" enctype="multipart/form-data" novalidate>
                    
                    <!-- Name -->
                    <div class="mb-3">
                        <label for="name" class="form-label">Full Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control <?php echo isset($errors['name']) ? 'is-invalid' : ''; ?>" id="name" name="name" value="<?php echo htmlspecialchars($formData['name']); ?>" required>
                        <?php if (isset($errors['name'])): ?><div class="invalid-feedback"><?php echo $errors['name']; ?></div><?php endif; ?>
                    </div>

                    <!-- Email -->
                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                        <input type="email" class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" id="email" name="email" value="<?php echo htmlspecialchars($formData['email']); ?>" required>
                        <?php if (isset($errors['email'])): ?><div class="invalid-feedback"><?php echo $errors['email']; ?></div><?php endif; ?>
                    </div>

                    <!-- Phone -->
                    <div class="mb-3">
                        <label for="phone" class="form-label">Phone Number <span class="text-danger">*</span></label>
                        <input type="tel" class="form-control <?php echo isset($errors['phone']) ? 'is-invalid' : ''; ?>" id="phone" name="phone" value="<?php echo htmlspecialchars($formData['phone']); ?>" required>
                        <?php if (isset($errors['phone'])): ?><div class="invalid-feedback"><?php echo $errors['phone']; ?></div><?php endif; ?>
                    </div>

                    <!-- Serial Number -->
                    <div class="mb-3">
                        <label for="serial_number" class="form-label">Serial Number <span class="text-danger">*</span></label>
                        <input type="text" class="form-control <?php echo isset($errors['serial_number']) ? 'is-invalid' : ''; ?>" id="serial_number" name="serial_number" value="<?php echo htmlspecialchars($formData['serial_number']); ?>" required>
                        <?php if (isset($errors['serial_number'])): ?><div class="invalid-feedback"><?php echo $errors['serial_number']; ?></div><?php endif; ?>
                    </div>

                    <!-- Product Model -->
                    <div class="mb-3">
                        <label for="product_model" class="form-label">Product Model <span class="text-danger">*</span></label>
                        <select class="form-select <?php echo isset($errors['product_model']) ? 'is-invalid' : ''; ?>" id="product_model" name="product_model" required>
                            <option value="">Choose...</option>
                            <?php foreach ($productModels as $model): ?>
                                <option value="<?php echo htmlspecialchars($model); ?>" <?php echo ($formData['product_model'] == $model) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($model); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['product_model'])): ?><div class="invalid-feedback"><?php echo $errors['product_model']; ?></div><?php endif; ?>
                    </div>

                    <!-- Purchase Date -->
                    <div class="mb-3">
                        <label for="purchase_date" class="form-label">Purchase Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control <?php echo isset($errors['purchase_date']) ? 'is-invalid' : ''; ?>" id="purchase_date" name="purchase_date" value="<?php echo htmlspecialchars($formData['purchase_date']); ?>" required>
                        <?php if (isset($errors['purchase_date'])): ?><div class="invalid-feedback"><?php echo $errors['purchase_date']; ?></div><?php endif; ?>
                    </div>

                    <!-- Receipt Image -->
                    <div class="mb-3">
                        <label for="receipt_image" class="form-label">Proof of Purchase (Receipt Image) <span class="text-danger">*</span></label>
                        <input type="file" class="form-control <?php echo isset($errors['receipt_image']) ? 'is-invalid' : ''; ?>" id="receipt_image" name="receipt_image" accept="image/png, image/jpeg, image/gif" required>
                        <?php if (isset($errors['receipt_image'])): ?><div class="invalid-feedback d-block"><?php echo $errors['receipt_image']; ?></div><?php endif; ?>
                        <div id="imageHelp" class="form-text">Max file size: 2MB. Allowed types: JPG, PNG, GIF.</div>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg">Register Warranty</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
<?php
// Final close of DB connection if it was opened and not closed yet (e.g. for GET request)
if ($link) {
    mysqli_close($link);
}
?>
