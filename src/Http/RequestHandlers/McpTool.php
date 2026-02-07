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
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Middleware\McpProtocol;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\RequestHandlers\AddChildToFamily;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\RequestHandlers\AddChildToIndividual;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\RequestHandlers\AddParentToIndividual;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\RequestHandlers\AddSpouseToFamily;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\RequestHandlers\AddSpouseToIndividual;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\RequestHandlers\AddUnlinkedRecord;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\RequestHandlers\Gedbas\PersonData;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\RequestHandlers\Gedbas\SearchSimple;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\RequestHandlers\GetRecord;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\RequestHandlers\LinkChildToFamily;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\RequestHandlers\LinkSpouseToIndividual;
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

use Exception;
use Throwable;


class McpTool implements RequestHandlerInterface
{
    private ResponseFactoryInterface  $response_factory;
    private StreamFactoryInterface    $stream_factory;
    private ModuleService             $module_service;


    public function __construct(
        ResponseFactoryInterface $response_factory, 
        StreamFactoryInterface $stream_factory, 
        ModuleService $module_service 
    ) {
        $this->response_factory   = $response_factory;
        $this->stream_factory     = $stream_factory;
        $this->module_service     = $module_service;
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
            $int_id    = Validator::parsedBody($request)->integer('id', McpProtocol::MCP_ID_DEFAULT);
            $string_id = Validator::parsedBody($request)->string('id', (string) McpProtocol::MCP_ID_DEFAULT);

            $id = ($string_id !== (string) McpProtocol::MCP_ID_DEFAULT) ? $string_id : $int_id;

            $payload = [
                'jsonrpc' => McpProtocol::JSONRPC_VERSION,
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
        /** @var CustomModuleLogInterface $log_module */
        $log_module = $this->module_service->findByName(WebtreesApi::activeModuleName());
        CustomModuleLog::addDebugLog($log_module, 'request' . ': ' . $request->getBody()->__toString());

        $mcp_tool_interface = Validator::attributes($request)->string('mcp_tool_interface', '');
        $int_id             = Validator::parsedBody($request)->integer('id', McpProtocol::MCP_ID_DEFAULT);
        $string_id          = Validator::parsedBody($request)->string('id', (string) McpProtocol::MCP_ID_DEFAULT);
        $tool_name          = Validator::parsedBody($request)->string('name', McpProtocol::MCP_TOOL_NAME_DEFAULT);
        $arguments          = Validator::parsedBody($request)->array('arguments');

        $id = ($string_id !== (string) McpProtocol::MCP_ID_DEFAULT) ? $string_id : $int_id;
        

        $request = new ServerRequest(method: 'GET', uri: '')
            ->withAttribute('mcp_tool_interface', $mcp_tool_interface)
            ->withQueryParams($arguments);
            
        if ($mcp_tool_interface === WebtreesMcpToolRequestHandlerInterface::class) {
            switch ($tool_name) {
                case WebtreesApi::PATH_GET_RECORD:
                    $handler = Registry::container()->get(GetRecord::class);
                    return $this->handleMcpTool($id, $request, $handler);
                case WebtreesApi::PATH_MODIFY_RECORD:
                    $handler = Registry::container()->get(ModifyRecord::class);
                    return $this->handleMcpTool($id, $request, $handler);
                case WebtreesApi::PATH_SEARCH_GENERAL:
                    $handler = Registry::container()->get(SearchGeneral::class);
                    return $this->handleMcpTool($id, $request, $handler);
                case WebtreesApi::PATH_GET_TREES:
                    $handler = Registry::container()->get(Trees::class);
                    return $this->handleMcpTool($id, $request, $handler);
                case WebtreesApi::PATH_GET_VERSION:
                    $handler = Registry::container()->get(WebtreesVersion::class);
                    return $this->handleMcpTool($id, $request, $handler);
                case WebtreesApi::PATH_ADD_UNLINKED_RECORD:
                    $handler = Registry::container()->get(AddUnlinkedRecord::class);
                    return $this->handleMcpTool($id, $request, $handler);
                case WebtreesApi::PATH_ADD_CHILD_TO_FAMILY:
                    $handler = Registry::container()->get(AddChildToFamily::class);
                    return $this->handleMcpTool($id, $request, $handler);
                case WebtreesApi::PATH_ADD_CHILD_TO_INDI:
                    $handler = Registry::container()->get(AddChildToIndividual::class);
                    return $this->handleMcpTool($id, $request, $handler);
                case WebtreesApi::PATH_ADD_PARENT_TO_INDI:
                    $handler = Registry::container()->get(AddParentToIndividual::class);
                    return $this->handleMcpTool($id, $request, $handler);
                case WebtreesApi::PATH_ADD_SPOUSE_TO_FAMILY:
                    $handler = Registry::container()->get(AddSpouseToFamily::class);
                    return $this->handleMcpTool($id, $request, $handler);
                case WebtreesApi::PATH_ADD_SPOUSE_TO_INDI:
                    $handler = Registry::container()->get(AddSpouseToIndividual::class);
                    return $this->handleMcpTool($id, $request, $handler);
                case WebtreesApi::PATH_LINK_CHILD_TO_FAMILY:
                    $handler = Registry::container()->get(LinkChildToFamily::class);
                    return $this->handleMcpTool($id, $request, $handler);
                case WebtreesApi::PATH_LINK_SPOUSE_TO_INDI:
                    $handler = Registry::container()->get(LinkSpouseToIndividual::class);
                    return $this->handleMcpTool($id, $request, $handler);
                default:
                    return Registry::responseFactory()->response(McpProtocol::payloadMethodUnknown($id), StatusCodeInterface::STATUS_OK, ['content-type' => 'application/json']);
            }
        }
        elseif ($mcp_tool_interface === GedbasMcpToolRequestHandlerInterface::class) {
            switch ($tool_name) {
                case WebtreesApi::PATH_GEDBAS_SEARCH_SIMPLE:
                    $handler = Registry::container()->get(SearchSimple::class);
                    return $this->handleMcpTool($id, $request, $handler);
                case WebtreesApi::PATH_GEDBAS_PERSON_DATA:
                    $handler = Registry::container()->get(PersonData::class);
                    return $this->handleMcpTool($id, $request, $handler);
                default:
                    return Registry::responseFactory()->response(McpProtocol::payloadMethodUnknown($id), StatusCodeInterface::STATUS_OK, ['content-type' => 'application/json']);
            }
        }

        return Registry::responseFactory()->response(McpProtocol::payloadMethodUnknown($id), StatusCodeInterface::STATUS_OK, ['content-type' => 'application/json']);
    }

	/** 
     * @param int|string                     $id              The MCP tool call ID
     * @param ServerRequestInterface         $request
     * @param McpToolRequestHandlerInterface $handler
     *
     * @return ResponseInterface
     */	
    private function handleMcpTool(int|string $id, ServerRequestInterface $request, McpToolRequestHandlerInterface $handler): ResponseInterface
    {
        // Create response
        return $this->response_factory->createResponse()
            ->withBody($this->toolResult($id, $handler->handle($request)))
            ->withHeader('content-type', 'application/json; charset=' . UTF8::NAME);
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
                'jsonrpc' => McpProtocol::JSONRPC_VERSION,
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
