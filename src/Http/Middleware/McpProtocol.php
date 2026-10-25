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
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Validator;
use Jefferson49\Webtrees\Log\CustomModuleLog;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\RequestHandlers\Gedbas\GedbasMcpToolRequestHandlerInterface;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\RequestHandlers\WebtreesMcpToolRequestHandlerInterface;
use Jefferson49\Webtrees\Module\WebtreesApi\Mcp\Errors;
use Jefferson49\Webtrees\Module\WebtreesApi\Mcp\McpLogger;
use Jefferson49\Webtrees\Module\WebtreesApi\Mcp\McpToolReferenceHandler;
use Jefferson49\Webtrees\Module\WebtreesApi\WebtreesApi;
use Mcp\Server;
use Mcp\Server\Session\FileSessionLock;
use Mcp\Server\Session\FileSessionStore;
use Mcp\Server\Transport\Http\Middleware\PassthroughMiddleware;
use Mcp\Server\Transport\StreamableHttpTransport;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use Throwable;

use function Jefferson49\Webtrees\Module\WebtreesApi\Helpers\api_response;


/**
 * Middleware to handle the MCP protocol with the MCP SDK (mcp/sdk)
 *
 * The MCP SDK processes the MCP protocol and calls the request handlers of the MCP tools.
 * The web server is managed by webtrees, i.e. the MCP SDK is only used to handle the MCP protocol.
 */
class McpProtocol implements MiddlewareInterface
{
    public const string JSONRPC_VERSION = '2.0';
    public const int    MCP_ID_DEFAULT  = -1;

    // Folder for the MCP sessions (relative to the webtrees data folder)
    public const string SESSION_FOLDER = 'mcp-sessions';

    // Directories with the MCP tools (relative to the module folder)
    private const string  WEBTREES_TOOLS_DIR   = 'src/Http/RequestHandlers';
    private const string  GEDBAS_TOOLS_DIR     = 'src/Http/RequestHandlers/Gedbas';
    private const string  GEDBAS_TOOLS_SUBFOLDER = 'Gedbas';

    public function __construct(
        private ResponseFactoryInterface $response_factory,
        private StreamFactoryInterface   $stream_factory,
    ) {
    }

    /**
     * Process the MCP protocol
     *
     * @param ServerRequestInterface  $request
     * @param RequestHandlerInterface $handler
     *
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        /** @var WebtreesApi $webtrees_api */
        $webtrees_api = Registry::container()->get(WebtreesApi::class);

        // Read the request body for logging and rewind, because the MCP SDK needs to read it again
        $body = $request->getBody();
        $raw_body = (string) $body;

        if ($body->isSeekable()) {
            $body->rewind();
        }

        CustomModuleLog::addDebugLog($webtrees_api, 'MCP request' . ': ' . $raw_body);

        try {
            $mcp_tool_interface = Validator::attributes($request)->string('mcp_tool_interface', '');
            $logger             = new McpLogger($webtrees_api);

            // The tools of the MCP server are discovered from the attributes of the request handlers
            [$scan_dirs, $exclude_dirs] = match ($mcp_tool_interface) {
                WebtreesMcpToolRequestHandlerInterface::class => [[self::WEBTREES_TOOLS_DIR], [self::GEDBAS_TOOLS_SUBFOLDER]],
                GedbasMcpToolRequestHandlerInterface::class   => [[self::GEDBAS_TOOLS_DIR], []],
                default                                       => [[], []],
            };

            if ($scan_dirs === []) {
                return api_response('Not Found', StatusCodeInterface::STATUS_NOT_FOUND);
            }

            $session_dir = rtrim(str_replace('\\', '/', Registry::filesystem()->dataName()), '/') . '/' . self::SESSION_FOLDER;

            $server = Server::builder()
                ->setServerInfo('webtrees MCP Server', $webtrees_api->customModuleVersion())
                ->setLogger($logger)
                ->setSession(new FileSessionStore($session_dir, logger: $logger))
                ->setSessionLock(new FileSessionLock($session_dir, logger: $logger))
                ->setReferenceHandler(new McpToolReferenceHandler($request, $webtrees_api))
                ->setDiscovery(dirname(__DIR__, 3), $scan_dirs, $exclude_dirs)
                ->build();

            // The web server is managed by webtrees. Therefore, CORS and host validation of the MCP SDK are not used
            $transport = new StreamableHttpTransport(
                $request,
                $this->response_factory,
                $this->stream_factory,
                $logger,
                [new PassthroughMiddleware()],
            );

            return $server->run($transport);
        }
        catch (Throwable $th) {
            CustomModuleLog::addDebugLog($webtrees_api, 'Error in class ' . substr(strrchr(get_class($this), '\\'), 1) . ' : ' . $th->getMessage());

            $payload = [
                'jsonrpc' => self::JSONRPC_VERSION,
                'id'      => json_decode($raw_body, true)['id'] ?? null,
                'error'   => [
                    'code'    => Errors::INTERNAL_ERROR,
                    'message' => StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR . ': ' . substr($th->getMessage(), 0, 512)
                ]
            ];

            return api_response($payload, StatusCodeInterface::STATUS_OK);
        }
    }
}
