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

namespace Jefferson49\Webtrees\Module\WebtreesApi\Http\Middleware;

use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Contracts\UserInterface;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Session;
use Fisharebest\Webtrees\SessionDatabaseHandler;
use Fisharebest\Webtrees\Validator;
use Fisharebest\Webtrees\Webtrees;
use Jefferson49\Webtrees\Helpers\Functions;
use Jefferson49\Webtrees\Log\CustomModuleLog;
use Jefferson49\Webtrees\Log\CustomModuleLogInterface;
use Jefferson49\Webtrees\Module\WebtreesApi\WebtreesApi;
use Psr\Clock\ClockInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use Throwable;

use function array_map;
use function explode;
use function implode;
use function Jefferson49\Webtrees\Module\WebtreesApi\Helpers\api_response;
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
    private readonly ClockInterface|null $clock;

    private const string SESSION_NAME        = 'WT2_API_SESSION';
    private const string SECURE_SESSION_NAME = '__Secure-WT-API-ID';


    public function __construct()
    {
        if (version_compare(Webtrees::VERSION, '2.3', '>=')) {
            $this->clock = Registry::container()->get(ClockInterface::class);;
        }
        else {
            $this->clock = null;
        }
    }

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

        // Logout the current user
        Auth::logout();

        // Start a new API session
        self::startSession($request, $this->clock);

        // Create the response
        $message = '';
        try {
            $response = $handler->handle($request);
            $exception = false;
        }
        catch (Throwable $th) {
            // Log error
            /** @var CustomModuleLogInterface $log_module */
            $log_module = Functions::getFromContainer(WebtreesApi::class);
            CustomModuleLog::addDebugLog($log_module, 'Error in class ' . substr(strrchr(get_class($this), '\\'), 1) . ' : ' . $th->getMessage());

            // Fail gracefully in order to finalize session management
            $exception = true;
            $message = $th->getMessage();
        }

        // Save the API session
        Session::save();

        // Recover the previous session with the previous user (if different to default "GUEST_USER" with id = 0)
        if ($remembered_user->id() !== 0) {
            self::startSession($remembered_request, $this->clock);
            Auth::login($remembered_user);
            Session::put('language', Auth::user()->getPreference(UserInterface::PREF_LANGUAGE));
            Session::put('theme', Auth::user()->getPreference(UserInterface::PREF_THEME));
        }

        if ($exception) {
            return api_response($message, StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR);
        }

        return $response;
    }

    /**
     * Start an API session
     * Modified code from: Fisharebest\Webtrees\Session
     *
     * @param ServerRequestInterface $request
     * @param ClockInterface $clock
     *
     * @return void
     */
    public static function startSession(ServerRequestInterface $request, ClockInterface|null $clock): void
    {
        // Store sessions in the database
        if (version_compare(Webtrees::VERSION, '2.3', '>=')) {
            session_set_save_handler(new SessionDatabaseHandler($request, $clock));
        }
        else {
            session_set_save_handler(new SessionDatabaseHandler($request));
        }

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
