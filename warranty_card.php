<?php
session_start();

// Include configuration
require_once 'config.php';

// Initialize variables
$warrantyData = null;
$errorMessage = '';
$warrantyId = null;

// --- Database Connection ---
$link = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
if ($link === false) {
    // For a real application, log this error and show a generic error page.
    // Since this page is for displaying data, an error here is critical.
    $errorMessage = "ERROR: Could not connect to the database. Please try again later.";
    // No further DB operations possible if connection fails.
} else {
    // --- Fetch Warranty ID ---
    if (isset($_GET['id']) && filter_var($_GET['id'], FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]])) {
        $warrantyId = (int)$_GET['id'];
    } elseif (isset($_SESSION['last_warranty_id']) && filter_var($_SESSION['last_warranty_id'], FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]])) {
        $warrantyId = (int)$_SESSION['last_warranty_id'];
        unset($_SESSION['last_warranty_id']); // Clear it after use
    } else {
        $errorMessage = "Invalid or missing warranty ID.";
    }

    // --- Fetch Warranty Data from Database ---
    if ($warrantyId && empty($errorMessage)) {
        $sql = "SELECT id, name, email, phone, serial_numbers, model, purchase_date, images FROM warranty_registration WHERE id = ?";
        
        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "i", $warrantyId);
            
            if (mysqli_stmt_execute($stmt)) {
                $result = mysqli_stmt_get_result($stmt);
                $warrantyData = mysqli_fetch_assoc($result);
                
                if (!$warrantyData) {
                    $errorMessage = "Warranty record not found for the provided ID.";
                }
                mysqli_free_result($result);
            } else {
                $errorMessage = "Error executing database query: " . mysqli_stmt_error($stmt);
                // Log mysqli_stmt_error($stmt) for debugging
            }
            mysqli_stmt_close($stmt);
        } else {
            $errorMessage = "Error preparing database query: " . mysqli_error($link);
            // Log mysqli_error($link) for debugging
        }
    }
    
    // Close connection
    mysqli_close($link);
} // End of database connection check

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Your Warranty Card - Jellyfish</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="css/style.css">
    <style>
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                margin: 0;
                background-color: #fff; /* Ensure background is white for printing */
            }
            .card {
                border: 1px solid #dee2e6 !important; /* Ensure card border prints */
                box-shadow: none !important; /* Remove shadow for printing */
            }
        }
    </style>
</head>
<body>
    <div class="container mt-5 mb-5">
        <h2 class="mb-4 text-center">Product Warranty Card</h2>

        <?php if (!empty($errorMessage)): ?>
            <div class="alert alert-danger text-center" role="alert">
                <?php echo htmlspecialchars($errorMessage); ?>
            </div>
            <div class="text-center mt-4 no-print">
                <a href="index.html" class="btn btn-primary">Back to Homepage</a>
            </div>
        <?php elseif ($warrantyData): ?>
            <div class="card shadow-sm">
                <div class="card-header text-center">
                    <h4>Warranty Details for #<?php echo htmlspecialchars($warrantyData['id']); ?></h4>
                </div>
                <div class="card-body p-4">
                    <div class="row mb-3">
                        <div class="col-sm-4"><strong>Name:</strong></div>
                        <div class="col-sm-8"><?php echo htmlspecialchars($warrantyData['name']); ?></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-sm-4"><strong>Email:</strong></div>
                        <div class="col-sm-8"><?php echo htmlspecialchars($warrantyData['email']); ?></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-sm-4"><strong>Phone:</strong></div>
                        <div class="col-sm-8"><?php echo htmlspecialchars($warrantyData['phone']); ?></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-sm-4"><strong>Serial Number:</strong></div>
                        <div class="col-sm-8"><?php echo htmlspecialchars($warrantyData['serial_numbers']); ?></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-sm-4"><strong>Product Model:</strong></div>
                        <div class="col-sm-8"><?php echo htmlspecialchars($warrantyData['model']); ?></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-sm-4"><strong>Purchase Date:</strong></div>
                        <div class="col-sm-8"><?php echo htmlspecialchars(date("F j, Y", strtotime($warrantyData['purchase_date']))); ?></div>
                    </div>
                    
                    <?php if (!empty($warrantyData['images']) && file_exists($warrantyData['images'])): ?>
                        <div class="mt-4 text-center">
                            <h5>Proof of Purchase:</h5>
                            <img src="<?php echo htmlspecialchars($warrantyData['images']); ?>" alt="Receipt Image for <?php echo htmlspecialchars($warrantyData['serial_numbers']); ?>" class="img-fluid img-thumbnail" style="max-height: 400px;">
                        </div>
                    <?php elseif (!empty($warrantyData['images'])): ?>
                         <div class="mt-4 text-center">
                            <h5>Proof of Purchase:</h5>
                            <p class="text-danger">Receipt image not found at path: <?php echo htmlspecialchars($warrantyData['images']); ?>. Please contact support.</p>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="card-footer text-center no-print">
                    <a href="index.html" class="btn btn-secondary me-2">Back to Homepage</a>
                    <button onclick="window.print()" class="btn btn-primary">Print Warranty Card</button>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-warning text-center" role="alert">
                No warranty data to display. If you just registered, please try again or contact support.
            </div>
            <div class="text-center mt-4 no-print">
                <a href="index.html" class="btn btn-primary">Back to Homepage</a>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
