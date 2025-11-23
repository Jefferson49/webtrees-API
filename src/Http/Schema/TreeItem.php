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

namespace Jefferson49\Webtrees\Module\WebtreesApi\Http\Schema;

use OpenApi\Attributes as OA;


/**
 * TreeItem
 *
 * A tree item with id, name, title, media directory, and imported status
 */

#[OA\Schema(
    title: 'TreeItem',
    description: 'A tree item with id, name, title, media directory, and imported status',
    additionalProperties: false,
)]
class TreeItem
{
    public function __construct(int $id, string $name, string $title, string $media_directory, string $imported) {
        $this->id              = $id;
        $this->name            = $name;
        $this->title           = $title;
        $this->media_directory = $media_directory; 
        $this->imported        = $imported;
    }
    
    #[OA\Property(
        property: 'id', 
        type: 'integer', 
        description: 'The ID of the tree',
    )]
    public int $id;

    #[OA\Property(
        property: 'name', 
        type: 'string', 
        description: 'The name of the tree',
        maxLength: 1024,
        pattern: "^[^<>:\"/\\|?*\r\n]+$",
        example: 'mytree',
    )]
    public string $name;

    #[OA\Property(
        property: 'title', 
        type: 'string', 
        description: 'The title of the tree',
        maxLength: 1024,
        example: 'My Family Tree',
    )]
    public string $title;

    #[OA\Property(
        property: 'media_directory', 
        type: 'string', 
        description: 'The media directory of the tree',
        maxLength: 1024,
        example: 'media/',
    )]
    public string $media_directory;

    #[OA\Property(
        property: 'imported', 
        type: 'string', 
        description: 'Whether the tree has already been imported',
        example: 'yes',
        )]
    public string $imported;
}
