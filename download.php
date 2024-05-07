<?php

$csvFile = 'playlist-allslides.csv';

$file_handle = fopen($csvFile, "r");
if ($file_handle) {
	while (($row = fgetcsv($file_handle)) !== false) {
		if (!$header) {
			$header = $row; // Store header row
		} else {
			$csvArray[] = array_combine($header, $row);
		}
	}
	fclose($file_handle);
}
else {
	$_SESSION['errors'][] = "Error opening CSV file.";
}

if($_SERVER["REQUEST_METHOD"] == "POST" AND isset($_POST['action'])) {
	$action = $_POST['action'];
	switch ($action) {
		case 'download':
			download_images_from_array($csvArray);
		break;
	} // switch
}
else {
	echo 'Invalid request.';
	exit;
}

function hideUniqid($text) {
	return preg_replace('/_uniqid.*?(?=\.)/', '', $text);
}

function download_images_from_array($imageArray, $zipFilename = 'images.zip') {
    // Error handling (modify as needed)
    if (empty($imageArray)) { 
        echo 'Image array empty.';
        return false; 
    }

    // Create a temporary directory for downloaded images
    $tmp_dir = '/var/www/tmp/' . uniqid('image_download_');
    mkdir($tmp_dir);

	// Download images
    foreach ($imageArray as $image) {
        $url = $image['URL']; // extract URL, assumes "URL" key in 2D assoc array        
        $filename = basename($url);
        $filename = hideUniqid($filename);
        $originalFilename = $filename; // Store original filename
        
        // Check for file name collisions in the tmp directory
        $i = 0;
        while (file_exists($tmp_dir . '/' . $filename)) {
			$i++;
			$filename = pathinfo($originalFilename, PATHINFO_FILENAME).'-'.$i.'.'.pathinfo($originalFilename, PATHINFO_EXTENSION); 
		}

		$filepath = $tmp_dir . '/' . $filename;
        file_put_contents($filepath, file_get_contents($url));
    }

    // Create a zip archive
    $zip = new ZipArchive();
    $zip->open($tmp_dir . '/' . $zipFilename, ZipArchive::CREATE); 

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp_dir));
    foreach ($files as $file) {
        if (!$file->isDir()){
            $zip->addFile($file->getRealPath(), str_replace($tmp_dir . '/', '', $file->getPathname()));
        }
    }
    $zip->close();

    // Send the zip file to the browser for download
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $zipFilename . '"');
    readfile($tmp_dir . '/' . $zipFilename);  

    // Cleanup (delete temporary directory and zip file)
    delete_directory($tmp_dir); 
    unlink($tmp_dir . '/' . $zipFilename);

    return true;
}

function delete_directory($dir) { // Helper function to delete directories
    $files = array_diff(scandir($dir), array('.','..'));
    foreach ($files as $file) {
        (is_dir("$dir/$file")) ? delete_directory("$dir/$file") : unlink("$dir/$file");
    }
    return rmdir($dir);
}
?>