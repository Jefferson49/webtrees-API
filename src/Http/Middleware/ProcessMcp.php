<?php

/**
 * webtrees: online genealogy
 * Copyright (C) 2025 webtrees development team
 *                    <http://webtrees.net>
 *
 * CustomModuleManager (webtrees custom module):
 * Copyright (C) 2025 Markus Hemprich
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

namespace Jefferson49\Webtrees\Module\WebtreesApi\Http\Middleware;

use Fig\Http\Message\StatusCodeInterface;
use Fig\Http\Message\RequestMethodInterface;
use Fisharebest\Webtrees\Registry;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\RequestHandlers\Mcp;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response405;
use Jefferson49\Webtrees\Module\WebtreesApi\Mcp\Errors;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Middleware to restrict access to administrators.
 */
class ProcessMcp implements MiddlewareInterface
{
    /**
     * A middleware to authorize access to the API
     *
     * @param ServerRequestInterface  $request
     * @param RequestHandlerInterface $handler
     *
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {   
        //If GET request, handle the request
        if ($request->getMethod() === RequestMethodInterface::METHOD_GET) {
            return $handler->handle($request);
        }

        //If POST request, convert to a GET request with modified parameters
        elseif ($request->getMethod() === RequestMethodInterface::METHOD_POST) {

            $body= json_decode($request->getBody()->getContents(), true);

            // If JSON parse error
            if ($body === null) {
                $payload = [
                    'jsonrpc' => Mcp::JSONRPC_VERSION,
                    'id'      => Mcp::MCP_ID_DEFAULT,
                    'error' => [
                        'code'    => Errors::PARSE_ERROR,
                        'message' => Errors::getMcpErrorMessage(Errors::PARSE_ERROR),
                    ],
                ];

                return Registry::responseFactory()->response(
                    json_encode($payload), 
                    StatusCodeInterface::STATUS_OK, 
                    ['content-type' => 'application/json']);
            }

            $id     = $body['id'] ?? Mcp::MCP_ID_DEFAULT;
            $method = $body['method'] ?? Mcp::MCP_METHOD_DEFAULT;
            $params = $body['params'] ?? [];

            $params['id']     = $id;
            $params['method'] = $method;

            $request = $request->withParsedBody($params)->withMethod(RequestMethodInterface::METHOD_GET);
            return $handler->handle($request);
        }

        //For all other request methods, return 405 Method Not Allowed
        else {
            return new Response405();
        }
    }
}
