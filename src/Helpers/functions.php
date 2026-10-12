<?php

/**
 * webtrees: online genealogy
 * Copyright (C) 2026 webtrees development team
 *                    <http://webtrees.net>
 *
 * CustomModuleManager (webtrees custom module):
 * Copyright (C) 2026 Markus Hemprich
 *                    <http://www.familienforschung-hemprich.de>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 *
 * webtrees API
 *
 * A webtrees(https://webtrees.net) 2.2 custom module to provide an API for webtrees
 *
 */

declare(strict_types=1);

namespace Jefferson49\Webtrees\Module\WebtreesApi\Helpers;

use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Webtrees;
use Jefferson49\Webtrees\Helpers\Functions;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ResponseFactoryInterface as PSR17ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;



/**
 * Create a response.
 *
 * @param array<mixed>|object|string $content
 * @param int                        $code
 * @param array<string>              $headers
 *
 * @return ResponseInterface
 */
function api_response(array|object|string $content = '', int $code = StatusCodeInterface::STATUS_OK, array $headers = []): ResponseInterface
{
    // Avoid that webtrees response will return 204 STATUS_NO_CONTENT in case of an empty content
    if ($content === '' && $code === StatusCodeInterface::STATUS_OK) {
        $content = 'OK';
    }

    // As a default, webtrees-API returns text/plain. Avoid that webtrees response will return text/HTML as content-type
    if (is_string($content)) {
        $headers['content-type'] ??= 'text/plain';
    }

    if (version_compare(Webtrees::VERSION, '2.3.0', '>=')) {

        //Since webtrees >= 2.3 uses a specific ENUM type for HTTP status codes, we need to modify the response function

        $response_factory = Functions::getFromContainer(PSR17ResponseFactoryInterface::class);
        $stream_factory   = Functions::getFromContainer(StreamFactoryInterface::class);

        //Code from: Fisharebest\Webtrees\Factories\ResponseFactory
        //Last check: 2026-10-11
        if (!is_string($content)) {
            $content = json_encode($content, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            $headers['content-type'] ??= 'application/json';
        }

        $stream = $stream_factory->createStream($content);

        $response = $response_factory
            ->createResponse($code)
            ->withBody($stream);

        foreach ($headers as $key => $value) {
            $response = $response->withHeader($key, $value);
        }

        return $response;
    }
    else {
        return Registry::responseFactory()->response($content, $code, $headers);
    }
}
