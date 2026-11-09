<?php

declare(strict_types=1);

namespace Nyholm\Psr7;

use Psr\Http\Message\StreamInterface;
use Symfony\Component\Debug\ErrorHandler as SymfonyLegacyErrorHandler;
use Symfony\Component\ErrorHandler\ErrorHandler as SymfonyErrorHandler;

if (\PHP_VERSION_ID >= 70400 || (new \ReflectionMethod(StreamInterface::class, '__toString'))->hasReturnType()) {
    /**
     * @internal
     */
    trait Stream_Trait
    {
        public function __toString(): string
        {
            if ($this->isSeekable()) {
                $this->seek(0);
            }

            return $this->getContents();
        }
    }
} else {
    /**
     * @internal
     */
    trait Stream_Trait
    {
        /**
         * @return string
         */
        public function __toString()
        {
            try {
                if ($this->isSeekable()) {
                    $this->seek(0);
                }

                return $this->getContents();
            } catch (\Throwable $e) {
                if (\is_array($error_handler = \set_error_handler('var_dump'))) {
                    $error_handler = $error_handler[0] ?? null;
                }
                \restore_error_handler();

                if ($e instanceof \Error || $error_handler instanceof SymfonyErrorHandler || $error_handler instanceof SymfonyLegacyErrorHandler) {
                    return \trigger_error((string) $e, \E_USER_ERROR);
                }

                return '';
            }
        }
    }
}
