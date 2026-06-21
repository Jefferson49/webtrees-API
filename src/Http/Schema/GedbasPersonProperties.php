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

namespace Jefferson49\Webtrees\Module\WebtreesApi\Http\Schema;

use OpenApi\Attributes as OA;


/**
 * GedbasPersonProperty
 *
 * Properties of persons used within GEDBAS search results
 */

#[OA\Schema(
    title: 'GedbasPersonProperty', 
    description: 'Properties of persons used within GEDBAS search results.',
    additionalProperties: false,
)]
class GedbasPersonProperties
{
    #[OA\Property(
        property: 'Type', 
        description: 'The type of a person`s characteristic or event',
        type: 'string',
    )]
    public string $type;
    
    #[OA\Property(
        property: 'Value', 
        description: 'The type of a person`s characteristic or event',
        type: 'string',
    )]
    public string $value;

    #[OA\Property(
        property: 'Date', 
        description: 'The date of a person`s characteristic or event',
        type: 'string',
    )]
    public string $date;

    #[OA\Property(
        property: 'Place', 
        description: 'The place of a person`s characteristic or event',
        type: 'string',
    )]
    public string $place;

    #[OA\Property(
        property: 'Source IDs', 
        description: 'A list with the IDs of the sources, which are related to a person`s characteristic or event',
        type: 'array',
        items: new OA\Items(
            type: 'string',
        )
    )]
    public array $source_IDs;
}
