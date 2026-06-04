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

namespace Jefferson49\Webtrees\Module\WebtreesApi\Http\Parameter;

use OpenApi\Attributes as OA;


/**
 * Note
 *
 * A note for a GEDCOM record
 */

#[OA\Parameter(
    name: 'note',
    in: 'query',
    description: self::GEDCOM_DESCRIPTION,
    required: false,
    schema: new OA\Schema(
        type: 'string',
    ),
),]
class Note
{
    const string GEDCOM_DESCRIPTION = 'Text of a note. In case of a NOTE record, the note text will be added after the level 0 NOTE tag. For all other record types, the text will be added as a level 1 NOTE structure.';
}
