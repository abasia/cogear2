<?php

/**
 *  User gear
 *
 * @author		Dmitriy Belyaev <admin@cogear.ru>
 * @copyright		Copyright (c) 2011, Dmitriy Belyaev
 * @license		http://cogear.ru/license.html
 * @link		http://cogear.ru
 * @package		Core
 * @subpackage
 * @version		$Id$
 */
class User_Gear extends Gear {

    protected $name = 'User';
    protected $description = 'Manage users.';
    protected $order = -10;
    protected $current;
    protected $roles;
    protected $hooks = array(
        'post.show.full.before' => 'postShowUserNavbar',
    );

    /**
     * Init
     */
    public function init() {
        parent::init();
        $this->adapter = new User_Object();
        $this->adapter->init();
        new Twitter_Bootstrap_Navbar(array(
                    'name' => 'navbar',
                    'render' => 'before',
                ));
    }

    /**
     * Menu builder
     * 
     * @param string $name
     * @param object $menu 
     */
    public function menu($name, $menu) {
        d('User');
        switch ($name) {
            case 'navbar':
                if ($this->user->id) {
                    $menu->register(array(
                        'label' => $this->getAvatarImage('avatar.navbar'),
                        'link' => $this->getLink(),
                        'place' => 'left',
                    ));
                    $menu->register(array(
                        'label' => $this->getName(),
                        'link' => $this->getLink(),
                        'place' => 'left',
                        'active' => TRUE,
                    ));
                    $menu->register(array(
                        'label' => t('Logout'),
                        'link' => s('/user/logout'),
                        'place' => 'right',
                    ));
                } else {
                    $menu->register(array(
                        'label' => t('Login'),
                        'link' => l('/user/login'),
                        'place' => 'right',
                    ));
                    $menu->register(array(
                        'label' => t('Register'),
                        'link' => l('/user/register'),
                        'place' => 'right',
                    ));
                }
                break;
        }
        d();
    }

    /**
     * Dispatcher
     * @param string $action
     */
    public function index($action = 'index', $subaction=NULL) {
        switch ($action) {
            case 'login':
            case 'register':
            case 'lostpassword':
                d('User');
                new Menu_Auto(array(
                            'name' => 'user.login',
                            'template' => 'Twitter_Bootstrap.tabs',
                            'elements' => array(
                                'login' => array(
                                    'label' => t('Login'),
                                    'link' => l('/user/login'),
                                ),
                                'lostpassword' => array(
                                    'label' => t('Lost password'),
                                    'link' => l('/user/lostpassword'),
                                    'access' => $this->router->check('user/lostpassword'),
                                ),
                                'register' => array(
                                    'label' => t('Register'),
                                    'link' => l('/user/register'),
                                ),
                            ),
                            'render' => 'content',
                        ));
                d();
        }
        switch ($action) {
            case 'login':
                $this->login_action();
                break;
            case 'logout':
                $this->logout_action();
                break;
            case 'lostpassword':
                $this->lostpassword_action();
                break;
            case 'register':
                $this->register_action();
                break;
            case 'index':
            case 'profile':
                $this->show_action();
                break;
            case 'edit':
                $this->edit_action($subaction);
                break;
            default:
                $this->show_action($action);
        }
    }

    /**
     * Users list
     */
    public function users($action = NULL, $subaction = NULL) {
        switch ($action) {
            default:
                $grid = new Grid('users');
                $users = new User_Object();
                $this->db->order('id', 'ASC');
                $pager = new Pager_Pages(array(
                            'count' => $users->count(),
                            'current' => $subaction,
                            'per_page' => config('pages.per_page', 5),
                            'base_uri' => '/users/page/',
                            'target' => 'content',
                        ));
                $grid->adopt($users->findAll());
                $grid->show();
                $pager->show();
        }
    }

    /**
     * Show user profile
     * 
     * @param string $login
     */
    public function show_action($login = NULL) {
        if ($login) {
            $user = new User_Object();
            $this->db->where('login', $login);
            if (!$user->find()) {
                return event('404');
            }
        } else {
            $user = $this->adapter;
        }
        if ($user->id) {
            $user->navbar()->show();
            $tpl = new Template('User.profile');
            $tpl->user = $user;
            $tpl->show();
        } else {
            return event('404');
        }
    }

    /**
     * Edit action
     * 
     * @param   string  $login
     */
    public function edit_action($id = NULL) {
        $id OR $id = $this->user->id;
        $user = new User_Object();
        $this->db->where('id', $id);
        if (!$user->find()) {
            return event('404');
        }
        if (!access('user.edit.all') && $this->id != $user->id) {
            return event('403');
        }
        $user->navbar()->show();
        $form = new Form('User.profile');
        $user->password = '';
        $form->attach($user->object);
        if ($user->id == 1) {
            $form->elements->delete->options->render = FALSE;
        }
        if ($result = $form->result()) {
            if ($user->login != $result['login']) {
                $redirect = Url::gear('user') . $result['login'];
            }
            if ($result->delete && access('users delete_all')) {
                $user->delete();
                flash_success(t('User <b>%s</b> was deleted!'));
                redirect(l('/users'));
            }
            $user->object->adopt($result);
            if ($result->password) {
                $user->hashPassword();
            } else {
                unset($user->password);
            }
            if ($user->update()) {
                flash_success(t('User data saved!', 'User'), t('Success'));
                if ($user->id == $this->id) {
                    $this->store($user->object->toArray());
                }
                redirect(l('/user/edit/' . $id));
            }
        }
        $form->show();
    }

    /**
     * Login form show
     */
    public function login_action() {
        if ($this->isLogged()) {
            return warning('You are already logged in!', 'Authorization');
        }
        $form = new Form('User.login');
        if ($data = $form->result()) {
            $this->attach($data);
            $this->hashPassword();
            if ($this->login()) {
                $data->saveme && $this->remember();
                redirect(Url::gear('user'));
            } else {
                error(t('Wrong credentials.', 'User'), t('Authentification error', 'User'));
            }
        }
        $form->show();
    }

    /**
     * Logout
     */
    public function logout_action() {
        $this->logout();
        redirect(Url::link());
    }

    /**
     * Lost password recovery
     */
    public function lostpassword_action() {
        $form = new Form('User.lostpassword');
        if ($result = $form->result()) {
            $this->attach($result);
            if ($this->find()) {

                back();
            } else {
                error(t('Wrong credentials.', 'User'), t('Authentification error', 'User'));
            }
        }
        $form->show();
    }

    /**
     * User registration
     */
    public function register_action($code = NULL) {
        if (!config('user.register.enabled', TRUE)) {
            return warning('Registration is turned off by site admin');
        }
        if ($this->isLogged()) {
            return warning('You are already logged in!', 'Authorization');
        }
        if ($code) {
            $user = new User();
            $user->hash = $code;
            if($user->find()){
                $form = new Form('User.register.verify');
                if($result = $form->result()){
                    $user->attach($result); 
                    $user->hashPassword();
                    $user->hash = $user->genHash($user->password);
                    $user->save();
                    $this->attach($user->adapter->object);
                    if($this->login()){
                        flash_success(t('Registration is complete!','User.register'));
                        redirect($this->getLink());
                    }
                }
                $form->show();
            }
            else {
                error(t('Registration code was not found.','User.register'));
            }
        } else {
            $form = new Form('User.register');
            if ($result = $form->result()) {
                $user = new User();
                $user->email = $result->email;
                if(!$user->find()){
                    $user->hash = $this->secure->genHash();
                }
                if (config('user.register.verification',TRUE)) {
                    $verify_link = l('/register/'.$user->hash, TRUE);
                    $mail = new Mail(array(
                        'name' => 'register.verify',
                        'subject' => t('Registraion on %s','Mail.registration',config('site.url')),
                        'body' => t('You have been successfully registered on http://%s. <br/>
                            Please, click following link to procceed email verification:<p>
                            <a href="http://%s">%s</a>','Mail.registration',config('site.url'),$verify_link,$verify_link),
                    ));
                    $mail->to($user->email);
                    if($mail->send()){
                        $user->save();
                        success(t('Confirmation letter has been successfully send to <b>%s</b>. Follow the instructions.','Mail.registration',$user->email));
                    }
                } else {
                    $user->save();
                    redirect(l('/user/register/'.$user->hash));
                }
            } else {
                $form->show();
            }
        }
    }

    /**
     * Get user roles
     * 
     * @return  Core_ArrayObject
     */
    public function getRoles() {
        if ($this->roles) {
            return $this->roles;
        }
        $this->roles = new Core_ArrayObject(array(
                    0 => 'guest',
                    1 => 'admin',
                    100 => 'user'
                ));
        if ($extra_groups = $this->system_cache->read('user_roles', TRUE)) {
            $this->roles->mix($extra_groups);
        }
        return $this->roles;
    }

    /**
     * Get translated roles list
     * 
     * @return array
     */
    public function getRolesList() {
        $roles = array();
        foreach ($this->roles as $id => $role) {
            $roles[$id] = t($role, 'User Roles');
        }
        return $roles;
    }

    /**
     * Administrate users
     * 
     * @param string $action 
     */
    public function admin($action = '') {
        new Menu_Tabs('admin_user', Url::gear('admin') . 'user');
        switch ($action) {
            case 'add':
                $this->admin_add();
                break;
            default:
                $this->users();
        }
    }

    /**
     * Add a new user
     */
    public function admin_add() {
        $form = new Form('User.register');
        if ($data = $form->result()) {
            $user = new User_Object(FALSE);
            $user->attach($data);
            $user->hashPassword();
            $user->save();
            info('User was successfully registered!', 'Registration succeed.');
        }
        else
            $form->show();
    }

    /**
     * Show user navbar
     *
     * @param object $Stack 
     */
    public function postShowUserNavbar($Stack) {
        return $Stack->object->author->navbar()->show();
    }

}