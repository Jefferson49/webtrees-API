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
 * ExportAction
 *
 * The action to perform when exporting a tree from webtrees
 */

#[OA\Schema(
    title: 'export_action',
    description: 'The action to perform when exporting a tree from webtrees',
    type: 'string',
    enum: self::SCHEMA_ENUM_VALUES,
    default: self::ACTION_DOWNLOAD,
    additionalProperties: false,
)]
class ExportAction
{
    // Code from: Jefferson49\Webtrees\Module\ExtendedImportExport\DownloadGedcomWithURL
    public const string ACTION_DOWNLOAD = 'download';
    public const string ACTION_SAVE     = 'save';
    public const string ACTION_BOTH     = 'both';
    public const string ACTION_GEDBAS   = 'gedbas';

    public const array SCHEMA_ENUM_VALUES = [
        self::ACTION_DOWNLOAD,
        self::ACTION_SAVE,
        self::ACTION_BOTH,
        self::ACTION_GEDBAS,
    ];
}
