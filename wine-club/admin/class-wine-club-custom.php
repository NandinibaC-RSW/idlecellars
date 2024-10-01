<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @link       http://www.domagojfranc.com
 * @since      1.0.0
 *
 * @package    Wine_Club
 * @subpackage Wine_Club/admin
 */

class Wine_Club_Custom {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;
	private $logger;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}



    /**
     *  Create WP Admin Tabs on-the-fly.
     *
     *  @since 1.0.0
     */

	function admin_tabs($tabs, $current=NULL){

		if(is_null($current)){

			if(isset($_GET['page'])){

				$current = $_GET['page'];

			}

		}

		$content = '';
				$content .='<div id="woo-club-header-logo"><img style="height: 120px" src="' .plugins_url('wine-club/admin/images/clubconnection.png'). '" ></div>';

		$content .= '<h2 class="nav-tab-wrapper">';

		foreach($tabs as $location => $tabname){

			if($current == $location){

				$class = ' nav-tab-active';

			} else{

				$class = '';    

			}

        $content .= '<a class="nav-tab'.$class.'" href="?page='.$location.'">'.$tabname.'</a>';

		}

		$content .= '</h2>';

        return $content;

	}

} 
