<?php
function createThumbnail($imagePath) {
    $pathInfo = pathinfo($imagePath);
    $thumbDir = $pathInfo['dirname'] . '/thumbnails';
    
    // Create thumbnails directory if it doesn't exist
    if (!file_exists($thumbDir)) {
        mkdir($thumbDir, 0777, true);
    }
    
    $thumbPath = $thumbDir . '/' . $pathInfo['filename'] . '.' . $pathInfo['extension'];
    
    // Load the image based on its type
    $extension = strtolower($pathInfo['extension']);
    switch ($extension) {
        case 'jpg':
        case 'jpeg':
            $source = imagecreatefromjpeg($imagePath);
            break;
        case 'png':
            $source = imagecreatefrompng($imagePath);
            break;
        case 'gif':
            $source = imagecreatefromgif($imagePath);
            break;
        default:
            return false;
    }
    
    if (!$source) {
        return false;
    }
    
    // Get original dimensions
    $width = imagesx($source);
    $height = imagesy($source);
    $aspectRatio = $width / $height;
    
    // Calculate thumbnail dimensions
    $baseSize = 800;
    if ($aspectRatio > 1.5) { // Wide image
        $thumbWidth = $baseSize;
        $thumbHeight = (int)($baseSize / $aspectRatio);
    } elseif ($aspectRatio < 0.7) { // Tall image
        $thumbWidth = (int)($baseSize * 0.7);
        $thumbHeight = (int)($thumbWidth / $aspectRatio);
    } else { // Standard image
        $thumbWidth = $baseSize;
        $thumbHeight = (int)($baseSize / $aspectRatio);
    }
    
    // Create thumbnail
    $thumb = imagecreatetruecolor((int)$thumbWidth, (int)$thumbHeight);
    
    // Preserve transparency for PNG images
    if ($extension === 'png') {
        imagealphablending($thumb, false);
        imagesavealpha($thumb, true);
        $transparent = imagecolorallocatealpha($thumb, 255, 255, 255, 127);
        imagefilledrectangle($thumb, 0, 0, $thumbWidth, $thumbHeight, $transparent);
    }
    
    // Resize the image
    imagecopyresampled($thumb, $source, 0, 0, 0, 0, $thumbWidth, $thumbHeight, $width, $height);
    
    // Save the thumbnail
    switch ($extension) {
        case 'jpg':
        case 'jpeg':
            imagejpeg($thumb, $thumbPath, 95);
            break;
        case 'png':
            imagepng($thumb, $thumbPath, 2);
            break;
        case 'gif':
            imagegif($thumb, $thumbPath);
            break;
    }
    
    // Clean up
    imagedestroy($source);
    imagedestroy($thumb);
    
    return true;
}

function checkThumbnailExists($imagePath) {
    $thumbPath = getThumbPath($imagePath);
    return file_exists($thumbPath);
}

function getThumbPath($imagePath) {
    $pathInfo = pathinfo($imagePath);
    $thumbDir = $pathInfo['dirname'] . '/thumbnails';
    // Match gallery.php behavior - use same filename
    return $thumbDir . '/' . $pathInfo['basename'];
}

function getAllImages() {
    $images = [];
    $galleryDir = __DIR__ . '/gallery';
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($galleryDir));

    foreach ($iterator as $file) {
        if ($file->isFile()) {
            // Skip files in thumbnails directories
            if (strpos($file->getPathname(), '/thumbnails/') !== false) {
                continue;
            }
            
            $extension = strtolower($file->getExtension());
            if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif'])) {
                $relativePath = str_replace(__DIR__ . '/', '', $file->getPathname());
                $images[] = [
                    'path' => $relativePath,
                    'has_thumbnail' => checkThumbnailExists($file->getPathname()),
                    'size' => formatSize($file->getSize()),
                    'modified' => date('Y-m-d H:i:s', $file->getMTime())
                ];
            }
        }
    }

    return $images;
}

function formatSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, 1) . ' ' . $units[$pow];
}

// Handle thumbnail generation requests
if (isset($_POST['generate']) && isset($_POST['image'])) {
    $imagePath = __DIR__ . '/' . $_POST['image'];
    if (file_exists($imagePath)) {
        createThumbnail($imagePath);
        header('Location: ' . $_SERVER['PHP_SELF'] . '?generated=' . urlencode($_POST['image']));
        exit;
    }
}

// Handle generate all request
if (isset($_POST['generate_all'])) {
    $images = getAllImages();
    $generated = 0;
    foreach ($images as $image) {
        if (!$image['has_thumbnail']) {
            $imagePath = __DIR__ . '/' . $image['path'];
            if (createThumbnail($imagePath)) {
                $generated++;
            }
        }
    }
    header('Location: ' . $_SERVER['PHP_SELF'] . '?generated_all=' . $generated);
    exit;
}

$images = getAllImages();
$totalImages = count($images);
$thumbnailsGenerated = count(array_filter($images, function($img) { return $img['has_thumbnail']; }));
$pendingCount = $totalImages - $thumbnailsGenerated;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thumbnail Generator</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .summary {
            margin-bottom: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 4px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #f8f9fa;
            font-weight: 600;
        }
        tr:hover {
            background: #f8f9fa;
        }
        .status {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 14px;
        }
        .status.generated {
            background: #d4edda;
            color: #155724;
        }
        .status.pending {
            background: #fff3cd;
            color: #856404;
        }
        form {
            display: inline;
        }
        button {
            padding: 4px 8px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        button:hover {
            background: #0056b3;
        }
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .generate-all {
            background: #28a745;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
            margin-bottom: 20px;
        }
        
        .generate-all:hover {
            background: #218838;
        }
        
        .generate-all:disabled {
            background: #6c757d;
            cursor: not-allowed;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Thumbnail Generator</h1>
        
        <?php if (isset($_GET['generated'])): ?>
        <div class="success-message">
            Successfully generated thumbnail for: <?= htmlspecialchars($_GET['generated']) ?>
        </div>
        <?php endif; ?>

        <?php if (isset($_GET['generated_all'])): ?>
        <div class="success-message">
            Successfully generated <?= (int)$_GET['generated_all'] ?> thumbnails
        </div>
        <?php endif; ?>

        <div class="summary">
            <h2>Summary</h2>
            <p>Total Images: <?= $totalImages ?></p>
            <p>Thumbnails Generated: <?= $thumbnailsGenerated ?></p>
            <p>Pending: <?= $pendingCount ?></p>
            
            <?php if ($pendingCount > 0): ?>
            <form method="post" style="margin-top: 1rem;">
                <button type="submit" name="generate_all" class="generate-all">
                    Generate All Pending Thumbnails (<?= $pendingCount ?>)
                </button>
            </form>
            <?php endif; ?>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Image</th>
                    <th>Size</th>
                    <th>Modified</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($images as $image): ?>
                <tr>
                    <td><?= htmlspecialchars($image['path']) ?></td>
                    <td><?= htmlspecialchars($image['size']) ?></td>
                    <td><?= htmlspecialchars($image['modified']) ?></td>
                    <td>
                        <span class="status <?= $image['has_thumbnail'] ? 'generated' : 'pending' ?>">
                            <?= $image['has_thumbnail'] ? 'Generated' : 'Pending' ?>
                        </span>
                    </td>
                    <td>
                        <?php if (!$image['has_thumbnail']): ?>
                        <form method="post">
                            <input type="hidden" name="image" value="<?= htmlspecialchars($image['path']) ?>">
                            <button type="submit" name="generate">Generate</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
