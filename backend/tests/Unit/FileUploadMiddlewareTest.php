<?php

namespace Tests\Unit;

use App\Middleware\FileUploadMiddleware;
use App\Services\FileUploadService;
use App\Services\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Server\RequestHandlerInterface;

class FileUploadMiddlewareTest extends TestCase
{
    private FileUploadMiddleware $middleware;
    private FileUploadService $fileUploadService;
    private Logger $logger;

    protected function setUp(): void
    {
        $this->fileUploadService = $this->createMock(FileUploadService::class);
        $this->logger = $this->createMock(Logger::class);

        $this->middleware = new FileUploadMiddleware(
            $this->fileUploadService,
            $this->logger,
            ['max_files' => 5, 'allowed_fields' => ['image', 'document']]
        );
    }

    private function createMockRequest(
        string $contentType = 'application/json',
        array $uploadedFiles = []
    ): ServerRequestInterface {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getHeaderLine')->with('Content-Type')->willReturn($contentType);
        $request->method('getUploadedFiles')->willReturn($uploadedFiles);
        $request->method('withAttribute')->willReturnSelf();

        return $request;
    }

    private function createMockResponse(): ResponseInterface
    {
        return $this->createMock(ResponseInterface::class);
    }

    private function createMockHandler(): RequestHandlerInterface
    {
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($this->createMockResponse());

        return $handler;
    }

    private function createMockUploadedFile(
        string $filename = 'test.jpg',
        string $mimeType = 'image/jpeg'
    ): \Psr\Http\Message\UploadedFileInterface {
        $uploadedFile = $this->createMock(\Psr\Http\Message\UploadedFileInterface::class);
        $uploadedFile->method('getClientFilename')->willReturn($filename);
        $uploadedFile->method('getClientMediaType')->willReturn($mimeType);
        $uploadedFile->method('getSize')->willReturn(1000);
        $uploadedFile->method('getError')->willReturn(UPLOAD_ERR_OK);

        $stream = $this->createMock(StreamInterface::class);
        $uploadedFile->method('getStream')->willReturn($stream);

        return $uploadedFile;
    }

    public function testProcessNonMultipartRequest(): void
    {
        $request = $this->createMockRequest('application/json');
        $handler = $this->createMockHandler();

        $response = $this->middleware->process($request, $handler);

        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testProcessMultipartRequestWithoutFiles(): void
    {
        $request = $this->createMockRequest('multipart/form-data');
        $handler = $this->createMockHandler();

        $response = $this->middleware->process($request, $handler);

        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testProcessMultipartRequestWithValidFiles(): void
    {
        $uploadedFile = $this->createMockUploadedFile();
        $request = $this->createMockRequest('multipart/form-data', ['image' => $uploadedFile]);
        $handler = $this->createMockHandler();

        $this->fileUploadService->expects($this->once())
            ->method('uploadFile')
            ->with($uploadedFile, 'image')
            ->willReturn([
                'success' => true,
                'filename' => 'uploaded_file.jpg',
                'original_name' => 'test.jpg',
                'path' => '/uploads/uploaded_file.jpg',
                'size' => 1000,
                'mime_type' => 'image/jpeg',
                'url' => '/uploads/uploaded_file.jpg'
            ]);

        $this->logger->expects($this->once())
            ->method('info')
            ->with('File upload middleware processed files', $this->anything());

        $response = $this->middleware->process($request, $handler);

        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testProcessMultipartRequestWithMultipleFiles(): void
    {
        $file1 = $this->createMockUploadedFile('file1.jpg');
        $file2 = $this->createMockUploadedFile('file2.jpg');

        $request = $this->createMockRequest('multipart/form-data', [
            'image' => [$file1, $file2]
        ]);
        $handler = $this->createMockHandler();

        $this->fileUploadService->expects($this->exactly(2))
            ->method('uploadFile')
            ->willReturn([
                'success' => true,
                'filename' => 'uploaded_file.jpg',
                'original_name' => 'test.jpg',
                'path' => '/uploads/uploaded_file.jpg',
                'size' => 1000,
                'mime_type' => 'image/jpeg',
                'url' => '/uploads/uploaded_file.jpg'
            ]);

        $response = $this->middleware->process($request, $handler);

        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testProcessRequestWithTooManyFiles(): void
    {
        $files = [];
        for ($i = 0; $i < 6; $i++) {
            $files["file{$i}"] = $this->createMockUploadedFile("file{$i}.jpg");
        }

        $request = $this->createMockRequest('multipart/form-data', $files);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Too many files uploaded. Maximum allowed: 5');

        $handler = $this->createMockHandler();
        $this->middleware->process($request, $handler);
    }

    public function testProcessRequestWithDisallowedField(): void
    {
        $uploadedFile = $this->createMockUploadedFile();
        $request = $this->createMockRequest('multipart/form-data', ['disallowed' => $uploadedFile]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('File upload not allowed for field: disallowed');

        $handler = $this->createMockHandler();
        $this->middleware->process($request, $handler);
    }

    public function testProcessRequestWithFileUploadError(): void
    {
        $uploadedFile = $this->createMockUploadedFile();
        $request = $this->createMockRequest('multipart/form-data', ['image' => $uploadedFile]);
        $handler = $this->createMockHandler();

        $this->fileUploadService->expects($this->once())
            ->method('uploadFile')
            ->willThrowException(new \Exception('Upload failed'));

        $this->logger->expects($this->once())
            ->method('warning')
            ->with('File upload failed for field', $this->anything());

        // Middleware now catches exceptions and continues processing
        $response = $this->middleware->process($request, $handler);
        $this->assertInstanceOf(\Psr\Http\Message\ResponseInterface::class, $response);
    }

    public function testGetUploadedFilesHelper(): void
    {
        $uploadedFiles = ['test' => 'data'];
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getAttribute')->with('uploaded_files')->willReturn($uploadedFiles);

        $result = FileUploadMiddleware::getUploadedFiles($request);

        $this->assertEquals($uploadedFiles, $result);
    }

    public function testGetFileUploadServiceHelper(): void
    {
        $service = $this->createMock(FileUploadService::class);
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getAttribute')->with('file_upload_service')->willReturn($service);

        $result = FileUploadMiddleware::getFileUploadService($request);

        $this->assertSame($service, $result);
    }
}