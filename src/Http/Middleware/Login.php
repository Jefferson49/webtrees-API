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

namespace Jefferson49\Webtrees\Module\WebtreesApi\Http\Middleware;

use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Contracts\UserInterface;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Validator;
use Fisharebest\Webtrees\Services\UserService;
use Fisharebest\Webtrees\Session;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response500;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use Throwable;


/**
 * A middleware to login into webtrees
 */
class Login implements MiddlewareInterface
{
    /**
     * A middleware to login into webtrees
     *
     * @param ServerRequestInterface  $request
     * @param RequestHandlerInterface $handler
     *
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {   
        $oauth_user_id = Validator::attributes($request)->string('oauth_user_id');

        $user_service = new UserService();
        $user_id = (int) $oauth_user_id;
        $api_user = $user_service->find($user_id);

        // Login the user
        Auth::login($api_user);
        Session::put('language', Auth::user()->getPreference(UserInterface::PREF_LANGUAGE));

        // Allow request handlers, modules, etc. to have a dependency on the current user.
        Registry::container()->set(UserInterface::class, $api_user);

        $request = $request->withAttribute('user', $api_user);

        // Create the response
        try {
            $response = $handler->handle($request);
        }
        catch (Throwable $th) {

            // Always logout in order to fail gracefully with log out of user
            Auth::logout();
            return new Response500($th->getMessage());
        }

        // Log out
        Auth::logout();

        return $response;
    }
}
