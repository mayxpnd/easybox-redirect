<?php
// setup_database.php
// This script creates the database and necessary tables.

// Include the database configuration file
require_once __DIR__ . '/../config.php';

// --- Step 1: Connect to MySQL server (without selecting a specific database initially) ---
$link_server = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD);

// Check server connection
if($link_server === false){
    die("ERROR: Could not connect to MySQL server. " . mysqli_connect_error() . "\n");
}
echo "Successfully connected to MySQL server.\n";

// --- Step 2: Create the database if it doesn't exist ---
$db_name = DB_NAME; // Get database name from config
$sql_create_db = "CREATE DATABASE IF NOT EXISTS `$db_name`";

if(mysqli_query($link_server, $sql_create_db)){
    echo "Database '$db_name' created successfully or already exists.\n";
} else {
    die("ERROR: Could not create database '$db_name'. " . mysqli_error($link_server) . "\n");
}

// Close the server-only connection
mysqli_close($link_server);

// --- Step 3: Re-connect to MySQL server, this time selecting the specific database ---
// The $link variable from config.php should now connect to the correct DB
// or we can establish a new one. For clarity, let's use the $link from config.php
// by re-including it, or better, establish a new connection with DB_NAME.

$link_db = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check database connection
if($link_db === false){
    die("ERROR: Could not connect to database '$db_name'. " . mysqli_connect_error() . "\n");
}
echo "Successfully connected to database '$db_name'.\n";


// --- Step 4: Create 'products' table ---
$sql_create_products_table = "
CREATE TABLE IF NOT EXISTS `products` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `model` VARCHAR(255) NOT NULL
)";

if(mysqli_query($link_db, $sql_create_products_table)){
    echo "Table 'products' created successfully or already exists.\n";
} else {
    die("ERROR: Could not create table 'products'. " . mysqli_error($link_db) . "\n");
}

// --- Step 5: Create 'warranty_registration' table ---
$sql_create_warranty_table = "
CREATE TABLE IF NOT EXISTS `warranty_registration` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `phone` VARCHAR(50) NOT NULL,
    `serial_numbers` VARCHAR(255) NOT NULL UNIQUE,
    `model` VARCHAR(255) NOT NULL,
    `purchase_date` DATE NOT NULL,
    `images` VARCHAR(255) NOT NULL
)";

if(mysqli_query($link_db, $sql_create_warranty_table)){
    echo "Table 'warranty_registration' created successfully or already exists.\n";
} else {
    die("ERROR: Could not create table 'warranty_registration'. " . mysqli_error($link_db) . "\n");
}

// --- Step 6: Populate 'products' table with sample models (if empty) ---
$sql_check_products = "SELECT id FROM `products` LIMIT 1";
$result_check_products = mysqli_query($link_db, $sql_check_products);

if ($result_check_products && mysqli_num_rows($result_check_products) == 0) {
    $product_models = ['JellyPal Large', 'GlowJelly Mini', 'OceanBuddy'];
    $sql_insert_product = "INSERT INTO `products` (model) VALUES (?)";
    $stmt_insert_product = mysqli_prepare($link_db, $sql_insert_product);

    if ($stmt_insert_product) {
        foreach ($product_models as $model) {
            mysqli_stmt_bind_param($stmt_insert_product, "s", $model);
            if(mysqli_stmt_execute($stmt_insert_product)){
                echo "Inserted product: '$model'.\n";
            } else {
                echo "ERROR: Could not insert product '$model'. " . mysqli_stmt_error($stmt_insert_product) . "\n";
            }
        }
        mysqli_stmt_close($stmt_insert_product);
        echo "Product population step completed.\n";
    } else {
        echo "ERROR: Could not prepare statement for inserting products. " . mysqli_error($link_db) . "\n";
    }
} else if (!$result_check_products) {
    echo "ERROR: Could not check if 'products' table is empty. " . mysqli_error($link_db) . "\n";
} else {
    echo "'products' table already has data. Skipping population.\n";
}

// Close the final database connection
mysqli_close($link_db);

echo "Database setup script completed successfully.\n";
?>
