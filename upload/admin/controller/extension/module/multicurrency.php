<?php

class ControllerExtensionModuleMulticurrency extends Controller {

    private $roundPrice;
    private $configCurrency;

    public function index() {
        $this->load->language('extension/module/multicurrency');
        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('setting/setting');
        $this->load->model('localisation/currency');
        $this->load->model('extension/module/multicurrency');

        $this->setRoundPrice();

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {

            $config_currency = $this->getConfigCurrency();

            $options = $this->request->post['options'];
            unset($this->request->post['options']);

            // Current Manufacturers Currencies
            $current_manufacturers = $this->model_extension_module_multicurrency->getCurrencies();
            unset($current_manufacturers['default']);

            // Request Manufacturers Currencies
            $request_manufacturers = [];
            $manufacturers = [];

            if (isset($this->request->post['manufacturers'])) {
                $request_manufacturers = $this->request->post['manufacturers'];
                unset($this->request->post['manufacturers']);
            }


            foreach ($current_manufacturers as $manufacturer_id => $manufacturer) {
                if (!isset($request_manufacturers[$manufacturer_id])) {
                    $manufacturers[$manufacturer_id] = [
                        'name' => $manufacturer['name'], 'currency_id' => $config_currency['currency_id'], 'currency_value' => $config_currency['value'], 'remove_currency_id' => $manufacturer['currency_id']
                    ];
                }
            }

            foreach ($request_manufacturers as $manufacturer_id => $manufacturer) {
                if (isset($current_manufacturers[$manufacturer_id])) {
                    if (((int)$manufacturer['currency_id'] !== (int)$current_manufacturers[$manufacturer_id]['currency_id']) || ((float)$manufacturer['currency_value'] !== (float)$current_manufacturers[$manufacturer_id]['currency_value'])) {
                        $manufacturers[$manufacturer_id] = $manufacturer;
                    }
                } else {
                    $manufacturers[$manufacturer_id] = $manufacturer;
                }
            }

            foreach ($manufacturers as $manufacturer_id => $manufacturer) {
                $this->model_extension_module_multicurrency->updateManufacturerCurrency($manufacturer_id, $manufacturer);
                if (!(bool)$options['update_prices']) {
                    if (isset($manufacturer['remove_currency_id'])) {
                        $this->model_extension_module_multicurrency->removeProductsCurrency($manufacturer_id, $manufacturer, $this->roundPrice);
                    } else {
                        $this->model_extension_module_multicurrency->updateProductsCurrency($manufacturer_id, $manufacturer, $this->roundPrice);
                    }
                }
            }

            if ((bool)$options['update_prices']) {
                $this->model_extension_module_multicurrency->updateProductsCurrencies($this->roundPrice);
            }

            if ($manufacturers || (bool)$options['update_prices']) {
                $this->model_extension_module_multicurrency->deleteCache();
            }

            $this->model_setting_setting->editSetting('module_multicurrency', $this->request->post);

            $this->session->data['success'] = $this->language->get('text_success');

            if ($options['save_and_stay']) {
                $this->response->redirect($this->url->link('extension/module/multicurrency', 'user_token=' . $this->session->data['user_token'], true));
            } else {
                $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true));
            }
        }

        if (isset($this->request->post['module_multicurrency_status'])) {
            $data['module_multicurrency_status'] = $this->request->post['module_multicurrency_status'];
        } else {
            $data['module_multicurrency_status'] = $this->model_setting_setting->getSettingValue('module_multicurrency_status');
        }

        $data['module_multicurrency_round_price'] = $this->roundPrice;

        $data['manufacturers'] = $this->model_extension_module_multicurrency->getCurrencies();
        unset($data['manufacturers']['default']);

        $data['currencies'] = [];
        $currencies = $this->model_localisation_currency->getCurrencies();
        unset($currencies[$this->config->get('config_currency')]);
        foreach ($currencies as $currency) {
            $data['currencies'][$currency['currency_id']] = [
                'currency_id' => $currency['currency_id'],
                'title' => $currency['title'],
                'code' => $currency['code']
            ];
        }

        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }

        $data['breadcrumbs'] = array();
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'), 'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_extension'), 'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'), 'href' => $this->url->link('extension/module/multicurrency', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['user_token'] = $this->session->data['user_token'];

        $data['action'] = $this->url->link('extension/module/multicurrency', 'user_token=' . $this->session->data['user_token'], true);
        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/module/multicurrency', $data));
    }

    public function getCurrencies() {
        $this->load->model('extension/module/multicurrency');
        $currencies = $this->model_extension_module_multicurrency->getCurrencies();

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($currencies));
    }

    public function getManufacturersWithoutCurrency() {
        $this->load->model('extension/module/multicurrency');
        $currencies = $this->model_extension_module_multicurrency->getManufacturersWithoutCurrency($this->getConfigCurrency());

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($currencies));
    }

    public function install() {
        $this->load->model('setting/setting');
        $this->model_setting_setting->editSetting('module_multicurrency_round_price', ['module_multicurrency_round_price' => '1']);

        $this->load->model('localisation/currency');
        $config_currency = $this->model_localisation_currency->getCurrencyByCode($this->config->get('config_currency'));

        $this->load->model('extension/module/multicurrency');
        $this->model_extension_module_multicurrency->createTables($config_currency);
    }

    public function uninstall() {
        $this->load->model('extension/module/multicurrency');
        $this->model_extension_module_multicurrency->dropTables();
    }

    private function getConfigCurrency() {
        if (!$this->configCurrency) {
            $this->load->model('localisation/currency');
            $this->configCurrency = $this->model_localisation_currency->getCurrencies()[$this->config->get('config_currency')];
        }
        return $this->configCurrency;
    }

    private function setRoundPrice() {
        $this->roundPrice = isset($this->request->post['module_multicurrency_round_price']) ? (int)$this->request->post['module_multicurrency_round_price'] : (int)$this->model_setting_setting->getSettingValue('module_multicurrency_round_price');
    }

    protected function validate() {
        if (!$this->user->hasPermission('modify', 'extension/module/multicurrency')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }
        return !$this->error;
    }
}