<?php
/**
 * Plugin Name: Affilicious
 * Plugin URI: https://affilicioustheme.com/downloads/affilicious/
 * Author: Affilicious Theme
 * Author URI: https://affilicioustheme.com/
 * Description: Manage affiliate products in Wordpress with price comparisons, automatically updated shops, product variants and much more.
 * Version: 0.9.1
 * License: GPL-2.0 or later
 * Requires at least: 4.5
 * Tested up to: 4.8.1
 * Text Domain: affilicious
 * Domain Path: languages/
 *
 * Affilicious Plugin
 * Copyright (C) 2016-2017, Affilicious - support@affilicioustheme.de
 *
 * Affilicious is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * any later version.
 *
 * Affilicious is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Affilicious. If not, see <http://www.gnu.org/licenses/>.
 */

if (!defined('ABSPATH')) {
	exit('Not allowed to access pages directly.');
}

define('AFFILICIOUS_VERSION', '0.9.1');
define('AFFILICIOUS_MIN_PHP_VERSION', '5.6');
define('AFFILICIOUS_BASE_NAME', plugin_basename(__FILE__));
define('AFFILICIOUS_ROOT_PATH', plugin_dir_path(__FILE__));
define('AFFILICIOUS_ROOT_URL', plugin_dir_url(__FILE__));

if(!class_exists('Affilicious')) {

	class Affilicious
	{
		const NAME = 'affilicious';
		const VERSION = '0.9.1';
		const MIN_PHP_VERSION = '5.6';

		/**
		 * Stores the singleton instance
		 *
		 * @since 0.3
		 * @var Affilicious
		 */
		private static $instance;

		/**
		 * Register all services and parameters for the pimple dependency injection
		 *
		 * @see http://pimple.sensiolabs.org
		 * @var \Pimple\Container
		 */
		private $container;

		/**
		 * Get the instance of the affilicious plugin
		 *
		 * @since 0.3
		 * @return Affilicious
		 */
		public static function get_instance()
		{
			if (self::$instance === null) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * Convenient way to get the service from the dependency injection container.
		 *
		 * @since 0.8
		 * @param string $service_id
		 * @return mixed
		 */
		public static function get($service_id)
		{
			$container = self::get_instance()->get_container();
			$service = $container[$service_id];

			return $service;
		}

		/**
		 * Get the root url to the plugin.
		 *
		 * @since 0.7
		 * @return string
		 */
		public static function get_root_url()
		{
			return AFFILICIOUS_ROOT_URL;
		}

		/**
		 * Get the root path to the plugin.
		 *
		 * @since 0.7
		 * @return string
		 */
		public static function get_root_path()
		{
			return AFFILICIOUS_ROOT_PATH;
		}

		/**
		 * Prepare the plugin with for usage with Wordpress and namespaces.
		 *
		 * @since 0.3
		 */
		private function __construct()
		{
			if (file_exists(__DIR__ . '/vendor/autoload.php')) {
				require(__DIR__ . '/vendor/autoload.php');
			}

			spl_autoload_register(array($this, 'autoload'));

			// Check the PHP version requirement
			if (version_compare(phpversion(), self::MIN_PHP_VERSION, '>=')) {
				$this->container = new \Pimple\Container();
			}
		}

		/**
		 * Get a reference to the dependency injection container.
		 *
		 * @see http://pimple.sensiolabs.org
		 * @since 0.3
		 * @return \Pimple\Container
		 */
		public function get_container()
		{
			return $this->container;
		}

		/**
		 * Run the loader to execute all of the hooks with WordPress.
		 *
		 * @since 0.3
		 */
		public function run()
		{
			register_activation_hook(__FILE__, array($this, 'activate'));
			register_deactivation_hook(__FILE__, array($this, 'deactivate'));

			// Check the PHP version requirement
			if (version_compare(phpversion(), self::MIN_PHP_VERSION, '>=')) {
				$this->load_functions();
				$this->load_services();
				$this->register_public_hooks();
				$this->register_admin_hooks();
				$this->migrate();

				// TODO: This old legacy class will be removed later
				new \Affilicious\Product\Meta_Box\Meta_Box_Manager();
			}
		}

		/**
		 * Make namespaces compatible with the source code of this plugin.
		 *
		 * @since 0.3
		 * @param string $class
		 */
		public function autoload($class)
		{
			$prefix = 'Affilicious\\';
			if (stripos($class, $prefix) === false) {
				return;
			}

			$file_path = str_ireplace('Affilicious\\', '', $class) . '.php';
			$file_path = strtolower($file_path);
			$file_path = str_replace('_', '-', $file_path);

			$test_prefix = 'Affilicious\\Tests\\';
			if (stripos($class, $test_prefix) !== false) {
				$file_path = __DIR__ . '/' . $file_path;
			} else {
				$file_path = __DIR__ . '/src/' . $file_path;
			}

			$file_path = str_replace('\\', DIRECTORY_SEPARATOR, $file_path);

			/** @noinspection PhpIncludeInspection */
			include_once($file_path);
		}

		/**
		 * The code that runs during plugin activation.
		 *
		 * @since 0.3
		 */
		public function activate()
		{
			// Check the PHP version requirement
			if (!version_compare(phpversion(), self::MIN_PHP_VERSION, '>=')) {
				deactivate_plugins(AFFILICIOUS_BASE_NAME);

				$this->load_textdomain();
				wp_die(sprintf(
					__('The Affilicious Plugin requires at least the PHP Version %s to reveal the full potential. Please switch the PHP version in your hosting provider.', 'affilicious'),
					self::MIN_PHP_VERSION
				));
			}

			$slug_rewrite_setup = $this->container['affilicious.product.setup.slug_rewrite'];
			$slug_rewrite_setup->activate();

			$update_timer = $this->container['affilicious.product.update.timer'];
			$update_timer->activate();
		}

		/**
		 * The code that runs during plugin deactivation.
		 *
		 * @since 0.3
		 */
		public function deactivate()
		{
			$slug_rewrite_setup = $this->container['affilicious.product.setup.slug_rewrite'];
			$slug_rewrite_setup->deactivate();

			$update_timer = $this->container['affilicious.product.update.timer'];
			$update_timer->deactivate();
		}

		/**
		 * Migrate the old code to the new version.
		 *
		 * @since 0.8
		 */
		public function migrate()
		{
			add_action('admin_init', function() {
				$migrated = get_option('_affilicious_migrated_to_beta');
				if($migrated !== 'yes') {
					$product_post_type_migration = $this->container['affilicious.product.migration.post_type'];
					$product_post_type_migration->migrate();

					$shop_post_type_migration = $this->container['affilicious.shop.migration.post_type'];
					$shop_post_type_migration->migrate();

					$currency_code_migration = $this->container['affilicious.shop.migration.currency_code'];
					$currency_code_migration->migrate();

					$shop_post_to_term_migration = $this->container['affilicious.shop.migration.post_to_term'];
					$shop_post_to_term_migration->migrate();

					$detail_post_to_term_migration = $this->container['affilicious.detail.migration.post_to_term'];
					$detail_post_to_term_migration->migrate();

					$attribute_post_to_term_migration = $this->container['affilicious.attribute.migration.post_to_term'];
					$attribute_post_to_term_migration->migrate();

					$product_details_migration = $this->container['affilicious.product.migration.details'];
					$product_details_migration->migrate();

					$product_shops_migration = $this->container['affilicious.product.migration.shops'];
					$product_shops_migration->migrate();

					$product_variants_migration = $this->container['affilicious.product.migration.variants'];
					$product_variants_migration->migrate();

					add_option('_affilicious_migrated_to_beta', 'yes');
				}

				$cleaned_variants = get_option('_affilicious_migrated_to_beta_with_cleaned_variants');
				if($cleaned_variants !== 'yes') {
					$clean_variants_migration = $this->container['affilicious.product.migration.clean_variants'];
					$clean_variants_migration->migrate();

					add_option('_affilicious_migrated_to_beta_with_cleaned_variants', 'yes');
				}

				$inherit_status = get_option('_affilicious_migrated_to_beta_with_variant_status_inherit');
				if($inherit_status !== 'yes') {
					$variant_inherit_status_migration = $this->container['affilicious.product.migration.variant_inherit_status'];
					$variant_inherit_status_migration->migrate();

					add_option('_affilicious_migrated_to_beta_with_variant_status_inherit', 'yes');
				}

				$product_slug_migration = $this->container['affilicious.product.migration.product_slug'];
				$product_slug_migration->migrate();

				$affiliate_product_id_to_090_migration = $this->container['affilicious.product.migration.affiliate_product_id_to_090'];
				$affiliate_product_id_to_090_migration->migrate();

				$tags_to_090_migration = $this->container['affilicious.product.migration.tags_to_090'];
				$tags_to_090_migration->migrate();

				$product_variant_terms_to_0820_migration = $this->container['affilicious.product.migration.product_variant_terms_to_0820'];
				$product_variant_terms_to_0820_migration->migrate();

				$product_slugs_to_0818_migration = $this->container['affilicious.product.migration.product_slugs_to_0818'];
				$product_slugs_to_0818_migration->migrate();
			}, 9999);
		}

		/**
		 * Register the plugin textdomain for internationalization.
		 *
		 * @since 0.5.1
		 */
		public function load_textdomain()
		{
			$dir = basename(dirname(__FILE__)) . '/languages/';
			load_plugin_textdomain(self::NAME, false, $dir);
		}

		/**
		 * Load the simple functions for an easier usage in templates.
		 *
		 * @since 0.5.1
		 */
		public function load_functions()
		{
			require_once(__DIR__ . '/functions.php');
		}

		/**
		 * Register the services for the dependency injection.
		 *
		 * @since 0.3
		 */
		public function load_services()
		{
			// Common services
			$this->container['affilicious.common.generator.slug'] = function () {
				return new \Affilicious\Common\Generator\Wordpress\Wordpress_Slug_Generator();
			};

			$this->container['affilicious.common.generator.key'] = function () {
				return new \Affilicious\Common\Generator\Carbon\Carbon_Key_Generator();
			};

			$this->container['affilicious.common.filter.link_target'] = function () {
				return new \Affilicious\Common\Filter\Link_Target_Filter();
			};

			$this->container['affilicious.common.admin.filter.footer_text'] = function () {
				return new \Affilicious\Common\Admin\Filter\Footer_Text_Filter();
			};

			$this->container['affilicious.common.admin.setup.carbon'] = function () {
				return new \Affilicious\Common\Admin\Setup\Carbon_Setup();
			};

			$this->container['affilicious.common.admin.page.addons'] = function () {
				return new \Affilicious\Common\Admin\Page\Addons_Page();
			};

			$this->container['affilicious.common.admin.license.processor'] = function ($c) {
				return new \Affilicious\Common\Admin\License\License_Processor(
					$c['affilicious.common.admin.license.manager']
				);
			};

			$this->container['affilicious.common.admin.license.manager'] = function () {
				return new \Affilicious\Common\Admin\License\License_Manager();
			};

			$this->container['affilicious.common.admin.setup.license_handler'] = function ($c) {
				return new \Affilicious\Common\Admin\Setup\License_Handler_Setup(
					$c['affilicious.common.admin.license.manager']
				);
			};

			$this->container['affilicious.common.admin.options.affilicious'] = function ($c) {
				return new \Affilicious\Common\Admin\Options\Affilicious_Options(
					$c['affilicious.common.admin.license.manager'],
					$c['affilicious.common.admin.license.processor']
				);
			};

			$this->container['affilicious.common.admin.setup.assets'] = function() {
				return new \Affilicious\Common\Admin\Setup\Assets_Setup();
			};

			// Provider services
			$this->container['affilicious.provider.setup.provider'] = function ($c) {
				return new \Affilicious\Provider\Setup\Provider_Setup(
					$c['affilicious.provider.repository.provider']
				);
			};

			$this->container['affilicious.provider.setup.amazon_provider'] = function ($c) {
				return new \Affilicious\Provider\Setup\Amazon_Provider_Setup(
					$c['affilicious.provider.factory.provider.amazon']
				);
			};

			$this->container['affilicious.provider.repository.provider'] = function ($c) {
				return new \Affilicious\Provider\Repository\Carbon\Carbon_Provider_Repository(
					$c['affilicious.common.generator.key']
				);
			};

			$this->container['affilicious.provider.factory.provider.amazon'] = function ($c) {
				return new \Affilicious\Provider\Factory\In_Memory\In_Memory_Amazon_Provider_Factory(
					$c['affilicious.common.generator.slug']
				);
			};

			$this->container['affilicious.provider.validator.amazon_credentials'] = function () {
				return new \Affilicious\Provider\Validator\Amazon_Credentials_Validator();
			};

			$this->container['affilicious.provider.admin.options.amazon'] = function ($c) {
				return new \Affilicious\Provider\Admin\Options\Amazon_Options(
					$c['affilicious.provider.validator.amazon_credentials']
				);
			};

			// Shop services
			$this->container['affilicious.shop.setup.shop_template'] = function () {
				return new \Affilicious\Shop\Setup\Shop_Template_Setup();
			};

			$this->container['affilicious.shop.repository.shop_template'] = function () {
				return new \Affilicious\Shop\Repository\Carbon\Carbon_Shop_Template_Repository();
			};

			$this->container['affilicious.shop.factory.shop_template'] = function ($c) {
				return new \Affilicious\Shop\Factory\In_Memory\In_Memory_Shop_Template_Factory(
					$c['affilicious.common.generator.slug']
				);
			};

			$this->container['affilicious.shop.migration.post_to_term'] = function ($c) {
				return new \Affilicious\Shop\Migration\Post_To_Term_Migration(
					$c['affilicious.shop.factory.shop_template'],
					$c['affilicious.shop.repository.shop_template']
				);
			};

			$this->container['affilicious.shop.migration.post_type'] = function () {
				return new \Affilicious\Shop\Migration\Post_Type_Migration();
			};

			$this->container['affilicious.shop.migration.currency_code'] = function () {
				return new \Affilicious\Shop\Migration\Currency_Code_Migration();
			};

			$this->container['affilicious.shop.admin.meta_box.shop_template'] = function($c) {
				return new \Affilicious\Shop\Admin\Meta_Box\Shop_Template_Meta_Box(
					$c['affilicious.provider.repository.provider']
				);
			};

			$this->container['affilicious.shop.admin.filter.table_columns'] = function() {
				return new \Affilicious\Shop\Admin\Filter\Table_Columns_Filter();
			};

			$this->container['affilicious.shop.admin.filter.table_rows'] = function($c) {
				return new \Affilicious\Shop\Admin\Filter\Table_Rows_Filter(
					$c['affilicious.provider.repository.provider']
				);
			};

			// Detail services
			$this->container['affilicious.detail.setup.detail_template'] = function () {
				return new \Affilicious\Detail\Setup\Detail_Template_Setup();
			};

			$this->container['affilicious.detail.factory.detail_template'] = function ($c) {
				return new \Affilicious\Detail\Factory\In_Memory\In_Memory_Detail_Template_Factory(
					$c['affilicious.common.generator.slug']
				);
			};

			$this->container['affilicious.detail.repository.detail_template'] = function () {
				return new \Affilicious\Detail\Repository\Carbon\Carbon_Detail_Template_Repository();
			};

			$this->container['affilicious.detail.migration.post_to_term'] = function ($c) {
				return new \Affilicious\Detail\Migration\Post_To_Term_Migration(
					$c['affilicious.detail.factory.detail_template'],
					$c['affilicious.detail.repository.detail_template']
				);
			};

			$this->container['affilicious.detail.admin.meta_box.detail_template'] = function() {
				return new \Affilicious\Detail\Admin\Meta_Box\Detail_Template_Meta_Box();
			};

			$this->container['affilicious.detail.admin.filter.table_columns'] = function() {
				return new \Affilicious\Detail\Admin\Filter\Table_Columns_Filter();
			};

			$this->container['affilicious.detail.admin.filter.table_rows'] = function() {
				return new \Affilicious\Detail\Admin\Filter\Table_Rows_Filter();
			};

			// Attribute services
			$this->container['affilicious.attribute.setup.attribute_template'] = function () {
				return new \Affilicious\Attribute\Setup\Attribute_Template_Setup();
			};

			$this->container['affilicious.attribute.repository.attribute_template'] = function () {
				return new \Affilicious\Attribute\Repository\Carbon\Carbon_Attribute_Template_Repository();
			};

			$this->container['affilicious.attribute.factory.attribute_template'] = function ($c) {
				return new \Affilicious\Attribute\Factory\In_Memory\In_Memory_Attribute_Template_Factory(
					$c['affilicious.common.generator.slug']
				);
			};

			$this->container['affilicious.attribute.migration.post_to_term'] = function ($c) {
				return new \Affilicious\Attribute\Migration\Post_To_Term_Migration(
					$c['affilicious.attribute.factory.attribute_template'],
					$c['affilicious.attribute.repository.attribute_template']
				);
			};

			$this->container['affilicious.attribute.admin.meta_box.attribute_template'] = function() {
				return new \Affilicious\Attribute\Admin\Meta_Box\Attribute_Template_Meta_Box();
			};

			$this->container['affilicious.attribute.admin.filter.table_columns'] = function() {
				return new \Affilicious\Attribute\Admin\Filter\Table_Columns_Filter();
			};

			$this->container['affilicious.attribute.admin.filter.table_rows'] = function() {
				return new \Affilicious\Attribute\Admin\Filter\Table_Rows_Filter();
			};

			// Product services
			$this->container['affilicious.product.setup.product'] = function () {
				return new \Affilicious\Product\Setup\Product_Setup();
			};

			$this->container['affilicious.product.repository.product'] = function ($c) {
				return new \Affilicious\Product\Repository\Carbon\Carbon_Product_Repository(
					$c['affilicious.common.generator.slug'],
					$c['affilicious.common.generator.key'],
					$c['affilicious.shop.repository.shop_template'],
					$c['affilicious.attribute.repository.attribute_template'],
					$c['affilicious.detail.repository.detail_template']
				);
			};

			$this->container['affilicious.product.factory.simple_product'] = function () {
				return new \Affilicious\Product\Factory\In_Memory\In_Memory_Simple_Product_Factory();
			};

			$this->container['affilicious.product.factory.complex_product'] = function () {
				return new \Affilicious\Product\Factory\In_Memory\In_Memory_Complex_Product_Factory();
			};

			$this->container['affilicious.product.factory.product_variant'] = function () {
				return new \Affilicious\Product\Factory\In_Memory\In_Memory_Product_Variant_Factory();
			};

			$this->container['affilicious.product.listener.changed_status_complex_product'] = function ($c) {
				return new \Affilicious\Product\Listener\Changed_Status_Complex_Product_Listener(
					$c['affilicious.product.repository.product']
				);
			};

			$this->container['affilicious.product.listener.saved_complex_product'] = function ($c) {
				return new \Affilicious\Product\Listener\Saved_Complex_Product_Listener(
					$c['affilicious.product.repository.product']
				);
			};

			$this->container['affilicious.product.listener.deleted_complex_product'] = function ($c) {
				return new \Affilicious\Product\Listener\Deleted_Complex_Product_Listener(
					$c['affilicious.product.repository.product']
				);
			};

			$this->container['affilicious.product.setup.custom_taxonomies'] = function () {
				return new \Affilicious\Product\Setup\Custom_Taxonomies_Setup();
			};

			$this->container['affilicious.product.setup.canonical'] = function () {
				return new \Affilicious\Product\Setup\Canonical_Setup();
			};

			$this->container['affilicious.product.setup.admin_bar'] = function () {
				return new \Affilicious\Product\Setup\Admin_Bar_Setup();
			};

			$this->container['affilicious.product.filter.complex_product'] = function () {
				return new \Affilicious\Product\Filter\Complex_Product_Filter();
			};

			$this->container['affilicious.product.setup.slug_rewrite'] = function () {
				return new \Affilicious\Product\Setup\Slug_Rewrite_Setup();
			};

			$this->container['affilicious.product.update.timer'] = function ($c) {
				return new \Affilicious\Product\Update\Update_Timer(
					$c['affilicious.product.update.manager']
				);
			};

			$this->container['affilicious.product.update.manager'] = function ($c) {
				return new \Affilicious\Product\Update\Update_Manager(
					$c['affilicious.product.repository.product'],
					$c['affilicious.shop.repository.shop_template'],
					$c['affilicious.provider.repository.provider']
				);
			};

			$this->container['affilicious.product.setup.update_worker'] = function ($c) {
				return new \Affilicious\Product\Setup\Update_Worker_Setup(
					$c['affilicious.product.update.manager']
				);
			};

			$this->container['affilicious.product.setup.amazon_update_worker'] = function ($c) {
				return new \Affilicious\Product\Setup\Amazon_Update_Worker_Setup(
					$c['affilicious.product.repository.product'],
					$c['affilicious.shop.repository.shop_template'],
					$c['affilicious.provider.repository.provider']
				);
			};

			$this->container['affilicious.product.setup.update_queue'] = function ($c) {
				return new \Affilicious\Product\Setup\Update_Queue_Setup(
					$c['affilicious.product.update.manager']
				);
			};

			$this->container['affilicious.product.import.amazon'] = function($c) {
				return new \Affilicious\Product\Import\Amazon\Amazon_Import(
					$c['affilicious.provider.repository.provider']
				);
			};

			$this->container['affilicious.product.search.amazon'] = function($c) {
				return new \Affilicious\Product\Search\Amazon\Amazon_Search(
					$c['affilicious.provider.repository.provider']
				);
			};

			$this->container['affilicious.product.migration.post_type'] = function () {
				return new \Affilicious\Product\Migration\Post_Type_Migration();
			};

			$this->container['affilicious.product.migration.product_slugs_to_0818'] = function () {
				return new \Affilicious\Product\Migration\Product_Slugs_To_0818_Migration();
			};

			$this->container['affilicious.product.migration.product_variant_terms_to_0820'] = function ($c) {
				return new \Affilicious\Product\Migration\Product_Variant_Terms_To_0820_Migration(
					$c['affilicious.product.repository.product']
				);
			};

			$this->container['affilicious.product.migration.affiliate_product_id_to_090'] = function () {
				return new \Affilicious\Product\Migration\Affiliate_Product_Id_To_090_Migration();
			};

			$this->container['affilicious.product.migration.variants'] = function ($c) {
				return new \Affilicious\Product\Migration\Variants_Migration(
					$c['affilicious.product.repository.product'],
					$c['affilicious.attribute.repository.attribute_template'],
					$c['affilicious.shop.repository.shop_template'],
					$c['affilicious.product.factory.product_variant']
				);
			};

			$this->container['affilicious.product.migration.shops'] = function ($c) {
				return new \Affilicious\Product\Migration\Shops_Migration(
					$c['affilicious.product.repository.product'],
					$c['affilicious.shop.repository.shop_template']
				);
			};

			$this->container['affilicious.product.migration.clean_variants'] = function () {
				return new \Affilicious\Product\Migration\Clean_Variants_Migration();
			};

			$this->container['affilicious.product.migration.variant_inherit_status'] = function () {
				return new \Affilicious\Product\Migration\Variant_Inherit_Status_Migration();
			};

			$this->container['affilicious.product.migration.product_slug'] = function () {
				return new \Affilicious\Product\Migration\Product_Slug_Migration();
			};

			$this->container['affilicious.product.migration.details'] = function ($c) {
				return new \Affilicious\Product\Migration\Details_Migration(
					$c['affilicious.product.repository.product'],
					$c['affilicious.detail.repository.detail_template']
				);
			};

			$this->container['affilicious.product.migration.tags_to_090'] = function($c) {
				return new \Affilicious\Product\Migration\Tags_To_090_Migration(
					$c['affilicious.detail.repository.detail_template'],
					$c['affilicious.attribute.repository.attribute_template']
				);
			};

			$this->container['affilicious.product.admin.page.import'] = function ($c) {
				return new \Affilicious\Product\Admin\Page\Import_Page(
					$c['affilicious.shop.repository.shop_template'],
					$c['affilicious.provider.repository.provider']
				);
			};

			$this->container['affilicious.product.admin.meta_box.product'] = function($c) {
				return new \Affilicious\Product\Admin\Meta_Box\Product_Meta_Box(
					$c['affilicious.shop.repository.shop_template'],
					$c['affilicious.attribute.repository.attribute_template'],
					$c['affilicious.detail.repository.detail_template'],
					$c['affilicious.common.generator.key']
				);
			};

			$this->container['affilicious.product.admin.options.product'] = function () {
				return new \Affilicious\Product\Admin\Options\Product_Options();
			};

			$this->container['affilicious.product.admin.ajax_handler.amazon_search'] = function ($c) {
				return new \Affilicious\Product\Admin\Ajax_Handler\Amazon_Search_Ajax_Handler(
					$c['affilicious.product.search.amazon'],
					$c['affilicious.product.repository.product']
				);
			};

			$this->container['affilicious.product.admin.ajax_handler.amazon_import'] = function ($c) {
				return new \Affilicious\Product\Admin\Ajax_Handler\Amazon_Import_Ajax_Handler(
					$c['affilicious.product.import.amazon'],
					$c['affilicious.product.repository.product'],
					$c['affilicious.shop.factory.shop_template'],
					$c['affilicious.shop.repository.shop_template'],
					$c['affilicious.provider.repository.provider']
				);
			};

			$this->container['affilicious.product.admin.filter.table_content'] = function () {
				return new \Affilicious\Product\Admin\Filter\Table_Content_Filter();
			};

			$this->container['affilicious.product.admin.filter.table_count'] = function () {
				return new \Affilicious\Product\Admin\Filter\Table_Count_Filter();
			};

			$this->container['affilicious.product.admin.filter.menu_order'] = function() {
				return new \Affilicious\Product\Admin\Filter\Menu_Order_Filter();
			};
		}

		/**
		 * Register all of the hooks related to the public-facing functionality.
		 *
		 * @since 0.3
		 */
		public function register_public_hooks()
		{
			// Hook the text domain for the correct translations.
			add_action('plugins_loaded', array($this, 'load_textdomain'));

			// Hook the products.
			$product_setup = $this->container['affilicious.product.setup.product'];
			add_action('init', array($product_setup, 'init'), 0);

			// Hook the custom product taxonomies.
			$custom_product_taxonomies_setup = $this->container['affilicious.product.setup.custom_taxonomies'];
			add_action('init', array($custom_product_taxonomies_setup, 'init'), 0);

			// Hook the shop templates.
			$shop_template_setup = $this->container['affilicious.shop.setup.shop_template'];
			add_action('init', array($shop_template_setup, 'init'), 0);

			// Hook the attribute templates.
			$attribute_template_setup = $this->container['affilicious.attribute.setup.attribute_template'];
			add_action('init', array($attribute_template_setup, 'init'), 0);

			// Hook the detail templates.
			$detail_template_setup = $this->container['affilicious.detail.setup.detail_template'];
			add_action('init', array($detail_template_setup, 'init'), 0);

			// Hook the product slug rewrite.
			$slug_rewrite_setup = $this->container['affilicious.product.setup.slug_rewrite'];
			add_action('init', array($slug_rewrite_setup, 'run'), 1);
			add_action('added_option', array($slug_rewrite_setup, 'prepare'), 80, 1);
			add_action('updated_option', array($slug_rewrite_setup, 'prepare'), 80, 1);

			// Hook the providers.
			$provider_setup = $this->container['affilicious.provider.setup.provider'];
			$amazon_provider_setup = $this->container['affilicious.provider.setup.amazon_provider'];
			add_action('init', array($provider_setup, 'init'), 5);
			add_filter('aff_provider_init', array($amazon_provider_setup, 'init'), 5);

			// Hook the product update queues.
			$update_queue_setup = $this->container['affilicious.product.setup.update_queue'];
			add_filter('aff_provider_after_init', array($update_queue_setup, 'init'));

			// Hook the product update workers.
			$update_worker_setup = $this->container['affilicious.product.setup.update_worker'];
			$amazon_update_worker_setup = $this->container['affilicious.product.setup.amazon_update_worker'];
			add_action('init', array($update_worker_setup, 'init'), 5);
			add_filter('aff_product_update_worker_init', array($amazon_update_worker_setup, 'init'), 5);

			// Hook the product update timer to update the products regularly.
			$update_timer = $this->container['affilicious.product.update.timer'];
			add_action('aff_product_update_run_tasks_hourly', array($update_timer, 'run_tasks_hourly'));
			add_action('aff_product_update_run_tasks_twice_daily', array($update_timer, 'run_tasks_twice_daily'));
			add_action('aff_product_update_run_tasks_daily', array($update_timer, 'run_tasks_daily'));

			// Hook the product listeners.
			$saved_complex_product_listener = $this->container['affilicious.product.listener.saved_complex_product'];
			$deleted_complex_product_listener = $this->container['affilicious.product.listener.deleted_complex_product'];
			$changed_status_complex_product_listener = $this->container['affilicious.product.listener.changed_status_complex_product'];
			add_action('carbon_after_save_post_meta', array($saved_complex_product_listener, 'listen'), 10, 3);
			add_action('delete_post', array($deleted_complex_product_listener, 'listen'));
			add_action('transition_post_status', array($changed_status_complex_product_listener, 'listen'), 10, 3);

			// Hook the canonical tags to improve SEO with product variants.
			$canonical_setup = $this->container['affilicious.product.setup.canonical'];
			add_action('wp_head', array($canonical_setup, 'init'));

			// Hook the admin bar to make it compatible with products.
			$admin_bar_setup = $this->container['affilicious.product.setup.admin_bar'];
			add_action('admin_bar_menu', array($admin_bar_setup, 'init'), 99);

			// Filter the complex products from the front end search.
			$complex_product_filter = $this->container['affilicious.product.filter.complex_product'];
			add_action('pre_get_posts', array($complex_product_filter, 'filter'));

			// Hook the license handlers for the extensions.
			$license_handler_setup = $this->container['affilicious.common.admin.setup.license_handler'];
			add_action('init', array($license_handler_setup, 'init'), 15);

			// Hook the link targets to make affiliate links work again.
			$link_target_filter = $this->container['affilicious.common.filter.link_target'];
			add_filter('tiny_mce_before_init', array($link_target_filter, 'filter'));

			// Hook into this action if you want to create custom extensions or themes with the dependency injection container.
			do_action('aff_hooks');

			add_action('init', function() {
				/** @deprecated 1.1 */
				do_action('aff_init');

				/** @deprecated 1.0 */
				do_action('affilicious_init');
			});
		}

		/**
		 * Register all of the hooks related to the admin area functionality.
		 *
		 * @since 0.3
		 */
		public function register_admin_hooks()
		{
			// Hook the custom Carbon Fields.
			$carbon_fields_setup = $this->container['affilicious.common.admin.setup.carbon'];
			add_action('after_setup_theme', array($carbon_fields_setup, 'init'), 15);

			// Hook the product meta box.
			$product_meta_box = $this->container['affilicious.product.admin.meta_box.product'];
			add_action('init', array($product_meta_box, 'render'), 10);

			// Hook the shop template meta box.
			$shop_template_meta_box = $this->container['affilicious.shop.admin.meta_box.shop_template'];
			add_action('init', array($shop_template_meta_box, 'render'), 10);

			// Hook the attribute template meta box.
			$attribute_template_meta_box = $this->container['affilicious.attribute.admin.meta_box.attribute_template'];
			add_action('init', array($attribute_template_meta_box, 'render'), 10);

			// Hook the detail template meta box.
			$detail_template_meta_box = $this->container['affilicious.detail.admin.meta_box.detail_template'];
			add_action('init', array($detail_template_meta_box, 'render'), 10);

			// Hook the admin assets.
			$assets_setup = $this->container['affilicious.common.admin.setup.assets'];
			add_action('admin_enqueue_scripts', array($assets_setup, 'add_styles'));
			add_action('admin_enqueue_scripts', array($assets_setup, 'add_scripts'));

			// Hook the product admin table filters.
			$product_admin_table_content_filter = $this->container['affilicious.product.admin.filter.table_content'];
			$product_admin_table_count_filter = $this->container['affilicious.product.admin.filter.table_count'];
			add_action('pre_get_posts', array($product_admin_table_content_filter, 'filter'));
			add_filter("views_edit-aff_product", array($product_admin_table_count_filter, 'filter'), 10, 1);

			// Hook the attribute template admin table filters.
			$attribute_template_admin_table_columns_filter = $this->container['affilicious.attribute.admin.filter.table_columns'];
			$attribute_template_admin_table_rows_filter = $this->container['affilicious.attribute.admin.filter.table_rows'];
			add_filter('manage_edit-aff_attribute_tmpl_columns',  array($attribute_template_admin_table_columns_filter, 'filter'));
			add_filter('manage_aff_attribute_tmpl_custom_column', array($attribute_template_admin_table_rows_filter, 'filter'), 15, 3);

			// Hook the detail template admin table filters.
			$detail_template_admin_table_columns_filter = $this->container['affilicious.detail.admin.filter.table_columns'];
			$detail_template_admin_table_rows_filter = $this->container['affilicious.detail.admin.filter.table_rows'];
			add_filter('manage_edit-aff_detail_tmpl_columns',  array($detail_template_admin_table_columns_filter, 'filter'));
			add_filter('manage_aff_detail_tmpl_custom_column', array($detail_template_admin_table_rows_filter, 'filter'), 15, 3);

			// Hook the shop template admin table filters.
			$shop_template_admin_table_columns_filter = $this->container['affilicious.shop.admin.filter.table_columns'];
			$shop_template_admin_table_rows_filter = $this->container['affilicious.shop.admin.filter.table_rows'];
			add_filter('manage_edit-aff_shop_tmpl_columns',  array($shop_template_admin_table_columns_filter, 'filter'));
			add_filter('manage_aff_shop_tmpl_custom_column', array($shop_template_admin_table_rows_filter, 'filter'), 15, 3);

			// Hook the option pages.
			$affilicious_options = $this->container['affilicious.common.admin.options.affilicious'];
			$product_options = $this->container['affilicious.product.admin.options.product'];
			$provider_options = $this->container['affilicious.provider.admin.options.amazon'];
			add_action('init', array($affilicious_options, 'render'), 15);
			add_action('init', array($product_options, 'render'), 20);
			add_action('init', array($provider_options, 'render'), 20);

			// Hook the import page.
			$import_page = $this->container['affilicious.product.admin.page.import'];
			add_action('admin_menu', array($import_page, 'init'), 10);

			// Hook the add-ons page.
			$addons_page = $this->container['affilicious.common.admin.page.addons'];
			add_action('admin_menu', array($addons_page, 'init'), 100);

			// Hook the menu order filter.
			$product_admin_menu_order_filter = $this->container['affilicious.product.admin.filter.menu_order'];
			add_filter('custom_menu_order', array($product_admin_menu_order_filter, 'filter'));

			// Hook the admin footer text.
			$admin_footer_text_filter = $this->container['affilicious.common.admin.filter.footer_text'];
			add_filter('admin_footer_text', array($admin_footer_text_filter, 'filter'));

			// Hook the ajax handlers.
			$amazon_search_ajax_handler = $this->container['affilicious.product.admin.ajax_handler.amazon_search'];
			$amazon_import_ajax_handler = $this->container['affilicious.product.admin.ajax_handler.amazon_import'];
			add_action('wp_ajax_aff_product_admin_amazon_search', array($amazon_search_ajax_handler, 'handle'));
			add_action('wp_ajax_aff_product_admin_amazon_import', array($amazon_import_ajax_handler, 'handle'));

			// Hook into this action if you want to create custom extensions or themes with the dependency injection container.
			do_action('aff_admin_hooks');

			// Add a custom affilicious admin init hook.
			add_action('admin_init', function() {
				/** @deprecated 1.1 */
				do_action('aff_admin_init');

				/** @deprecated 1.0 */
				do_action('affilicious_admin_init');
			});
		}
	}
}

/**
 * Run the Affilicious plugin.
 *
 * @since 0.8.11
 */
function aff_run_plugin()
{
	$aff_plugin = Affilicious::get_instance();
	$aff_plugin->run();
}

aff_run_plugin();
