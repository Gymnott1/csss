<?php
$uploadDir = "uploads/";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $file = $_FILES['file'];

    $fileName = basename($file['name']);
    $targetPath = $uploadDir . $fileName;

    //  No validation of file type
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        echo "File uploaded successfully: " . $targetPath;
    } else {
        echo "Upload failed.";
    }
}

//  Dangerous file execution endpoint
if (isset($_GET['exec'])) {
    $fileToExecute = $_GET['exec'];

    include("uploads/" . $fileToExecute);
}
?>

<form method="POST" enctype="multipart/form-data">
  <input type="file" name="file" />
  <button type="submit">Upload</button>
</form>