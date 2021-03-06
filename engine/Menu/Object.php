<?php

/**
 * Menu
 *
 * @author		Dmitriy Belyaev <admin@cogear.ru>
 * @copyright		Copyright (c) 2011, Dmitriy Belyaev
 * @license		http://cogear.ru/license.html
 * @link		http://cogear.ru
 * @package		Core
 * @subpackage
 * @version		$Id$
 */
class Menu_Object extends Object {

    protected $pointer = 0;
    public $options = array(
        'name' => 'primary',
        'template' => 'Menu.menu',
        'show_empty' => TRUE,
        'render' => 'content',
    );

    /**
     * Constructor
     *
     * @param array $options
     */
    public function __construct($options) {
        parent::__construct($options);
        cogear()->menu->register($this->options->name, $this);
        $this->options->base = rtrim(parse_url($this->options->base ? $this->options->base : Url::link(), PHP_URL_PATH), '/') . '/';
        $this->options->render && hook($this->options->render, array($this, 'output'));
        // Register elements from config
        if ($this->options->elements) {
            foreach ($this->options->elements as $item) {
                $this->register($item->toArray());
            }
        }
    }

    /**
     * Register menu item to menu
     *
     * @param string $path
     * @param Menu_Item $item
     */
    public function register($item) {
        if (is_array($item)) {
            isset($item['order']) OR $item['order'] = $this->pointer++;
            $item = new Menu_Item($item);
        }
        if ($item->access !== FALSE) {
            $this->append($item);
        }
        return $this;
    }

    /**
     * Set menu items active
     */
    public function setActive() {
        foreach ($this as &$item) {
           NULL === $item->options->active && cogear()->router->check(trim($item->link, ' /')) && $item->options->active = TRUE;
        }
    }
    
    /**
     * Filter menu elements with conditions
     *
     * @param   array   $conditions
     * @return Menu_Object
     */
    public function filter(array $condtitions) {
        $result = new Core_ArrayObject();
        foreach ($this as $item) {
            $stop = FALSE;
            foreach ($condtitions as $key => $value) {
                if ($item->$key == $value) {
                    $result->append($item);
                }
            }
        }
        return $result->count() ? $result : NULL;
    }

    /**
     * Render menu
     *
     * @param string $glue
     * @return string
     */
    public function render() {
        event('menu.render', $this);
        if ($this->count()) {
            $this->uasort('Core_ArrayObject::sortByOrder');
            $this->setActive();
        }
        if ($this->count() OR $this->options->show_empty) {
            $tpl = new Template($this->options->template);
            $tpl->menu = $this;
            return $tpl->render();
        }
        return NULL;
    }

    /**
     * Show menu
     */
    public function output() {
        echo $this->render();
    }

}
