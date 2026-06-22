<?php
/**
 * =============================================
 * Database Connection File - EduHack AI
 * =============================================
 * 
 * This file establishes a secure connection to the MySQL database
 * using MySQLi procedural approach.
 * 
 * @package EduHack AI
 * @version 1.0
 * @author EduHack Team
 */

// =============================================
// 1. DATABASE CONFIGURATION
// =============================================

// Database server hostname (usually 'localhost' for local development)
$host = 'localhost';

// Database username (default is 'root' for XAMPP/WAMP)
$username = 'root';

// Database password (empty by default for local development)
$password = '';

// Database name (must match the one created in phpMyAdmin)
$database = 'eduhack_ai';

// =============================================
// 2. ESTABLISH DATABASE CONNECTION
// =============================================

/**
 * Create connection using MySQLi (Improved MySQL extension)
 * 
 * This is the preferred method for connecting to MySQL in PHP
 * as it provides better security and features compared to
 * the older mysql_* functions.
 */
$conn = mysqli_connect($host, $username, $password, $database);

// =============================================
// 3. CHECK CONNECTION
// =============================================

/**
 * Verify if the connection was successful.
 * If connection fails, display a user-friendly error message
 * and stop script execution.
 */
if (!$conn) {
    /**
     * For development: Show detailed error
     * For production: Show generic error to avoid exposing sensitive info
     */
    
    // Development mode (show detailed error)
    $error_message = "Connection failed: " . mysqli_connect_error();
    
    // Uncomment below line for production (hide database details)
    // $error_message = "Database connection failed. Please try again later.";
    
    // Display error message with HTML formatting
    echo "<!DOCTYPE html>";
    echo "<html>";
    echo "<head>";
    echo "<title>Database Connection Error</title>";
    echo "<style>
            body { font-family: Arial, sans-serif; background: #f4f4f4; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
            .error-container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); max-width: 500px; text-align: center; }
            .error-icon { font-size: 50px; color: #dc3545; margin-bottom: 20px; }
            .error-title { color: #dc3545; margin-bottom: 10px; }
            .error-message { color: #666; margin-bottom: 20px; }
            .error-detail { background: #f8f9fa; padding: 10px; border-radius: 5px; font-size: 14px; color: #666; }
            .btn { display: inline-block; background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-top: 15px; }
          </style>";
    echo "</head>";
    echo "<body>";
    echo "<div class='error-container'>";
    echo "<div class='error-icon'>⚠️</div>";
    echo "<h2 class='error-title'>Database Connection Error</h2>";
    echo "<p class='error-message'>Unable to connect to the database. Please check your configuration.</p>";
    echo "<div class='error-detail'>";
    echo "<strong>Error:</strong> " . mysqli_connect_error();
    echo "</div>";
    echo "<a href='javascript:location.reload()' class='btn'>Try Again</a>";
    echo "</div>";
    echo "</body>";
    echo "</html>";
    
    // Stop script execution
    die();
}

// =============================================
// 4. SET CHARACTER ENCODING
// =============================================

/**
 * Set UTF-8 character set for proper handling of
 * special characters, emojis, and multilingual content.
 * 
 * This ensures that data stored/retrieved from the database
 * maintains correct encoding.
 */
if (!mysqli_set_charset($conn, "utf8mb4")) {
    /**
     * UTF-8 is the preferred charset for modern web applications.
     * utf8mb4 supports 4-byte characters (emojis, special symbols)
     */
    error_log("Error setting character set: " . mysqli_error($conn));
    // Continue execution even if charset fails - but log the error
}

// =============================================
// 5. SUCCESS MESSAGE (Optional - for debugging)
// =============================================

/**
 * Development debugging: Uncomment the line below
 * to verify connection is successful.
 * Comment or remove in production.
 */
// echo "✅ Database connected successfully!";

// =============================================
// 6. ADDITIONAL CONFIGURATION (Optional)
// =============================================

/**
 * Set timezone to match your location
 * This helps with accurate timestamp handling
 */
date_default_timezone_set('Asia/Kolkata'); // Change to your timezone

/**
 * Set error reporting for development
 * In production, set error_reporting(0);
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

// =============================================
// 7. USAGE EXAMPLE
// =============================================

/**
 * How to use this file in other PHP scripts:
 * 
 * // Include this file at the top of your PHP scripts
 * require_once 'includes/db.php';
 * 
 * // Now you can use $conn variable for database operations
 * $sql = "SELECT * FROM users WHERE role = 'student'";
 * $result = mysqli_query($conn, $sql);
 * 
 * // Don't forget to close connection when done
 * // mysqli_close($conn);
 */

// =============================================
// 8. HELPER FUNCTIONS (Optional - for convenience)
// =============================================

/**
 * Function to safely escape user input
 * 
 * @param string $value The input string to escape
 * @return string Escaped string safe for database insertion
 */
function escapeString($value) {
    global $conn;
    return mysqli_real_escape_string($conn, trim($value));
}

/**
 * Function to get last inserted ID
 * 
 * @return int The last auto-generated ID
 */
function getLastInsertId() {
    global $conn;
    return mysqli_insert_id($conn);
}

/**
 * Function to check if table exists
 * 
 * @param string $tableName Name of the table to check
 * @return bool True if table exists, false otherwise
 */
function tableExists($tableName) {
    global $conn;
    $sql = "SHOW TABLES LIKE '$tableName'";
    $result = mysqli_query($conn, $sql);
    return mysqli_num_rows($result) > 0;
}

function columnExists($tableName, $columnName) {
    global $conn;
    $tableNameEscaped = mysqli_real_escape_string($conn, $tableName);
    $columnNameEscaped = mysqli_real_escape_string($conn, $columnName);
    $sql = "SHOW COLUMNS FROM `$tableNameEscaped` LIKE '$columnNameEscaped'";
    $result = mysqli_query($conn, $sql);
    return $result && mysqli_num_rows($result) > 0;
}

// =============================================
// 9. SECURITY BEST PRACTICES
// =============================================

/**
 * SECURITY NOTE:
 * 
 * 1. Never commit this file with real credentials to version control
 * 2. In production, use environment variables for sensitive data
 * 3. Always use prepared statements for SQL queries (to prevent SQL injection)
 * 4. Use HTTPS in production
 * 5. Regularly update PHP and MySQL versions
 */

// =============================================
// 10. CONNECTION TEST (Optional)
// =============================================

/**
 * Uncomment the code below to test the connection
 * and display database information
 */
/*
echo "<h3>Database Connection Status</h3>";
echo "✅ Connected successfully to database: <strong>" . $database . "</strong><br>";
echo "Server Info: " . mysqli_get_server_info($conn) . "<br>";
echo "MySQL Client Version: " . mysqli_get_client_info() . "<br>";
echo "Connected to: " . mysqli_get_host_info($conn) . "<br>";
*/

// =============================================
// END OF CONNECTION FILE
// =============================================
?>