<?php
/**
* PHPCI - Continuous Integration for PHP
*
* @copyright    Copyright 2013, Block 8 Limited.
* @license      https://github.com/Block8/PHPCI/blob/master/LICENSE.md
* @link         http://www.phptesting.org/
*/

namespace PHPCI\Model;

use b8\Store\Factory;
use PHPCI\Model\Base\BuildBase;
use PHPCI\Builder;
use Symfony\Component\Yaml\Parser as YamlParser;

/**
* Build Model
* @uses         PHPCI\Model\Base\BuildBase
* @author       Dan Cryer <dan@block8.co.uk>
* @package      PHPCI
* @subpackage   Core
*/
class Build extends BuildBase
{
    const STATUS_NEW = 0;
    const STATUS_RUNNING = 1;
    const STATUS_SUCCESS = 2;
    const STATUS_FAILED = 3;

    public $currentBuildPath = null;

    /**
    * Get link to commit from another source (i.e. Github)
    */
    public function getCommitLink()
    {
        return '#';
    }

    /**
     * @return string
     */
    public function getProjectTitle()
    {
        $project = $this->getProject();
        return $project ? $project->getTitle() : "";
    }

    /**
    * Get link to branch from another source (i.e. Github)
    */
    public function getBranchLink()
    {
        return '#';
    }

    /**
    * Send status updates to any relevant third parties (i.e. Github)
    */
    public function sendStatusPostback()
    {
        return;
    }

    /**
     * Store build metadata
     */
    public function storeMeta($key, $value)
    {
        $value = json_encode($value);
        Factory::getStore('Build')->setMeta($this->getProjectId(), $this->getId(), $key, $value);
    }

    /**
     * Is this build successful?
     */
    public function isSuccessful()
    {
        return ($this->getStatus() === self::STATUS_SUCCESS);
    }

    /**
     * @param Builder $builder
     * @param string  $buildPath
     *
     * @return bool
     */
    protected function handleConfig(Builder $builder, $buildPath)
    {
        $build_config = null;

        // Try phpci.yml first:
        if (is_file($buildPath . '/phpci.yml')) {
            $build_config = file_get_contents($buildPath . '/phpci.yml');
        }

        // Try getting the project build config from the database:
        if (empty($build_config)) {
            $build_config = $this->getProject()->getBuildConfig();
        }

        // Fall back to zero config plugins:
        if (empty($build_config)) {
            $build_config = $this->getZeroConfigPlugins($builder);
        }

        if (is_string($build_config)) {
            $yamlParser = new YamlParser();
            $build_config = $yamlParser->parse($build_config);
        }

        $builder->setConfigArray($build_config);
        return true;
    }

    protected function getZeroConfigPlugins(Builder $builder)
    {
        $pluginDir = PHPCI_DIR . 'PHPCI/Plugin/';
        $dir = new \DirectoryIterator($pluginDir);

        $config = array(
            'build_settings' => array(
                'ignore' => array(
                    'vendor/',
                )
            )
        );

        foreach ($dir as $item) {
            if ($item->isDot()) {
                continue;
            }

            if (!$item->isFile()) {
                continue;
            }

            if ($item->getExtension() != 'php') {
                continue;
            }

            $className = '\PHPCI\Plugin\\'.$item->getBasename('.php');

            $reflectedPlugin = new \ReflectionClass($className);

            if (!$reflectedPlugin->implementsInterface('\PHPCI\ZeroConfigPlugin')) {
                continue;
            }

            foreach (array('setup', 'test', 'complete', 'success', 'failure') as $stage) {
                if ($className::canExecute($stage, $builder, $this)) {
                    $config[$stage][$className] = array();
                }
            }
        }

        return $config;
    }

    public function getFileLink($file, $line = null)
    {
        return null;
    }
}
