<?php
header('Content-Type: application/json');

// Configuration
define('GALLERY_DIR', __DIR__ . '/gallery');
define('IMAGES_PER_PAGE', 20);
define('CONFIG_FILE', __DIR__ . '/config.ini');
define('THUMBNAIL_WIDTH', 400);
define('THUMBNAIL_HEIGHT', 400);

// Helper functions
function getConfig() {
    if (file_exists(CONFIG_FILE)) {
        return parse_ini_file(CONFIG_FILE, true);
    }
    return ['gallery' => ['title' => 'Photo Gallery']];
}

function ensureThumbnailDir($albumPath) {
    $thumbDir = $albumPath . '/thumbnails';
    if (!file_exists($thumbDir)) {
        mkdir($thumbDir, 0755, true);
    }
    return $thumbDir;
}

function createThumbnail($sourcePath, $albumPath) {
    $thumbDir = ensureThumbnailDir($albumPath);
    $filename = basename($sourcePath);
    $thumbnailPath = $thumbDir . '/' . $filename;
    
    // Get original image dimensions
    $imageInfo = getimagesize($sourcePath);
    if (!$imageInfo) {
        return false;
    }
    
    $originalWidth = $imageInfo[0];
    $originalHeight = $imageInfo[1];
    $aspectRatio = $originalWidth / $originalHeight;
    
    // Calculate thumbnail dimensions maintaining aspect ratio
    // Base size around 800px to show more detail
    $baseSize = 800;
    if ($aspectRatio > 1.5) { // Wide image
        $thumbWidth = $baseSize * 1.5; // Can span up to 1.5 columns
        $thumbHeight = $thumbWidth / $aspectRatio;
    } elseif ($aspectRatio < 0.7) { // Tall image
        $thumbHeight = $baseSize * 1.4; // 40% taller than base
        $thumbWidth = $thumbHeight * $aspectRatio;
    } else { // Standard image
        $thumbWidth = $baseSize;
        $thumbHeight = $baseSize / $aspectRatio;
    }
    
    // If thumbnail exists and is newer than source, return dimensions
    if (file_exists($thumbnailPath) && 
        filemtime($thumbnailPath) >= filemtime($sourcePath)) {
        return [
            'url' => str_replace(__DIR__, '', $thumbnailPath),
            'width' => round($thumbWidth),
            'height' => round($thumbHeight),
            'aspect_ratio' => $aspectRatio
        ];
    }
    
    // Load the image based on its type
    switch ($imageInfo[2]) {
        case IMAGETYPE_JPEG:
            $source = imagecreatefromjpeg($sourcePath);
            break;
        case IMAGETYPE_PNG:
            $source = imagecreatefrompng($sourcePath);
            break;
        case IMAGETYPE_GIF:
            $source = imagecreatefromgif($sourcePath);
            break;
        default:
            return false;
    }
    
    // Create and save thumbnail
    $thumbnail = imagecreatetruecolor(round($thumbWidth), round($thumbHeight));
    
    // Enable alpha channel for PNG images
    if ($imageInfo[2] === IMAGETYPE_PNG) {
        imagealphablending($thumbnail, false);
        imagesavealpha($thumbnail, true);
        $transparent = imagecolorallocatealpha($thumbnail, 255, 255, 255, 127);
        imagefilledrectangle($thumbnail, 0, 0, round($thumbWidth), round($thumbHeight), $transparent);
    }
    
    // Use better quality resampling
    imagecopyresampled(
        $thumbnail, $source,
        0, 0, 0, 0,
        round($thumbWidth), round($thumbHeight),
        $originalWidth, $originalHeight
    );

    // Apply sharpening
    $sharpen = array(
        array(-1, -1, -1),
        array(-1, 16, -1),
        array(-1, -1, -1)
    );
    $divisor = array_sum(array_map('array_sum', $sharpen));
    imageconvolution($thumbnail, $sharpen, $divisor, 0);
    
    // Save with high quality settings
    switch ($imageInfo[2]) {
        case IMAGETYPE_JPEG:
            imagejpeg($thumbnail, $thumbnailPath, 95); // Increased quality from 85 to 95
            break;
        case IMAGETYPE_PNG:
            imagepng($thumbnail, $thumbnailPath, 2); // Reduced compression (0-9, lower = better quality)
            break;
        case IMAGETYPE_GIF:
            imagegif($thumbnail, $thumbnailPath);
            break;
    }
    
    imagedestroy($thumbnail);
    imagedestroy($source);
    
    return [
        'url' => str_replace(__DIR__, '', $thumbnailPath),
        'width' => round($thumbWidth),
        'height' => round($thumbHeight),
        'aspect_ratio' => $aspectRatio
    ];
}

function getAlbums() {
    $albums = [];
    $dirs = glob(GALLERY_DIR . '/*', GLOB_ONLYDIR);
    
    foreach ($dirs as $dir) {
        $albumData = [
            'path' => basename($dir),
            'title' => basename($dir)
        ];
        
        $iniFile = $dir . '/album.ini';
        if (file_exists($iniFile)) {
            $metadata = parse_ini_file($iniFile);
            if ($metadata && isset($metadata['title'])) {
                $albumData['title'] = $metadata['title'];
            }
        }
        
        $albums[] = $albumData;
    }
    
    return $albums;
}

function getImages($page = 1, $album = '') {
    $images = [];
    $start = ($page - 1) * IMAGES_PER_PAGE;
    
    $searchPath = $album 
        ? GALLERY_DIR . '/' . $album . '/*.{jpg,jpeg,png,gif}'
        : GALLERY_DIR . '/*/*.{jpg,jpeg,png,gif}';
    
    $files = glob($searchPath, GLOB_BRACE);
    $files = array_filter($files, 'is_file');
    $files = array_filter($files, function($file) {
        return !strstr($file, '/thumbnails/');
    });
    $files = array_values($files);
    $files = array_slice($files, $start, IMAGES_PER_PAGE);
    
    foreach ($files as $file) {
        $dir = dirname($file);
        $filename = basename($file);
        $thumbnailData = createThumbnail($file, $dir);
        
        if ($thumbnailData) {
            $imageData = [
                'url' => str_replace(__DIR__, '', $file),
                'thumbnail' => $thumbnailData,
                'title' => pathinfo($filename, PATHINFO_FILENAME)
            ];
            
            $metadataFile = $dir . '/metadata.ini';
            if (file_exists($metadataFile)) {
                $metadata = parse_ini_file($metadataFile, true);
                if ($metadata && isset($metadata[$filename])) {
                    $imageData = array_merge($imageData, $metadata[$filename]);
                }
            }
            
            $images[] = $imageData;
        }
    }
    
    return $images;
}

// Main logic
$action = $_GET['action'] ?? '';
$response = [];

switch ($action) {
    case 'albums':
        $response = getAlbums();
        break;
        
    case 'images':
        $page = (int)($_GET['page'] ?? 1);
        $album = $_GET['album'] ?? '';
        $response = getImages($page, $album);
        break;
        
    case 'config':
        $response = getConfig();
        break;
        
    default:
        http_response_code(400);
        $response = ['error' => 'Invalid action'];
}

echo json_encode($response);
