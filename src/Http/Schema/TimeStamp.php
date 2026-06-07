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

use Jefferson49\Webtrees\Module\ExtendedImportExport\DownloadGedcomWithURL;
use OpenApi\Attributes as OA;


/**
 * Time stamp
 *
 * The type of time stamp, which is added to the filename of an exported GEDCOM file.
 */

#[OA\Schema(
    title: 'timestamp',
    description: 'The type of time stamp, which is added to the filename of an exported GEDCOM file',
    type: 'string',
    enum: self::SCHEMA_ENUM_VALUES,
    default: self::TIME_STAMP_NONE,
    additionalProperties: false,
)]
class TimeStamp
{
    // Code from: Jefferson49\Webtrees\Module\ExtendedImportExport\DownloadGedcomWithURL    
    public const string TIME_STAMP_NONE   = 'none';
    public const string TIME_STAMP_PREFIX = 'prefix';
    public const string TIME_STAMP_POSTFIX = 'postfix';

    public const array SCHEMA_ENUM_VALUES = [
        self::TIME_STAMP_NONE,
        self::TIME_STAMP_PREFIX,
        self::TIME_STAMP_POSTFIX,
    ];
}
