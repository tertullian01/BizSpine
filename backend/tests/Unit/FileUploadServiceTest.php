<?php

namespace Tests\Unit;

use App\Services\FileUploadService;
use App\Services\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\UploadedFileInterface;

class FileUploadServiceTest extends TestCase
{
    private FileUploadService $fileUploadService;
    private Logger $logger;
    private string $testUploadDir;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(Logger::class);
        $baseDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'protected' . DIRECTORY_SEPARATOR . 'database';
        if (!is_dir($baseDir)) {
            mkdir($baseDir, 0777, true);
        }
        $this->testUploadDir = $baseDir . DIRECTORY_SEPARATOR . 'upload_tmp_' . bin2hex(random_bytes(8));

        if (!is_dir($this->testUploadDir)) {
            mkdir($this->testUploadDir, 0777, true);
        }

        $config = [
            'upload_path' => $this->testUploadDir . DIRECTORY_SEPARATOR,
            'max_file_size' => 1024 * 1024, // 1MB for testing
            'allowed_extensions' => ['jpg', 'png'],
            'allowed_mime_types' => ['image/jpeg', 'image/png'],
            'secure_filename' => true,
            'create_thumbnails' => false,
        ];

        $this->fileUploadService = new FileUploadService($this->logger, $config);
    }

    protected function tearDown(): void
    {
        // Clean up test files
        $this->removeDirectory($this->testUploadDir);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    private function createMockUploadedFile(
        string $content = 'test content',
        string $filename = 'test.jpg',
        string $mimeType = 'image/jpeg',
        int $size = 100,
        int $error = UPLOAD_ERR_OK
    ): UploadedFileInterface {
        $stream = $this->createMock(\Psr\Http\Message\StreamInterface::class);
        $stream->method('getContents')->willReturn($content);

        $uploadedFile = $this->createMock(UploadedFileInterface::class);
        $uploadedFile->method('getClientFilename')->willReturn($filename);
        $uploadedFile->method('getClientMediaType')->willReturn($mimeType);
        $uploadedFile->method('getSize')->willReturn($size);
        $uploadedFile->method('getError')->willReturn($error);
        $uploadedFile->method('getStream')->willReturn($stream);
        $uploadedFile->method('moveTo')->willReturnCallback(function ($targetPath) use ($content) {
            file_put_contents($targetPath, $content);
        });

        return $uploadedFile;
    }

    public function testUploadValidFile(): void
    {
        $uploadedFile = $this->createMockUploadedFile();

        $this->logger->expects($this->once())
            ->method('info')
            ->with('File uploaded successfully', $this->anything());

        $result = $this->fileUploadService->uploadFile($uploadedFile);

        $this->assertTrue($result['success']);
        $this->assertEquals('test.jpg', $result['original_name']);
        $this->assertEquals(100, $result['size']);
        $this->assertEquals('image/jpeg', $result['mime_type']);
        $this->assertFileExists($result['path']);
        $this->assertStringContainsString('test content', file_get_contents($result['path']));
    }

    public function testUploadFileWithInvalidExtension(): void
    {
        $uploadedFile = $this->createMockUploadedFile('test', 'test.exe', 'application/octet-stream');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('File extension \'exe\' is not allowed');

        $this->fileUploadService->uploadFile($uploadedFile);
    }

    public function testUploadFileTooLarge(): void
    {
        $uploadedFile = $this->createMockUploadedFile('test', 'test.jpg', 'image/jpeg', 2 * 1024 * 1024); // 2MB

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('File size 2097152 bytes exceeds maximum allowed size');

        $this->fileUploadService->uploadFile($uploadedFile);
    }

    public function testUploadFileWithUploadError(): void
    {
        $uploadedFile = $this->createMockUploadedFile('test', 'test.jpg', 'image/jpeg', 100, UPLOAD_ERR_INI_SIZE);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('The uploaded file exceeds the upload_max_filesize directive in php.ini');

        $this->fileUploadService->uploadFile($uploadedFile);
    }

    public function testSecureFilenameGeneration(): void
    {
        $uploadedFile = $this->createMockUploadedFile('test', 'test file with spaces.jpg');

        $result = $this->fileUploadService->uploadFile($uploadedFile);

        $this->assertTrue($result['success']);
        $this->assertStringNotContainsString(' ', $result['filename']);
        $this->assertStringEndsWith('.jpg', $result['filename']);
    }

    public function testGetFileInfo(): void
    {
        $uploadedFile = $this->createMockUploadedFile();
        $result = $this->fileUploadService->uploadFile($uploadedFile);

        $fileInfo = $this->fileUploadService->getFileInfo($result['filename']);

        $this->assertNotNull($fileInfo);
        $this->assertEquals($result['filename'], $fileInfo['filename']);
        $this->assertEquals(12, $fileInfo['size']); // Size of 'test content'
        $this->assertStringContainsString('/uploads/', $fileInfo['url']);
    }

    public function testGetFileInfoForNonexistentFile(): void
    {
        $fileInfo = $this->fileUploadService->getFileInfo('nonexistent.jpg');

        $this->assertNull($fileInfo);
    }

    public function testDeleteFile(): void
    {
        $uploadedFile = $this->createMockUploadedFile();
        $result = $this->fileUploadService->uploadFile($uploadedFile);

        $this->assertFileExists($result['path']);

        $deleted = $this->fileUploadService->deleteFile($result['filename']);

        $this->assertTrue($deleted);
        $this->assertFileDoesNotExist($result['path']);
    }

    public function testDeleteNonexistentFile(): void
    {
        $deleted = $this->fileUploadService->deleteFile('nonexistent.jpg');

        $this->assertFalse($deleted);
    }

    public function testUploadMultipleFiles(): void
    {
        $file1 = $this->createMockUploadedFile('content1', 'file1.jpg');
        $file2 = $this->createMockUploadedFile('content2', 'file2.jpg');

        $uploadedFiles = [$file1, $file2];

        $result = $this->fileUploadService->uploadMultipleFiles($uploadedFiles);

        $this->assertTrue($result['success']);
        $this->assertCount(2, $result['uploaded']);
        $this->assertEquals(0, $result['total_errors']);
        $this->assertEquals(2, $result['total_uploaded']);
    }

    public function testUploadMultipleFilesWithErrors(): void
    {
        $validFile = $this->createMockUploadedFile('content', 'valid.jpg');
        $invalidFile = $this->createMockUploadedFile('content', 'invalid.exe', 'application/octet-stream');

        $uploadedFiles = [$validFile, $invalidFile];

        $result = $this->fileUploadService->uploadMultipleFiles($uploadedFiles);

        $this->assertFalse($result['success']);
        $this->assertCount(1, $result['uploaded']);
        $this->assertCount(1, $result['errors']);
        $this->assertEquals(1, $result['total_errors']);
        $this->assertEquals(1, $result['total_uploaded']);
    }
}