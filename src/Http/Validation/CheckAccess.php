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

namespace Jefferson49\Webtrees\Module\WebtreesApi\Http\Validation;

use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Contracts\UserInterface;
use Fisharebest\Webtrees\GedcomRecord;
use Fisharebest\Webtrees\Http\Exceptions\HttpNotFoundException;
use Fisharebest\Webtrees\Http\Exceptions\HttpAccessDeniedException;
use Fisharebest\Webtrees\Tree;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response200;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response403;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response404;
use Psr\Http\Message\ResponseInterface;


class CheckAccess
{
	/**
     * Check record access
     * 
     * @param GedcomRecord $record
     *
     * @return ResponseInterface
     */	
    public static function checkRecordAccess(GedcomRecord $record): ResponseInterface {

        if ($record === null) {
            return new Response404('Record not found');
        }

        try {
            $record = Auth::checkRecordAccess($record, true);
        } catch (HttpNotFoundException | HttpAccessDeniedException $e) {
            return new Response403('Insufficient permissions: No access to record.');
        }

        return new Response200();
    }

	/**
     * Check whether the user has appropriate write access for API operations
     * 
     * @param Tree $tree
     *
     * @return ResponseInterface
     */	
    public static function checkUserWriteAccess(Tree $tree): ResponseInterface {
    
        if (Auth::isModerator($tree)) {
            return new Response403('Insufficient permissions: API users must not have moderator rights');
        }        

        if (!Auth::isEditor($tree)) {
            return new Response403('Insufficient permissions: API user does not have editor rights for the tree.');
        }

        if (Auth::user()->getPreference(UserInterface::PREF_AUTO_ACCEPT_EDITS) === '1') {
            return new Response403('Insufficient permissions: Automatically accept changes must be activated for the API user.');
        }

        return new Response200();
    }
}
