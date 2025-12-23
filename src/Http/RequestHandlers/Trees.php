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
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\GedcomImportService;
use Fisharebest\Webtrees\Services\TreeService;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response401;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response403;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response406;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response429;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response500;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Schema\TreeItem;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use Throwable;


class Trees implements WebtreesMcpToolRequestHandlerInterface
{
    #[OA\Get(
        path: '/trees',
        tags: ['webtrees'],
        responses: [
            new OA\Response(
                response: '200',
                description: 'A list of the available trees',
                content: new OA\MediaType(
                    mediaType: 'application/json',
                    schema: new OA\Schema(
                        type: 'object',
                        properties: [
                            new OA\Property(
                                property: 'trees',
                                type: 'array', 
                                items: new OA\Items(ref: TreeItem::class),
                            ),
                        ],
                        required: ['trees'],
                    ),
                ),
            ),
            new OA\Response(
                response: '401', 
                description: 'Unauthorized: Missing authorization header or bearer token.',
                ref: Response401::class,
            ),
            new OA\Response(
                response: '403', 
                description: 'Unauthorized: Insufficient permissions.',
                ref: Response403::class,
            ),
            new OA\Response(
                response: '406', 
                description: 'Not acceptable',
                ref: Response406::class,
            ),
            new OA\Response(
                response: '429', 
                description: 'Too many requests',
                ref: Response429::class,
            ),
            new OA\Response(
                response: '500', 
                description: 'Internal server error',
                ref: Response429::class,
            ),
        ]
    )]
	/**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */	
    public function handle(ServerRequestInterface $request): ResponseInterface {
        try {
            return $this->trees($request);        
        }
        catch (Throwable $th) {
            return new Response500($th->getMessage());
        }
    }

	/**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */	
    private function trees(ServerRequestInterface $request): ResponseInterface
    {
        $tree_service = new TreeService(new GedcomImportService());
        $trees = $tree_service->all();
        $tree_list = [];

        foreach ($trees as $tree) {
            $tree_list[] = new TreeItem(
                id: $tree->id(),
                name: $tree->name(),
                title: $tree->title(),
                media_directory: $tree->getPreference(setting_name: 'MEDIA_DIRECTORY'),
                imported: $tree->getPreference(setting_name: 'imported') ? 'yes' : 'no'
            );
        }

        return Registry::responseFactory()->response(json_encode(['trees' => $tree_list]), StatusCodeInterface::STATUS_OK);
    }

    /**
     * The tool description for the MCP protocol provided as an array (which can be converted to JSON)
     * 
     * @return string
     */	    
    public static function getMcpToolDescription(): array
    {
        return [
            'name' => 'get-trees',
            'description' => 'Get a list of the available trees [API: GET /trees]',
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
                                'id' => [
                                    'type' => 'integer'
                                ],
                                'name' => [
                                    'type' => 'string'
                                ],
                                'title' => [
                                    'type' => 'string'
                                ],
                                'media_directory' => [
                                    'type' => 'string'
                                ],
                                'imported' => [
                                    'type' => 'string'
                                ],
                            ],
                            'required' => ['id', 'name', 'title', 'media_directory', 'imported'],
                        ],
                    ],
                ],
                'required' => ['trees'],
            ],                       
            'annotations' => [
                'title' => 'get-trees',
                'readOnlyHint' => true,
                'destructiveHint' => false,
                'idempotentHint' => true,
                'openWorldHint' => true,
                'deprecated' => false
            ],
        ];
    }
}
