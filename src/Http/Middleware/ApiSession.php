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
use Fisharebest\Webtrees\Session;
use Fisharebest\Webtrees\SessionDatabaseHandler;
use Fisharebest\Webtrees\Validator;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Response\Response500;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use Throwable;

use function array_map;
use function explode;
use function implode;
use function parse_url;
use function rawurlencode;
use function session_name;
use function session_register_shutdown;
use function session_set_save_handler;
use function session_start;

use const PHP_URL_PATH;
use const PHP_URL_SCHEME;

/**
 * A middleware to create a specific session for API access
 */
class ApiSession extends Session implements MiddlewareInterface
{
    private const string SESSION_NAME        = 'WT2_API_SESSION';
    private const string SECURE_SESSION_NAME = '__Secure-WT-API-ID';

    /**
     * A middleware to create a specific session for API access
     *
     * @param ServerRequestInterface  $request
     * @param RequestHandlerInterface $handler
     *
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {   
        // Save the current session
        $remembered_request = $request;
        $remembered_user = Auth::user();
        self::save();

        // Start a new API session
        self::start($request);

        // Create the response
        $message = '';
        try {
            $response = $handler->handle($request);
            $exception = false;
        }
        catch (Throwable $th) {
            // Fail gracefully in order to finalize session management
            $exception = true;
            $message = $th->getMessage();
        }

        // Save the API session
        Session::save();

        // Recover the previous session with the previous user (if different to default "GUEST_USER" with id = 0)
        if ($remembered_user->id() !== 0) {
            Session::start($remembered_request);
            Auth::login($remembered_user);
            Session::put('language', Auth::user()->getPreference(UserInterface::PREF_LANGUAGE));
            Session::put('theme', Auth::user()->getPreference(UserInterface::PREF_THEME));
        }

        if ($exception) {
            return new Response500($message);
        }

        return $response;
    }

    /**
     * Start an API session
     * Modified code from: Fisharebest\Webtrees\Session
     *
     * @param ServerRequestInterface $request
     *
     * @return void
     */
    public static function start(ServerRequestInterface $request): void
    {
        // Store sessions in the database
        session_set_save_handler(new SessionDatabaseHandler($request));

        $url    = Validator::attributes($request)->string('base_url');
        $secure = parse_url($url, PHP_URL_SCHEME) === 'https';
        $path   = (string) parse_url($url, PHP_URL_PATH);

        // Paths containing UTF-8 characters need special handling.
        $path = implode('/', array_map(static fn (string $x): string => rawurlencode($x), explode('/', $path)));

        session_name($secure ? self::SECURE_SESSION_NAME : self::SESSION_NAME);
        session_register_shutdown();
        session_start();

        // Prevent session fixation attacks by choosing a new session ID.
        self::regenerate(true);
        self::put('initiated', true);
    }
}
