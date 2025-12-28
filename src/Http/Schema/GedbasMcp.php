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


/**
 * Mcp
 *
 * Schemas for MCP desciriptions
 */

class GedbasMcp extends Mcp
{
    const array ID = [
        'type' => 'string',
        'description' => 'The GEDBAS ID of a person',
        'pattern' => '^[0-9]{1,12}$',
        'maxLength' => 12,
    ];

    const array UID = [
        'type' => 'string',
        'description' => 'The Unique Identifier (UID) of a person',
    ];

    const array PERSON_PROPERTY = [
        "type"=> "object",
        "properties"=> [
            "Type"=> [
                "type"=> "string"
            ],
            "Value"=> [
                "type"=> "string"
            ],
            "Date"=> [
                "type"=> "string"
            ],
            "Place"=> [
                "type"=> "string"
            ]
        ]
    ];

    const array PARENT = [
        "type"=> "object",
        "properties"=> [
            "parent"=> [
                "type"=> "string"
            ],
            "name"=> [
                "type"=> "string"
            ],
            "id"=> self::ID,
        ]
    ];

    const array SPOUSE = [
        "type"=> "object",
        "properties"=> [
            "name"=> [
                "type"=> "string"
            ],
            "id"=> self::ID,
        ]
    ];

    const array CHILD = [
        "type"=> "object",
        "properties"=> [
            "name"=> [
                "type"=> "string"
            ],
            "birthdate"=> [
                "type"=> "string"
            ],
            "id"=> self::ID,
        ]
    ];

    const array FAMILY = [
        "type"=> "object",
        "properties"=> [
            "mariage"=> [
                "type"=> "object",
                "properties"=> [
                    "Date"=> [
                        "type"=> "string"
                    ],
                    "Place"=> [
                        "type"=> "string"
                    ]
                ]
            ],
            "spouse"=> self::SPOUSE,
            "children"=> [
                "type"=> "array",
                "items"=> self::CHILD,
            ]
        ]
    ];

}
