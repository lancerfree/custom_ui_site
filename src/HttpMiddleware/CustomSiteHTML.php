<?php

namespace Drupal\custom_ui_site\HttpMiddleware;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\custom_site\Custom\PathInfo;
use Drupal\custom_ui_site\Custom\UISiteTemplate;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Drupal\Core\Site\Settings;
use Drupal\custom_ui_site\Custom\CustomUISiteTable;
use voku\helper\HtmlDomParser;

/**
 * Uses the early response for JS Framework domain.
 */
class CustomSiteHTML implements HttpKernelInterface {

  const CACHE_PREFIX = "custom_ui_site:CustomSiteHTML:response";

  /**
   * The wrapped HTTP kernel.
   *
   * @var \Symfony\Component\HttpKernel\HttpKernelInterface
   */
  protected $httpKernel;

  /**
   * Current pathinfo class.
   *
   * @var PathInfo
   */
  protected $pathInfo;

  /**
   * Cache manager.
   *
   * @var CacheBackendInterface
   */
  protected $cacheManager;

  /**
   * JS Framework template class.
   *
   * @var UISiteTemplate
   */
  protected $uiTpl;

  /**
   * Constructs a CustomSiteHTML object.
   *
   * @param \Symfony\Component\HttpKernel\HttpKernelInterface $http_kernel
   *   The decorated kernel.
   */
  public function __construct(HttpKernelInterface $http_kernel) {
    $this->httpKernel = $http_kernel;
  }

  /**
   * Handle request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The input request.
   * @param int $type
   *   The type of the request. One of HttpKernelInterface::MASTER_REQUEST or
   *   HttpKernelInterface::SUB_REQUEST.
   * @param bool $catch
   *   Whether to catch exceptions or not.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   A Response instance
   * @throws \Exception
   *   When an Exception occurs during processing.
   *
   */
  public function handle(Request $request, $type = self::MASTER_REQUEST, $catch = TRUE) {
    $main_domain_name = settings::get('main_domain', NULL);
    if (!$main_domain_name || ($request->getHost() === $main_domain_name)) {
      return $this->httpKernel->handle($request, $type, $catch);
    }

    // Initialise
    $this->pathInfo = new PathInfo($request);
    $bioborgin_template_path = settings::get('bioborgin_template_path', NULL);
    $this->uiTpl = new UISiteTemplate($bioborgin_template_path);
    $this->cacheManager = \Drupal::cache();

    $cache_header = 'HIT';
    $prefixed_path = $this->pathInfo->getPrefixedPath();
    $content = $this->fetchResponseContentCache($prefixed_path);
    if (!$content) {
      $content = $this->getEntityResponseContent();
      $this->storeResponseContentCache($prefixed_path, $content);
      $cache_header = 'MISS';
    }
    $response = new Response($content);
    // Used custom cache because page cache response policy not properly work.
    $response->headers->set('X-Drupal-Cache', $cache_header);
    $response->headers->set('Cache-Control', 'public, max-age=' . 21600);
    return $response;
  }

  /**
   * Return response for entities.
   *
   * @return string
   */
  public function getEntityResponseContent(){
    $search_uri= $this->fixEntityPath();
    $metatags = $this->getEntityMetatags(['alias' => $search_uri, 'langcode' => $this->pathInfo->getLang()]);
    $host_regex = "@(http[s]?:\/\/)[^:\/\s]+((\/\w+)*\/)@i";
    $href_property = "/(href=[\"']?)((?:.(?![\"']?\s+(?:\S+)=|[>\"']))+.)([\"']?)/i";
    $curr_host = $this->pathInfo->getRequest()->getHost();
    $currurl=$this->pathInfo->getRequest()->getUri();
    if ($metatags) {
      $metatags = preg_replace($host_regex, '$1' . $curr_host . '$2', $metatags);
    }
    //Metataags use current url when build it - but now another one.

    $metatags_dom = HtmlDomParser::str_get_html($metatags);
    $el = $metatags_dom->find('[rel=canonical]', 0);
    if (count($el)) {
      $el->setAttribute('href', $currurl);
      $metatags = $metatags_dom->outerHtml();
    }
    //Front has some difference uri
    if($cont = $this->pathInfo->replaceURI('/export-main-navigation/', '/news/')) {
      $metatags = $cont;
    }

    return (string)$this->uiTpl->appendToHead($metatags);
  }

  /**
   * Gets metatags by conditions.
   *
   * @param array $cond
   *   Select condition.
   * @return string
   */
  public function getEntityMetatags($cond) {
    $table = new CustomUISiteTable();
    $result = $table->selectItems($cond);
    if ($result && !empty($result['data']['metatags'])) {
      return $result['data']['metatags'];
    }
    return '';
  }

  /**
   * Fix entity paths because front and backend have some different aliases.
   *
   * @return mixed|string
   */
  public function fixEntityPath(){
    $fix_paths = [
      ['/events/', '/'],
    ];
    if(!$this->pathInfo->startURI('/news/details/')){
      $fix_paths[]=['/news/', '/export-main-navigation/'];
    }
    foreach ($fix_paths AS $path) {
      if ($path = $this->pathInfo->replaceURI($path[0], $path[1])) {
       return $db_search_path = $path;
        break;
      }
    }
    return $this->pathInfo->getURI();
  }

  /**
   * Get front page response.
   *
   * @return Response
   */
  public function getFrontPageMetatagsResponse() {
    $front_config = $this->getConfigData('custom_metatags.metatags.front_page');
    $metatags[] = [
      'type' => 'link',
      'rel' => 'canonical',
      'href' => $this->pathInfo->getRequest()->getUri()
    ];
    $map=[
      'meta_title'=>[
        'type' => 'meta',
        'name' => 'title',
        'value_field'=>'content'
      ],
      'meta_description'=>[
        'type' => 'meta',
        'name' => 'description',
        'value_field'=>'content'
      ],
      'meta_keywords'=>[
        'type' => 'meta',
        'name' => 'keywords',
        'value_field'=>'content'
      ],
    ];

    if(!empty($front_config['meta_title'])){
      $this->uiTpl->addHeadTitle($front_config['meta_title']);
    }

    if ($front_config) {
      foreach($front_config AS $key =>$value){
        if (!empty($map[$key])) {
          $tag = $map[$key];
          if(!empty($tag['value_field'])){
            $tag[$tag['value_field']]=$value;
            unset($tag['value_field']);
          }
          $metatags[] = $tag;
        }
      }
    }

    $content = (string)$this->uiTpl->addHeadTags($metatags);
    return new Response($content);
  }

  /**
   * Returns Data of the specified config.
   *
   * @return array
   */
  public function getConfigData($config_name) {
    $lang_code = $this->pathInfo->getLang();
    $raw_data_translated = [];
    if ($lang_code == 'en') {
      $config_lang = \Drupal::languageManager()->getLanguageConfigOverride('en', $config_name);
      $translated = $config_lang->get();
      if ($translated) {
        $raw_data_translated = $translated;
      }
    }
    $config = \Drupal::config($config_name);
    $raw_data = $config->getRawData();

    unset($raw_data['_core'], $raw_data['langcode']);
    $raw_data_translated += $raw_data;
    return $raw_data_translated;
  }

  /**
   * Store HTML content to db.
   *
   * @param $file_path
   *   Path to template.
   * @param $file_content
   *   File content.
   */
  protected function storeResponseContentCache($file_path, $data_store) {
    $md5 = md5($file_path);
    $cache_name = self::CACHE_PREFIX . ':' . $md5;
    $this->cacheManager->set($cache_name, $data_store, CacheBackendInterface::CACHE_PERMANENT, array(self::CACHE_PREFIX, $cache_name));
  }

  /**
   * Fetch content from db.
   *
   * @param string $file_path
   *   Path to template.
   * @return mixed
   */
  protected function fetchResponseContentCache($file_path) {
    $md5 = md5($file_path);
    $cache_name = self::CACHE_PREFIX . ':' . $md5;
    $cache = $this->cacheManager->get($cache_name);
    if ($cache && $cache->data) {
      return $cache->data;
    }
  }

}