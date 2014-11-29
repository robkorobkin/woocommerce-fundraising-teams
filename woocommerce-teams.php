<?php
/**
 * Plugin Name: WooCommerce Teams
 * Plugin URI: http://tbd.org
 * Description: This plugin enables the creation of fundraising teams within WooCommerce.
 * Version: 1.0
 * Author: Rob Korobkin
 * Author URI: http://URI_Of_The_Plugin_Author
 * License: GPL2
 
  Copyright 2014 Rob Korobkin (email: rob.korobkin@gmail.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA

	--
	
	Plugin Resources:  http://codex.wordpress.org/Writing_a_Plugin
	ReadMe.txt generator: http://generatewp.com/plugin-readme/
	

 */
	
	defined('ABSPATH') or die("No script kiddies please!");
	
	global $wooTeamsFields;
	$wooTeamsFields = array('teamList', 'allowUserSubmittedTeams', 'barGraphColor');
	
	
	
	// admin stuff
	if ( is_admin() ){ // admin actions
		add_action( 'admin_menu', 'woo_teams_admin_menu' );
		add_action( 'admin_init', 'register_wtsettings' );
	}
	
	// public-facing stuff
	else {
	
	
	}	
	
	// create custom admin menu item
	function woo_teams_admin_menu(){

		//create new top-level menu
		add_menu_page('Woo Teams', 'Woo Teams', 'administrator', __FILE__, 'woo_teams_admin_page');

		//call register settings function
		add_action( 'admin_init', 'register_wtSettings' );
		
	}
	
	//register our settings
	function register_wtSettings(){
		global $wooTeamsFields;
		foreach($wooTeamsFields as $f){
			register_setting( 'wooteams-settings-group', $f );
		}
	}
	
	
	
	function woo_teams_admin_page(){
	
		global $wooTeamsFields;
	
		foreach($wooTeamsFields as $f){
			$pluginState[$f] = esc_attr( get_option($f) );
		}
		
	
		echo 	'	<style type="text/css">
						#wooteams-adminform 			{ }
						#wooteams-adminform td 			{ padding-bottom: 10px; vertical-align: top; padding-right: 10px; }
						#wooteams-adminform .tInput 	{ width: 300px; }
						#wooteams-adminform textarea 	{ height: 400px; width: 300px; }
					</style>
		
					<form style="padding: 30px;" id="wooteams-adminform" method="post" action="options.php">';
					
		settings_fields( 'wooteams-settings-group' );
		do_settings_sections( 'wooteams-settings-group' );					
					
					
		echo			'<h2>Woocommerce Team Settings</h2>
						<table>
							<tr>
								<td><label for="allowUserSubmitted">Allow User Submitted Teams</label></td>
								<td><input type="checkbox" name="allowUserSubmitted" id="allowUserSubmitted"
											checked="' . $pluginState['allowUserSubmitted'] . '" /></td>
							</tr>
							<tr>
								<td><label for="barGraphColor">Bar Graph Color (hex)</label></td>						
								<td><input type="text" name="barGraphColor" id="barGraphColor" class="tInput" 
											value="' . $pluginState['barGraphColor'] . '"/></td>
							</tr>
							<tr>
								<td><label for="teamList">Teams (team 1, team 2 etc.)</label></td>
								<td><textarea name="teamList" id="teamList">' . 
										$pluginState['teamList'] . 
									'</textarea></td>
							</tr>
						</table>';
						submit_button();
		echo 		'</form>';
	
	}
	
	


// SHOW INPUTS
add_filter( 'woocommerce_checkout_fields' , 'add_wooteams_to_checkout' );
function add_wooteams_to_checkout( $fields ) {

	// get woocommerce teams
	$teamsRaw = explode(',', esc_attr( get_option('teamList') ));
	$teams[] = "Select your fundraising team...";
	foreach($teamsRaw as $t) $teams[] = trim($t);

	// display
	$fields['order']['team_id'] = array(
		'type' => "select",
		'label' => "Are you helping to raise money for a particular team?",	
		'options' => $teams
	);
	$fields['order']['team_name'] = array(
		'type' => "text",
		'label' => "Write in team name:"
	);
    return $fields;
}

// SAVE INFORMATION
add_action( 'woocommerce_checkout_update_order_meta', 'save_wooteam_on_checkout' );
function save_wooteam_on_checkout( $order_id ) {
	
	// get teams
	$teamsRaw = explode(',', esc_attr( get_option('teamList') ));
	$teams[] = "Select your fundraising team...";
	foreach($teamsRaw as $t) $teams[] = trim($t);
	
	// save selection
    if ( !empty($_POST['team_id']) && $_POST['team_id'] != "0" ) {
		$teamName = $teams[$_POST['team_id']];
		update_post_meta( $order_id, 'Team Name', $teamName);
	}
	
	// save user-submitted
	else if(!empty($_POST['team_name'])) {
		$teamName = $_POST['team_name'];
		update_post_meta( $order_id, 'Team Name', $teamName);
    }
    
    // save default
	else {
		$teamName = "No team selected";
		update_post_meta( $order_id, 'Team Name', $teamName);
	}
}

// show scoreboard
function wooteams_scoreboard_display( $atts ){
	
	
	
	// ITERATE THROUGH ALL ORDERS AND TALLY UP TEAMS - THIS MAY TAKE A WHILE... COULD BE CACHED etc.
	$args = array(
		'post_type'			=> 'shop_order',
		'posts_per_page' 	=> '-1'
	);
	$posts_array = get_posts( $args );
	$scores = array();
	
	foreach($posts_array as $post){

		$status = $post -> post_status;

		$tmp = get_post_meta( $post -> ID, "_order_total");
		$total = $tmp[0];
	
		if($status != "wc-processing" && $status != "wc-completed") continue;	
	
		$tmp = get_post_meta( $post -> ID, "Team Name");
		$teamName = trim($tmp[0]);
		
		$noGood = array("Individual (not associated with a team)", "No team selected", "");
		if( !in_array($teamName, $noGood) ) {
			$score = array(
				"total" => $total,
				"id"	=> $post -> ID
			);
			$scores[$teamName]["orders"][] 	=  $score;
			$scores[$teamName]["total"] 	+= $score["total"];
			$scores[$teamName]["teamName"] 	=  $teamName;
		}
	}
	if(count($scores) == 0) return '<div class="wt-empty">Nothing to display<br /><br />There are no approved orders associated with a team.</div>';
	function sortTeams($a, $b){
		return $a['total'] < $b['total'];
	}
	usort($scores, sortTeams);



	
	$html = '<table id="scoreBoard">';
	foreach($scores as $teamName => $team){
		$html .= 	"<tr>" . 
						'<td class="label">' . $team["teamName"] . " (" . count($team["orders"]) . ")</td>" .
						'<td style="line-height: 40px;  font-size: 13px;">$' . $team['total'] . "</td>" .
						"<td>" . 
							'<div  class="bar" style="width: ' . ($team['total'] *.1) . 'px;"></div>' .
						"</td>" .
					"</tr>";
	}
	$html .= '</table>
	<style type="text/css">
		#scoreBoard {
			border: solid 1px #ccc;
			padding: 10px; border: solid 1px #ccc; margin: 30px auto; background: white;
		}
		#scoreBoard .label 	{ padding-right: 20px;}
		#scoreBoard .bar 	{ height: 40px; border: solid 1px black; background: green; }
		#scoreBoard table 	{ border-collapse: collapse; }
		#scoreBoard td 		{ border: none; }
	</style>';

	return $html;
}
add_shortcode( 'wooteams-scoreboard', 'wooteams_scoreboard_display' );

