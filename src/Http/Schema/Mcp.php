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

use Fisharebest\Webtrees\Elements\PedigreeLinkageType;
use Fisharebest\Webtrees\Family;
use Fisharebest\Webtrees\Gedcom;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Media;
use Fisharebest\Webtrees\Note;
use Fisharebest\Webtrees\Repository;
use Fisharebest\Webtrees\Source;
use Fisharebest\Webtrees\Submitter;
use Jefferson49\Webtrees\Module\WebtreesApi\WebtreesApi;


/**
 * Mcp
 *
 * Schemas for MCP desciriptions
 */

class Mcp
{
    const string APPEND  = 'append';
    const string PREPEND = 'prepend';

    const array TREE = [
        'type' => 'string',
        'description' => 'The name of a webtrees tree.',
        'maxLength' => 1024,
        'pattern' => '^' . WebtreesApi::REGEX_FILE_NAME . '$',
    ];

    const array XREF =  [
        'type' => 'string',
        'description' => 'A GEDCOM cross-reference identifier (XREF).',
        'maxLength' => 20,
        'pattern' => '^' . Gedcom::REGEX_XREF .'$'
    ];
    const array GEDCOM = [
        'type' => 'string',
        'description' => 'GEDCOM text in accordance to the GEDCOM standard. The GEDCOM text must not contain a level 0 line, because it is created automatically. "\n" or "%OA" will be detected as line break.',
        'default' => '',
        'example' => '1 NOTE A record created by the webtrees API.\n1 NOTE Read description about line breaks.',
    ];

    const array RECORD_TYPE = [
        'type' => 'string',
        'description' => 'The type of the GEDCOM record to create.',
        'enum' => [ 
            Family::RECORD_TYPE, 
            Individual::RECORD_TYPE, 
            Media::RECORD_TYPE, 
            Note::RECORD_TYPE, 
            Repository::RECORD_TYPE, 
            Source::RECORD_TYPE, 
            Submitter::RECORD_TYPE
        ],
        'maxLength' => 4,
        'pattern' => '^' . Gedcom::REGEX_TAG .'$',
    ];

    const array GEDCOM_FORMAT = [
        'type' => 'string',
        'description' => 'The format of the GEDCOM data. Possible values are "gedcom" (GEDCOM 5.5.1), "gedcom-record" (single GEDCOM 5.5.1 record) "gedcom-x" (default; a JSON GEDCOM format defined by Familysearch), and "json" (identical to gedcom-x).',
        'enum' => ['gedcom', 'gedcom-record', 'gedcom-x', 'json'],
        'default' => 'gedcom-x'
    ];

    const array  RELATIONSHIP_ENUM = [
        PedigreeLinkageType::VALUE_ADOPTED,
        PedigreeLinkageType::VALUE_BIRTH,
        PedigreeLinkageType::VALUE_FOSTER,
        PedigreeLinkageType::VALUE_SEALING,
        PedigreeLinkageType::VALUE_RADA,
    ];

    
	/**
     * An MCP tool schema with a certain description.
     * 
     * @return string
     */	    
    public static function withDescription(array $schema, string $description, string $method = ''): array
    {
        switch ($method) {
            case self::PREPEND:
                $schema['description'] = $description . ' ' . $schema['description'];
                break;
            case self::APPEND:
                $schema['description'] .=  ' ' . $description;
                break;
            default:
                $schema['description'] = $description;
        }

        return $schema;
    } 
}
