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

namespace Jefferson49\Webtrees\Module\WebtreesApi\Mcp;

use Fig\Http\Message\RequestMethodInterface;
use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Validator;
use Jefferson49\Webtrees\Log\CustomModuleLog;
use Jefferson49\Webtrees\Log\CustomModuleLogInterface;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Middleware\McpToolPermission;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\RequestHandlers\McpToolRequestHandlerInterface;
use Mcp\Capability\Registry\ElementReference;
use Mcp\Capability\Registry\ReferenceHandlerInterface;
use Mcp\Capability\Registry\ToolReference;
use Mcp\Exception\ToolCallException;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Result\CallToolResult;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use stdClass;


/**
 * Calls the request handlers of webtrees API for MCP tool calls, which are received from the MCP SDK.
 *
 * The request handlers of webtrees can only be called with HTTP GET requests. Therefore, the arguments
 * of the MCP tool call (received via POST) are converted to query parameters of a GET request.
 */
class McpToolReferenceHandler implements ReferenceHandlerInterface
{
    public function __construct(
        private ServerRequestInterface $request,
        private CustomModuleLogInterface $log_module,
    ) {
    }

    /**
     * {@inheritDoc}
     *
     * @see ReferenceHandlerInterface::handle()
     */
    public function handle(ElementReference $reference, array $arguments): mixed
    {
        if (!$reference instanceof ToolReference || !is_array($reference->handler)) {
            throw new ToolCallException('Unsupported MCP element');
        }

        $tool_name     = $reference->tool->name;
        $handler_class = $reference->handler[0];

        if (!is_string($handler_class) || !is_subclass_of($handler_class, McpToolRequestHandlerInterface::class)) {
            throw new ToolCallException('Unknown MCP tool: ' . $tool_name);
        }

        // Authorize the tool call
        $scopes        = Validator::attributes($this->request)->array('oauth_scopes');
        $access_denied = McpToolPermission::authorize($tool_name, $scopes);

        if ($access_denied !== null) {
            CustomModuleLog::addDebugLog($this->log_module, 'MCP tool access denied: ' . $tool_name);

            throw new ToolCallException(StatusCodeInterface::STATUS_FORBIDDEN . ': ' . $access_denied);
        }

        // Remove internal arguments of the MCP SDK
        $tool_arguments = array_filter($arguments, fn (string|int $key): bool => !str_starts_with((string) $key, '_'), ARRAY_FILTER_USE_KEY);

        // Convert the POST request to a GET request, which can be handled by the webtrees request handlers
        $tool_request = $this->request
            ->withQueryParams($tool_arguments)
            ->withMethod(RequestMethodInterface::METHOD_GET);

        $handler = Registry::container()->get($handler_class);

        return $this->toolResult($handler->handle($tool_request));
    }

    /**
     * Convert a response of a request handler to an MCP tool result
     *
     * @param ResponseInterface $response
     *
     * @return CallToolResult
     */
    private function toolResult(ResponseInterface $response): CallToolResult
    {
        $status_code = $response->getStatusCode();
        $body        = $response->getBody();

        $body->rewind();
        $content = $body->getContents();

        if (!in_array($status_code, [StatusCodeInterface::STATUS_OK, StatusCodeInterface::STATUS_CREATED], true)) {
            CustomModuleLog::addDebugLog($this->log_module, 'MCP error response: ' . $response->getReasonPhrase() . ' ' . $content);

            return CallToolResult::error([new TextContent($status_code . ': ' . $response->getReasonPhrase() . ' ' . $content)]);
        }

        // Use JSON objects as structured content. Otherwise, wrap the content as text
        $decoded            = json_validate($content) ? json_decode($content) : null;
        $structured_content = $decoded instanceof stdClass ? $decoded : ['type' => 'text', 'text' => $content];

        CustomModuleLog::addDebugLog($this->log_module, 'MCP response: ' . mb_substr($content, 0, 1024));

        return new CallToolResult([new TextContent($content)], false, $structured_content);
    }
}
