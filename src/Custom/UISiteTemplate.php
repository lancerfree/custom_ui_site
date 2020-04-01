<?php

namespace Drupal\custom_ui_site\Custom;

use Drupal\Core\Cache\CacheBackendInterface;

/**
 * Class UISiteTemplate
 * @package Drupal\custom_ui_site\Custom
 */
class UISiteTemplate {

  /**
   * Cache prefix.
   *
   * @var string
   */
  const CACHE_PREFIX = 'custom_ui_site:ui_site_template';

  /**
   * Init HTML content - not changed.
   *
   * @var string
   */
  private $initContent = '';

  /**
   * Result content - changed.
   *
   * @var string
   */
  private $resultContent = '';

  /**
   * Heaa tags.
   *
   * @var array.
   */
  private $headTags = [];

  /**
   * Current title to insert.
   *
   * @var string
   */
  private $title;

  /**
   * UISiteTemplate constructor.
   * @param string $file_path
   *   File path to template.
   */
  function __construct($file_path) {
    $this->cacheManager = \Drupal::cache();
    $content = $this->getDBContent($file_path);
    if (!$content) {
      $content = $this->getDiskContent($file_path);
      if (!$content) {
        $content = $this->getDefaultHtml();
      } else {
        $this->setDBContent($file_path, $content);
      }
    }
    $this->initContent = $content;
    $this->resultContent = $content;
  }

  /**
   * Tries return content from disk.
   *
   * @param string $file_path
   *   Path to template.
   * @return NULL|string
   */
  protected function getDiskContent($file_path) {
    if (file_exists($file_path) && ($file_content = file_get_contents($file_path))) {
      return $file_content;
    }
  }

  /**
   * Set HTML content to db.
   *
   * @param $file_path
   *   Path to template.
   * @param $file_content
   *   File content.
   */
  protected function setDBContent($file_path, $file_content) {
    $md5 = md5($file_path);
    $cache_name = self::CACHE_PREFIX . ':' . $md5;
    $this->cacheManager->set($cache_name, $file_content, CacheBackendInterface::CACHE_PERMANENT, array(self::CACHE_PREFIX, $cache_name));
  }

  /**
   * Gets content from db.
   *
   * @param string $file_path
   *   Path to template.
   * @return mixed
   */
  protected function getDBContent($file_path) {
    $md5 = md5($file_path);
    $cache_name = self::CACHE_PREFIX . ':' . $md5;
    $cache = $this->cacheManager->get($cache_name);
    if ($cache && $cache->data) {
      return $cache->data;
    }
  }

  /**
   * Returns HTML tag content.
   *
   * @param string $type
   *   Tag type.
   * @param array $properties
   *   Tag properties.
   * @param string $content
   *   Tag content.
   * @return string
   */
  static public function getTag($type, $properties = [], $content = '') {
    $unclosed_tags = ['link' => 1, 'meta' => 1];
    $tag_string = "<{$type} ";
    foreach ($properties AS $type_property => $tag_value) {
      $tag_value_trimmed = trim($tag_value);
      if (!$tag_value_trimmed) {
        continue;
      }
      $notempty = TRUE;
      $coded_value = htmlspecialchars_decode($tag_value_trimmed);

      $tag_string .= " $type_property=\"{$coded_value}\"";
    }
    if ($content && $trimmed_content = trim($content)) {
      $tag_string .= ">$trimmed_content</{$type}>";
    } else {
      if (isset($unclosed_tags[$type])) {
        $tag_string .= '>';
      } else {
        $tag_string .= '/>';
      }

    }
    if (!empty($notempty) || !empty($trimmed_content)) {
      return $tag_string;
    }
  }

  /**
   * Return string parsed tags
   *
   * @param array $tags
   *   Tags
   * @param bool $implode
   *   Flag implade.
   * @return array|string
   */
  static public function getTags($tags, $implode = TRUE) {
    $results = [];
    foreach ($tags AS $tag_index => $values) {
      if (empty($values['type'])) {
        continue;
      }
      $type = $values['type'];
      $tag_values = $values;
      $content = $tag_values['value'] ?? '';
      unset($tag_values['type'], $tag_values['value']);
      $tag_string = self::getTag($type, $tag_values, $content);
      if ($tag_string) {
        $results[$tag_index] = $tag_string;
      }
    }
    if ($implode) {
      return implode('', $results);
    }
    return $results;
  }

  /**
   * Appends to head html any string.
   *
   * @param $str
   *   Appended string
   * @return $this
   */
  function appendToHead($str) {
    if (strpos($str, '</title>')) {
      $regex_title = "@<\s*title[^>]*>([\s\S]*?)<[\s]*/\s*title>@i";
      $this->resultContent = preg_replace($regex_title, $str, $this->resultContent);
    } else {
      $regex_end_title = "@<\s*/title\s*>@i";
      $this->resultContent = preg_replace($regex_end_title, '$0' . $str, $this->resultContent);
    }
    return $this;
  }

  /**
   * Prepare tags that will be added to the html head.
   *
   * @param array $tags
   *   List tags.
   * @return $this
   */
  function addHeadTags($tags) {
    if (empty($tags[0])) {
      $this->headTags[] = $tags;
    } else {
      $this->headTags = array_merge($this->headTags, $tags);
    }
    return $this;
  }

  /**
   * Prepare page title.
   *
   * @param string $title
   *   Title.
   * @param bool $default_suffix
   *   Flag site name view.
   */
  function addHeadTitle($title, $default_suffix = TRUE){
    $suffix = " | Bioborgin";
    if($title){
      $this->title = $title.($default_suffix?$suffix:'');
    }
  }

  /**
   * Insert title to the page.
   *
   * @param string $title
   *   Title to insert.
   */
  function setTitle($title){
    $regex_title ="@<\s*title[^>]*>([\s\S]*?)<[\s]*/\s*title>@i";
    $title = self::getTag('title',[],$title);
    $this->resultContent= preg_replace($regex_title, $title, $this->resultContent);
  }

  /**
   * Then the template not exist that template is used.
   *
   * @return string
   */
  protected function getDefaultHtml() {
    return "<!DOCTYPE html>
<html>
<head>
    <title>Template dosnt exist</title>
</head>
<body>
<h1>Template dosn't exist</h1>
</body>
</html>";
  }

  /**
   * Casting object to string.
   *
   * @return string
   */
  function __toString() {
    $tags_string = '';
    foreach ($this->headTags AS $tag) {
      if (is_array($tag)) {
        $tag_str = self::getTags([$tag]);
        if ($tag_str) {
          $tags_string .= $tag_str;
        }
      } else {
        $tags_string .= $tag;
      }
    }
    $this->appendToHead($tags_string);
    if($this->title){
       $this->setTitle($this->title);
    }
    return $this->resultContent;
  }

}