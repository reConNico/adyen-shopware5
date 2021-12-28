<?php

declare(strict_types=1);

namespace AdyenPayment\Certificate\Mapper;

use Monolog\Logger;
use Symfony\Component\HttpFoundation\Response;

final class HttpCodeToLogLevel implements HttpCodeToLogLevelInterface
{
    public function __invoke(int $statusCode): int
    {
        if (in_array($statusCode, [Response::HTTP_BAD_REQUEST, Response::HTTP_NOT_FOUND], true)) {
            return Logger::ERROR;
        }

        if (Response::HTTP_INTERNAL_SERVER_ERROR === $statusCode) {
            return Logger::CRITICAL;
        }

        return Logger::INFO;
    }
}
