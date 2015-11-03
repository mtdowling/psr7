<?php
namespace GuzzleHttp\Psr7;

use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;

class UploadedFile implements UploadedFileInterface
{
    private static $uploadErrorCode = [
        UPLOAD_ERR_OK,
        UPLOAD_ERR_INI_SIZE,
        UPLOAD_ERR_FORM_SIZE,
        UPLOAD_ERR_PARTIAL,
        UPLOAD_ERR_NO_FILE,
        UPLOAD_ERR_NO_TMP_DIR,
        UPLOAD_ERR_CANT_WRITE,
        UPLOAD_ERR_EXTENSION
    ];

    /** @var string */
    private $clientFilename;

    /** @var string|null */
    private $clientMediaType;
    /** @var int */

    private $error;
    /** @var null|string */

    private $filePath;

    /** @var bool */
    private $moved = false;

    /** @var null|int */
    private $size;

    /** @var StreamInterface */
    private $stream;

    public function __construct($fileOrStream, $error, $size = null, $clientFilename = null, $clientMediaType = null)
    {
        if (is_string($fileOrStream) && !empty($fileOrStream)) {
            $this->filePath = $fileOrStream;
        } else if ($fileOrStream instanceof StreamInterface) {
            $this->stream = $fileOrStream;
        } else if (is_resource($fileOrStream)) {
            $this->stream = new Stream($fileOrStream);
        } else {
            throw new \InvalidArgumentException('Invalid file or stream; must be string, resource or stream');
        }

        if (!in_array($error, self::$uploadErrorCode)) {
            throw new \InvalidArgumentException('Invalid error code');
        }
        $this->error = $error;

        if ($size !== null && !is_int($size)) {
            throw new \InvalidArgumentException('Invalid size; must be int or null');
        }
        $this->size = $size;

        if ($clientFilename !== null && !is_string($clientFilename)) {
            throw new \InvalidArgumentException('Invalid client filename; must be string or null');
        }
        $this->clientFilename = $clientFilename;


        if ($clientMediaType !== null && !is_string($clientMediaType)) {
            throw new \InvalidArgumentException('Invalid client media type; must be string or null');
        }
        $this->clientMediaType = $clientMediaType;
    }

    public function moveTo($targetPath)
    {
        if (!is_string($targetPath) || empty($targetPath)) {
            throw new \InvalidArgumentException('Invalid target path provided; must be a non-empty string');
        }

        if ($this->moved == true) {
            throw new \RuntimeException('Cannot move file; already moved');
        }

        $sapiEnvironment = PHP_SAPI;
        if (empty($this->filePath) || empty($sapiEnvironment) || strpos($sapiEnvironment, 'cli') === 0) {
            $handle = try_fopen($targetPath, 'wb+');
            while (!$this->getStream()->eof()) {
                fwrite($handle, $this->getStream()->read(4096));
            }
            fclose($handle);
        } else {
            if (move_uploaded_file($this->filePath, $targetPath) === false) {
                throw new \RuntimeException('Error during the move operation');
            }
        }

        $this->moved = true;
    }

    public function getStream()
    {
        if ($this->moved == true) {
            throw new \RuntimeException('Cannot get stream; file moved');
        }

        if ($this->stream == null) {
            $handle = try_fopen($this->filePath, 'r');
            $this->stream = new Stream($handle);
        }

        return $this->stream;
    }

    public function getSize()
    {
        if ($this->size != null) {
            return $this->size;
        }

        if ($this->stream instanceof StreamInterface) {
            return $this->stream->getSize();
        }

        return null;
    }

    public function getError()
    {
        return $this->error;
    }

    public function getClientFilename()
    {
        return $this->clientFilename;
    }

    public function getClientMediaType()
    {
        return $this->clientMediaType;
    }
}
