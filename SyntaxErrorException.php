<?php

declare(strict_types=1);

namespace Qubus\View;

use Qubus\Exception\BaseException;
use Qubus\Exception\Exception;

final class SyntaxErrorException extends Exception
{
    protected Token $token;

    protected string $path;

    /**
     * @throws BaseException
     */
    public function __construct(string $message, Token $token)
    {
        $this->token = $token;

        $line = $token->getLine();
        $char = $token->getChar();
        parent::__construct(sprintf("$message in line %s char %d", $line, $char));
    }

    public function setTemplateFile($path): SyntaxErrorException
    {
        $this->path = $path;
        return $this;
    }

    public function getTemplateFile(): string
    {
        return $this->path;
    }

    public function __toString(): string
    {
        return (string) $this->message;
    }

    public function setMessage($message): SyntaxErrorException
    {
        $this->message = $message;
        return $this;
    }

    public function getToken(): Token
    {
        return $this->token;
    }
}
