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
 * WebtreesVersionItem
 *
 * webtrees version
 */

#[OA\Schema(
    title: 'WebtreesVersionItem',
    description: 'webtrees version',
    additionalProperties: false,
)]
class WebtreesVersionItem
{
    public function __construct(string $version) {
        $this->version = $version;
    }
    
    #[OA\Property(
        property: 'version', 
        type: 'string', 
        description: 'webtrees version',
        maxLength: 100,
        pattern: "^[0-9]+\.[0-9]+(\.[0-9]+)?(-[A-Za-z0-9]+)?$",
        example: '1.2.3'
    )]
    public string $version;
}
