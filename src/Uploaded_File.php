<?php

declare(strict_types=1);

namespace Nyholm\Psr7;

use Psr\Http\Message\{StreamInterface, UploadedFileInterface};

/**
 * @author Michael Dowling and contributors to guzzlehttp/psr7
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 * @author Martijn van der Ven <martijn@vanderven.se>
 *
 * @final This class should never be extended. See https://github.com/Nyholm/psr7/blob/master/doc/final.md
 */
class Uploaded_File implements UploadedFileInterface
{
    /** @var array */
    private const ERRORS = [
        \UPLOAD_ERR_OK => 1,
        \UPLOAD_ERR_INI_SIZE => 1,
        \UPLOAD_ERR_FORM_SIZE => 1,
        \UPLOAD_ERR_PARTIAL => 1,
        \UPLOAD_ERR_NO_FILE => 1,
        \UPLOAD_ERR_NO_TMP_DIR => 1,
        \UPLOAD_ERR_CANT_WRITE => 1,
        \UPLOAD_ERR_EXTENSION => 1,
    ];

    /** @var string */
    private $client_filename;

    /** @var string */
    private $client_media_type;

    /** @var int */
    private $error;

    /** @var string|null */
    private $file;

    /** @var bool */
    private $moved = false;

    /** @var int */
    private $size;

    /** @var StreamInterface|null */
    private $stream;

    /**
     * @param StreamInterface|string|resource $stream_or_file
     * @param int $size
     * @param int $error_status
     * @param string|null $client_filename
     * @param string|null $client_media_type
     */
    public function __construct($stream_or_file, $size, $error_status, $client_filename = null, $client_media_type = null)
    {
        if (false === \is_int($error_status) || !isset(self::ERRORS[$error_status])) {
            throw new \InvalidArgumentException('Upload file error status must be an integer value and one of the "UPLOAD_ERR_*" constants');
        }

        if (false === \is_int($size)) {
            throw new \InvalidArgumentException('Upload file size must be an integer');
        }

        if (null !== $client_filename && !\is_string($client_filename)) {
            throw new \InvalidArgumentException('Upload file client filename must be a string or null');
        }

        if (null !== $client_media_type && !\is_string($client_media_type)) {
            throw new \InvalidArgumentException('Upload file client media type must be a string or null');
        }

        $this->error = $error_status;
        $this->size = $size;
        $this->client_filename = $client_filename;
        $this->client_media_type = $client_media_type;

        if (\UPLOAD_ERR_OK === $this->error) {
            // Depending on the value set file or stream variable.
            if (\is_string($stream_or_file) && '' !== $stream_or_file) {
                $this->file = $stream_or_file;
            } elseif (\is_resource($stream_or_file)) {
                $this->stream = Stream::create($stream_or_file);
            } elseif ($stream_or_file instanceof StreamInterface) {
                $this->stream = $stream_or_file;
            } else {
                throw new \InvalidArgumentException('Invalid stream or file provided for Uploaded_File');
            }
        }
    }

    /**
     * @throws \RuntimeException if is moved or not ok
     */
    private function validate_active(): void
    {
        if (\UPLOAD_ERR_OK !== $this->error) {
            throw new \RuntimeException('Cannot retrieve stream due to upload error');
        }

        if ($this->moved) {
            throw new \RuntimeException('Cannot retrieve stream after it has already been moved');
        }
    }

    public function getStream(): StreamInterface
    {
        $this->validate_active();

        if ($this->stream instanceof StreamInterface) {
            return $this->stream;
        }

        if (false === $resource = @\fopen($this->file, 'r')) {
            throw new \RuntimeException(\sprintf('The file "%s" cannot be opened: %s', $this->file, \error_get_last()['message'] ?? ''));
        }

        return Stream::create($resource);
    }

    public function moveTo($targetPath): void
    {
        $this->validate_active();

        if (!\is_string($targetPath) || '' === $targetPath) {
            throw new \InvalidArgumentException('Invalid path provided for move operation; must be a non-empty string');
        }

        if (null !== $this->file) {
            $this->moved = 'cli' === \PHP_SAPI ? @\rename($this->file, $targetPath) : @\move_uploaded_file($this->file, $targetPath);

            if (false === $this->moved) {
                throw new \RuntimeException(\sprintf('Uploaded file could not be moved to "%s": %s', $targetPath, \error_get_last()['message'] ?? ''));
            }
        } else {
            $stream = $this->getStream();
            if ($stream->isSeekable()) {
                $stream->rewind();
            }

            if (false === $resource = @\fopen($targetPath, 'w')) {
                throw new \RuntimeException(\sprintf('The file "%s" cannot be opened: %s', $targetPath, \error_get_last()['message'] ?? ''));
            }

            $dest = Stream::create($resource);

            while (!$stream->eof()) {
                if (!$dest->write($stream->read(1048576))) {
                    break;
                }
            }

            $this->moved = true;
        }
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function getError(): int
    {
        return $this->error;
    }

    public function getClientFilename(): ?string
    {
        return $this->client_filename;
    }

    public function getClientMediaType(): ?string
    {
        return $this->client_media_type;
    }
}
