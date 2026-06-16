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
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

use Throwable;

use function Jefferson49\Webtrees\Module\WebtreesApi\Helpers\api_response;


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

            return api_response($payload, StatusCodeInterface::STATUS_OK);
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
        $scopes             = Validator::attributes($request)->array('oauth_scopes');
        $int_id             = Validator::parsedBody($request)->integer('id', McpProtocol::MCP_ID_DEFAULT);
        $string_id          = Validator::parsedBody($request)->string('id', (string) McpProtocol::MCP_ID_DEFAULT);
        $tool_name          = Validator::parsedBody($request)->string('name', McpProtocol::MCP_TOOL_NAME_DEFAULT);
        $arguments          = Validator::parsedBody($request)->array('arguments');

        $id = ($string_id !== (string) McpProtocol::MCP_ID_DEFAULT) ? $string_id : $int_id;
        

        $request = new ServerRequest(method: 'GET', uri: '')
            ->withAttribute('mcp_tool_interface', $mcp_tool_interface)
            ->withAttribute('oauth_scopes', $scopes)
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
                case WebtreesApi::PATH_DELETE_RECORD:
                    $handler = Registry::container()->get(DeleteRecord::class);
                    return $this->handleMcpTool($id, $request, $handler);
                case WebtreesApi::PATH_LINK_CHILD_TO_FAMILY:
                    $handler = Registry::container()->get(LinkChildToFamily::class);
                    return $this->handleMcpTool($id, $request, $handler);
                case WebtreesApi::PATH_LINK_SPOUSE_TO_INDI:
                    $handler = Registry::container()->get(LinkSpouseToIndividual::class);
                    return $this->handleMcpTool($id, $request, $handler);
                default:
                    return api_response(McpProtocol::payloadMethodUnknown($id), StatusCodeInterface::STATUS_OK);
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
                    return api_response(McpProtocol::payloadMethodUnknown($id), StatusCodeInterface::STATUS_OK);
            }
        }

        return api_response(McpProtocol::payloadMethodUnknown($id), StatusCodeInterface::STATUS_OK);
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
            ->withBody(McpProtocol::toolResult($id, $handler->handle($request)))
            ->withHeader('content-type', 'application/json');
    }
}
