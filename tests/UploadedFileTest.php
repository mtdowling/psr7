<?php

declare(strict_types=1);

namespace GuzzleHttp\Tests\Psr7;

use GuzzleHttp\Psr7\Stream;
use GuzzleHttp\Psr7\UploadedFile;
use GuzzleHttp\Psr7\Utils;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * @covers GuzzleHttp\Psr7\UploadedFile
 */
class UploadedFileTest extends TestCase
{
    private $cleanup;

    protected function setUp(): void
    {
        $this->cleanup = [];
    }

    protected function tearDown(): void
    {
        foreach ($this->cleanup as $file) {
            if (is_scalar($file) && file_exists($file)) {
                unlink($file);
            }
        }
    }

    public function invalidStreams()
    {
        return [
            'null'         => [null],
            'true'         => [true],
            'false'        => [false],
            'int'          => [1],
            'float'        => [1.1],
            'array'        => [['filename']],
            'object'       => [(object) ['filename']],
        ];
    }

    /**
     * @dataProvider invalidStreams
     */
    public function testRaisesExceptionOnInvalidStreamOrFile($streamOrFile)
    {
        $this->expectException(\InvalidArgumentException::class);

        new UploadedFile($streamOrFile, 0, UPLOAD_ERR_OK);
    }

    public function testGetStreamReturnsOriginalStreamObject()
    {
        $stream = new Stream(fopen('php://temp', 'r'));
        $upload = new UploadedFile($stream, 0, UPLOAD_ERR_OK);

        self::assertSame($stream, $upload->getStream());
    }

    public function testGetStreamReturnsWrappedPhpStream()
    {
        $stream = fopen('php://temp', 'wb+');
        $upload = new UploadedFile($stream, 0, UPLOAD_ERR_OK);
        $uploadStream = $upload->getStream()->detach();

        self::assertSame($stream, $uploadStream);
    }

    public function testGetStreamReturnsStreamForFile()
    {
        $this->cleanup[] = $stream = tempnam(sys_get_temp_dir(), 'stream_file');
        $upload = new UploadedFile($stream, 0, UPLOAD_ERR_OK);
        $uploadStream = $upload->getStream();
        $r = new ReflectionProperty($uploadStream, 'filename');
        $r->setAccessible(true);

        self::assertSame($stream, $r->getValue($uploadStream));
    }

    public function testSuccessful()
    {
        $stream = Utils::streamFor('Foo bar!');
        $upload = new UploadedFile($stream, $stream->getSize(), UPLOAD_ERR_OK, 'filename.txt', 'text/plain');

        self::assertEquals($stream->getSize(), $upload->getSize());
        self::assertEquals('filename.txt', $upload->getClientFilename());
        self::assertEquals('text/plain', $upload->getClientMediaType());

        $this->cleanup[] = $to = tempnam(sys_get_temp_dir(), 'successful');
        $upload->moveTo($to);
        self::assertFileExists($to);
        self::assertEquals($stream->__toString(), file_get_contents($to));
    }

    public function invalidMovePaths()
    {
        return [
            'null'   => [null],
            'true'   => [true],
            'false'  => [false],
            'int'    => [1],
            'float'  => [1.1],
            'empty'  => [''],
            'array'  => [['filename']],
            'object' => [(object) ['filename']],
        ];
    }

    /**
     * @dataProvider invalidMovePaths
     */
    public function testMoveRaisesExceptionForInvalidPath($path)
    {
        $stream = Utils::streamFor('Foo bar!');
        $upload = new UploadedFile($stream, 0, UPLOAD_ERR_OK);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('path');
        $upload->moveTo($path);
    }

    public function testMoveCannotBeCalledMoreThanOnce()
    {
        $stream = Utils::streamFor('Foo bar!');
        $upload = new UploadedFile($stream, 0, UPLOAD_ERR_OK);

        $this->cleanup[] = $to = tempnam(sys_get_temp_dir(), 'diac');
        $upload->moveTo($to);
        self::assertFileExists($to);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('moved');
        $upload->moveTo($to);
    }

    public function testCannotRetrieveStreamAfterMove()
    {
        $stream = Utils::streamFor('Foo bar!');
        $upload = new UploadedFile($stream, 0, UPLOAD_ERR_OK);

        $this->cleanup[] = $to = tempnam(sys_get_temp_dir(), 'diac');
        $upload->moveTo($to);
        self::assertFileExists($to);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('moved');
        $upload->getStream();
    }

    public function nonOkErrorStatus()
    {
        return [
            'UPLOAD_ERR_INI_SIZE'   => [ UPLOAD_ERR_INI_SIZE ],
            'UPLOAD_ERR_FORM_SIZE'  => [ UPLOAD_ERR_FORM_SIZE ],
            'UPLOAD_ERR_PARTIAL'    => [ UPLOAD_ERR_PARTIAL ],
            'UPLOAD_ERR_NO_FILE'    => [ UPLOAD_ERR_NO_FILE ],
            'UPLOAD_ERR_NO_TMP_DIR' => [ UPLOAD_ERR_NO_TMP_DIR ],
            'UPLOAD_ERR_CANT_WRITE' => [ UPLOAD_ERR_CANT_WRITE ],
            'UPLOAD_ERR_EXTENSION'  => [ UPLOAD_ERR_EXTENSION ],
        ];
    }

    /**
     * @dataProvider nonOkErrorStatus
     */
    public function testConstructorDoesNotRaiseExceptionForInvalidStreamWhenErrorStatusPresent($status)
    {
        $uploadedFile = new UploadedFile('not ok', 0, $status);
        self::assertSame($status, $uploadedFile->getError());
    }

    /**
     * @dataProvider nonOkErrorStatus
     */
    public function testMoveToRaisesExceptionWhenErrorStatusPresent($status)
    {
        $uploadedFile = new UploadedFile('not ok', 0, $status);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('upload error');
        $uploadedFile->moveTo(__DIR__ . '/' . sha1(uniqid('', true)));
    }

    /**
     * @dataProvider nonOkErrorStatus
     */
    public function testGetStreamRaisesExceptionWhenErrorStatusPresent($status)
    {
        $uploadedFile = new UploadedFile('not ok', 0, $status);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('upload error');
        $uploadedFile->getStream();
    }

    public function testMoveToCreatesStreamIfOnlyAFilenameWasProvided()
    {
        $this->cleanup[] = $from = tempnam(sys_get_temp_dir(), 'copy_from');
        $this->cleanup[] = $to = tempnam(sys_get_temp_dir(), 'copy_to');

        copy(__FILE__, $from);

        $uploadedFile = new UploadedFile($from, 100, UPLOAD_ERR_OK, basename($from), 'text/plain');
        $uploadedFile->moveTo($to);

        self::assertFileEquals(__FILE__, $to);
    }
}
