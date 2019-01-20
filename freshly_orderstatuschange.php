<?php
/**
 * 2007-2019 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 *  @author    PrestaShop SA <contact@prestashop.com>
 *  @copyright 2007-2019 PrestaShop SA
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class Freshly_Orderstatuschange extends Module
{
    protected $config_form = false;

    public function __construct()
    {
        $this->name = 'freshly_orderstatuschange';
        $this->tab = 'others';
        $this->version = '1.0.0';
        $this->author = 'Dídac Ríos';
        $this->need_instance = 1;

        /**
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Freshly Order Status Changer');
        $this->description = $this->l('Module to notify changes in order status');

        $this->confirmUninstall = $this->l('You are about uninstalling the module. Continue?');

        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
    }

    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
     */
    public function install()
    {
        Configuration::updateValue('FRESHLY_ORDERSTATUSCHANGE_LIVE_MODE', false);
        // TODO: Create form in backend to configurate the different settings
        // Protocol HTTP or HTTPS
        Configuration::updateValue('FRESHLY_ORDERSTATUSCHANGE_API_PROTOCOL', 'http');
        Configuration::updateValue('FRESHLY_ORDERSTATUSCHANGE_API_SERVER', 'localhost');
        Configuration::updateValue('FRESHLY_ORDERSTATUSCHANGE_API_PORT', '5656');

        return parent::install() &&
        $this->registerHook('header') &&
        $this->registerHook('backOfficeHeader') &&
        $this->registerHook('actionOrderStatusUpdate') &&
        $this->registerHook('actionOrderStatusPostUpdate');
    }

    public function uninstall()
    {
        Configuration::deleteByName('FRESHLY_ORDERSTATUSCHANGE_LIVE_MODE');

        return parent::uninstall();
    }

    /**
     * Load the configuration form
     */
    public function getContent()
    {
        /**
         * If values have been submitted in the form, process.
         */
        if (((bool) Tools::isSubmit('submitFreshly_orderstatuschangeModule')) == true) {
            $this->postProcess();
        }

        $this->context->smarty->assign('module_dir', $this->_path);

        $output = $this->context->smarty->fetch($this->local_path . 'views/templates/admin/configure.tpl');

        return $output;
    }

    /**
     * Create the form that will be displayed in the configuration of your module.
     */
    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitFreshly_orderstatuschangeModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
        . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(), /* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($this->getConfigForm()));
    }

    /**
     * Create the structure of your form.
     */
    protected function getConfigForm()
    {
        return array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Settings'),
                    'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Live mode'),
                        'name' => 'FRESHLY_ORDERSTATUSCHANGE_LIVE_MODE',
                        'is_bool' => true,
                        'desc' => $this->l('Use this module in live mode'),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled'),
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled'),
                            ),
                        ),
                    ),
                    array(
                        'col' => 3,
                        'type' => 'text',
                        'prefix' => '<i class="icon icon-envelope"></i>',
                        'desc' => $this->l('Enter a valid email address'),
                        'name' => 'FRESHLY_ORDERSTATUSCHANGE_ACCOUNT_EMAIL',
                        'label' => $this->l('Email'),
                    ),
                    array(
                        'type' => 'password',
                        'name' => 'FRESHLY_ORDERSTATUSCHANGE_ACCOUNT_PASSWORD',
                        'label' => $this->l('Password'),
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );
    }

    /**
     * Set values for the inputs.
     */
    protected function getConfigFormValues()
    {
        return array(
            'FRESHLY_ORDERSTATUSCHANGE_LIVE_MODE' => Configuration::get('FRESHLY_ORDERSTATUSCHANGE_LIVE_MODE', true),
            'FRESHLY_ORDERSTATUSCHANGE_ACCOUNT_EMAIL' => Configuration::get('FRESHLY_ORDERSTATUSCHANGE_ACCOUNT_EMAIL', 'contact@prestashop.com'),
            'FRESHLY_ORDERSTATUSCHANGE_ACCOUNT_PASSWORD' => Configuration::get('FRESHLY_ORDERSTATUSCHANGE_ACCOUNT_PASSWORD', null),
        );
    }

    /**
     * Save form data.
     */
    protected function postProcess()
    {
        $form_values = $this->getConfigFormValues();

        foreach (array_keys($form_values) as $key) {
            Configuration::updateValue($key, Tools::getValue($key));
        }
    }

    /**
     * Add the CSS & JavaScript files you want to be loaded in the BO.
     */
    public function hookBackOfficeHeader()
    {
        if (Tools::getValue('module_name') == $this->name) {
            $this->context->controller->addJS($this->_path . 'views/js/back.js');
            $this->context->controller->addCSS($this->_path . 'views/css/back.css');
        }
    }

    /**
     * Add the CSS & JavaScript files you want to be added on the FO.
     */
    public function hookHeader()
    {
        $this->context->controller->addJS($this->_path . '/views/js/front.js');
        $this->context->controller->addCSS($this->_path . '/views/css/front.css');
    }

    public function hookActionOrderStatusUpdate($params)
    {
        // Get order information needed, and send via POST to defined freshly-picking-backend URL

        $order = new Order($params['id_order']);

        $orderProducts = $order->getProducts();

        $productsNames = [];
        foreach ($orderProducts as $orderProduct) {
            $product = new Product($orderProduct['id_product'], false, (int) (Configuration::get('PS_LANG_DEFAULT')));
            $productsNames[] = $product->name;
        }

        $delivery_address = new Address($order->id_address_delivery, (int) (Configuration::get('PS_LANG_DEFAULT')));
        $delivery_country = new Country($delivery_address->id_country, (int) (Configuration::get('PS_LANG_DEFAULT')));

        $orderInfo = array(
            "id_order" => $order->id,
            "date_add" => $order->date_add,
            "customer_name" => $delivery_address->firstname . ' ' . $delivery_address->lastname,
            "delivery_address" => $delivery_address->address1,
            "country" => $delivery_country->name,
            "products" => $productsNames,
            "status" => $params['newOrderStatus']->id,
        );

        // If there is a second line of the address, concatenate it
        if ($delivery_address->address2) {
            $orderInfo['delivery_addres'] = $orderInfo['delivery_address'] . ' ' . $delivery_address->address2;
        }

        $this->postData($orderInfo);
    }

    public function hookActionOrderStatusPostUpdate($params)
    {
        $this->hookActionOrderStatusUpdate($params);
    }

    public function postData($data)
    {

        $configurationUrl = Configuration::get('FRESHLY_ORDERSTATUSCHANGE_API_PROTOCOL', 0) . '://' . Configuration::get('FRESHLY_ORDERSTATUSCHANGE_API_SERVER', 0);
        $endpoint = 'api/order';

        $url = $configurationUrl . '/' . $endpoint;

        $data_string = json_encode($data);

        // Use curl, if ther is no curl use strem_context_create
        // TODO: * Improve error handling
        //       * Add some kind of logger

        if (function_exists('curl_version')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_VERBOSE, true);
            curl_setopt($ch, CURLOPT_PORT, Configuration::get('FRESHLY_ORDERSTATUSCHANGE_API_PORT', 0));
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
            ));
            $result = curl_exec($ch);
        } else {
            $options = array(
                'http' => array(
                    'method' => 'POST',
                    'content' => $data_string,
                    'header' => "Content-Type: application/json\r\n" .
                    "Accept: application/json\r\n",
                ),
            );

            $context = stream_context_create($options);
            $url = $configurationUrl . ':' . Configuration::get('FRESHLY_ORDERSTATUSCHANGE_API_PORT', 0) . '/' . $endpoint;
            $result = file_get_contents($url, false, $context);
            $response = json_decode($result);
        }

        return $result;
    }
}
