<?php

declare(strict_types=1);

namespace Qubus\View;

use function count;
use function implode;
use function is_array;
use function is_int;
use function sprintf;
use function str_replace;

final class TokenStream
{
    protected array $tokens;
    protected Token $currentToken;
    protected array $queue;
    protected int $cursor;
    protected bool $eos;

    public function __construct(array $tokens)
    {
        $this->tokens = $tokens;
        $this->currentToken = new Token(Token::EOF, null, 1, 1);
        $this->queue = [];
        $this->cursor = 0;
        $this->eos = false;
        $this->next();
    }

    public function next(): Token
    {
        if ($this->eos) {
            return $this->currentToken;
        }

        $token = $this->tokens[$this->cursor++];

        $old = $this->currentToken;

        $this->currentToken = $token;

        $this->eos = $token->getType() === Token::EOF;

        return $old;
    }

    public function look(int $t = 1): Token
    {
        $t--;
        $length = count($this->tokens);
        if ($this->cursor + $t > $length) {
            $t = 0;
        }
        if ($this->cursor + $t < 0) {
            $t = -$this->cursor;
        }
        return $this->tokens[$this->cursor + $t];
    }

    public function skip(int $times = 1): TokenStream
    {
        for ($i = 0; $i < $times; $i++) {
            $this->next();
        }
        return $this;
    }

    /**
     * @throws SyntaxErrorException
     */
    public function expect($primary, $secondary = null): Token
    {
        $token = $this->getCurrentToken();
        if (null === $secondary && ! is_int($primary)) {
            $secondary = $primary;
            $primary = Token::NAME;
        }
        if (! $token->test($primary, $secondary)) {
            if (null === $secondary) {
                $expecting = Token::getTypeError($primary);
            } elseif (is_array($secondary)) {
                $expecting = '"' . implode('" or "', $secondary) . '"';
            } else {
                $expecting = '"' . $secondary . '"';
            }
            if ($token->getType() === Token::EOF) {
                throw new SyntaxErrorException('unexpected end of file', $token);
            } else {
                throw new SyntaxErrorException(
                    sprintf(
                        'unexpected "%s", expecting %s',
                        str_replace("\n", '\n', $token->getValue()),
                        $expecting
                    ),
                    $token
                );
            }
        }
        $this->next();
        return $token;
    }

    /**
     * @throws SyntaxErrorException
     */
    public function expectTokens($tokens): TokenStream
    {
        foreach ($tokens as $token) {
            $this->expect($token->getType(), $token->getValue());
        }
        return $this;
    }

    public function test($primary, $secondary = null): bool
    {
        return $this->getCurrentToken()->test($primary, $secondary);
    }

    /**
     * @throws SyntaxErrorException
     */
    public function consume($primary, $secondary = null): bool
    {
        if ($this->test($primary, $secondary)) {
            $this->expect($primary, $secondary);
            return true;
        } else {
            return false;
        }
    }

    public function isEOS(): bool
    {
        return $this->eos;
    }

    public function getCurrentToken(): Token
    {
        return $this->currentToken;
    }

    public function getTokens(): array
    {
        return $this->tokens;
    }
}
