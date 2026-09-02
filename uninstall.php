<?php

/**
 * Fired when the plugin is uninstalled.
 *
 * @link       https://dukkanjo.com
 * @since      1.0.0
 *
 * @package    Dukkan_Plugin
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Drop the loyalty points ledger table.
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}dukkan_loyalty_ledger" );

// Remove the loyalty settings option.
delete_option( 'dukkan_loyalty_settings' );

// Remove all users' loyalty points balances.
delete_metadata( 'user', 0, '_dukkan_loyalty_points', '', true );
