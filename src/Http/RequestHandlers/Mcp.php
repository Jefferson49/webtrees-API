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

namespace Jefferson49\Webtrees\Module\WebtreesApi\Http\RequestHandlers;

use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Encodings\UTF8;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\ModuleService;
use Fisharebest\Webtrees\Validator;
use Jefferson49\Webtrees\Log\CustomModuleLog;
use Jefferson49\Webtrees\Log\CustomModuleLogInterface;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\RequestHandlers\Gedbas\GedbasMcpToolRequestHandlerInterface;
use Jefferson49\Webtrees\Module\WebtreesApi\Mcp\Errors;
use Jefferson49\Webtrees\Module\WebtreesApi\WebtreesApi;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\RequestHandlers\AddUnlinkedRecord;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\RequestHandlers\Gedbas\PersonData;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\RequestHandlers\Gedbas\SearchSimple;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\RequestHandlers\GetRecord;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\RequestHandlers\ModifyRecord;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\RequestHandlers\SearchGeneral;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\RequestHandlers\Trees;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\RequestHandlers\WebtreesVersion;
use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

use DateTime;
use Exception;
use ReflectionClass;
use Throwable;


class Mcp implements RequestHandlerInterface
{
    private string                    $webtrees_api_version;
    private ResponseFactoryInterface  $response_factory;
    private StreamFactoryInterface    $stream_factory;
    private ModuleService             $module_service;
    protected string                  $mcp_tool_interface;

    public const LATEST_PROTOCOL_VERSION  = '2025-03-26';
    public const DEFAULT_PROTOCOL_VERSION = '2024-11-05';
    public const JSONRPC_VERSION = '2.0';
    public const MCP_ID_DEFAULT        = -1;
    public const MCP_METHOD_DEFAULT    = 'unknown';
    public const MCP_TOOL_NAME_DEFAULT = 'unknown';

    public function __construct(
        ResponseFactoryInterface $response_factory, 
        StreamFactoryInterface $stream_factory, 
        ModuleService $module_service, 
        string $mcp_tool_interface = WebtreesMcpToolRequestHandlerInterface::class)
    {
        $this->response_factory   = $response_factory;
        $this->stream_factory     = $stream_factory;
        $this->module_service     = $module_service;
        $this->mcp_tool_interface = $mcp_tool_interface;

        //$module_service = New ModuleService();
        /** @var WebtreesApi $webtrees_api To avoid IDE warnings */
        $webtrees_api = $this->module_service->findByName(module_name: WebtreesApi::activeModuleName());

        $this->webtrees_api_version = $webtrees_api->customModuleVersion();
    }

	/**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */	
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        try {
            return $this->handleMcpRequest($request);        
        }
        catch (Throwable $th) {
            $int_id    = Validator::parsedBody($request)->integer('id', Mcp::MCP_ID_DEFAULT);
            $string_id = Validator::parsedBody($request)->string('id', (string) Mcp::MCP_ID_DEFAULT);

            $id = ($string_id !== (string) Mcp::MCP_ID_DEFAULT) ? $string_id : $int_id;

            $payload = [
                'jsonrpc' => self::JSONRPC_VERSION,
                'id' => $id,
                'error' => [
                    'code'    => Errors::INTERNAL_ERROR,
                    'message' => StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR . ': ' . substr($th->getMessage(), 0, 512)
                ]
            ];

            return Registry::responseFactory()->response(
                json_encode($payload), 
                StatusCodeInterface::STATUS_OK, 
                ['content-type' => 'application/json']
            );
        }
    }   

	/**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */	
    public function handleMcpRequest(ServerRequestInterface $request): ResponseInterface
    {   
        /** @var CustomModuleLogInterface $log_module To avoid IDE warnings */
        $log_module = $this->module_service->findByName(WebtreesApi::activeModuleName());
        CustomModuleLog::addDebugLog($log_module, 'request' . ': ' . $request->getBody()->__toString());

        $protocolVersion = Validator::parsedBody($request)->string('protocolVersion', self::DEFAULT_PROTOCOL_VERSION);
        $int_id          = Validator::parsedBody($request)->integer('id', Mcp::MCP_ID_DEFAULT);
        $string_id       = Validator::parsedBody($request)->string('id', (string) Mcp::MCP_ID_DEFAULT);
        $method          = Validator::parsedBody($request)->string('method', self::MCP_METHOD_DEFAULT);
        $tool_name       = Validator::parsedBody($request)->string('name', self::MCP_TOOL_NAME_DEFAULT);
        $arguments       = Validator::parsedBody($request)->array('arguments');

        $id = ($string_id !== (string) Mcp::MCP_ID_DEFAULT) ? $string_id : $int_id;

        $request = new ServerRequest(method: 'GET', uri: '')
            ->withQueryParams($arguments);

        switch ($method) {
            case 'initialize':
                return Registry::responseFactory()->response($this->payloadInitialize($id, $protocolVersion), StatusCodeInterface::STATUS_OK, ['content-type' => 'application/json']);
            case 'notifications/initialized':
                return Registry::responseFactory()->response($this->payloadNotificationsInitialized($id), StatusCodeInterface::STATUS_ACCEPTED, ['content-type' => 'application/json']);
            case 'tools/list':
                return Registry::responseFactory()->response($this->payloadToolsList($id), StatusCodeInterface::STATUS_OK, ['content-type' => 'application/json']);
            case 'tools/call':
                if ($this->mcp_tool_interface === WebtreesMcpToolRequestHandlerInterface::class) {
                    switch ($tool_name) {
                        case 'get-record':
                            $handler = Registry::container()->get(GetRecord::class);
                            return $this->handleMcpTool($id, $request, $handler);
                        case 'modify-record':
                            $handler = Registry::container()->get(ModifyRecord::class);
                            return $this->handleMcpTool($id, $request, $handler);
                        case 'search-general':
                            $handler = Registry::container()->get(SearchGeneral::class);
                            return $this->handleMcpTool($id, $request, $handler);
                        case 'get-trees':
                            $handler = Registry::container()->get(Trees::class);
                            return $this->handleMcpTool($id, $request, $handler);
                        case 'get-version':
                            $handler = Registry::container()->get(WebtreesVersion::class);
                            return $this->handleMcpTool($id, $request, $handler);
                        case 'add-unlinked-record':
                            $handler = Registry::container()->get(AddUnlinkedRecord::class);
                            return $this->handleMcpTool($id, $request, $handler);
                        case 'add-child-to-family':
                            $handler = Registry::container()->get(AddChildToFamily::class);
                            return $this->handleMcpTool($id, $request, $handler);
                        default:
                            return Registry::responseFactory()->response($this->payloadMethodUnknown($id), StatusCodeInterface::STATUS_OK, ['content-type' => 'application/json']);
                    }
                }
                elseif ($this->mcp_tool_interface === GedbasMcpToolRequestHandlerInterface::class) {
                    switch ($tool_name) {
                        case 'search-simple':
                            $handler = Registry::container()->get(SearchSimple::class);
                            return $this->handleMcpTool($id, $request, $handler);
                        case 'get-person-data':
                            $handler = Registry::container()->get(PersonData::class);
                            return $this->handleMcpTool($id, $request, $handler);
                        default:
                            return Registry::responseFactory()->response($this->payloadMethodUnknown($id), StatusCodeInterface::STATUS_OK, ['content-type' => 'application/json']);
                    }
                }
            default:
                return Registry::responseFactory()->response($this->payloadMethodUnknown($id), StatusCodeInterface::STATUS_OK, ['content-type' => 'application/json']);
        }
    }

	/**
     * @param int|string                     $id       The MCP tool call ID
     * @param ServerRequestInterface         $request
     * @param McpToolRequestHandlerInterface $handler
     *
     * @return ResponseInterface
     */	
    private function handleMcpTool(int|string $id, ServerRequestInterface $request, McpToolRequestHandlerInterface $handler): ResponseInterface
    {
        //Create response
        return $this->response_factory->createResponse()
            ->withBody($this->toolResult($id, $handler->handle($request)))
            ->withHeader('content-type', 'application/json; charset=' . UTF8::NAME);
    }

	/**
     * @param int|string $id
     * @param string     $protocolVersion
     *
     * @return string
     */	
    private function payloadInitialize(int|string $id, string $protocolVersion): string 
    {
        //Check protocol version
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $protocolVersion) !== 1) {
            $protocolVersion = self::LATEST_PROTOCOL_VERSION;
        }
        else {
            $protocolVersion = new DateTime(self::LATEST_PROTOCOL_VERSION) === new DateTime($protocolVersion)
                //If the protocol versions are identical, use it
                ? $protocolVersion
                //If protocol versions do not match, use the latest protocol version
                : self::LATEST_PROTOCOL_VERSION;
        }

        $payload = [
            'jsonrpc' => self::JSONRPC_VERSION,
            'id' => $id,
            'result' => [
                'protocolVersion' => $protocolVersion,
                'capabilities' => [
                    'tools' => [
                        "listChanged" => false,
                    ],
                ],
                'serverInfo' => $this->getServerInfo(),
            ]
        ];

        return json_encode($payload);
    }

    /**
     * @param int|string $id
     *
     * @return string
     */	
    private function payloadNotificationsInitialized(int|string $id): string 
    {
        $payload = [
            'jsonrpc' => self::JSONRPC_VERSION,
            'id' => $id,
            'result' => null
        ];

        return json_encode($payload);        
    }

    /**
     * @param int|string $id
     *
     * @return string
     */	
    private function payloadMethodUnknown(int|string $id): string
    {
        $payload = [
            'jsonrpc' => self::JSONRPC_VERSION,
            'id' => $id,
            'error' => [
                'code'    => Errors::METHOD_NOT_FOUND,
                'message' => Errors::getMcpErrorMessage(Errors::METHOD_NOT_FOUND),
            ]
        ];

        return json_encode($payload);        
    }

    /**
     * @param int|string $id
     *
     * @return string
     */	
    private function payloadToolsList(int|string $id): string
    {
        $payload = [
            'jsonrpc' => self::JSONRPC_VERSION,
            'id' => $id,
            'result' => [
                'tools' => $this->getTools(),
            ],
        ];
        
        return json_encode($payload);
    }

    /**
     * @return array
     */	
    private function getTools(): array
    {
        // Load all request handler classes including those implementing the MCP tool interface
        foreach (array_merge(glob(__DIR__ . '/' . '*.php'), glob(__DIR__ . '/Gedbas/' . '*.php')) as $file) {
            require_once $file;
        }

        $tools = [];
        $tool_descriptions = [];

        // Find all classes implementing an MCP tool request handler interface
        foreach (get_declared_classes() as $class) {
            if (strpos($class, __NAMESPACE__ ) === 0) { // Check if the class is in the namespace
                $reflection = new ReflectionClass($class);
                if ($reflection->implementsInterface($this->mcp_tool_interface)) { // Check if it implements the interface
                    $tools[] = $class;
                }
            }
        }

        // Get the tool descriptions
        foreach ($tools as $tool) {
            $tool_descriptions[] = $tool::getMcpToolDescription();
        }

        return $tool_descriptions;
    }

    /**
     * @return array
     */	
    private function getServerInfo(): array    
    {
        return [
            'name' => 'webtrees MCP Server',
            'version' => $this->webtrees_api_version,
        ];
    }

    /**
     * Get the JSON for an MCP tool response
     * 
     * @param int|string        $id        The id of the MCP tool call
     * @param ResponseInterface $response  The response from an API request
     * 
     * @return StreamInterface
     */	
    private function toolResult(int|string $id, ResponseInterface $response): StreamInterface
    {
        $status_code    = $response->getStatusCode();
        $reason_phrase  = $response->getReasonPhrase();
        $content_stream = $response->getBody();
        $success_codes  = [
            StatusCodeInterface::STATUS_OK,
            StatusCodeInterface::STATUS_CREATED,
        ];

        // In case of an error
        if ($status_code === StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR) {
            throw new Exception($reason_phrase);
        }
        elseif (!in_array($status_code, $success_codes)) {
            $payload = [
                'jsonrpc' => self::JSONRPC_VERSION,
                'id' => $id,
                'result' => [
                    'content' => [
                        '0' => [
                            'type'=> 'text',
                            'text'=> $status_code . ': ' . $reason_phrase,
                        ],
                    ],
                    'isError' => true,
                ],
            ];

            return $this->stream_factory->createStream(json_encode($payload));
        }

        else {
            $output_stream = $this->stream_factory->createStream('');

            $string_id = is_string($id) ? '"' . $id . '"' : (string) $id;

            $output_stream->write('{"jsonrpc": "2.0","id": ' . $string_id . ',"result": {"content": [{"type": "text", "text":');

            // Copy content from the source stream to a string
            $content = '';
            $content_stream->rewind();
            while (!$content_stream->eof()) {
                // Read in chunks (8 kB)
                $content .= $content_stream->read(8192);
            }

            // Properly escape the content to be used in JSON
            $escaped_content = json_encode($content, JSON_UNESCAPED_UNICODE);

            // Write to the text representation in the output stream
            $output_stream->write($escaped_content);
            $output_stream->write('}]');

            $output_stream->write(',"structuredContent":');

            // If valid JSON, write the content to the structured content representation in the output stream
            if (json_validate($content)) {
                $output_stream->write($content);
            }
            // Else write the content as text to the structured content representation in the output stream
            else {
                $output_stream->write('{"type": "text", "text":');
                $output_stream->write($escaped_content);
                $output_stream->write('}');
            }

            $json3 = ',"isError": false}}';
            $output_stream->write($json3);

            // Rewind the destination stream to read its content
            $output_stream->rewind();

            return $output_stream;
        }        
    }
}
