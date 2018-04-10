<?php
/*
 * based on PageBuilder by mnoskov
 * https://github.com/mnoskov/pagebuilder
 */
class evoCartDiscounts {

    const version = '1.0.0';

    private $modx;
    private $data;
    private $conf   = [];
    private $themes = [];
    private $containers = [];
    private $path;
    private $params;
    private $lang;
    private $iterations = [];

    public function __construct($modx, $params = null) {
        $this->modx = $modx;

        $this->richeditor  = $modx->getConfig('which_editor');
        $this->browser     = $modx->getConfig('which_browser');
        $this->table       = $modx->getFullTableName('discounts');
        $this->path        = MODX_BASE_PATH . 'assets/plugins/evoCartDiscounts/config/';
        $this->params      = is_null($params) ? $modx->event->params : $params;
        $this->isBackend   = defined('IN_MANAGER_MODE') && IN_MANAGER_MODE == 'true';
        $this->isTV        = isset($this->params['tv']);

        if (empty($this->params['id'])) {
            $this->params['id'] = 0;
        }

        $lang = $modx->getConfig('manager_language');
        $lang = __DIR__ . '/lang/' . $lang . '.php';

        if (!is_readable($lang)) {
            $lang = __DIR__ . '/lang/english.php';
        }

        $this->lang = include $lang;
    }

    /**
     * Load and parse template
     *
     * @param  string $template String that contains template or binding with name of template
     * @param  array $data Values
     * @return string Result of parsing
     */
    private function parseTemplate($template, $data) {
        if (!function_exists('ParseCommand')) {
            require_once(MODX_MANAGER_PATH . 'includes/tmplvars.commands.inc.php');
        }

        $binding = ParseCommand($template);

        if (!empty($binding)) {
            list($command, $source) = $binding;

            switch ($command) {
                case 'CHUNK': {
                    $template = $this->modx->getChunk(trim($source));
                    break;
                }

                case 'FILE': {
                    $template = $this->modx->atBindFileContent($template);
                    break;
                }
            }
        }

        $template = $this->modx->mergeSettingsContent($template);

        return $this->modx->parseText($template , $data);
    }

    private function renderFieldsList($templates, $template, $config, $values) {
        $out = '';
        $data = [];

        if (isset($config['fields'])) {
            foreach ($config['fields'] as $field => $options) {
                if (isset($options['elements'])) {
                    if (!isset($templates[$field])) {
                        $data[$field] = $values[$field];

                        if (is_array($data[$field])) {
                            $data[$field] = implode('||', $data[$field]);
                        }
                    } else {
                        $fieldTemplates = $templates[$field];

                        if (!is_array($fieldTemplates)) {
                            $fieldTemplates = [ $fieldTemplates ];
                        }

                        foreach ($fieldTemplates as $name => $tpl) {
                            $key = $field . (!is_numeric($name) || $name > 0 ? '.' . $name : '');
                            $data[$key] = '';

                            foreach ($values[$field] as $index => $value) {
                                $this->iterations["{$field}_index"]     = $index;
                                $this->iterations["{$field}_iteration"] = $index + 1;

                                $data[$key] .= $this->parseTemplate($tpl, array_merge($this->iterations, [
                                    'value' => $value,
                                    'title' => $options['elements'][$value],
                                ]));
                            }
                        }
                    }

                    continue;
                }

                if ($options['type'] != 'group') {
                    $data[$field] = $values[$field];
                    continue;
                }

                if (!isset($templates[$field])) {
                    $data[$field] = "<div>Template for fieldgroup '$field' not defined</div>";
                    continue;
                }

                $fieldTemplates = $templates[$field];

                if (!is_array($fieldTemplates)) {
                    $fieldTemplates = [ $fieldTemplates ];
                }

                foreach ($fieldTemplates as $name => $tpl) {
                    $key = $field . (!is_numeric($name) || $name > 0 ? '.' . $name : '');
                    $data[$key] = '';

                    foreach ($values[$field] as $index => $value) {
                        $this->iterations["{$field}_index"]     = $index;
                        $this->iterations["{$field}_iteration"] = $index + 1;

                        $data[$key] .= $this->renderFieldsList($templates, $tpl, $options, $value);
                    }
                }
            }
        }

        return $this->parseTemplate($template, array_merge($this->iterations, $values, $data));
    }

    /**
     * Shows all content blocks for document
     *
     * @param  int $params Snippet parameters
     * @return string Output
     */
    public function render($params) {
        $params = array_merge([
            'container' => 'default',
            'blocks'    => '*',
            'templates' => '',
            'offset'    => 0,
            'limit'     => 0,
            'renderTo'  => 'templates',
        ], $params);

        if ($params['blocks'] != '*') {
            $params['blocks'] = explode(',', $params['blocks']);
        }

        $containers = explode(',', $params['container']);
        $result = [];

        foreach ($containers as $container) {
            $params['container'] = trim($container);
            //$this->fetch($params['user_id'], $params['container']);
            $query = $this->modx->db->getValue($this->modx->db->select('`values`', $this->table, 'user_id = '.$params['user_id'], "", 1));

            $values = json_decode($query, true);

            $out = [];
            if($values['main'] > 0) {
                $out['main'] = $values['main'];
            }
            if($values['rules']) {
                $out['rules'] = $values['rules'];
            }
            return $out;
        }
    }

    /**
     * Renders template in admin panel
     *
     * @param  string $template Template
     * @param  array $data Values for binding to template
     * @return string Output
     */
    public function renderTpl($template, $data) {
        $data['l'] = $this->lang;
        extract($data);

        ob_start();
        include(__DIR__ . '/' . $template);
        $output = ob_get_contents();
        ob_end_clean();

        return $output;
    }

    /**
     * Renders form in new tab on document editing page, called at OnDocFormRender event
     *
     * @return string Output
     */
    public function renderForm() {
        $this->fetch($this->params['id']);

        if (empty($this->conf) || empty($this->params['id'])) {
            return '';
        }

        // load manager lang file for date settings
        include MODX_MANAGER_PATH . 'includes/lang/' . $this->modx->getConfig('manager_language') . '.inc.php';

        return $this->renderTpl('tpl/form.tpl', [
            'version'    => self::version,
            'browseurl'  => MODX_MANAGER_URL . 'media/browser/' . $this->browser . '/browse.php',
            'containers' => $this->containers,
            'configs'    => $this->conf,
            'blocks'     => $this->data,
            'adminlang'  => $_lang,
            'thumbsDir'  => $this->modx->getConfig('thumbsDir'),
            'picker'     => [
                'yearOffset' => $this->modx->getConfig('datepicker_offset'),
                'format'     => $this->modx->getConfig('datetime_format') . ' hh:mm:00',
            ],
        ]);
    }

    /**
     * Prepares data row for output, if 'prepare' key defined
     *
     * @param  array  $options config options
     * @param  array  $values  values
     * @return array           modified values
     */
    private function prepareData(&$options, &$values) {
        if (isset($options['prepare'])) {
            $params = [
                'options' => &$options,
                'values'  => &$values,
            ];

            if (is_callable($options['prepare'])) {
                call_user_func_array($options['prepare'], $params);
            } else {
                $this->modx->runSnippet($options['prepare'], $params);
            }
        }

        return $values;
    }

    /**
     * Determines whether a block can be included and shown
     * after filtering by parameters
     *
     * @param  array   $block Block configuration
     * @param  integer $docid Current document identifier
     * @return boolean
     */
    private function canIncludeBlock($block, $docid) {
        if ($this->isBackend && $block['isContainer']) {
            $isTVBlock = isset($block['placement']) && $block['placement'] == 'tv';
            if ($this->isTV && !$isTVBlock || !$this->isTV && $isTVBlock) {
                return false;
            }
        }

        foreach ([ 'show_in_templates', 'show_in_docs', 'hide_in_docs' ] as $opt) {
            if (isset($block[$opt]) && !is_array($block[$opt])) {
                $block[$opt] = [ $block[$opt] ];
            }
        }

        if (isset($block['show_in_templates']) && !in_array($this->params['template'], $block['show_in_templates'])) {
            return false;
        }

        if (isset($block['hide_in_docs']) && in_array($docid, $block['hide_in_docs'])) {
            return false;
        }

        if (isset($block['show_in_docs'])) {
            if (in_array($docid, $block['show_in_docs'])) {
                return true;
            } else if (!isset($block['show_in_templates'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Loads saved content blocks and configuration files
     *
     * @param  int $docid Document identificator
     * @param  string $container Name of the container
     */
    private function fetch($docid, $containerName = null) {
        $this->containers['default'] = [
            'title'     => !empty($this->params['tabName']) ? $this->params['tabName'] : 'Page Builder',
            'addType'   => !empty($this->params['addType']) ? $this->params['addType'] : 'dropdown',
            'placement' => !empty($this->params['placement']) ? $this->params['placement'] : 'content'
        ];

        // If there's tv placement and tv name is not 'default',
        // then there should not be default container
        if ($this->isTV && $this->params['container'] != 'default') {
            unset($this->containers['default']['placement']);
        }

        $this->conf = [];

        if (!isset($this->params['template'])) {
            if ($docid == $modx->documentIdentifier) {
                $this->params['template'] = $this->modx->documentObject['template'];
            } else {
                $doc = $this->modx->getDocument($docid, 'template');
                $this->params['template'] = $doc['template'];
            }
        }

        // Loading all config files, that complied with filters
        foreach (scandir($this->path) as $entry) {
            if (pathinfo($entry, PATHINFO_EXTENSION) == 'php') {
                $name  = pathinfo($entry, PATHINFO_FILENAME);
                $block = include($this->path . $entry);

                $block['isContainer'] = strpos($name, 'container.') === 0;

                if ($this->canIncludeBlock($block, $docid)) {
                    if ($this->isBackend) {
                        unset($block['templates']);
                    }

                    if ($block['isContainer']) {
                        $name = str_replace('container.', '', $name);
                        $block['sections'] = [];
                        $this->containers[$name] = $block;
                    } else {
                        if (!isset($block['container'])) {
                            $block['container'] = 'default';
                        }

                        if (!isset($block['order'])) {
                            $block['order'] = PHP_INT_MAX;
                        }

                        $block['name'] = $name;
                        $this->conf[$name] = $block;
                    }
                }
            }
        }

        foreach ($this->conf as $name => $block) {
            $containers = $block['container'];

            if (!is_array($containers)) {
                $containers = [$containers];
            }

            foreach ($containers as $container) {
                if (!isset($this->containers[$container])) {
                    continue;
                }

                $this->containers[$container]['sections'][] = $name;
            }
        }

        uasort($this->conf, function($a, $b) {
            if ($a['order'] == $b['order']) {
                return $a['title'] < $b['title'] ? -1 : 1;
            }
            return ($a['order'] < $b['order']) ? -1 : 1;
        });

        $this->containers = array_filter($this->containers, function($container) {
            $isTVContainer = isset($container['placement']) && $container['placement'] = 'tv';
            if ($this->isTV && !$isTVContainer) {
                return false;
            }

            return !empty($container['sections']);
        });

        $this->conf = array_filter($this->conf, function($conf) {
            $containers = isset($conf['container']) ? $conf['container'] : ['default'];

            if (!is_array($containers)) {
                $containers = [$containers];
            }

            foreach ($containers as $container) {
                if (isset($this->containers[$container])) {
                    return true;
                }
            }

            return false;
        });

        $this->containers = array_map(function($item) {
            $item['sections'] = array_unique($item['sections']);
            return $item;
        }, $this->containers);


        $this->data = [];

        $query = $this->modx->db->select('*', $this->table, "`user_id` = '$docid'" . ($containerName !== null ? " AND `container` = '$containerName'" : '') . (!$this->isBackend ? " AND `visible` = '1'" : ''), "`index` ASC");

        while ($row = $this->modx->db->getRow($query)) {
            $row['config'] = str_replace('.php', '', $row['config']);

            if (isset($this->conf[ $row['config'] ])) {
                $row['values'] = json_decode($row['values'], true);
                $this->data[] = $row;
            }
        }
    }

    /**
     * Called at OnDocFormSave event for saving content blocks
     */
    public function save() {
        if (isset($_POST['contentblocks'])) {
            $docid  = !empty($this->params['id']) ? $this->params['id'] : null;

            if(!$docid) {
                exit('Ошибка передачи данных');
            }

            foreach ($_POST['contentblocks'] as $container => $blocks) {
                if (is_array($blocks)) {
                    $exists = array_map(function($element) {
                        return $element['id'];
                    }, $blocks);

                    $this->modx->db->delete($this->table, "`user_id` = '$docid' AND `container` = '$container' AND `id` NOT IN ('" . implode("','", $exists) . "')");

                    foreach ($blocks as $index => $row) {
                        $data = [
                            'container' => $this->modx->db->escape($container),
                            'config'    => $this->modx->db->escape($row['config']),
                            'values'    => $this->modx->db->escape($row['values']),
                            'visible'   => $row['visible'] > 0 ? 1 : 0,
                            'index'     => $index,
                        ];

                        if (!empty($row['id'])) {
                            $this->modx->db->update($data, $this->table, "`id` = '{$row[id]}'");
                        } else {
                            $data['user_id'] = $docid;
                            $this->modx->db->insert($data, $this->table);
                        }
                    }
                } else {
                    $this->modx->db->delete($this->table, "`user_id` = '" . $docid . "' AND `container` = '$container'");
                }
            }
        }
    }

    /**
     * Parse values from modx-style string,
     * e.g. 1||2,
     * or @EVAL ...,
     * or @SELECT
     *
     * @param  mixed $input
     * @return mixed
     */
    private function parseValues($input) {
        if (!function_exists('ParseIntputOptions')) {
            require_once(MODX_MANAGER_PATH . 'includes/tmplvars.inc.php');
        }

        if (!function_exists('ProcessTVCommand')) {
            require_once(MODX_MANAGER_PATH . 'includes/tmplvars.commands.inc.php');
        }

        if (!is_string($input)) {
            return $input;
        } else {
            $values   = [];
            $elements = ParseIntputOptions(ProcessTVCommand($input, '', '', 'tvform', $tv = []));

            if (!empty($elements)) {
                foreach ($elements as $element) {
                    list($val, $key) = is_array($element) ? $element : explode('==', $element);

                    if (strlen($val) == 0) {
                        $val = $key;
                    }

                    if (strlen($key) == 0) {
                        $key = $val;
                    }

                    $values[$key] = $val;
                }
            }
        }

        return $values;
    }

    /**
     * Renders field control
     *
     * @param  array $field Array with field options
     * @param  string $name Symbolic identificator of field
     * @param  mixed $value Value of field
     * @return string Output
     */
    public function renderField($field, $name, $value) {
        $out = '';

        $default = '';

        if (!empty($field['default'])) {
            $default = $this->parseValues($field['default']);

            if ($field['type'] != 'checkbox' && is_array($default)) {
                $default = reset($default);
            }
        }

        $params = [
            'name'     => $name,
            'field'    => $field,
            'value'    => is_null($value) ? $default : $value,
            'elements' => [
                '' => $this->lang['No variants provided'],
            ],
        ];

        switch ($field['type']) {
            case 'group': {
                if (!is_array($value)) {
                    $value = [ [] ];
                } else {
                    array_unshift($value, []);
                }

                return $this->renderTpl('tpl/field_group.tpl', array_merge($params, [
                    'values' => $value,
                ]));
            }

            case 'richtext': {
                if (isset($field['theme']) && !isset($this->themes[ $field['theme'] ]) && in_array($this->richeditor, [ 'TinyMCE4' ])) {
                    $result = $this->modx->invokeEvent('OnRichTextEditorInit', [
                        'editor'  => $this->richeditor,
                        'options' => [ 'theme' => $field['theme'] ],
                    ]);

                    if (is_array($result)) {
                        $result = implode('', $result);
                    }

                    $this->themes[ $field['theme'] ] = $result;
                }

                return $this->renderTpl('tpl/field_richtext.tpl', $params) . $this->trigger('OnPBFieldRender', $params);
            }

            case 'checkbox': {
                if (!is_array($value)) {
                    $value = [ $value ];
                }
            }

            case 'radio': {
                $params['layout'] = 'vertical';

                if (isset($field['layout']) && in_array($field['layout'], [ 'horizontal', 'vertical' ])) {
                    $params['layout'] = $field['layout'];
                }
            }

            case 'dropdown': {
                if (!empty($field['elements'])) {
                    $params['elements'] = $this->parseValues($field['elements']);
                }
            }

            default: {
                return $this->renderTpl('tpl/field_' . $field['type'] . '.tpl', $params) . $this->trigger('OnPBFieldRender', $params);
            }
        }

        return '';
    }

    /**
     * Wrapper for invokeEvent method
     *
     * @param  string $event  Name of the event
     * @param  array  $params
     * @return string
     */
    public function trigger($event, $params) {
        $result = $this->modx->invokeEvent($event, $params);

        if (is_array($result)) {
            $result = implode($result);
        }

        return $result;
    }

    /**
     * Called ad OnWebPageInit, OnManagerPageInit events once after plugin installed
     */
    public function install() {
        $table = $this->modx->getFullTableName('system_eventnames');

        foreach (['OnPBContainerRender', 'OnPBFieldRender'] as $event) {
            $query = $this->modx->db->select('*', $table, "`name` = '" . $event . "'");

            if (!$this->modx->db->getRecordCount($query)) {
                $this->modx->db->insert([
                    'name'      => $event,
                    'service'   => 6,
                    'groupname' => 'PageBuilder',
                ], $table);
            }
        }
    }

    /**
     * Called at OnDocDuplicate event
     */
    public function duplicate() {
        if ($this->params['id'] && $this->params['new_id']) {
            $query = $this->modx->db->select('*', $this->table, "`user_id` = '" . $this->params['id'] . "'", "`index` ASC");

            while ($row = $this->modx->db->getRow($query)) {
                $this->modx->db->insert([
                    'user_id' => $this->params['new_id'],
                    'container'   => $this->modx->db->escape($row['container']),
                    'config'      => $this->modx->db->escape($row['config']),
                    'values'      => $this->modx->db->escape($row['values']),
                    'visible'     => $row['visible'] > 0 ? 1 : 0,
                    'index'       => $row['index'],
                ], $this->table);
            }
        }
    }

    /**
     * Called at OnBeforeEmptyTrash event
     */
    public function delete() {
        if (!empty($this->params['ids'])) {
            $this->modx->db->delete($this->table, "`user_id` IN ('" . implode("','", $this->params['ids']) . "')");
        }
    }

}
