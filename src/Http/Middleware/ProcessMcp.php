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

namespace Jefferson49\Webtrees\Module\WebtreesApi\Http\Middleware;

use Fig\Http\Message\StatusCodeInterface;
use Fig\Http\Message\RequestMethodInterface;
use Jefferson49\Webtrees\Helpers\Functions;
use Jefferson49\Webtrees\Log\CustomModuleLog;
use Jefferson49\Webtrees\Log\CustomModuleLogInterface;
use Jefferson49\Webtrees\Module\WebtreesApi\WebtreesApi;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Middleware\McpProtocol;
use Jefferson49\Webtrees\Module\WebtreesApi\Mcp\Errors;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function Jefferson49\Webtrees\Module\WebtreesApi\Helpers\api_response;


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
        // Read the incoming request body once and reuse it for logging and decoding, because reading the content several times might modify it
        $raw_body = $request->getBody()->getContents();

        /** @var CustomModuleLogInterface $log_module */
        $log_module = Functions::getFromContainer(WebtreesApi::class);
        CustomModuleLog::addDebugLog($log_module, 'MCP request' . ': ' . $raw_body);

        //If POST request, convert to a GET request with modified parameters
        if ($request->getMethod() === RequestMethodInterface::METHOD_POST) {
            $trimmed_body = trim($raw_body);

            // Detect clearly invalid payloads before JSON decoding.
            if ($trimmed_body === '') {
                CustomModuleLog::addDebugLog($log_module, 'JSON parse error' . ': empty request body');
            }

            if (str_starts_with($trimmed_body, "\xEF\xBB\xBF")) {
                CustomModuleLog::addDebugLog($log_module, 'JSON parse error' . ': UTF-8 BOM detected in request body');
            }

            if (!mb_check_encoding($trimmed_body, 'UTF-8')) {
                CustomModuleLog::addDebugLog($log_module, 'JSON raw body is not valid UTF-8' . ': ' . bin2hex($trimmed_body));
            }

            if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $trimmed_body)) {
                CustomModuleLog::addDebugLog($log_module, 'JSON parse error' . ': control character detected in request body');
            }

            $body = json_decode($trimmed_body, true);

            // If JSON parse error
            if ($trimmed_body === '' OR $body === null) {
				// Log error
				CustomModuleLog::addDebugLog($log_module, 'JSON parse error' . ': ' . json_last_error_msg() . ' | body: ' . $trimmed_body);

                $payload = [
                    'jsonrpc' => McpProtocol::JSONRPC_VERSION,
                    'id'      => McpProtocol::MCP_ID_DEFAULT,
                    'error' => [
                        'code'    => Errors::PARSE_ERROR,
                        'message' => Errors::getMcpErrorMessage(Errors::PARSE_ERROR),
                    ],
                ];

                return api_response($payload, StatusCodeInterface::STATUS_OK);
            }

            // If we do not receive a valid JSON-RPC request or notification, respond with bad request
            // For example, we might receive a JSON-RPC response
            if (!isset($body['method'])) {
				// Log error
				CustomModuleLog::addDebugLog($log_module, 'JSON-RPC request does not contain a MCP method');

                return api_response('Bad Request', StatusCodeInterface::STATUS_BAD_REQUEST);
            }

            // If the JSON-RPC request does not contain the content type "application/json" in the header, return unsupported media type
            if (!str_contains($request->getHeaderLine('content-type'), 'application/json')) {
				// Log error
				CustomModuleLog::addDebugLog($log_module, 'JSON-RPC request does not contain the content type "application/json" in the header');

                return api_response('Unsupported Media Type', StatusCodeInterface::STATUS_UNSUPPORTED_MEDIA_TYPE);
            }

            $id     = $body['id']     ?? McpProtocol::MCP_ID_DEFAULT;
            $method = $body['method'] ?? McpProtocol::MCP_METHOD_DEFAULT;
            $params = $body['params'] ?? [];

            $params['id']     = $id;
            $params['method'] = $method;

            // Proceed to the next middleware/request handler
            $request = $request->withParsedBody($params)->withMethod(RequestMethodInterface::METHOD_GET);
            return $handler->handle($request);
        }

        //For all other request methods, return 405 Method Not Allowed
        else {
            return api_response('Method Not Allowed', StatusCodeInterface::STATUS_METHOD_NOT_ALLOWED);
        }
    }
}
