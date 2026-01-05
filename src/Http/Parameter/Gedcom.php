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

namespace Jefferson49\Webtrees\Module\WebtreesApi\Http\Parameter;

use Jefferson49\Webtrees\Module\WebtreesApi\Http\Schema\Gedcom as GedcomSchema; 
use OpenApi\Attributes as OA;


/**
 * GEDCOM
 *
 * A GEDCOM data snippet
 */

#[OA\Parameter(
    name: 'gedcom',
    in: 'query',
    description: self::GEDCOM_DESCRIPTION,
    required: false,
    schema: new OA\Schema(
        ref: GedcomSchema::class,
    ),
),]
class Gedcom
{
    const string GEDCOM_DESCRIPTION = 'The GEDCOM text, which shall be added to the newly created record. The GEDCOM text must not contain a level 0 line, because it is created automatically. "\n" or "%OA" will be detected as line break.';
}
