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
    
    // If thumbnail already exists and is newer than source, return it
    if (file_exists($thumbnailPath) && 
        filemtime($thumbnailPath) >= filemtime($sourcePath)) {
        return str_replace(__DIR__, '', $thumbnailPath);
    }
    
    // Load the image based on its type
    $imageInfo = getimagesize($sourcePath);
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
    
    // Calculate new dimensions maintaining aspect ratio
    $width = $imageInfo[0];
    $height = $imageInfo[1];
    
    $ratio = min(THUMBNAIL_WIDTH / $width, THUMBNAIL_HEIGHT / $height);
    $new_width = round($width * $ratio);
    $new_height = round($height * $ratio);
    
    // Create and save thumbnail
    $thumbnail = imagecreatetruecolor($new_width, $new_height);
    
    // Preserve transparency for PNG images
    if ($imageInfo[2] === IMAGETYPE_PNG) {
        imagealphablending($thumbnail, false);
        imagesavealpha($thumbnail, true);
        $transparent = imagecolorallocatealpha($thumbnail, 255, 255, 255, 127);
        imagefilledrectangle($thumbnail, 0, 0, $new_width, $new_height, $transparent);
    }
    
    imagecopyresampled(
        $thumbnail, $source,
        0, 0, 0, 0,
        $new_width, $new_height,
        $width, $height
    );
    
    switch ($imageInfo[2]) {
        case IMAGETYPE_JPEG:
            imagejpeg($thumbnail, $thumbnailPath, 85);
            break;
        case IMAGETYPE_PNG:
            imagepng($thumbnail, $thumbnailPath, 8);
            break;
        case IMAGETYPE_GIF:
            imagegif($thumbnail, $thumbnailPath);
            break;
    }
    
    imagedestroy($thumbnail);
    imagedestroy($source);
    
    return str_replace(__DIR__, '', $thumbnailPath);
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
    $files = array_values($files); // Reset array keys after filtering
    $files = array_slice($files, $start, IMAGES_PER_PAGE);
    
    foreach ($files as $file) {
        $dir = dirname($file);
        $filename = basename($file);
        $thumbnailUrl = createThumbnail($file, $dir);
        
        $imageData = [
            'url' => str_replace(__DIR__, '', $file),
            'thumbnail_url' => $thumbnailUrl,
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
