<?php

class ModelExtensionModuleMulticurrency extends Model {

    public function getManufacturersWithoutCurrency($config_currency) {
        $currencies = $this->cache->get('admin.manufacturer.without-currency');

        if ($currencies === false) {
            $currencies = [];
            $query = $this->db->query("SELECT `manufacturer_id`, `name` FROM " . DB_PREFIX . "manufacturer WHERE `currency_id` = '" . (int)$config_currency['currency_id'] . "'");
            foreach ($query->rows as $manufacturer) {
                $currencies[$manufacturer['manufacturer_id']] = $manufacturer['name'];
            }

            $this->cache->set('admin.manufacturer.without-currency', $currencies);
        }

        return $currencies;
    }

    public function getCurrencies() {
        $currencies = $this->cache->get('admin.manufacturer.currency');

        if (!$currencies) {
            $this->load->model('localisation/currency');
            $shop_currencies = $this->model_localisation_currency->getCurrencies();
            $config_currency = $shop_currencies[$this->config->get('config_currency')];

            $currencies = [];
            foreach ($shop_currencies as $currency) {
                $currencies['default'][$currency['currency_id']] = [
                    'currency_value' => $currency['value']
                ];
            }

            $query = $this->db->query("SELECT `manufacturer_id`, `name`, `currency_id`, `currency_value` FROM " . DB_PREFIX . "manufacturer WHERE NOT `currency_id` = '" . (int)$config_currency['currency_id'] . "'");
            foreach ($query->rows as $manufacturer) {
                $currencies[$manufacturer['manufacturer_id']] = [
                    'name' => $manufacturer['name'], 'currency_id' => $manufacturer['currency_id'], 'currency_value' => $manufacturer['currency_value']
                ];
            }

            $this->cache->set('admin.manufacturer.currency', $currencies);
        }

        return $currencies;
    }

    public function updateManufacturerCurrency($manufacturer_id, $manufacturer) {
        $this->db->query("UPDATE `" . DB_PREFIX . "manufacturer` SET `currency_id` = '" . (int)$manufacturer['currency_id'] . "', `currency_value` = '" . (float)$manufacturer['currency_value'] . "' WHERE `manufacturer_id` = '" . (int)$manufacturer_id . "'");
    }

    public function updateProductsCurrencies($round_price) {
        $this->db->query("UPDATE `" . DB_PREFIX . "product` AS `p` LEFT JOIN `" . DB_PREFIX . "manufacturer` AS `m` ON `p`.`manufacturer_id` = `m`.`manufacturer_id` AND `p`.`currency_id` = `m`.`currency_id` SET `p`.`price` = " . ($round_price ? 'ROUND' : '') . "(IF(`m`.`currency_value`, `p`.`currency_price` * `m`.`currency_value`, `p`.`currency_price` * (SELECT `value` FROM `" . DB_PREFIX . "currency` AS `c` WHERE `c`.`currency_id` = `p`.`currency_id`)))");
    }

    public function updateProductsCurrency($manufacturer_id, $manufacturer, $round_price) {
        $this->db->query("UPDATE `" . DB_PREFIX . "product` AS `p` INNER JOIN `" . DB_PREFIX . "product` AS `pp` ON `p`.`product_id` = `pp`.`product_id` SET `p`.`price` = " . ($round_price ? 'ROUND' : '') . "(`pp`.`currency_price` * " . (float)$manufacturer['currency_value'] . ") WHERE `p`.`manufacturer_id` = '" . (int)$manufacturer_id . "' AND `p`.`currency_id` = '" . (int)$manufacturer['currency_id'] . "'");
    }

    public function removeProductsCurrency($manufacturer_id, $manufacturer, $round_price) {
        $this->db->query("UPDATE `" . DB_PREFIX . "product` AS `p` INNER JOIN `" . DB_PREFIX . "product` AS `pp` ON `p`.`product_id` = `pp`.`product_id` INNER JOIN `" . DB_PREFIX . "currency` AS `c` ON `p`.`currency_id` = `c`.`currency_id` SET `p`.`price` = " . ($round_price ? 'ROUND' : '') . "(`pp`.`currency_price` * `c`.`value`) WHERE `p`.`manufacturer_id` = '" . (int)$manufacturer_id . "' AND `p`.`currency_id` = '" . (int)$manufacturer['remove_currency_id'] . "'");
    }

    public function deleteCache() {
        $this->cache->delete('admin.manufacturer.currency');
        $this->cache->delete('admin.manufacturer.without-currency');
    }

    public function createTables($config_currency) {
        $this->db->query("ALTER TABLE `" . DB_PREFIX . "product` ADD `currency_id` INT(11) NOT NULL DEFAULT '" . (int)$config_currency['currency_id'] . "', ADD `currency_price` DECIMAL(15,4) NOT NULL");
        $this->db->query("ALTER TABLE `" . DB_PREFIX . "manufacturer` ADD `currency_id` INT(11) NOT NULL DEFAULT '" . (int)$config_currency['currency_id'] . "', ADD `currency_value` DOUBLE(15,8) NOT NULL DEFAULT '" . (float)$config_currency['value'] . "'");
        $this->db->query("UPDATE `" . DB_PREFIX . "product` AS `p` INNER JOIN `" . DB_PREFIX . "product` AS `pp` ON `p`.`product_id` = `pp`.`product_id` SET `p`.`currency_price` = ROUND(`pp`.`price`)");
    }

    public function dropTables() {
        $this->deleteCache();
        $this->db->query("ALTER TABLE `" . DB_PREFIX . "product` DROP `currency_id`, DROP `currency_price`");
        $this->db->query("ALTER TABLE `" . DB_PREFIX . "manufacturer` DROP `currency_id`, DROP `currency_value`");
    }
}