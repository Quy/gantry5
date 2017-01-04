<?php
/**
 * @package   Gantry5
 * @author    RocketTheme http://www.rockettheme.com
 * @copyright Copyright (C) 2007 - 2016 RocketTheme, LLC
 * @license   Dual License: MIT or GNU/GPLv2 and later
 *
 * http://opensource.org/licenses/MIT
 * http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Gantry Framework code that extends GPL code is considered GNU/GPLv2 and later
 */

namespace Gantry\Component\Config;

use Gantry\Component\File\CompiledYamlFile;
use Gantry\Framework\Gantry;
use RocketTheme\Toolbox\Blueprints\BlueprintForm;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

/**
 * The Config class contains configuration information.
 *
 * @author RocketTheme
 */
class BlueprintsForm extends BlueprintForm
{
    /**
     * @var string
     */
    protected $context = 'gantry-blueprints://';

    /**
     * @var BlueprintSchema
     */
    protected $blueprintSchema;

    /**
     * Get nested structure containing default values defined in the blueprints.
     *
     * Fields without default value are ignored in the list.
     *
     * @return array
     */
    public function getDefaults()
    {
        $this->initInternals();

        return $this->blueprintSchema->getDefaults();
    }

    /**
     * Merge two arrays by using blueprints.
     *
     * @param  array $data1
     * @param  array $data2
     * @param  string $name         Optional
     * @param  string $separator    Optional
     * @return array
     */
    public function mergeData(array $data1, array $data2, $name = null, $separator = '.')
    {
        $this->initInternals();

        return $this->blueprintSchema->mergeData($data1, $data2, $name, $separator);
    }

    /**
     * Return data fields that do not exist in blueprints.
     *
     * @param  array  $data
     * @param  string $prefix
     * @return array
     */
    public function extra(array $data, $prefix = '')
    {
        $this->initInternals();

        return $this->blueprintSchema->extra($data, $prefix);
    }

    /**
     * Validate data against blueprints.
     *
     * @param  array $data
     * @throws \RuntimeException
     */
    public function validate(array $data)
    {
        $this->initInternals();

        $this->blueprintSchema->validate($data);
    }

    /**
     * Filter data by using blueprints.
     *
     * @param  array $data
     * @return array
     */
    public function filter(array $data)
    {
        $this->initInternals();

        return $this->blueprintSchema->filter($data);
    }

    /**
     * Initialize validator.
     */
    protected function initInternals()
    {
        if (!isset($this->blueprintSchema)) {
            $types = [
                'container.set' => [
                    'input@' => false
                ],
                'separator.note' => [
                    'input@' => false
                ],
                'separator.separator' => [
                    'input@' => false
                ],
                'key' => [
                    'input@' => false
                ],
                'collection.list' => [
                    'array' => true
                ]
            ];

            $this->blueprintSchema = new BlueprintSchema;
            $this->blueprintSchema->setTypes($types);
            $this->blueprintSchema->embed('', $this->items);
            $this->blueprintSchema->init();
        }
    }

    /**
     * @param string $filename
     * @return string
     */
    protected function loadFile($filename)
    {
        $file = CompiledYamlFile::instance($filename);
        $content = $file->content();
        $file->free();

        return $content;
    }

    /**
     * @param string|array $path
     * @param string $context
     * @return array
     */
    protected function getFiles($path, $context = null)
    {
        if (is_string($path) && !strpos($path, '://')) {
            // Resolve filename.
            if (isset($this->overrides[$path])) {
                $path = $this->overrides[$path];
            } else {
                if ($context === null) {
                    $context = $this->context;
                }
                if ($context && $context[strlen($context)-1] !== '/') {
                    $context .= '/';
                }
                $path = $context . $path;

                if (!preg_match('/\.yaml$/', $path)) {
                    $path .= '.yaml';
                }
            }
        }

        if (is_string($path) && strpos($path, '://')) {
            /** @var UniformResourceLocator $locator */
            $locator = Gantry::instance()['locator'];

            $files = $locator->findResources($path);
        } else {
            $files = (array) $path;
        }

        return $files;
    }

    /**
     * @param array $field
     * @param string $property
     * @param array $call
     */
    protected function dynamicData(array &$field, $property, array &$call)
    {
        $params = $call['params'];

        if (is_array($params)) {
            $function = array_shift($params);
        } else {
            $function = $params;
            $params = [];
        }

        list($o, $f) = preg_split('/::/', $function, 2);
        if (!$f) {
            if (function_exists($o)) {
                $data = call_user_func_array($o, $params);
            }
        } else {
            if (method_exists($o, $f)) {
                $data = call_user_func_array(array($o, $f), $params);
            }
        }

        // If function returns a value,
        if (isset($data)) {
            if (isset($field[$property]) && is_array($field[$property]) && is_array($data)) {
                // Combine field and @data-field together.
                $field[$property] += $data;
            } else {
                // Or create/replace field with @data-field.
                $field[$property] = $data;
            }
        }
    }

    /**
     * @param array $field
     * @param string $property
     * @param array $call
     */
    protected function dynamicConfig(array &$field, $property, array &$call)
    {
        $value = $call['params'];

        $default = isset($field[$property]) ? $field[$property] : null;
        $config = Gantry::instance()['config']->get($value, $default);

        if (!is_null($config)) {
            $field[$property] = $config;
        }
    }

    /**
     * Get blueprints by using dot notation for nested arrays/objects.
     *
     * @example $value = $this->resolve('this.is.my.nested.variable');
     * returns ['this.is.my', 'nested.variable']
     *
     * @param array  $path
     * @param string  $separator
     * @return array
     */
    public function resolve(array $path, $separator = '.')
    {
        $fields = false;
        $parts = [];
        $current = $this['form.fields'];
        $result = [null, null, null];

        while (($field = current($path)) !== null) {
            if (!$fields && isset($current['fields'])) {
                if (!empty($current['array'])) {
                    $result = [$current, $parts, $path ? implode($separator, $path) : null];
                    // Skip item offset.
                    $parts[] = array_shift($path);
                }

                $current = $current['fields'];
                $fields = true;

            } elseif (isset($current[$field])) {
                $parts[] = array_shift($path);
                $current = $current[$field];
                $fields = false;

            } elseif (isset($current['.' . $field])) {
                $parts[] = array_shift($path);
                $current = $current['.' . $field];
                $fields = false;

            } else {
                // properly loop through nested containers to find deep matching fields
                $inner_fields = null;
                foreach($current as $field) {
                    $type = isset($field['type']) ? $field['type'] : '-undefined-';
                    $container = (0 === strpos($type, 'container.')) || $type === '-undefined-';
                    $fields = isset($field['fields']);
                    $container_fields = [];

                    // if the field has no type, it most certainly is a container
                    if ($type === '-undefined-') {
                        // loop through all the container inner fields and reduce to a flat blueprint
                        $current_fields = isset($current['fields']) ? $current['fields'] : $current;
                        foreach ($current_fields as $container_field) {
                            if (isset($container_field['fields'])) {
                                $container_fields[] = $container_field['fields'];
                            }
                        }

                        // any container structural data can be discarded, flatten
                        $field = array_reduce($container_fields, 'array_merge', []);
                    }

                    if ($container && is_array($field)) {
                        $inner_fields = $field;
                        break;
                    }
                }

                // if a deep matching field is found, set it to current and continue cycling through
                if ($inner_fields) {
                    $current = $inner_fields;
                    continue;
                }

                // nothing found, exit the loop
                break;
            }
        }

        return $result;
    }
}
