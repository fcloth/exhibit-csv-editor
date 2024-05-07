<?php

$csvPlaylist = 'playlist.csv'; // CSV file used by Exhibit app

$file_handle = fopen($csvPlaylist, "r");
if ($file_handle) {
	while (($row = fgetcsv($file_handle)) !== false) {
		if (!$header) {
			$header = $row; // Store header row
		} else {
			$slideData[] = array_combine($header, $row);
		}
	}
	fclose($file_handle);
}
else {
	$_SESSION['errors'][] = "Error opening CSV file.";
}

// convert duration value into ms
foreach ($slideData as &$slide) {
	list($hours, $minutes, $seconds) = explode(":", $slide['Duration']);
	$totalSeconds = ($hours * 3600) + ($minutes * 60) + $seconds;
	$slide['Duration'] = $totalSeconds * 1000;
}
?>

<!DOCTYPE html>
<html>
<head>
  <title>Preview &#8250; Exhibit CSV Editor</title>
  	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<link rel="stylesheet" href="/main.css?<?php echo filemtime($_SERVER['DOCUMENT_ROOT'].'/main.css'); ?>" type="text/css">

	<style type="text/css">
		body {
			background-color: black;
		}
		
		#slideshow {
			position: fixed;
			top: 0;
			left: 0;
			width: 100%;
			height: 100%;
			background-color: black;
		}
		
		#slideshow img {
			width: 100%;
			height: 100%;
			object-fit: contain;
			position: absolute; /* Allow for stacking images */
			opacity: 0;          /* Start all images fully hidden */
			transition: opacity 1s ease-in-out;
		}

		#loader {
			position: absolute;
			top: 50%;
			left: 50%;
			transform: translate(-50%, -50%); /* Center the loader */ 
			font-size: 20px;
			color: #ddd;
		}		

		#counts {
			position: absolute;
			bottom: 20px;
			right: 20px;
			display: flex;
			gap: 20px		
		}
		
		#timer, #current-slide {
			color: rgba(255,255,255,0.8);
			font-family: var(--fixed-width-font-family);
			font-size: 24px;
			background-color: rgba(0, 0, 0, 0.5);
			padding: 10px;
			border-radius: 10px;
			z-index: 999;
		}
	</style>
</head>
<body>

<div id="slideshow">
	<div id="loader" style="display: block;">Loading...</div> 
	<div id="counts">
		<div id="current-slide">0/0</div> 
		<div id="timer">0:00</div> 
	</div>
</div>

<script>
let images = [
	<?php 
	$imageEntries = []; // Temporary array to hold the data
	foreach ($slideData as &$slide) {
	  $imageEntries[] = "{ src: '{$slide['URL']}', duration: {$slide['Duration']} }"; 
	}
	unset($slide);
	echo implode(",", $imageEntries); // Join elements with commas 
	?>
];
console.log(images);

let currentIndex = 0;
const slideshowContainer = document.getElementById('slideshow');
let countdownInterval = null; // Initialize interval variable

function showSlide() {
  const newImg = document.createElement('img');
  newImg.src = images[currentIndex].src;
  newImg.style.opacity = 0;
  slideshowContainer.appendChild(newImg);

  // Fade in the new image
  setTimeout(() => {
    newImg.style.opacity = 1;
  }, 0); 

  const currentSlideElement = document.getElementById('current-slide');
  currentSlideElement.textContent = `${currentIndex + 1}/${images.length}`;

  // Fade out the old image
  const oldSlide = slideshowContainer.querySelector('img:not(:last-child)');
  if (oldSlide) {
    setTimeout(() => { 
      oldSlide.style.opacity = 0;
    }, 200); 

    setTimeout(() => {
      slideshowContainer.removeChild(oldSlide);
    }, 2200); 
  }

  currentIndex = (currentIndex + 1) % images.length;

  // Reset and start the timer
  clearInterval(countdownInterval);
  startTimer();
}

function startTimer() {
  let remainingTime = images[currentIndex].duration; 
  const timerElement = document.getElementById('timer');

  countdownInterval = setInterval(() => {
    remainingTime -= 1000; // Subtract a second

    const minutes = Math.floor(remainingTime / 60000);
    let seconds = Math.floor((remainingTime % 60000) / 1000);

    seconds = seconds < 10 ? '0' + seconds : seconds; 
    timerElement.textContent = `${minutes}:${seconds}`;

    if (remainingTime <= 0) {
      clearInterval(countdownInterval); 
      showSlide(); 
    }
  }, 1000); 
}

document.addEventListener('keydown', handleKeyPress);

function handleKeyPress(event) {
  if (event.keyCode === 39) { // right arrow key
    showSlide(); // trigger a slide change and reset the timer
  }
  else if (event.keyCode === 37) { // left arrow key
	if (currentIndex === 1) { currentIndex = images.length - 1; } // if at start, loop back
	else if (currentIndex === 0) { currentIndex = images.length - 2; } // if at end, go back one
	else { currentIndex = (currentIndex - 2); } // otherwise go back normally
    showSlide();
  }
}

async function preloadImages(images) {
  const promises = images.slice(0, 3) // Example: initially preload only the first 3 images
  .map(async (imageData) => {
    const img = new Image();
    img.src = imageData.src;
    await img.decode(); 
    return img;
  });

  return await Promise.all(promises);
}

// intersection observer to lazy load more as needed
const intersectionObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            // Load image when in viewport (modify to fit your logic)
        } 
    });
});

// Initial Setup 
(async () => {
  document.getElementById('loader').style.display = 'block'; // Show loader

  await preloadImages(images); 
  showSlide(); // Start with the first slide

  document.getElementById('loader').style.display = 'none'; // Hide loader
  
  // listener for click on the image to advance the slide
  slideshowContainer.addEventListener('click', () => {
    showSlide(); // Advance the slide
  });
})();

// Close window when escape is pressed
document.addEventListener('keydown', function(event) {
    if (event.key === "Escape" || event.keyCode === 27) { // Check for both 'Escape' and legacy keyCode
        window.close();
    }
});
</script> 

</body>
</html>