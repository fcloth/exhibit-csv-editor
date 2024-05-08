<?php
session_start();

$pageTitle = "Exhibit CSV Editor";

$siteBaseURL = 'exhibit.example.com:8890';

// Verify write permissions set correctly for files and directories named here
$csvFile = 'playlist-allslides.csv'; // CSV file containing all images including hidden
$csvLiveFile = 'playlist.csv'; // CSV file used by Exhibit app
$slidesPath = 'slides/'; // location of slide images

// Functions
function writeArrayToCSV($array, $file) {
	$fp = fopen($file, 'w'); // open file for writing
	
	$header_row = array_keys($array[0]); // header row (assumes all inner arrays have the same keys, and array has been reindexed to remove empty slots)
	fwrite($fp, implode(',', $header_row) . "\n");

	$count = count($array);
	$i = 0;
	foreach ($array as $row) { // add data rows
		$csv_line = implode(',', array_values($row)); // create CSV line (avoiding fputcsv because Exhibit fails with quotation marks in CSV)
		if (++$i !== $count) {
			$csv_line .= "\n"; // insert new line, except on final row
		}
		fwrite($fp, $csv_line);
	}
	fclose($fp); // close the file		
}

function hideUniqid($text) {
	return preg_replace('/_uniqid.*?(?=\.)/', '', $text);
}

// Build array using the CSV file
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

// If form is posted, do this
if($_SERVER["REQUEST_METHOD"] == "POST" AND isset($_POST['action'])) {
	$data = explode(",", $_POST['action']); // split on comma
	$action = $data[0];
	$secondaryAction = $data[1];
	
	switch ($action) {
		case 'upload':
			$errors = [];
			$_SESSION['uploadedFiles'] = [];
			
			$allowedExtensions = ['jpg', 'jpeg', 'png']; 
			$maxFileSize = 6 * 1024 * 1024; // 3MB
			$maxImageWidth = 3840;
			$maxImageHeight = 2160;
							
			$numberOfFiles = count($_FILES['files']['name']); 
			$fileInfos = array();
			for ($i = 0; $i < $numberOfFiles; $i++) {
				$fileInfos[] = array(
					'name' => $_FILES['files']['name'][$i],
					'size' => $_FILES['files']['size'][$i],
					'tmp_name' => $_FILES['files']['tmp_name'][$i],
					'type' => $_FILES['files']['type'][$i],
					'error' => $_FILES['files']['error'][$i]
				);
			}

			$uploadedSlideDuration = $_POST['duration'];
			$uploadedSlideOrder = $_POST['order'];
			
			if ($uploadedSlideOrder == 'start') {
				$fileInfos = array_reverse($fileInfos); // reverse order if appending to start
			}	
				
			foreach ($fileInfos as $fileInfo) {
				$fileExtension = strtolower(pathinfo($fileInfo['name'], PATHINFO_EXTENSION));
				
				if ($fileInfo['error'] == 4) { // UPLOAD_ERR_NO_FILE
					$_SESSION['errors'][] = "No files were selected for upload.";
					continue;
				}
				
				if (!in_array($fileExtension, $allowedExtensions)) {
					$_SESSION['errors'][] = "File {$fileInfo['name']} has an invalid extension.";
					continue;
				}
			
				if ($fileInfo['size'] > $maxFileSize) {
					$_SESSION['errors'][] = "File {$fileInfo['name']} exceeds the maximum size (6 MB).";
					continue;
				}
				
				$imageDims = getimagesize($fileInfo['tmp_name']);
				if ($imageDims[0] > $maxImageWidth OR $imageDims[1] > $maxImageHeight) {
					$_SESSION['errors'][] = "File {$fileInfo['name']} exceeds the maximum dimensions ({$maxImageWidth} x {$maxImageHeight}.)";
					continue;
				}
				
				// Sanitize file name
				$sanitizedFileName = trim(pathinfo($fileInfo['name'], PATHINFO_FILENAME));
				$sanitizedFileName = strtolower($sanitizedFileName);
		
				$pattern = array(' ', '.', '(', ')', '#', '$', '%', '/', '\\');
				$replace = array('_', '-', '', '', '', '', '', '', '');
				$sanitizedFileName = str_replace($pattern, $replace, $sanitizedFileName);
				
				// Make unique filename
				$uniqueFileName = $sanitizedFileName . '_uniqid' . uniqid() . '.' . strtolower(pathinfo($fileInfo['name'], PATHINFO_EXTENSION));

				if (move_uploaded_file($fileInfo['tmp_name'], $slidesPath . $uniqueFileName)) {
					$_SESSION['uploadedFiles'][] = $uniqueFileName;
					
					// add values to csvArray
					$newChildArray = array(
						"Name" => $uniqueFileName,
						"URL" => "https://".$siteBaseURL."/". $slidesPath . $uniqueFileName,
						"Duration" => "0:00:".$uploadedSlideDuration,
						"UpdateInterval" => "1:00:00",
						"StartOn" => "1/1/24 0:00",
						"EndBy" => "12/31/44 23:59",
						"Cache" => "yes",
						"Visibility" => "show"
					);
					if ($uploadedSlideOrder == 'start' AND !empty($csvArray)) {
						array_unshift($csvArray, $newChildArray); // prepend assuming $csvArray is not empty
					}
					else { $csvArray[] = $newChildArray; }
					
				} // if move_uploaded_file
				else {
					$_SESSION['errors'][] = $fileInfo['name'];
				}
			} // for
			break;
			
		case 'delete':
			$selectedItems = $_POST['selected_items'] ?? [];
			foreach ($selectedItems as $item) {
				unlink($slidesPath . $item); // delete image

				foreach ($csvArray as $key => $child) {
					if ($child['Name'] === $item) {
						unset($csvArray[$key]); // drop from array
						$count += 1;
					}
				}
				$csvArray = array_values($csvArray); // re-index array
				$_SESSION['message'] = "Deleted {$count} slide(s).";
			}
			break;
			
		case 'hide':
		case 'show':
			$selectedItems = $_POST['selected_items'] ?? [];
			$visibility = ($action == 'show') ? 'visible' : 'hidden';
			foreach ($selectedItems as $item) {
				foreach ($csvArray as $key => &$child) {
					if ($child['Name'] === $item) {
						$child['Visibility'] = $visibility;
						$count += 1;
					}
				}
				$_SESSION['message'] = "{$count} slide(s) set to {$visibility}.";
			}
			break;				
			
		case 'set':
			$selectedItems = $_POST['selected_items'] ?? [];
			$changeDurationTo = $_POST['duration'];
			
			foreach ($selectedItems as $item) {
				foreach ($csvArray as $key => &$child) {
					if ($child['Name'] === $item) {
						$child['Duration'] = '0:00:'.$changeDurationTo;
						$count += 1;
					}
				}
				$_SESSION['message'] = "Duration set to {$changeDurationTo}&thinsp;s for {$count} slide(s).";
			}
			break;
			
		case 'sort':
			$filenameColumn = array_column($csvArray, 'Name');
			array_multisort($filenameColumn, SORT_ASC, $csvArray);
			$_SESSION['message'] = "Sorted all slides alphabetically.";
			break;
		
		case 'shuffle':
			shuffle($csvArray);
			$_SESSION['message'] = "Slide order shuffled.";
			break;
			
		case 'move':
			$selectedItems = $_POST['selected_items'] ?? [];
			$direction = $secondaryAction;
			
			switch ($direction) {
				case 'first':
				case 'last':
					foreach (array_reverse($selectedItems) as $item) { // processes each item from last to first, keeping same relative order for selected items
						foreach ($csvArray as $key => $child) {
							if ($child['Name'] === $item) {
								$value = $csvArray[$key];
								unset($csvArray[$key]); // drop from array
								if ($direction == 'first') {
									array_unshift($csvArray, $value); // prepend to front of array
								}
								elseif ($direction == 'last') {
									$csvArray[$key] = $value; // add to end of array
								}
							}
						}
					}
					break;	
				case 'up':
				case 'down':
					if ($direction === 'down') {
						$selectedItems = array_reverse($selectedItems); // reverse for 'down'
					}

					// Iterate through items to move
					foreach ($selectedItems as $nameToMove) {
						$keyToMove = null;
						foreach ($csvArray as $key => $item) {
							if ($item['Name'] === $nameToMove) {
								$keyToMove = $key;
								break; 
							}
						}
				
						if ($keyToMove !== null) {
							$keys = array_keys($csvArray);
							$currentIndex = array_search($keyToMove, $keys);
				
							if ($direction === 'up' && $currentIndex > 0) {
								$newIndex = $currentIndex - 1;
							} elseif ($direction === 'down' && $currentIndex < count($keys) - 1) {
								$newIndex = $currentIndex + 1;
							} else {
								continue; // Skip to the next item
							}
				
							// Perform the swap 
							$temp = $csvArray[$keys[$newIndex]];
							$csvArray[$keys[$newIndex]] = $csvArray[$keyToMove];
							$csvArray[$keyToMove] = $temp;
						} else {
							$_SESSION['errors'][] = "File {$nameToMove} not found.";
						}
					}
					break;
				} // switch for $direction
			break;
		
		case 'download':
			require_once('/download.php');
			break;
		default:
			echo "Invalid action";
			break;
	} // switch


	// duplicate array for filtering
	$csvArrayFiltered = $csvArray;
	
	// remove all hidden slides from filtered array
	foreach ($csvArrayFiltered as $key => &$subArray) {
		if (isset($subArray['Visibility']) && $subArray['Visibility'] === 'hidden') {
			unset($csvArrayFiltered[$key]);  // remove the child array
		}
	}
	$csvArrayFiltered = array_values($csvArrayFiltered); // re-index array
				
	// drop 'Visibility' key entirely from array for writing to live playlist file
	$csvArrayFiltered = array_map(function($childArray) {
		unset($childArray['Visibility']);
		return $childArray;
	}, $csvArrayFiltered);

	// write array to CSV files
	writeArrayToCSV($csvArray, $csvFile); // write complete file including custom Visibility key
	writeArrayToCSV($csvArrayFiltered, $csvLiveFile); // write filtered file for Exhibit to read
	
	// redirect to self to allow page refresh to work
	$referer = $_SERVER['HTTP_REFERER'];
	header('Location: '.$referer);
	exit();
} // if form post
?>
	
<!DOCTYPE html>
<html lang="en">

<head>	
	<meta charset="UTF-8" />
	<title><?= $pageTitle ?></title>
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
	<meta name='robots' content='noindex,follow' />

	<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
	<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
	<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
	<link rel="manifest" href="/site.webmanifest">

	<link rel="stylesheet" href="/main.css?<?php echo filemtime($_SERVER['DOCUMENT_ROOT'].'/main.css'); ?>" type="text/css">
</head>
	
<body>
	<div id="upload-background-blur"></div>
	<div id="upload-form-box">
		<div id="upload-form-title">Upload image(s)</div>
			<div id="upload-form-body">			
				<form action="" method="post" enctype="multipart/form-data">	
					<input type="file" id="upload-files" name="files[]" multiple>
					
					<div class="upload-form-options">
						<label for="upload-duration">Duration: </label>
						<select id="upload-duration" name="duration">
							<option value="10">10 seconds</option>		
							<option value="15">15 seconds</option>
							<option value="20" selected>20 seconds</option>		
							<option value="30">30 seconds</option>
							<option value="45">45 seconds</option>		
						</select>
						
						<label for="upload-order">Add to: </label>
						<select id="upload-order" name="order">
							<option value="start" selected>Beginning</option>		
							<option value="end">End</option>	
						</select>
					</div>
										
					<button type="submit" name="action" value="upload">Upload</button>
				</form>
			</div>
		<div id="upload-form-details">
			<ul>
				<li>Max file size: <span class="emphasis">6&nbsp;MB</span></li>
				<li>Supported formats: <span class="emphasis">JPG</span>, <span class="emphasis">PNG</span></li>
				<li>Resolution: <span class="emphasis">1920x1080</span> or <span class="emphasis">3840x2160</span></li>
			</ul>
		</div>			
	</div>

	<?php
	echo '<div id="confirmation-messages">';
		// echo successes
		if (!empty($_SESSION['uploadedFiles'])) {
			echo '<div class="upload-success-message">';
				echo 'Successfully uploaded ' . count($_SESSION["uploadedFiles"]) . ' file(s).';
				unset($_SESSION['uploadedFiles']);
			echo '</div>';
		}
		
		if (!empty($_SESSION['message'])) {
			echo '<div class="good-message">';
				echo $_SESSION['message'];
				unset($_SESSION['message']);
			echo '</div>';
		}
	
		// echo errors
		if (!empty($_SESSION['errors'])) {
			echo '<div class="error-message">';
				foreach ($_SESSION['errors'] as $error) {
					echo $error . '<br>';
				}
				unset($_SESSION['errors']);
		echo '</div>';
		}
	echo '</div>';
	?>

	<div id="wrapper">
		<div id="header">
			<div class="header-col-1">
				<div id="environment-type" class="live">&#x25cf;&nbsp;Live</div>
			</div>
			<div class="header-col-2">
				<h1><img src="/images/logo.png" />Exhibit CSV Editor</h1>
			</div>
			<div class="header-col-3">
				<?php echo '<a href="/slideshow.php" target="_blank" id="preview-link"><svg width="1080" height="1080" viewBox="0 0 1080 1080" xmlns="http://www.w3.org/2000/svg"><path id="Triangle" stroke="none" d="M 969.897034 540 L 149.102936 67 L 149.102936 1013 Z"/></svg>Preview</a>'; ?>
			</div>
		</div>

		<?php
		echo '<form method="post" action="" id="slides-form">';
			echo '<div class="control-ribbon">';
				echo '<div class="ribbon-col-1">';
					echo '<div class="button-group">';
						echo '<div class="narrow-hide">File</div>';
						echo '<div>';
							echo '<button type="submit" name="action" class="toggleable-button" value="delete" onclick="return confirm(\'Are you sure you want to delete the selected slide(s)? This cannot be undone.\')" disabled>Delete</button>';
							echo '<button type="submit" name="action" class="toggleable-button segmented-button segmented-first" value="hide" disabled>Hide</button>';
							echo '<button type="submit" name="action" class="toggleable-button segmented-button segmented-last" value="show" disabled>Show</button>';
						echo '</div>';
					echo '</div>';

					echo '<div class="button-group narrow-hide">';
						echo '<div>Duration</div>';
						echo '<div>';
							echo '<select id="set-duration-select" name="duration" class="toggleable-button" disabled>';
								echo '<option value="">&nbsp;&bull;&bull;&bull;</option>';
								echo '<option value="10">10 sec</option>';
								echo '<option value="15">15 sec</option>';
								echo '<option value="20">20 sec</option>';
								echo '<option value="30">30 sec</option>';
								echo '<option value="45">45 sec</option>';
							echo '</select>';
							echo '<button type="submit" id="set-duration-button" name="action" value="set" disabled>Set</button>';
						echo '</div>';
					echo '</div>';

					echo '<div class="button-group narrow-hide" style="justify-self: center;">';
						echo '<div>Move</div>';
						echo '<div>';
							echo '<button type="submit" name="action" class="toggleable-button segmented-button segmented-first" value="move,first" disabled>First</button>';
							echo '<button type="submit" name="action" class="toggleable-button segmented-button narrow-hide-first" value="move,up" disabled>&larr;</button>';
							echo '<button type="submit" name="action" class="toggleable-button segmented-button narrow-hide-first" value="move,down" disabled>&rarr;</button>';
							echo '<button type="submit" name="action" class="toggleable-button segmented-button segmented-last" value="move,last" disabled>Last</button>';
						echo '</div>';
					echo '</div>';
				echo '</div>';
				echo '<div class="ribbon-col-2">';
					echo '<a onclick="#" id="show-upload-button">Add slides</a>';
				echo '</div>';
				echo '<div class="ribbon-col-3">';
					echo '<div class="button-group">';
						echo '<div class="narrow-hide">All slides</div>';
						echo '<div>';
							echo '<button type="button" class="" onclick="checkAll()">Select All</button>';
							echo '<button type="submit" name="action" value="shuffle" class="narrow-hide" onclick="return confirm(\'Are you sure you want to shuffle the order of all slides? This cannot be undone.\')">Shuffle</button>';
							echo '<button type="submit" name="action" value="sort" class="narrow-hide" onclick="return confirm(\'Are you sure you want to alphabetically sort all slides? This cannot be undone.\')">Sort</button>';
						echo '</div>';
					echo '</div>';
				echo '</div>';
			echo '</div>';

			echo '<div id="slide-container">';	
				if (count($csvArray) > 0) {	
					foreach ($csvArray as $slide) {
						$sanitizedUrl = htmlspecialchars($slide['URL']);
						echo '<label><div class="slide-div'.(($slide['Visibility']=='hidden')?' slide-hidden':"").'" style="background-image: url(\'' . $sanitizedUrl . '\');">';
			
							echo '<input type="checkbox" name="selected_items[]" class="slide-checkbox" value="'.$slide['Name'].'">';

							echo '<div class="slide-details slide-details-duration">';
								// slide duration
								list($hours, $minutes, $seconds) = explode(":", $slide['Duration']);
								if ($minutes != '00') { echo $minutes.'m '; }
								echo $seconds.'&thinsp;s';
							echo '</div>'; // slide-details					
							echo '<div class="slide-details slide-details-filename">';
								// filename from URL
								$prettyFilename = hideUniqid(basename(parse_url($slide['URL'], PHP_URL_PATH)));
								echo $prettyFilename;
							echo '</div>'; // slide-details
						echo '</div></label>'; // slide-div
					} // foreach
				}
			else {
				echo '<div id="no-slides-found">No slides found</div>';
			}
			echo '</div>'; // slide-container
		echo '</form>';	

	
		echo '<div id="items-selected-box">';
			echo '<a onclick="uncheckAll();"><svg version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px"	 viewBox="0 0 1080 1080" style="enable-background:new 0 0 1080 1080;" xml:space="preserve"><g id="X" class="st0"><path class="st1" d="M929,151L929,151c-24.99-24.99-65.52-24.99-90.51,0L151,838.49c-24.99,24.99-24.99,65.52,0,90.51l0,0		c24.99,24.99,65.52,24.99,90.51,0L929,241.51C953.99,216.52,953.99,175.99,929,151z"/>	<path class="st1" d="M151,151L151,151c-24.99,24.99-24.99,65.52,0,90.51L838.49,929c24.99,24.99,65.52,24.99,90.51,0l0,0 c24.99-24.99,24.99-65.52,0-90.51L241.51,151C216.52,126.01,175.99,126.01,151,151z"/></g></svg></a>';
			echo '<span>x slide(s) selected</span>';
		echo '</div>';
	
		echo '<div class="footer">';
			echo '<div class="footer-col-1">';
				echo '<a href="/help.php" id="help-link">Help</a>';
			echo '</div>';
			echo '<div class="footer-col-2">';
				$slidesHidden = 0;
				foreach ($csvArray as $item) {
					if ($item['Visibility'] === 'hidden') {
						$slidesHidden++;
					}
				}
				$slidesVisible = count($csvArray) - $slidesHidden;
				
				echo '<b>'.$slidesVisible.'&nbsp;slides', $slidesHidden > 0? " ({$slidesHidden}&nbsp;hidden)" : "",'</b><br/>';
				
				$totalSeconds = 0;
				foreach ($csvArray as $slide) {
					if ($slide['Visibility'] != 'hidden') {
						list($hours, $minutes, $seconds) = explode(":", $slide['Duration']);
						$totalSeconds += ($hours * 3600) + ($minutes * 60) + $seconds;
					}
				}
				
				if ($totalSeconds >= 3600) {
					echo floor($totalSeconds / 3600) .'&nbsp;hr&nbsp;'; // echo hours
				}
				if ($totalSeconds >= 60) {
					echo floor(($totalSeconds / 60) % 60) .'&nbsp;min&nbsp;'; // echo minutes
				}
				if ($totalSeconds % 60 != 0) {
					echo $totalSeconds % 60 . '&nbsp;sec'; // echo seconds remainder
				}
			echo '</div>';
			echo '<div class="footer-col-3">';
				echo '<form method="post" action="/download.php">';
					echo '<input type="hidden" name="action" value="download">';
					echo '<a href="download" onclick="this.parentNode.submit(); return false;" id="download-link"><svg id="Layer_1" data-name="Layer 1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1080 1080"><g id="Top"><path class="cls-1" d="M138.87,1010.88c0,38.03,31.09,69.12,69.12,69.12h663.41c38.03,0,69.12-31.09,69.12-69.12s-31.09-69.12-69.12-69.12h-290.28l325.34-325.33c27-27,27-70.77,0-97.71h0c-27-27-70.77-27-97.71,0l-199.57,199.57V63.27c0-35.39-28.65-64.04-64.04-64.04s-64.04,28.65-64.04,64.04v655.02l-199.57-199.57c-27-27-70.77-27-97.71,0h0c-27,27-27,70.77,0,97.71l325.34,325.33H207.92c-37.95,0-69.05,31.09-69.05,69.12Z"/></g></svg>Download</a>';
				echo '</form>';
			echo '</div>';
		echo '</div>';
		?>
	</div> <!-- wrapper -->


<script>
	// fade out messages
	const alertDiv = document.getElementById('confirmation-messages');
	if (alertDiv) { 
		setTimeout(function() {
			alertDiv.style.transition = "opacity 1s ease-out"; // Transition for a smooth fade
			alertDiv.style.opacity = 0; 
		}, 5000); // 1000 milliseconds = 1 second
		setTimeout(function() {
			alertDiv.style.display = 'none';
		}, 6000);
	}
	
	const checkboxContainer = document.querySelector('#slide-container');
	const checkboxes = document.querySelectorAll('#slides-form .slide-checkbox');

	const slideEditButtons = document.querySelectorAll('#slides-form .toggleable-button');
	const slideEditSelect = document.querySelectorAll('#slides-form select');
	const setDurationSelect = document.getElementById("set-duration-select");
	const setDurationButton = document.getElementById("set-duration-button");	

	const slideDivs = document.querySelectorAll('.slide-div');
	const slideDivsHidden = document.querySelectorAll('.slide-hidden');

	const itemsSelectedBox = document.querySelector('#items-selected-box');
	const itemsSelectedSpan = itemsSelectedBox.querySelector('span');

	function updateButtonAndSelectState() {
		const isChecked = Array.from(checkboxContainer.querySelectorAll('input[type="checkbox"]')).some(checkbox => checkbox.checked);
		const checkedCount = Array.from(checkboxContainer.querySelectorAll('input[type="checkbox"]')).filter(checkbox => checkbox.checked).length;

		slideEditButtons.forEach(button => button.disabled = !isChecked);
		slideEditSelect.forEach(button => button.disabled = !isChecked);

		if (isChecked) { itemsSelectedBox.classList.add('show'); }
		else {
			itemsSelectedBox.classList.remove('show');
			setDurationSelect.value = "";
			setDurationButton.disabled = true;
		}
		itemsSelectedSpan.textContent = `${checkedCount} ${checkedCount === 1 ? 'slide' : 'slides'} selected`;
	}

	function checkAll() {
		checkboxes.forEach(checkbox => {
			checkbox.checked = true;		
		});
		updateButtonAndSelectState();
		slideDivs.forEach(function(slideDiv) {
			slideDiv.classList.add('slide-checked');
		});
	}
	
	function uncheckAll() {
		checkboxes.forEach(checkbox => {
			checkbox.checked = false;
		});
		updateButtonAndSelectState();
		slideDivs.forEach(function(slideDiv) {
			slideDiv.classList.remove('slide-checked');
		});
	}
	
	checkboxContainer.addEventListener('change', updateButtonAndSelectState);

	// handle duration dropdown
	setDurationSelect.addEventListener("change", function() {
		if (setDurationSelect.value !== "") {
			setDurationButton.disabled = false;
		} else {
			setDurationButton.disabled = true;
		}
	});
	
	// additional styling when slide checkbox is checked
	checkboxes.forEach(checkbox => {
		checkbox.addEventListener('change', function() {
			const parentElement = this.closest('.slide-div');
			
			if (this.checked) {
				parentElement.classList.add('slide-checked');
			} else {
				parentElement.classList.remove('slide-checked');
			}
		});
	});

	// shift click range of slides
	let lastChecked = null;
	checkboxes.forEach(checkbox => {
		checkbox.addEventListener('click', (event) => {
			if (event.shiftKey && lastChecked) { 
				// Shift-click range selection
				let inBetween = false;
				checkboxes.forEach(box => {
					if (box === checkbox || box === lastChecked) {
						inBetween = !inBetween;
					}
					if (inBetween) {
						box.checked = true;
						box.parentNode.classList.add('slide-checked'); //
					}
				});
			}
			lastChecked = checkbox;
		});
	});

	// upload box
	const showButton = document.getElementById('show-upload-button');
	const hiddenDiv = document.getElementById('upload-form-box');
	const backdropDiv = document.getElementById('upload-background-blur');
	
	showButton.addEventListener('click', () => {
	   hiddenDiv.classList.toggle('show');
	   backdropDiv.classList.toggle('show');
	   uncheckAll();
	}); 
	
	document.addEventListener('click', (event) => {
	  if (event.target !== showButton && !hiddenDiv.contains(event.target)) {
		hiddenDiv.classList.remove('show');
		backdropDiv.classList.remove('show');
	  }
	});

	document.addEventListener('keydown', function(event) {
		if (event.key === "Escape") {
			hiddenDiv.classList.remove('show');
			backdropDiv.classList.remove('show');
			uncheckAll();
		}
	});
</script>

</body>
</html>