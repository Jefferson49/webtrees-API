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

namespace Jefferson49\Webtrees\Module\WebtreesApi;

use Fisharebest\Localization\Translation;
use Fisharebest\Webtrees\FlashMessages;
use Fisharebest\Webtrees\Html;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Module\AbstractModule;
use Fisharebest\Webtrees\Module\ModuleConfigInterface;
use Fisharebest\Webtrees\Module\ModuleConfigTrait;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Module\ModuleCustomTrait;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Validator;
use Fisharebest\Webtrees\View;
use Jefferson49\Webtrees\Exceptions\GithubCommunicationError;
use Jefferson49\Webtrees\Helpers\GithubService;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\Middleware\AuthApi;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\RequestHandlers\GedcomData;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\RequestHandlers\SearchGeneral;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\RequestHandlers\Trees;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\RequestHandlers\Test;
use Jefferson49\Webtrees\Module\WebtreesApi\Http\RequestHandlers\WebtreesVersion;
use OpenApi\Attributes as OA;
use OpenApi\Generator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use RuntimeException;
use Throwable;


#[OA\OpenApi(openapi: OA\OpenApi::VERSION_3_1_0, security: [['bearerAuth' => []]])]
#[OA\Info(
    title: 'webtrees API', 
    version: self::CUSTOM_VERSION,
)]
#[OA\Tag(
    name: 'webtrees',
    description: 'webtrees API'
)]
#[OA\Server(url: 'https://localhost/webtrees/api', description: 'webtrees server')]
#[OA\SecurityScheme(securityScheme: 'bearerAuth', type: 'http', scheme: 'bearer', description: 'Basic Auth')]

class WebtreesApi extends AbstractModule implements
	ModuleCustomInterface, 
	ModuleConfigInterface
{
    use ModuleConfigTrait;
    use ModuleCustomTrait;

	//Custom module version
	public const CUSTOM_VERSION = '1.0.0-alpha';

	//Routes
    protected const ROUTE_API                  = '/api';
    protected const ROUTE_API_WEBTREES_VERSION = '/api/version';
    protected const ROUTE_API_SEARCH_GENERAL   = '/api/search-general';
    protected const ROUTE_API_GET_GEDCOM_DATA  = '/api/gedcom-data';
    protected const ROUTE_API_TREES            = '/api/trees';
    protected const ROUTE_API_TEST             = '/api/test';

	//Github repository
	public const GITHUB_REPO = 'Jefferson49/webtrees-api';

	//Github API URL to get the information about the latest releases
	public const GITHUB_API_LATEST_VERSION = 'https://api.github.com/repos/'. self::GITHUB_REPO . '/releases/latest';
	public const GITHUB_API_TAG_NAME_PREFIX = '"tag_name":"v';

	//Author of custom module
	public const CUSTOM_AUTHOR = 'Markus Hemprich';

    //Prefences, Settings
	public const PREF_WEBTREES_API_TOKEN = "webtrees_api_token";
	public const PREF_USE_HASH      = "use_hash";

    //Errors
    public const ERROR_WEBTREES_ERROR    = "webtrees error";
    
    //Other constants
    public const MINIMUM_API_KEY_LENGTH = 32;
    public const REGEX_FILE_NAME = '[^<>:\"\/\\|?*\r\n]+';


   /**
     * WebtreesApi constructor.
     */
    public function __construct()
    {
        //Caution: Do not use the shared library jefferson47/webtrees-common within __construct(), 
        //         because it might result in wrong autoload behavior        
    }

    /**
     * Initialization.
     *
     * @return void
     */
    public function boot(): void
    {
        //Register this class in the webtrees container
        //This allows to access the module instance from other places, e.g. views/scripts (->assetUrl)
        Registry::container()->set(self::class, $this);

        $router = Registry::routeFactory()->routeMap();            

        //Register the routes for API requests
        $router
            ->get(Test::class, self::ROUTE_API_TEST, Test::class);
        $router
            ->get(WebtreesVersion::class, self::ROUTE_API_WEBTREES_VERSION, WebtreesVersion::class)
            ->extras(['middleware' => [AuthApi::class]]);
        $router
            ->get(SearchGeneral::class,   self::ROUTE_API_SEARCH_GENERAL,   SearchGeneral::class)
            ->extras(['middleware' => [AuthApi::class]]);
        $router
            ->get(GedcomData::class,   self::ROUTE_API_GET_GEDCOM_DATA,   GedcomData::class)
            ->extras(['middleware' => [AuthApi::class]]);
        $router
            ->get(Trees::class,   self::ROUTE_API_TREES,   Trees::class)
            ->extras(['middleware' => [AuthApi::class]]);

		// Register a namespace for the views.
		View::registerNamespace($this->name(), $this->resourcesFolder() . 'views/');
    }

    /**
     * {@inheritDoc}
     *
     * @return string
     *
     * @see \Fisharebest\Webtrees\Module\AbstractModule::title()
     */
    public function title(): string
    {
        return I18N::translate('webtrees API');
    }

    /**
     * {@inheritDoc}
     *
     * @return string
     *
     * @see \Fisharebest\Webtrees\Module\AbstractModule::description()
     */
    public function description(): string
    {
        /* I18N: Description of the “AncestorsChart” module */
        return I18N::translate('A custom module to provide an API for webtrees.');
    }

    /**
     * {@inheritDoc}
     *
     * @return string
     *
     * @see \Fisharebest\Webtrees\Module\AbstractModule::resourcesFolder()
     */
    public function resourcesFolder(): string
    {
        return __DIR__ . '/../resources/';
    }

    /**
     * Get the active module name, e.g. the name of the currently running module
     *
     * @return string
     */
    public static function activeModuleName(): string
    {
        return '_' . basename(dirname(__DIR__, 1)) . '_';
    }
    
    /**
     * {@inheritDoc}
     *
     * @return string
     *
     * @see \Fisharebest\Webtrees\Module\ModuleCustomInterface::customModuleAuthorName()
     */
    public function customModuleAuthorName(): string
    {
        return self::CUSTOM_AUTHOR;
    }

    /**
     * {@inheritDoc}
     *
     * @return string
     *
     * @see \Fisharebest\Webtrees\Module\ModuleCustomInterface::customModuleVersion()
     */
    public function customModuleVersion(): string
    {
        return self::CUSTOM_VERSION;
    }

    /**
     * {@inheritDoc}
     *
     * @return string
     *
     * @see \Fisharebest\Webtrees\Module\ModuleCustomInterface::customModuleLatestVersion()
     */
    public function customModuleLatestVersion(): string
    {
        return Registry::cache()->file()->remember(
            $this->name() . '-latest-version',
            function (): string {

                try {
                    //Get latest release from GitHub
                    return GithubService::getLatestReleaseTag(self::GITHUB_REPO);
                }
                catch (GithubCommunicationError $ex) {
                    // Can't connect to GitHub?
                    return $this->customModuleVersion();
                }
            },
            86400
        );
    }

    /**
     * {@inheritDoc}
     *
     * @return string
     *
     * @see \Fisharebest\Webtrees\Module\ModuleCustomInterface::customModuleSupportUrl()
     */
    public function customModuleSupportUrl(): string
    {
        return 'https://github.com/' . self::GITHUB_REPO;
    }

    /**
     * {@inheritDoc}
     *
     * @param string $language
     *
     * @return array
     *
     * @see \Fisharebest\Webtrees\Module\ModuleCustomInterface::customTranslations()
     */
    public function customTranslations(string $language): array
    {
        $lang_dir   = $this->resourcesFolder() . 'lang/';
        $file       = $lang_dir . $language . '.mo';
        if (file_exists($file)) {
            return (new Translation($file))->asArray();
        } else {
            return [];
        }
    }

    /**
     * View module settings in control panel
     *
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function getAdminAction(ServerRequestInterface $request): ResponseInterface
    {
        $this->layout = 'layouts/administration';

        $base_url           = Validator::attributes($request)->string('base_url');
        $pretty_urls        = Validator::attributes($request)->boolean('rewrite_urls', false);
        $path               = parse_url($base_url, PHP_URL_PATH) ?? '';
        $parameters         = ['route' => $path];
        $url                = $base_url . '/index.php';
        $webtrees_api_url        = Html::url($url, $parameters) . self::ROUTE_API;
        $pretty_webtrees_api_url = $base_url . self::ROUTE_API;

        // Generate the OpenApi json file (because we want to include the specific base URL)
        self::generateOpenApiFile($pretty_webtrees_api_url);

        return $this->viewResponse(
            $this->name() . '::settings',
            [
                'title'                       => $this->title(),
                'pretty_urls'                 => $pretty_urls,
                'webtrees_api_url'            => $webtrees_api_url,
                'pretty_webtrees_api_url'     => $pretty_webtrees_api_url,
                'uses_https'                  => strpos(Strtoupper($base_url), 'HTTPS://') === false ? false : true,
				self::PREF_WEBTREES_API_TOKEN => $this->getPreference(self::PREF_WEBTREES_API_TOKEN, ''),
				self::PREF_USE_HASH           => boolval($this->getPreference(self::PREF_USE_HASH, '1')),
                ]
        );
    }

    /**
     * Save module settings after returning from control panel
     *
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function postAdminAction(ServerRequestInterface $request): ResponseInterface
    {
        $save          = Validator::parsedBody($request)->string('save', '');
        $use_hash      = Validator::parsedBody($request)->boolean(self::PREF_USE_HASH, false);
        $new_api_token = Validator::parsedBody($request)->string('new_api_token', '');
        
        //Save the received settings to the user preferences
        if ($save === '1') {

            $new_key_error = false;

            //If no new API key is provided
			if($new_api_token === '') {
				//If use hash changed from true to false, reset key (hash cannot be used any more)
				if(boolval($this->getPreference(self::PREF_USE_HASH, '0')) && !$use_hash) {
					$this->setPreference(self::PREF_WEBTREES_API_TOKEN, '');
				}
				//If use hash changed from false to true, take old key (for planned encryption) and save as hash
				elseif(!boolval($this->getPreference(self::PREF_USE_HASH, '0')) && $use_hash) {
					$new_api_token = $this->getPreference(self::PREF_WEBTREES_API_TOKEN, '');
                    $hash_value = password_hash($new_api_token, PASSWORD_BCRYPT);
                    $this->setPreference(self::PREF_WEBTREES_API_TOKEN, $hash_value);
				}
                //If no new API key and no changes in hashing, do nothing
			}
			//If new API key is too short
			elseif(strlen($new_api_token) < self::MINIMUM_API_KEY_LENGTH) {
				$message = I18N::translate('The provided API authorization key is too short. Please provide a minimum length of %s characters.',(string) self::MINIMUM_API_KEY_LENGTH);
				FlashMessages::addMessage($message, 'danger');
                $new_key_error = true;				
			}
			//If new API key does not escape correctly
			elseif($new_api_token !== e($new_api_token)) {
				$message = I18N::translate('The provided API authorization key contains characters, which are not accepted. Please provide a different key.');
				FlashMessages::addMessage($message, 'danger');				
                $new_key_error = true;		
            }
			//If new API key shall be stored with a hash, create and save hash
			elseif($use_hash) {
				$hash_value = password_hash($new_api_token, PASSWORD_BCRYPT);
				$this->setPreference(self::PREF_WEBTREES_API_TOKEN, $hash_value);
			}
            //Otherwise, simply store the new API key
			else {
				$this->setPreference(self::PREF_WEBTREES_API_TOKEN, $new_api_token);
			}

            //Save settings to preferences
            if(!$new_key_error) {
                $this->setPreference(self::PREF_USE_HASH, $use_hash ? '1' : '0');
            }

            //Finally, show a success message
			$message = I18N::translate('The preferences for the module "%s" were updated.', $this->title());
			FlashMessages::addMessage($message, 'success');	
		}

        return redirect($this->getConfigLink());
    }

    /**
     * Get the namespace for the views
     *
     * @return string
     */
    public static function viewsNamespace(): string
    {
        return self::activeModuleName();
    }    

    /**
     * Gemerate an OpenApi JSON file
     *
     * @return void
     */
    public static function generateOpenApiFile(string $api_url): void {

        $json_file   = __DIR__ . '/../resources/OpenApi/OpenApi.json';
        $soure_pathes = [__DIR__ . '/../src/Http', __DIR__ . '/../src/WebtreesApi.php'];

        //Delete file if already existing
        if (file_exists($json_file)) {
            unlink($json_file);
        }

        //Open stream
        if (!$stream = fopen($json_file, "c")) {
            throw new RuntimeException('Cannot open file: ' . $json_file);
        }

        //Create OpenAPi description
        $open_api = Generator::scan($soure_pathes, ['*.php']);
        $json = $open_api->toJson();

        //Patch the base URL
        $json = str_replace('https://localhost/webtrees/api', $api_url, $json);

        //Write to json file
        try {
            fwrite($stream, $json);
        }
        catch (Throwable $th) {
            throw new RuntimeException('Cannot write to file: ' . $json_file);
        }

        return;
    }
}
