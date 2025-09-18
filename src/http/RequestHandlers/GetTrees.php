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
 * webtrees MCP server
 *
 * A webtrees(https://webtrees.net) 2.2 custom module to provide an MCP API for webtrees
 * 
 */


declare(strict_types=1);

namespace Jefferson49\Webtrees\Module\McpApi\Http\RequestHandlers;

use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Services\GedcomImportService;
use Fisharebest\Webtrees\Services\TreeService;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;


class GetTrees implements RequestHandlerInterface
{
    #[OA\Get(
        path: '/trees',
        responses: [
            new OA\Response(
                response: '200',
                description: 'A list of the available trees', 
                content: new OA\MediaType(
                    mediaType: 'application/json',
                    schema: new OA\Schema(
                        type: 'array', 
                        items: new OA\Items(ref: TreeItem::class),
                    ),
                ),
            ),
            new OA\Response(response: '401', description: 'Unauthorized: Missing authorization header or bearer token.'),
            new OA\Response(response: '403', description: 'Unauthorized: Insufficient permissions.'),
        ]
    )]
    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
    */	
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $tree_service = new TreeService(new GedcomImportService());
        $trees = $tree_service->all();
        $tree_list =[];

        foreach ($trees as $tree) {
            $tree_list[] = new TreeItem(
                name: $tree->name(),
                title: $tree->title(),
            );
        }

        return response(json_encode($tree_list), StatusCodeInterface::STATUS_OK);
    }
}

#[OA\Schema(
    title: 'TreeItem',
    description: 'A tree item with name and title',
)]
class TreeItem
{
    public function __construct(string $name, string $title) {
        $this->name = $name;
        $this->title = $title;
    }
    
    #[OA\Property(property: 'name', type: 'string', description: 'The name of the tree')]
    public string $name;
    #[OA\Property(property: 'title', type: 'string', description: 'The title of the tree')]
    public string $title;
}
