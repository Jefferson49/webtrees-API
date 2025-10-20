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
use Fisharebest\Webtrees\Html;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\ModuleService;
use Fisharebest\Webtrees\Validator;
use Jefferson49\Webtrees\Module\WebtreesApi\WebtreesApi;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\RequestHandlers\GedcomData;
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


class Mcp implements RequestHandlerInterface
{
    private string                   $webtrees_api_version;
    private ResponseFactoryInterface $response_factory;
    private StreamFactoryInterface   $stream_factory;
    private ModuleService            $module_service;

    const JSON_RPC_VERSION = '2.0';
    const MCP_ID_DEFAULT = -1;
    const MCP_ERROR_CODE_METHOD_NOT_FOUND  = -32601;
    const MCP_ERROR_TEXT_METHOD_NOT_FOUND = 'The method does not exist / is not available.';

    public function __construct(ResponseFactoryInterface $response_factory, StreamFactoryInterface $stream_factory, ModuleService $module_service)
    {
        $this->response_factory = $response_factory;
        $this->stream_factory   = $stream_factory;
        $this->module_service   = $module_service;

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
        $protocolVersion = Validator::parsedBody($request)->string('protocolVersion', '2024-11-05');
        $id              = Validator::parsedBody($request)->integer('id', 0);
        $method          = Validator::parsedBody($request)->string('method', 'unknown');
        $tool_name       = Validator::parsedBody($request)->string('name', 'unknown');
        $arguments       = Validator::parsedBody($request)->array('arguments');

        $arguments['id'] = $id;
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
                switch ($tool_name) {
                    case 'get-gedcom-data':
                        $handler = Registry::container()->get(GedcomData::class);
                        return $this->response_factory->createResponse()
                            ->withBody($this->toolResult($handler->handle($request), $id))
                            ->withHeader('content-type', 'application/json; charset=' . UTF8::NAME);
                    case 'get-search-general':
                        $handler = Registry::container()->get(SearchGeneral::class);
                        return $this->response_factory->createResponse()
                            ->withBody($this->toolResult($handler->handle($request), $id))
                            ->withHeader('content-type', 'application/json; charset=' . UTF8::NAME);
                    case 'get-trees':
                        $handler = Registry::container()->get(Trees::class);
                        return $this->response_factory->createResponse()
                            ->withBody($this->toolResult($handler->handle($request), $id))
                            ->withHeader('content-type', 'application/json; charset=' . UTF8::NAME);
                    case 'get-version':
                        $handler = Registry::container()->get(WebtreesVersion::class);
                        return $this->response_factory->createResponse()
                            ->withBody($this->toolResult($handler->handle($request), $id))
                            ->withHeader('content-type', 'application/json; charset=' . UTF8::NAME);
                    default:
                        return response($this->payloadToolUnknown($id), StatusCodeInterface::STATUS_OK, ['content-type' => 'application/json']);
                }
            default:
                return Registry::responseFactory()->response($this->payloadMethodUnknown($id), StatusCodeInterface::STATUS_OK, ['content-type' => 'application/json']);
        }
    }

	/**
     * @param int $id
     * @param string $protocolVersion
     *
     * @return string
     */	
    private function payloadInitialize(int $id, string $protocolVersion): string 
    {
        $payload = [
            'jsonrpc' => self::JSON_RPC_VERSION,
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
     * @param int $id
     *
     * @return string
     */	
    private function payloadNotificationsInitialized(int $id): string 
    {
        $payload = [
            'jsonrpc' => self::JSON_RPC_VERSION,
            'id' => $id,
            'result' => null
        ];

        return json_encode($payload);        
    }
    
    /**
     * @param int $id
     *
     * @return string
     */	
    private function payloadToolUnknown(int $id): string
    {
        $payload = [
            'jsonrpc' => self::JSON_RPC_VERSION,
            'id' => $id,
            'error' => [
                'code'    => self::MCP_ERROR_CODE_METHOD_NOT_FOUND,
                'message' => self::MCP_ERROR_TEXT_METHOD_NOT_FOUND,
            ]
        ];

        return json_encode($payload);        
    }

    /**
     * @param int $id
     *
     * @return string
     */	
    private function payloadMethodUnknown(int $id): string
    {
        $payload = [
            'jsonrpc' => self::JSON_RPC_VERSION,
            'id' => $id,
            'error' => [
                'code'    => self::MCP_ERROR_CODE_METHOD_NOT_FOUND,
                'message' => self::MCP_ERROR_TEXT_METHOD_NOT_FOUND,
            ]
        ];

        return json_encode($payload);        
    }

    /**
     * @param int $id
     *
     * @return string
     */	
    private function payloadToolsList(int $id): string
    {
        $payload = [
            'jsonrpc' => self::JSON_RPC_VERSION,
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
        return [
            [
                'name' => 'get-gedcom-data',
                'description' => 'GET /gedcom-data [API: GET /gedcom-data]',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'tree' => [
                            'type' => 'string',
                            'description' => 'The name of the tree. (in: query)',
                            'maxLength' => 1024,
                            'pattern' => WebtreesApi::REGEX_FILE_NAME,
                        ],
                        'xref' => [
                            'type' => 'string',
                            'description' => 'The XREF (i.e. GEDOM cross-reference identifier) of the record to retrieve. (in: query)',
                            'maxLength' => 20,
                            'pattern' => '^[A-Za-z0-9:_.-][1,20]$'
                        ],
                        'format' => [
                            'type' => 'string',
                            'description' => 'The format of the output. Possible values are "gedcom" (GEDCOM 5.5.1), "gedcom-x" (default; a JSON GEDCOM format defined by Familysearch), and "json" (identical to gedcom-x). (in: query)',
                            'enum' => ['gedcom', 'gedcom-x', 'json'],
                            'default' => 'gedcom-x'
                        ]
                    ],
                    'required' => ['tree', 'xref']
                ],
                'outputSchema' => [
                    'type' => 'object',
                ],
                'annotations' => [
                    'title' => 'GET /gedcom-data',
                    'readOnlyHint' => true,
                    'destructiveHint' => false,
                    'idempotentHint' => true,
                    'openWorldHint' => true,
                    'deprecated' => false
                ]
            ],
            [
                'name' => 'get-search-general',
                'description' => 'GET /search-general [API: GET /search-general]',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'tree' => [
                            'type' => 'string',
                            'description' => 'The name of the tree. If not provided, all trees will be searched. (in: query)',
                            'maxLength' => 1024,
                            'pattern' => WebtreesApi::REGEX_FILE_NAME,
                        ],
                        'query' => [
                            'type' => 'string',
                            'description' => 'The search query. (in: query)',
                            'maxLength' => 8192
                        ]
                    ],
                    'required' => ['query']
                ],
				'outputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'records' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'tree' => [
                                        'type' => 'string'
                                    ],
                                    'xref' => [
                                        'type' => 'string'
                                    ],
                                ],
                                'required' => ['tree', 'xref'],
                            ],
                        ],
                    ],
                    'required' => ['records'],
                ],                       
                'annotations' => [
                    'title' => 'GET /search-general',
                    'readOnlyHint' => true,
                    'destructiveHint' => false,
                    'idempotentHint' => true,
                    'openWorldHint' => true,
                    'deprecated' => false
                ]
            ],
            [
                'name' => 'get-trees',
                'description' => 'GET /trees [API: GET /trees]',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => (object)[],
                    'required' => []
                ],
				'outputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'trees' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'name' => [
                                        'type' => 'string'
                                    ],
                                    'title' => [
                                        'type' => 'string'
                                    ],
                                ],
                                'required' => ['name', 'title'],
                            ],
                        ],
                    ],
                    'required' => ['trees'],
                ],                       
                'annotations' => [
                    'title' => 'GET /trees',
                    'readOnlyHint' => true,
                    'destructiveHint' => false,
                    'idempotentHint' => true,
                    'openWorldHint' => true,
                    'deprecated' => false
                ]
            ],
            [
                'name' => 'get-version',
                'description' => 'GET /version [API: GET /version]',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => (object)[],
                    'required' => []
                ],
				'outputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'version' => [
                            'type' => 'string',
                        ],
                    ],
                    'required' => ['version'],
                ],
                'annotations' => [
                    'title' => 'GET /version',
                    'readOnlyHint' => true,
                    'destructiveHint' => false,
                    'idempotentHint' => true,
                    'openWorldHint' => true,
                    'deprecated' => false
                ]
            ]
        ];
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
     * @param ResponseInterface $response  The response from an API request
     * @param int               $id        The id of the MCP tool call
     * 
     * @return StreamInterface
     */	
    private function toolResult(ResponseInterface $response, int $id): StreamInterface
    {
        $status_code    = $response->getStatusCode();
        $reason_phrase  = $response->getReasonPhrase();
        $content_stream = $response->getBody();

        // In case of an error
        if ($status_code !== StatusCodeInterface::STATUS_OK) {
            $payload = [
                'jsonrpc' => self::JSON_RPC_VERSION,
                'id' => $id,
                'result' => [
                    'content' => [
                        'type'=> 'text',
                        'text'=> $status_code . ': ' . $reason_phrase,
                    ],
                ],
                "isError" => true,
            ];

            return $this->stream_factory->createStream(json_encode($payload));
        }

        else {
            $output_stream = $this->stream_factory->createStream('');

            $output_stream->write('{"jsonrpc": "2.0","id": ' . $id . ',"result": {"content": [{"type": "text", "text": ""');

            $output_stream->write('}],"structuredContent":');

            // Copy content from the source stream to the destination stream
            !$content_stream->rewind();
            while (!$content_stream->eof()) {
                // Read in chunks (e.g., 8KB)
                $content = $content_stream->read(8192);
                $output_stream->write($content);
            }

            $json3 = ',"isError": false}}';
            $output_stream->write($json3);

            // Rewind the destination stream to read its content
            $output_stream->rewind();

            return $output_stream;
        }        
    }
}
