<?php
session_start();

// Include configuration
require_once 'config.php';

// Initialize variables
$errors = [];
$search_name = '';
$search_model = '';
$search_message = '';
$search_message_type = 'info'; // For Bootstrap alert class: info, warning, danger
$productModels = [];

// --- Database Connection ---
$link = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
if ($link === false) {
    // Critical error, display a generic message or die.
    // For a real application, log this and show a user-friendly error page.
    $search_message = "FATAL ERROR: Could not connect to the database. Please try again later or contact support.";
    $search_message_type = 'danger';
} else {
    // --- Fetch Product Models for Dropdown ---
    $sql_products = "SELECT model FROM products ORDER BY model ASC";
    $result_products = mysqli_query($link, $sql_products);
    if ($result_products) {
        while ($row = mysqli_fetch_assoc($result_products)) {
            $productModels[] = $row['model'];
        }
        mysqli_free_result($result_products);
    } else {
        // This is a non-fatal error for form display, but important.
        $errors['db_products'] = "Error fetching product models: " . mysqli_error($link);
        // Potentially log this error more formally
    }
}

// --- Form Processing ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && $link) { // Only process if DB connection was successful
    $search_name = trim($_POST['name'] ?? '');
    $search_model = trim($_POST['product_model'] ?? '');

    // --- Validation ---
    if (empty($search_name)) {
        $errors['name'] = "Your name is required.";
    }
    if (empty($search_model)) {
        $errors['product_model'] = "Product model is required.";
    } elseif (!in_array($search_model, $productModels) && !empty($productModels)) { // Check if productModels were loaded
        $errors['product_model'] = "Invalid product model selected.";
    }


    // --- Database Search (if no validation errors) ---
    if (empty($errors)) {
        $sql_search = "SELECT id FROM warranty_registration WHERE name = ? AND model = ? ORDER BY id DESC LIMIT 1"; // Get the latest if multiple
        
        if ($stmt_search = mysqli_prepare($link, $sql_search)) {
            mysqli_stmt_bind_param($stmt_search, "ss", $search_name, $search_model);
            
            if (mysqli_stmt_execute($stmt_search)) {
                $result_search = mysqli_stmt_get_result($stmt_search);
                $found_warranty = mysqli_fetch_assoc($result_search);
                mysqli_free_result($result_search);

                if ($found_warranty) {
                    mysqli_stmt_close($stmt_search);
                    mysqli_close($link);
                    header("Location: warranty_card.php?id=" . $found_warranty['id']);
                    exit;
                } else {
                    $search_message = "No warranty record found for the provided name and product model.";
                    $search_message_type = 'warning';
                }
            } else {
                $search_message = "Error executing search query: " . mysqli_stmt_error($stmt_search);
                $search_message_type = 'danger';
                // Log mysqli_stmt_error($stmt_search)
            }
            if(isset($stmt_search)) mysqli_stmt_close($stmt_search); // Ensure statement is closed
        } else {
            $search_message = "Error preparing search query: " . mysqli_error($link);
            $search_message_type = 'danger';
            // Log mysqli_error($link)
        }
    }
}

// Close connection if it's still open and not a POST success redirect
if ($link && $_SERVER["REQUEST_METHOD"] != "POST" || ($link && !empty($errors)) || ($link && !empty($search_message) && $search_message_type != 'info' && empty($errors))) {
    // mysqli_close($link); // Let's close it at the very end
}

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Check Warranty Status - Jellyfish</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-7 col-lg-6">
                <h2 class="mb-4 text-center">Check Your Warranty Status</h2>

                <?php if (!empty($search_message)): ?>
                    <div class="alert alert-<?php echo htmlspecialchars($search_message_type); ?> text-center" role="alert">
                        <?php echo htmlspecialchars($search_message); ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($errors) && isset($errors['db_products'])): ?>
                     <div class="alert alert-warning text-center" role="alert">
                        <?php echo htmlspecialchars($errors['db_products']); ?> <br>Product model selection may be unavailable.
                    </div>
                <?php endif; ?>
                
                <?php 
                $field_errors = $errors;
                unset($field_errors['db_products']); // Don't show db_products error with other field errors
                if (!empty($field_errors)): 
                ?>
                    <div class="alert alert-danger" role="alert">
                        <p class="mb-0"><strong>Please correct the following errors:</strong></p>
                        <ul>
                            <?php foreach ($field_errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>


                <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" novalidate>
                    <!-- Name -->
                    <div class="mb-3">
                        <label for="name" class="form-label">Your Full Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control <?php echo isset($errors['name']) ? 'is-invalid' : ''; ?>" id="name" name="name" value="<?php echo htmlspecialchars($search_name); ?>" required>
                        <?php if (isset($errors['name'])): ?><div class="invalid-feedback"><?php echo $errors['name']; ?></div><?php endif; ?>
                    </div>

                    <!-- Product Model -->
                    <div class="mb-3">
                        <label for="product_model" class="form-label">Product Model <span class="text-danger">*</span></label>
                        <select class="form-select <?php echo isset($errors['product_model']) ? 'is-invalid' : ''; ?>" id="product_model" name="product_model" required>
                            <option value="">Choose product...</option>
                            <?php if (!empty($productModels)): ?>
                                <?php foreach ($productModels as $model): ?>
                                    <option value="<?php echo htmlspecialchars($model); ?>" <?php echo ($search_model == $model) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($model); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <option value="" disabled>Could not load product models</option>
                            <?php endif; ?>
                        </select>
                        <?php if (isset($errors['product_model'])): ?><div class="invalid-feedback"><?php echo $errors['product_model']; ?></div><?php endif; ?>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg">Search Warranty</button>
                    </div>
                </form>

                <p class="mt-4 text-center">
                    <a href="index.html" class="btn btn-outline-secondary">Back to Homepage</a>
                </p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
<?php
// Final close of DB connection if it was opened and not closed yet
if ($link) {
    mysqli_close($link);
}
?>
