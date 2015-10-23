<?php
namespace GuzzleHttp\Tests\Psr7;

use GuzzleHttp\Psr7\Stream;
use GuzzleHttp\Psr7\UploadedFile;

class UploadedFileTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $tmpName = sys_get_temp_dir() . '/phpUxcOty';
        $_FILES = array(
            'avatar' => array(
                'tmp_name' => $tmpName,
                'name' => 'my-avatar.png',
                'size' => 90996,
                'type' => 'image/png',
                'error' => 0,
            ),
        );

        $tmpName = sys_get_temp_dir() . '/phpUxcOty';
        $handle = fopen($tmpName, 'w+');
        fwrite($handle, 'foobar');
        fclose($handle);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFileOrStreamMustBeValid()
    {
        new UploadedFile('', UPLOAD_ERR_OK);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testErrorCodeMustBeValid()
    {
        new UploadedFile('path_file', 42);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFileSizeMustBeValid()
    {
        $handle = fopen('php://temp', 'r+');
        new UploadedFile($handle, UPLOAD_ERR_OK, 4.2);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testClientFilenameMustBeValid()
    {
        $handle = fopen('php://temp', 'r+');
        new UploadedFile($handle, UPLOAD_ERR_OK, 0, 404);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testClientMediaTypeMustBeValid()
    {
        $handle = fopen('php://temp', 'r+');
        new UploadedFile($handle, UPLOAD_ERR_OK, 0, null, 404);
    }

    public function testResourceSize()
    {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, 'data');
        $stream = new Stream($handle);
        $uploadedFile = new UploadedFile($stream, UPLOAD_ERR_OK);
        $this->assertEquals(4, $uploadedFile->getSize());
    }

    public function testInstantiation()
    {
        $fileData = $_FILES['avatar'];
        $uploadedFile = new UploadedFile(
            $fileData['tmp_name'],
            $fileData['error'],
            $fileData['size'],
            $fileData['name'],
            $fileData['type']
        );
        $this->assertEquals(90996, $uploadedFile->getSize());
        $this->assertEquals('my-avatar.png', $uploadedFile->getClientFilename());
        $this->assertEquals('image/png', $uploadedFile->getClientMediaType());
        $this->assertEquals(90996, $uploadedFile->getSize());
        $this->assertEquals(UPLOAD_ERR_OK, $uploadedFile->getError());
    }

    public function testMoveTo()
    {
        $this->setExpectedException('RuntimeException');
        $fileData = $_FILES['avatar'];
        $uploadedFile = new UploadedFile(
            $fileData['tmp_name'],
            $fileData['error'],
            $fileData['size'],
            $fileData['name'],
            $fileData['type']
        );
        $tmpPath = sys_get_temp_dir() . '/phpUxcOtymove';
        $uploadedFile->moveTo($tmpPath);
        $this->assertTrue(file_exists($tmpPath));
        $uploadedFile->getStream();
    }
}
