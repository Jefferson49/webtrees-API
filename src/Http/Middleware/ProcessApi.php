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

use Fig\Http\Message\RequestMethodInterface;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response400;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response405;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;


/**
 * Middleware to restrict access to administrators.
 */
class ProcessApi implements MiddlewareInterface
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

        //If POST request, convert to a GET request
        elseif ($request->getMethod() === RequestMethodInterface::METHOD_POST) {

            $params  = $request->getQueryParams();
            $content = $request->getBody()->getContents();
            $body    = json_decode($content, true);

            //If JSON parse error, return "400 Bad request"
            if ($content !== '' && $body === null) {
                return new Response400();
            }

            $request = $request->withQueryParams($params)->withParsedBody($body)->withMethod(RequestMethodInterface::METHOD_GET);
            return $handler->handle($request);
        }

        //For all other request methods, return "405 Method Not Allowed"
        else {
            return new Response405();
        }
    }
}
