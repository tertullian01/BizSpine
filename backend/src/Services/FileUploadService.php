<?php

declare(strict_types=1);

namespace App\Services;

use Psr\Http\Message\UploadedFileInterface;
use App\Services\Logger;
use Exception;
use finfo;

class FileUploadService
{
    private Logger $logger;
    private array $config;

    public function __construct(Logger $logger, array $config = [])
    {
        $this->logger = $logger;
        $this->config = array_merge([
            'max_file_size' => 5 * 1024 * 1024, // 5MB default
            'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx'],
            'allowed_mime_types' => [
                'image/jpeg',
                'image/png',
                'image/gif',
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            ],
            'upload_path' => __DIR__ . '/../../uploads/',
            'create_thumbnails' => false,
            'thumbnail_size' => [150, 150],
            'secure_filename' => true,
        ], $config);
    }

    /**
     * Handle file upload with validation and storage
     */
    public function uploadFile(UploadedFileInterface $uploadedFile, string $fieldName = 'file', array $options = []): array
    {
        try {
            // Merge options with defaults
            $options = array_merge($this->config, $options);

            // Validate file
            $this->validateFile($uploadedFile, $options);

            // Generate secure filename
            $filename = $this->generateSecureFilename($uploadedFile, $options);

            // Create upload directory if it doesn't exist
            $uploadDir = $options['upload_path'];
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            // Move file to destination
            $destination = $uploadDir . $filename;
            $uploadedFile->moveTo($destination);

            // Create thumbnail if enabled and it's an image
            $thumbnailPath = null;
            if ($options['create_thumbnails'] && $this->isImage($uploadedFile)) {
                $thumbnailPath = $this->createThumbnail($destination, $filename, $options);
            }

            // Log successful upload
            $this->logger->info('File uploaded successfully', [
                'filename' => $filename,
                'original_name' => $uploadedFile->getClientFilename(),
                'size' => $uploadedFile->getSize(),
                'field' => $fieldName
            ]);

            return [
                'success' => true,
                'filename' => $filename,
                'original_name' => $uploadedFile->getClientFilename(),
                'path' => $destination,
                'size' => $uploadedFile->getSize(),
                'mime_type' => $uploadedFile->getClientMediaType(),
                'thumbnail_path' => $thumbnailPath,
                'url' => $this->getFileUrl($filename, $options)
            ];

        } catch (Exception $e) {
            $this->logger->error('File upload failed', [
                'error' => $e->getMessage(),
                'field' => $fieldName,
                'client_filename' => $uploadedFile->getClientFilename()
            ]);

            throw $e;
        }
    }

    /**
     * Handle multiple file uploads
     */
    public function uploadMultipleFiles(array $uploadedFiles, string $fieldName = 'files', array $options = []): array
    {
        $results = [];
        $errors = [];

        foreach ($uploadedFiles as $index => $uploadedFile) {
            try {
                $result = $this->uploadFile($uploadedFile, "{$fieldName}[{$index}]", $options);
                $results[] = $result;
            } catch (Exception $e) {
                $errors[] = [
                    'index' => $index,
                    'filename' => $uploadedFile->getClientFilename(),
                    'error' => $e->getMessage()
                ];
            }
        }

        return [
            'success' => count($errors) === 0,
            'uploaded' => $results,
            'errors' => $errors,
            'total_uploaded' => count($results),
            'total_errors' => count($errors)
        ];
    }

    /**
     * Validate uploaded file
     */
    private function validateFile(UploadedFileInterface $uploadedFile, array $options): void
    {
        // Check for upload errors
        if ($uploadedFile->getError() !== UPLOAD_ERR_OK) {
            throw new Exception($this->getUploadErrorMessage($uploadedFile->getError()));
        }

        // Check file size
        $fileSize = $uploadedFile->getSize();
        if ($fileSize > $options['max_file_size']) {
            throw new Exception("File size {$fileSize} bytes exceeds maximum allowed size of {$options['max_file_size']} bytes");
        }

        // Check file extension
        $clientFilename = $uploadedFile->getClientFilename();
        $extension = strtolower(pathinfo($clientFilename, PATHINFO_EXTENSION));
        if (!in_array($extension, $options['allowed_extensions'])) {
            throw new Exception("File extension '{$extension}' is not allowed. Allowed extensions: " . implode(', ', $options['allowed_extensions']));
        }

        // Check MIME type
        $mimeType = $uploadedFile->getClientMediaType();
        if (!in_array($mimeType, $options['allowed_mime_types'])) {
            throw new Exception("MIME type '{$mimeType}' is not allowed");
        }

        // Additional security check: verify file content matches extension
        $this->validateFileContent($uploadedFile, $extension, $mimeType);
    }

    /**
     * Validate file content matches the claimed type
     */
    private function validateFileContent(UploadedFileInterface $uploadedFile, string $extension, string $claimedMimeType): void
    {
        // Skip content validation if finfo is not available (for testing environments)
        if (!class_exists('finfo')) {
            return;
        }

        try {
            // Get actual MIME type from file content
            $fileInfo = new finfo(FILEINFO_MIME_TYPE);
            $stream = $uploadedFile->getStream();
            $stream->rewind();
            $content = $stream->read(1024); // Read first 1KB for detection
            $actualMimeType = $fileInfo->buffer($content);

            // Basic validation - ensure claimed and actual MIME types match for common cases
            $mimeMap = [
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'gif' => 'image/gif',
                'pdf' => 'application/pdf',
            ];

            if (isset($mimeMap[$extension]) && $actualMimeType !== $mimeMap[$extension]) {
                throw new Exception('File content does not match the claimed file type');
            }
        } catch (Exception $e) {
            if ($e->getMessage() === 'File content does not match the claimed file type') {
                throw $e;
            }
            $this->logger->warning('File content validation skipped', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Generate a secure filename
     */
    private function generateSecureFilename(UploadedFileInterface $uploadedFile, array $options): string
    {
        if (!$options['secure_filename']) {
            return $uploadedFile->getClientFilename();
        }

        $extension = strtolower(pathinfo($uploadedFile->getClientFilename(), PATHINFO_EXTENSION));
        $basename = pathinfo($uploadedFile->getClientFilename(), PATHINFO_FILENAME);

        // Sanitize basename
        $basename = preg_replace('/[^a-zA-Z0-9\-_\.]/', '_', $basename);
        $basename = substr($basename, 0, 100); // Limit length

        // Add timestamp and random string for uniqueness
        $timestamp = date('Ymd_His');
        $random = bin2hex(random_bytes(4));

        return "{$timestamp}_{$random}_{$basename}.{$extension}";
    }

    /**
     * Create thumbnail for image files
     */
    private function createThumbnail(string $sourcePath, string $filename, array $options): ?string
    {
        if (!function_exists('imagecreatetruecolor')) {
            return null; // GD not available
        }

        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $thumbnailDir = $options['upload_path'] . 'thumbnails/';

        if (!is_dir($thumbnailDir)) {
            mkdir($thumbnailDir, 0755, true);
        }

        $thumbnailPath = $thumbnailDir . 'thumb_' . $filename;

        try {
            $this->createImageThumbnail($sourcePath, $thumbnailPath, $options['thumbnail_size'], $extension);
            return $thumbnailPath;
        } catch (Exception $e) {
            $this->logger->warning('Failed to create thumbnail', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Create image thumbnail using GD
     */
    private function createImageThumbnail(string $sourcePath, string $destPath, array $size, string $extension): void
    {
        list($width, $height) = $size;

        // Create image resource based on type
        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                $sourceImage = imagecreatefromjpeg($sourcePath);
                break;
            case 'png':
                $sourceImage = imagecreatefrompng($sourcePath);
                break;
            case 'gif':
                $sourceImage = imagecreatefromgif($sourcePath);
                break;
            default:
                throw new Exception("Unsupported image type: {$extension}");
        }

        if (!$sourceImage) {
            throw new Exception("Failed to create image resource");
        }

        $sourceWidth = imagesx($sourceImage);
        $sourceHeight = imagesy($sourceImage);

        // Calculate thumbnail dimensions
        $ratio = min($width / $sourceWidth, $height / $sourceHeight);
        $thumbWidth = (int)($sourceWidth * $ratio);
        $thumbHeight = (int)($sourceHeight * $ratio);

        $thumbnail = imagecreatetruecolor($thumbWidth, $thumbHeight);

        // Preserve transparency for PNG/GIF
        if ($extension === 'png' || $extension === 'gif') {
            imagecolortransparent($thumbnail, imagecolorallocatealpha($thumbnail, 0, 0, 0, 127));
            imagealphablending($thumbnail, false);
            imagesavealpha($thumbnail, true);
        }

        imagecopyresampled($thumbnail, $sourceImage, 0, 0, 0, 0, $thumbWidth, $thumbHeight, $sourceWidth, $sourceHeight);

        // Save thumbnail
        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                imagejpeg($thumbnail, $destPath, 85);
                break;
            case 'png':
                imagepng($thumbnail, $destPath, 8);
                break;
            case 'gif':
                imagegif($thumbnail, $destPath);
                break;
        }

        imagedestroy($sourceImage);
        imagedestroy($thumbnail);
    }

    /**
     * Check if file is an image
     */
    private function isImage(UploadedFileInterface $uploadedFile): bool
    {
        $mimeType = $uploadedFile->getClientMediaType();
        return strpos($mimeType, 'image/') === 0;
    }

    /**
     * Get file URL for web access
     */
    private function getFileUrl(string $filename, array $options): string
    {
        // This would typically be configured based on your web server setup
        // For now, return a relative path
        return '/uploads/' . $filename;
    }

    /**
     * Delete uploaded file
     */
    public function deleteFile(string $filename, array $options = []): bool
    {
        $options = array_merge($this->config, $options);
        $filePath = $options['upload_path'] . $filename;

        if (file_exists($filePath)) {
            unlink($filePath);
            $this->logger->info('File deleted', ['filename' => $filename]);
            return true;
        }

        return false;
    }

    /**
     * Get upload error message
     */
    private function getUploadErrorMessage(int $errorCode): string
    {
        switch ($errorCode) {
            case UPLOAD_ERR_INI_SIZE:
                return 'The uploaded file exceeds the upload_max_filesize directive in php.ini';
            case UPLOAD_ERR_FORM_SIZE:
                return 'The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form';
            case UPLOAD_ERR_PARTIAL:
                return 'The uploaded file was only partially uploaded';
            case UPLOAD_ERR_NO_FILE:
                return 'No file was uploaded';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Missing a temporary folder';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Failed to write file to disk';
            case UPLOAD_ERR_EXTENSION:
                return 'A PHP extension stopped the file upload';
            default:
                return 'Unknown upload error';
        }
    }

    /**
     * Get file information
     */
    public function getFileInfo(string $filename, array $options = []): ?array
    {
        $options = array_merge($this->config, $options);
        $filePath = $options['upload_path'] . $filename;

        if (!file_exists($filePath)) {
            return null;
        }

        return [
            'filename' => $filename,
            'path' => $filePath,
            'size' => filesize($filePath),
            'modified' => filemtime($filePath),
            'url' => $this->getFileUrl($filename, $options)
        ];
    }
}