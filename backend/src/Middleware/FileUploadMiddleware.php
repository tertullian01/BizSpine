<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use App\Services\FileUploadService;
use App\Services\Logger;

class FileUploadMiddleware implements MiddlewareInterface
{
    private FileUploadService $fileUploadService;
    private Logger $logger;
    private array $config;

    public function __construct(FileUploadService $fileUploadService, Logger $logger, array $config = [])
    {
        $this->fileUploadService = $fileUploadService;
        $this->logger = $logger;
        $this->config = array_merge([
            'max_files' => 10,
            'max_file_size' => 5 * 1024 * 1024, // 5MB
            'allowed_fields' => [], // Empty array means all fields allowed
        ], $config);
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        $contentType = $request->getHeaderLine('Content-Type');

        // Only process multipart/form-data requests
        if (strpos($contentType, 'multipart/form-data') === false) {
            return $handler->handle($request);
        }

        try {
            // Parse uploaded files from the request
            $uploadedFiles = $request->getUploadedFiles();

            if (empty($uploadedFiles)) {
                return $handler->handle($request);
            }

            // Validate uploaded files
            $this->validateUploadedFiles($uploadedFiles);

            // Process and validate files
            $processedFiles = $this->processUploadedFiles($uploadedFiles);

            // Add processed files to request attributes
            $request = $request->withAttribute('uploaded_files', $processedFiles);
            $request = $request->withAttribute('file_upload_service', $this->fileUploadService);

            $this->logger->info('File upload middleware processed files', [
                'file_count' => count($processedFiles),
                'total_size' => array_sum(array_column($processedFiles, 'size'))
            ]);

        } catch (\Exception $e) {
            $this->logger->error('File upload middleware error', [
                'error' => $e->getMessage(),
                'content_type' => $contentType
            ]);

            // For file upload errors, we might want to return an error response
            // instead of continuing to the handler
            throw $e;
        }

        return $handler->handle($request);
    }

    /**
     * Validate uploaded files against configuration
     */
    private function validateUploadedFiles(array $uploadedFiles): void
    {
        $totalFiles = $this->countTotalFiles($uploadedFiles);

        if ($totalFiles > $this->config['max_files']) {
            throw new \Exception("Too many files uploaded. Maximum allowed: {$this->config['max_files']}");
        }

        $this->validateFileFields($uploadedFiles);
    }

    /**
     * Count total number of files in uploaded files array
     */
    private function countTotalFiles(array $uploadedFiles): int
    {
        $count = 0;
        foreach ($uploadedFiles as $field => $uploadedFile) {
            if (is_array($uploadedFile)) {
                $count += count($uploadedFile);
            } else {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Validate that file fields are allowed
     */
    private function validateFileFields(array $uploadedFiles): void
    {
        if (empty($this->config['allowed_fields'])) {
            return; // All fields allowed
        }

        foreach ($uploadedFiles as $field => $uploadedFile) {
            if (!in_array($field, $this->config['allowed_fields'])) {
                throw new \Exception("File upload not allowed for field: {$field}");
            }
        }
    }

    /**
     * Process and validate individual uploaded files
     */
    private function processUploadedFiles(array $uploadedFiles): array
    {
        $processedFiles = [];

        foreach ($uploadedFiles as $fieldName => $uploadedFile) {
            if (is_array($uploadedFile)) {
                // Multiple files for this field
                $processedFiles[$fieldName] = [];
                foreach ($uploadedFile as $index => $file) {
                    try {
                        $result = $this->fileUploadService->uploadFile($file, "{$fieldName}[{$index}]");
                        $processedFiles[$fieldName][] = $result;
                    } catch (\Exception $e) {
                        // Log error but continue processing other files
                        $this->logger->warning('File upload failed for field', [
                            'field' => $fieldName,
                            'index' => $index,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            } else {
                // Single file for this field
                try {
                    $result = $this->fileUploadService->uploadFile($uploadedFile, $fieldName);
                    $processedFiles[$fieldName] = $result;
                } catch (\Exception $e) {
                    $this->logger->warning('File upload failed for field', [
                        'field' => $fieldName,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }

        return $processedFiles;
    }

    /**
     * Get processed files from request (helper method for controllers)
     */
    public static function getUploadedFiles(Request $request): array
    {
        return $request->getAttribute('uploaded_files', []);
    }

    /**
     * Get file upload service from request (helper method for controllers)
     */
    public static function getFileUploadService(Request $request): ?FileUploadService
    {
        return $request->getAttribute('file_upload_service');
    }
}