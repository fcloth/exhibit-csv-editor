<?php
session_start();

$pagetitle = "Help &#8250; Exhibit CSV Editor";
?>
	
<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8" />
	<title><?= $pagetitle ?></title>
	<meta name="viewport" content="width=device-width, initial-scale=1" />

	<link rel="stylesheet" href="/main.css?<?php echo filemtime($_SERVER['DOCUMENT_ROOT'].'/main.css'); ?>" type="text/css">
	<style type="text/css">
		.how-does-this-work {
			max-width: 600px;
			padding: 40px 10px 10px 10px;
			margin: auto;
		}
	</style>
</head>

<html>
<body>
	<div class="how-does-this-work">
		<h2>How does this work?</h2>
		
		<p>This web application allows you to add or modify images in the slideshow running on the office&nbsp;televisions.</p>
		
		<p>The slideshow runs using an app called "Exhibit: Media Viewer," a screensaver app for Apple TV devices managed via MDM. It is installed on all Apple TVs in the office.</p>
		
		<p>To ensure the slideshow is always playing, some TVs are locked into the Exhibit app using Single App Mode. On other TVs, the user can open and close the app using the remote control.</p>
		
		<p>AirPlay screen mirroring works on all TVs whether or not the app is running.</p>
		
		<p>The Exhibit app is deployed and managed via Jamf. To modify the installation, disable Single App Mode, or adjust other settings, changes must be made by IT in Jamf.</p>
		
		<p>When images are added or removed using this tool, the changes are written to a CSV file on the web server. Exhibit checks that file for changes every 5 minutes. Then, the slide show will refresh, reflecting the latest changes.</p>
		
		<hr/>
		
		<p>Recommended image dimensions are 3840x2160 or&nbsp;1920x1080.</p>
		
		<p>Acceptable image formats are JPEG or PNG with a maximum file size of 6&nbsp;MB each.</p>
		
		<p><a href="/">&#x2039; Back to Exhibit CSV Editor</a></p>
	</div>
</body>
</html>