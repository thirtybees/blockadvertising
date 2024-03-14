<?php
/**
 * 2007-2016 PrestaShop
 *
 * Thirty Bees is an extension to the PrestaShop e-commerce software developed by PrestaShop SA
 * Copyright (C) 2017 Thirty Bees
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@thirtybees.com so we can send you a copy immediately.
 *
 * @author    Thirty Bees <modules@thirtybees.com>
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2017 Thirty Bees
 * @copyright 2007-2016 PrestaShop SA
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 * PrestaShop is an internationally registered trademark & property of PrestaShop SA
 */

if (!defined('_TB_VERSION_')) {
    exit;
}

/**
 * Class BlockAdvertising
 */
class BlockAdvertising extends Module
{
    /**
     * @var string Title associated to the image
     */
    public $adv_title;

    /**
     * @var string Link associated to the image
     */
    public $adv_link;

    /**
     * @var string Name of the image without extension
     */
    public $adv_imgname;

    /**
     * @var string Image path with extension
     */
    public $adv_img;

    /**
     * @var array
     */
    protected $errors;

    /**
     * BlockAdvertising constructor.
     *
     * @throws PrestaShopException
     */
    public function __construct()
    {
        $this->name = 'blockadvertising';
        $this->tab = 'advertising_marketing';
        $this->version = '0.1';
        $this->author = 'thirty bees';
        $this->need_instance = 0;

        $this->bootstrap = true;
        parent::__construct();

        $this->displayName = $this->l('Advertising block');
        $this->description = $this->l('Adds an advertisement block to selected sections of your e-commerce website.');
        $this->ps_versions_compliancy = array('min' => '1.0.0', 'max' => '1.0.0');

        $this->initialize();
    }

    /**
     * Set the properties of the module, like the link to the image and the title (contextual to the current shop context)
     *
     * @throws PrestaShopException
     */
    protected function initialize()
    {
        $this->adv_imgname = 'avt';
        if ((Shop::getContext() == Shop::CONTEXT_GROUP || Shop::getContext() == Shop::CONTEXT_SHOP)
            && file_exists(_PS_MODULE_DIR_.$this->name.'/img/'.$this->adv_imgname.'-g'.$this->context->shop->getContextShopGroupID().'.'.Configuration::get('BLOCKADVERT_IMG_EXT'))
        ) {
            $this->adv_imgname .= '-g'.$this->context->shop->getContextShopGroupID();
        }
        if (Shop::getContext() == Shop::CONTEXT_SHOP
            && file_exists(_PS_MODULE_DIR_.$this->name.'/img/'.$this->adv_imgname.'-s'.$this->context->shop->getContextShopID().'.'.Configuration::get('BLOCKADVERT_IMG_EXT'))
        ) {
            $this->adv_imgname .= '-s'.$this->context->shop->getContextShopID();
        }

        // If none of them available go default
        if ($this->adv_imgname == 'avt') {
            $this->adv_img = Tools::getMediaServer($this->name)._MODULE_DIR_.$this->name.'/img/fixtures/'.$this->adv_imgname.'.jpg';
        } else {
            $this->adv_img = Tools::getMediaServer($this->name)._MODULE_DIR_.$this->name.'/img/'.$this->adv_imgname.'.'.Configuration::get('BLOCKADVERT_IMG_EXT');
        }
        $this->adv_link = htmlentities(Configuration::get('BLOCKADVERT_LINK'), ENT_QUOTES, 'UTF-8');
        $this->adv_title = htmlentities(Configuration::get('BLOCKADVERT_TITLE'), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Install the module
     *
     * @return bool Indicates whether the module was successfully installed
     * @throws PrestaShopException
     */
    public function install()
    {
        if (!parent::install()) {
            return false;
        }

        // Hook the module either on the left or right column
        $theme = new Theme(Context::getContext()->shop->id_theme);
        if ((!$theme->default_left_column || !$this->registerHook('leftColumn'))
            && (!$theme->default_right_column || !$this->registerHook('rightColumn'))
        ) {
            // If there are no colums implemented by the template, throw an error and uninstall the module
            $this->errors[] = $this->l('This module needs to be hooked to a column, but your theme does not implement one');
            parent::uninstall();

            return false;
        }

        Configuration::updateGlobalValue('BLOCKADVERT_LINK', 'http://www.thirtybees.com/');
        Configuration::updateGlobalValue('BLOCKADVERT_TITLE', 'ThirtyBees');
        // Try to update with the extension of the image that exists in the module directory
        foreach (scandir(_PS_MODULE_DIR_.$this->name) as $file) {
            if (in_array($file, array('avt.jpg', 'avt.gif', 'avt.png'))) {
                Configuration::updateGlobalValue('BLOCKADVERT_IMG_EXT', substr($file, strrpos($file, '.') + 1));
            }
        }

        return true;
    }

    /**
     * Uninstall the module
     *
     * @return bool Indicates whether the module has been successfull uninstalled
     *
     * @throws PrestaShopException
     * @since 1.0.0
     */
    public function uninstall()
    {
        Configuration::deleteByName('BLOCKADVERT_LINK');
        Configuration::deleteByName('BLOCKADVERT_TITLE');
        Configuration::deleteByName('BLOCKADVERT_IMG_EXT');

        return (parent::uninstall());
    }

    /**
     * getContent used to display admin module form
     *
     * @return string content
     *
     * @throws PrestaShopException
     * @throws SmartyException
     * @since 1.0.0
     */
    public function getContent()
    {
        $this->postProcess();

        return $this->renderForm();
    }

    /**
     * @throws PrestaShopException
     */
    public function postProcess()
    {
        if (Tools::isSubmit('submitDeleteImgConf')) {
            $this->deleteCurrentImage();
        }

        $errors = '';
        if (Tools::isSubmit('submitAdvConf')) {
            if (isset($_FILES['adv_img']) && !empty($_FILES['adv_img']['tmp_name'])) {
                if ($error = ImageManager::validateUpload($_FILES['adv_img'], Tools::convertBytes(ini_get('upload_max_filesize')))) {
                    $errors .= $error;
                } else {
                    Configuration::updateValue('BLOCKADVERT_IMG_EXT', substr($_FILES['adv_img']['name'], strrpos($_FILES['adv_img']['name'], '.') + 1));

                    // Set the image name with a name contextual to the shop context
                    $this->adv_imgname = 'avt';
                    if (Shop::getContext() == Shop::CONTEXT_GROUP) {
                        $this->adv_imgname = 'avt-g'.(int) $this->context->shop->getContextShopGroupID();
                    } elseif (Shop::getContext() == Shop::CONTEXT_SHOP) {
                        $this->adv_imgname = 'avt-s'.(int) $this->context->shop->getContextShopID();
                    }

                    // Copy the image in the module directory with its new name
                    if (!move_uploaded_file($_FILES['adv_img']['tmp_name'], _PS_MODULE_DIR_.$this->name.'/img/'.$this->adv_imgname.'.'.Configuration::get('BLOCKADVERT_IMG_EXT'))) {
                        $errors .= $this->l('File upload error.');
                    }
                }
            }

            // If the link is not set, then delete it in order to use the next default value (either the global value or the group value)
            if ($link = Tools::getValue('adv_link')) {
                Configuration::updateValue('BLOCKADVERT_LINK', $link);
            } elseif (Shop::getContext() == Shop::CONTEXT_SHOP || Shop::getContext() == Shop::CONTEXT_GROUP) {
                Configuration::deleteFromContext('BLOCKADVERT_LINK');
            }

            // If the title is not set, then delete it in order to use the next default value (either the global value or the group value)
            if ($title = Tools::getValue('adv_title')) {
                Configuration::updateValue('BLOCKADVERT_TITLE', $title);
            } elseif (Shop::getContext() == Shop::CONTEXT_SHOP || Shop::getContext() == Shop::CONTEXT_GROUP) {
                Configuration::deleteFromContext('BLOCKADVERT_TITLE');
            }

            // Reset the module properties
            $this->initialize();
            $this->_clearCache('blockadvertising.tpl');

            if (!$errors) {
                Tools::redirectAdmin(AdminController::$currentIndex.'&configure='.$this->name.'&token='.Tools::getAdminTokenLite('AdminModules').'&conf=6');
            }
            echo $this->displayError($errors);
        }

    }

    /**
     * delete the contextual image (it is not allowed to delete the default image)
     *
     * @return void
     *
     * @throws PrestaShopException
     */
    private function deleteCurrentImage()
    {
        // Delete the image file
        if ($this->adv_imgname != 'avt' && file_exists(_PS_MODULE_DIR_.$this->name.'/img/'.$this->adv_imgname.'.'.Configuration::get('BLOCKADVERT_IMG_EXT'))) {
            unlink(_PS_MODULE_DIR_.$this->name.'/img/'.$this->adv_imgname.'.'.Configuration::get('BLOCKADVERT_IMG_EXT'));
        }

        // Update the extension to the global value or the shop group value if available
        Configuration::deleteFromContext('BLOCKADVERT_IMG_EXT');
        Configuration::updateValue('BLOCKADVERT_IMG_EXT', Configuration::get('BLOCKADVERT_IMG_EXT'));

        // Reset the properties of the module
        $this->initialize();
    }

    /**
     * @return string
     *
     * @throws PrestaShopException
     * @throws SmartyException
     */
    public function renderForm()
    {
        $formFields = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Configuration'),
                    'icon'  => 'icon-cogs',
                ),
                'input'  => array(
                    array(
                        'type'  => 'file',
                        'label' => $this->l('Image for the advertisement'),
                        'name'  => 'adv_img',
                        'desc'  => $this->l('By default the image will appear in the left column. The recommended dimensions are 155 x 163px.'),
                        'thumb' => $this->context->link->protocol_content.$this->adv_img,
                    ),
                    array(
                        'type'  => 'text',
                        'label' => $this->l('Target link for the image'),
                        'name'  => 'adv_link',
                    ),
                    array(
                        'type'  => 'text',
                        'label' => $this->l('Title of the target link'),
                        'name'  => 'adv_title',
                        'desc'  => $this->l('This title will be displayed when you mouse over the advertisement block in your shop.'),
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );

        /** @var AdminController $controller */
        $controller = $this->context->controller;

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $lang = new Language((int) Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitAdvConf';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFieldsValues(),
            'languages'    => $controller->getLanguages(),
            'id_language'  => $this->context->language->id,
        );

        return $helper->generateForm(array($formFields));
    }

    /**
     * @return array
     *
     * @throws PrestaShopException
     * @since 1.0.0
     */
    public function getConfigFieldsValues()
    {
        return array(
            'adv_link'  => Tools::getValue('adv_link', Configuration::get('BLOCKADVERT_LINK')),
            'adv_title' => Tools::getValue('adv_title', Configuration::get('BLOCKADVERT_TITLE')),
        );
    }

    /**
     * @param array $params
     *
     * @return string
     *
     * @throws PrestaShopException
     * @throws SmartyException
     */
    public function hookLeftColumn($params)
    {
        return $this->hookRightColumn($params);
    }

    /**
     * @param array $params
     *
     * @return string
     *
     * @throws PrestaShopException
     * @throws SmartyException
     */
    public function hookRightColumn($params)
    {
        if (!$this->isCached('blockadvertising.tpl', $this->getCacheId())) {
            $this->smarty->assign(
                array(
                    'image'     => $this->context->link->protocol_content.$this->adv_img,
                    'adv_link'  => $this->adv_link,
                    'adv_title' => $this->adv_title,
                )
            );
        }

        return $this->display(__FILE__, 'blockadvertising.tpl', $this->getCacheId());
    }

    /**
     * @param array $params
     *
     * @since 1.0.0
     */
    public function hookHeader($params)
    {
        $this->context->controller->addCSS($this->_path.'blockadvertising.css', 'all');
    }
}
