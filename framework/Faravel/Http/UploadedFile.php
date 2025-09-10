<?php

namespace Faravel\Http;

class UploadedFile
{
    protected string $originalName;
    protected string $mimeType;
    protected int $size;
    protected string $tmpName;
    protected int $error;

    public function __construct(array $file)
    {
        $this->originalName = $file['name'] ?? '';
        $this->mimeType     = $file['type'] ?? '';
        $this->size         = $file['size'] ?? 0;
        $this->tmpName      = $file['tmp_name'] ?? '';
        $this->error        = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    }

    public function getClientOriginalName(): string
    {
        return $this->originalName;
    }

    public function getClientMimeType(): string
    {
        return $this->mimeType;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function isValid(): bool
    {
        return $this->error === UPLOAD_ERR_OK && is_uploaded_file($this->tmpName);
    }

    public function getError(): int
    {
        return $this->error;
    }

    public function move(string $destination, ?string $newName = null): bool
    {
        if (!$this->isValid()) {
            return false;
        }

        $newName = $newName ?? $this->originalName;
        $target = rtrim($destination, '/') . '/' . basename($newName);

        return move_uploaded_file($this->tmpName, $target);
    }
}
