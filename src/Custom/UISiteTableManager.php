<?php

namespace Drupal\custom_ui_site\Custom;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Language\LanguageManager;
use Drupal\Core\Render\Renderer;
use Drupal\metatag\MetatagManager;

/**
 * Class UISiteTableManager
 * @package Drupal\custom_ui_site\Custom
 */
class UISiteTableManager {

  /**
   * Language Manager.
   *
   * @var LanguageManager
   */
  private $langManager;

  /**
   * Module Handler.
   *
   * @var ModuleHandler
   */
  private $moduleHandler;

  /**
   * Metatag Manager.
   *
   * @var MetatagManager
   */
  private $metatagManager = [];

  /**
   * Renderer.
   *
   * @var Renderer
   */
  private $renderer;

  /**
   * UITable
   *
   * @var CustomUISiteTable
   */
  private $uiTable;

  /**
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  private $metatagStorage;

  /**
   * Entity Manager
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  private $entityManager;

  /**
   * UISiteTableManager constructor.
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  function __construct() {
    $this->langManager = \Drupal::languageManager();
    $this->moduleHandler = \Drupal::service('module_handler');
    $this->metatagManager = \Drupal::service('metatag.manager');
    $this->renderer = \Drupal::service('renderer');
    $this->uiTable = new CustomUISiteTable();
    $this->metatagStorage = \Drupal::entityTypeManager()->getStorage('metatag_defaults');
    $this->entityManager = \Drupal::entityManager();
    $this->pathAliasManager = \Drupal::service('path.alias_manager');
  }

  /**
   * Populate custom_ui_site table.
   */
  public function populate() {
    $this->uiTable->deleteItems(NULL);
    $this->populateFrontPage();
    $this->populateNodes();
  }

  /**
   * Populate front page data .
   *
   * @param string $name
   *   Config name.
   */
  public function populateFrontPage($name = 'front') {
    $default_lang = 'is';
    foreach (['is', 'en'] AS $lang) {
      if ($default_lang !== $lang) {
        $currentLanguage = $this->langManager->getLanguage('is');
        $this->langManager->setConfigOverrideLanguage($this->langManager->getLanguage($lang));
      }
      $metatag_global = $this->metatagStorage->load('global');
      $metatag_selected = $this->metatagStorage->load($name);
      if ($metatag_selected) {
        $metatag_global->overwriteTags($metatag_selected->get('tags'));
      }
      if ($default_lang !== $lang) {
        $this->langManager->setConfigOverrideLanguage($currentLanguage);
      }
      $context = [
        'entity' => NULL,
      ];
      $this->moduleHandler->alter('metatags', $metatag_global, $context);

      $result = $this->metatagManager->generateElements($metatag_global->get('tags'));
      if (!$result['#attached']["html_head"]) {
        continue;
      }
      $metatags_string = $this->renderMetatags($result['#attached']["html_head"]);
      $data = ['metatags' => $metatags_string];
      $this->uiTable->addItem([
        'langcode' => $lang,
        'alias' => '/',
        'internal_path' => '/',
        'type' => 'front_page',
        'data' => serialize($data),
        'ui_path' => '/'
      ]);

    }
  }

  /**
   * Populates nodes.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function populateNodes() {
    $nids = \Drupal::entityQuery('node')->execute();

    $node_storage = $this->entityManager->getStorage('node');
    $ui_table = new CustomUISiteTable();
    foreach ($nids as $nid) {
      $node = $node_storage->load($nid);
      if($nid==78){
        $stop=true;
      }
      foreach (['is', 'en'] as $lng) {
        if ($node->hasTranslation($lng)) {
          $node_ln = $node->getTranslation($lng);
          $metatags = metatag_get_tags_from_route($node_ln);
          if ($node instanceof ContentEntityInterface && $node->hasLinkTemplate('canonical')) {
            // Current route represents a content entity. Build hreflang links.
            foreach ($node->getTranslationLanguages() as $language) {
              $url = $node->toUrl('canonical')
                ->setOption('language', $language)
                ->setAbsolute()
                ->toString();
              $metatags['#attached']["html_head"][] = [
                [
                  '#tag' => 'link',
                  '#attributes' => [
                    'rel' => 'alternate',
                    'hreflang' => $language->getId(),
                    'href' => $url,
                  ]
                ],
                TRUE,
              ];
            }
          }
          if (!$metatags['#attached']["html_head"]) {
            continue;
          }

          $metatags_string = $this->renderMetatags($metatags['#attached']["html_head"]);

          if (!$metatags_string) {
            continue;
          }
          $internal_path = '/node/' . $node_ln->id();
          $alias = $path = $this->pathAliasManager->getAliasByPath($internal_path, $lng);
          $data = ['metatags' => $metatags_string];
          $serialized_data = serialize($data);
          $ui_table->addItem([
            'langcode' => $lng,
            'alias' => $alias,
            'internal_path' => $internal_path,
            'type' => 'node',
            'data' => $serialized_data,
            'ui_path' => $alias
          ]);
        }
      }
    }
  }

  /**
   * Render metatags.
   *
   * @param array $metatags
   *   Metatags.
   * @return string
   */
  public function renderMetatags($metatags) {
    $clear_metatags = array_map(function ($arr) {
      $ret = $arr[0];
      $ret['#type'] = 'html_tag';
      if ($arr[1] === 'title') {
        $ret['#tag'] = 'title';
        if (!empty($ret['#attributes']['content'])) {
          $ret['#value'] = $ret['#attributes']['content'];
        }
        unset($ret['#attributes']);
      }
      return $ret;
    }, $metatags);
    return (string)$this->renderer->renderRoot($clear_metatags);
  }

}