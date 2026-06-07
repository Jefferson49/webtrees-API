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

use Fisharebest\Webtrees\Encodings\ANSEL;
use Fisharebest\Webtrees\Encodings\ASCII;
use Fisharebest\Webtrees\Encodings\CP437;
use Fisharebest\Webtrees\Encodings\CP850;
use Fisharebest\Webtrees\Encodings\ISO88591;
use Fisharebest\Webtrees\Encodings\ISO88592;
use Fisharebest\Webtrees\Encodings\MacRoman;
use Fisharebest\Webtrees\Encodings\UTF16BE;
use Fisharebest\Webtrees\Encodings\UTF16LE;
use Fisharebest\Webtrees\Encodings\UTF8;
use Fisharebest\Webtrees\Encodings\Windows1250;
use Fisharebest\Webtrees\Encodings\Windows1251;
use Fisharebest\Webtrees\Encodings\Windows1252;
use OpenApi\Attributes as OA;


/**
 * ImportEncoding
 *
 * A character encoding for a GEDCOM file
 */

#[OA\Schema(
    title: 'import_encoding',
    description: 'The character encoding of a GEDCOM file',
    type: 'string',
    enum: self::SCHEMA_ENUM_VALUES,
    default: UTF8::NAME,
    additionalProperties: false,
)]
class ImportEncoding
{
    // Code from: Fisharebest\Webtrees\Factories\EncodingFactory
    public const array SCHEMA_ENUM_VALUES = [
        UTF8::NAME,
        UTF16BE::NAME,
        UTF16LE::NAME,
        ANSEL::NAME,
        ASCII::NAME,
        ISO88591::NAME,
        ISO88592::NAME,
        Windows1250::NAME,
        Windows1251::NAME,
        Windows1252::NAME,
        CP437::NAME,
        CP850::NAME,
        MacRoman::NAME,
    ];
}
