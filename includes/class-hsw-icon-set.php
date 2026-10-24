<?php
/**
 * Icon names the bundled sprite can draw — generated from tools/_icons.txt.
 *
 * HSW_Helpers::icon_name() checks this list so an unknown name degrades to a
 * neutral dot instead of printing an English word into the UI.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

class HSW_Icon_Set {

	/**
	 * Every drawable icon name.
	 *
	 * @return string[]
	 */
	public static function names() {
		return array(
			'account_balance',
			'account_balance_wallet',
			'add',
			'add_circle',
			'add_shopping_cart',
			'arrow_back',
			'arrow_forward',
			'arrow_left_alt',
			'assignment_return',
			'auto_awesome',
			'badge',
			'battery_charging_full',
			'bolt',
			'bookmark',
			'bookmark_added',
			'calendar_month',
			'calendar_today',
			'call',
			'category',
			'check',
			'check_circle',
			'chevron_left',
			'chevron_right',
			'cleaning_services',
			'close',
			'confirmation_number',
			'contact_page',
			'contact_support',
			'credit_card',
			'delete',
			'delete_sweep',
			'description',
			'devices',
			'done',
			'edit',
			'edit_note',
			'error',
			'expand_more',
			'favorite',
			'filter_list',
			'flight_takeoff',
			'grid_view',
			'help',
			'history',
			'home',
			'info',
			'inventory_2',
			'label',
			'language',
			'local_activity',
			'local_fire_department',
			'local_shipping',
			'lock',
			'login',
			'logout',
			'loyalty',
			'mail',
			'markunread_mailbox',
			'memory',
			'menu',
			'notes',
			'notifications',
			'payments',
			'percent',
			'person',
			'photo_camera',
			'pin_drop',
			'place',
			'print',
			'published_with_changes',
			'rate_review',
			'receipt_long',
			'redeem',
			'remove',
			'replay',
			'rocket_launch',
			'savings',
			'schedule',
			'search',
			'security',
			'settings',
			'cart',
			'sell',
			'shield',
			'shopping_bag',
			'shopping_cart',
			'star',
			'storefront',
			'support_agent',
			'sweep',
			'task_alt',
			'timer',
			'trending_up',
			'tune',
			'two_wheeler',
			'verified',
			'verified_user',
			'visibility',
			'warehouse',
			'warning',
			'zoom_in',
		);
	}

	/**
	 * Whether the sprite has this glyph.
	 *
	 * @param string $name Icon name.
	 * @return bool
	 */
	public static function has( $name ) {
		static $set = null;
		if ( null === $set ) {
			$set = array_flip( self::names() );
		}
		return isset( $set[ $name ] );
	}
}
