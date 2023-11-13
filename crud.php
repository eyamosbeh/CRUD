<?php
error_log(print_r($_SERVER, true));

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "crud_db";

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    respondWithError("Database connection failed: " . $e->getMessage());
}

// Function to sanitize input data
function sanitize_input($data)
{
    global $conn;
    return htmlspecialchars(trim($data));
}

// Handle API requests
if ($_SERVER["REQUEST_METHOD"] === "GET") {
    if (isset($_GET['patients'])) {
        // Retrieve patients
        fetchAndRespond("SELECT * FROM patients");
    } elseif (isset($_GET['doctors'])) {
        // Retrieve doctors
        fetchAndRespond("SELECT * FROM doctors");
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Create or update a patient or doctor
    $data = json_decode(file_get_contents("php://input"), true);

    if (isset($_GET['patients'])) {
        handleCreateOrUpdate("patients", $data, [
            'name', 'age', 'email', 'phone'
        ]);
    } elseif (isset($_GET['doctors'])) {
        handleCreateOrUpdate("doctors", $data, [
            'name', 'specialization', 'email', 'phone'
        ]);
    }
}

if ($_SERVER["REQUEST_METHOD"] === "DELETE") {
    // Delete a patient or doctor
    $data = json_decode(file_get_contents("php://input"), true);

    if (isset($_GET['patients'])) {
        handleDelete("patients", $data, 'id');
    } elseif (isset($_GET['doctors'])) {
        handleDelete("doctors", $data, 'id');
    }
}

// If no API endpoint matched, return a 404 error
respondWithNotFound();

// Functions

function fetchAndRespond($query)
{
    global $conn;
    try {
        $stmt = $conn->query($query);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        respondWithJson($data);
    } catch (PDOException $e) {
        respondWithError("Database error: " . $e->getMessage());
    }
}

function handleCreateOrUpdate($table, $data, $fields)
{
    global $conn;
    foreach ($fields as $field) {
        if (!isset($data[$field])) {
            respondWithError("Missing required field: $field");
        }
    }

    $idField = $table === "patients" ? 'patient_id' : 'doctor_id';

    if (isset($data[$idField])) {
        // Update
        $id = sanitize_input($data[$idField]);
        $updateFields = array_map(function ($field) {
            return "$field=:$field";
        }, $fields);
        $updateFieldsString = implode(', ', $updateFields);
        $stmt = $conn->prepare("UPDATE $table SET $updateFieldsString WHERE $idField=:id");
    } else {
        // Insert
        $insertFields = implode(', ', $fields);
        $insertValues = ':' . implode(', :', $fields);
        $stmt = $conn->prepare("INSERT INTO $table ($insertFields) VALUES ($insertValues)");
    }

    foreach ($fields as $field) {
        $value = sanitize_input($data[$field]);
        $stmt->bindParam(":$field", $value);
    }

    if (isset($id)) {
        $stmt->bindParam(':id', $id);
    }

    try {
        $stmt->execute();
        respondWithJson(["message" => ucfirst($table) . " created/updated successfully"]);
    } catch (PDOException $e) {
        respondWithError("Database error: " . $e->getMessage());
    }
}

function handleDelete($table, $data, $idField)
{
    global $conn;
    if (!isset($data[$idField])) {
        respondWithError("Missing required field: $idField");
    }

    $id = sanitize_input($data[$idField]);

    $stmt = $conn->prepare("DELETE FROM $table WHERE $idField=:id");
    $stmt->bindParam(':id', $id);

    try {
        $stmt->execute();
        respondWithJson(["message" => ucfirst($table) . " deleted successfully"]);
    } catch (PDOException $e) {
        respondWithError("Database error: " . $e->getMessage());
    }
}

function respondWithJson($data)
{
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function respondWithError($error)
{
    respondWithJson(["error" => $error]);
}

function respondWithNotFound()
{
    http_response_code(404);
    respondWithError("Not Found");
}
?>
