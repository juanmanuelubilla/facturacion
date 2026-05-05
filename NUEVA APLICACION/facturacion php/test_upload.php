<?php
// Script simple para probar upload
echo "<h2>🧪 Test de Upload</h2>";

echo "<h3>POST Data:</h3>";
echo "<pre>";
print_r($_POST);
echo "</pre>";

echo "<h3>FILES Data:</h3>";
echo "<pre>";
print_r($_FILES);
echo "</pre>";

echo "<h3>Server Info:</h3>";
echo "<pre>";
echo "Request Method: " . $_SERVER['REQUEST_METHOD'] . "\n";
echo "Content Type: " . ($_SERVER['CONTENT_TYPE'] ?? 'No definido') . "\n";
echo "Content Length: " . ($_SERVER['CONTENT_LENGTH'] ?? 'No definido') . "\n";
echo "Max Upload Size: " . ini_get('upload_max_filesize') . "\n";
echo "Max POST Size: " . ini_get('post_max_size') . "\n";
echo "File Uploads: " . (ini_get('file_uploads') ? 'ON' : 'OFF') . "\n";
echo "Upload Temp Dir: " . ini_get('upload_tmp_dir') . "\n";
echo "</pre>";

if (isset($_FILES['imagen'])) {
    echo "<h3>Upload Details:</h3>";
    echo "<pre>";
    echo "Error Code: " . $_FILES['imagen']['error'] . "\n";
    echo "Error Message: ";
    switch ($_FILES['imagen']['error']) {
        case UPLOAD_ERR_OK:
            echo "No error, file uploaded successfully";
            break;
        case UPLOAD_ERR_INI_SIZE:
            echo "The uploaded file exceeds the upload_max_filesize directive in php.ini";
            break;
        case UPLOAD_ERR_FORM_SIZE:
            echo "The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form";
            break;
        case UPLOAD_ERR_PARTIAL:
            echo "The uploaded file was only partially uploaded";
            break;
        case UPLOAD_ERR_NO_FILE:
            echo "No file was uploaded";
            break;
        case UPLOAD_ERR_NO_TMP_DIR:
            echo "Missing a temporary folder";
            break;
        case UPLOAD_ERR_CANT_WRITE:
            echo "Failed to write file to disk";
            break;
        case UPLOAD_ERR_EXTENSION:
            echo "File upload stopped by extension";
            break;
        default:
            echo "Unknown upload error";
            break;
    }
    echo "</pre>";
}
?>

<form method="POST" enctype="multipart/form-data">
    <input type="hidden" name="accion" value="test">
    <input type="text" name="nombre" placeholder="Nombre de prueba" required><br><br>
    <input type="file" name="imagen" accept="image/*" required><br><br>
    <button type="submit">Test Upload</button>
</form>
