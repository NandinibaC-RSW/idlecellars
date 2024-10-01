<link rel="stylesheet" type="text/css" href="<?php echo plugins_url().'/wine-club/public/wine-club-public.css'; ?>">
<?php
    global $wpdb;
    $user = wp_get_current_user();

	// if($_POST['cancel_membership']) {
	// 	$retrieved_nonce = $_REQUEST['_wpnonce'];
	// 	if (!wp_verify_nonce($retrieved_nonce, 'cancelMembership_nonce' ) ) die( 'Failed security check' );

    //     $oldMembershipLevel = $wpdb->get_row($wpdb->prepare("SELECT name FROM ". $wpdb->prefix."wineClubMembershipLevels WHERE id=%d", get_the_author_meta( 'wineClubMembershipLevel', $user->ID )));
    //     update_user_meta($user->ID, 'wineClubMembershipLevel', '');
        
    //     do_action('wineClubMembershipUpdated', $user->ID, $oldMembershipLevel->name, '');
    // }

    if($_POST['change_membership'])
    {
    	$user_id = get_current_user_id();
    	if (!wp_verify_nonce($_REQUEST['_wpnonce'], 'changeMembership_nonce' ) ) die( 'Failed security check' );

			$membershipOld = $wpdb->get_row($wpdb->prepare("SELECT * FROM ".$wpdb->prefix."wineClubMembershipLevels WHERE id=%d", get_user_meta( $user_id, 'wineClubMembershipLevel', true)));
    		$val = get_user_meta( $user_id, 'wineClubMembershipLevel', true);

    		update_user_meta( $user_id, 'wineClubMembershipLevel', $_POST['wineClubMembershipLevel'] );

    		$membership = $wpdb->get_row($wpdb->prepare("SELECT * FROM ".$wpdb->prefix."wineClubMembershipLevels WHERE id=%d", $_POST['wineClubMembershipLevel']));

			$adminEmail = get_option('admin_email');
			$to 		= $adminEmail; 
			$subject 	= 'User Changed Membership Levels';
			$old_member = $membershipOld->name ?: "No membership";
			$new_member = $membership->name ?: "No membership";
			$messeage 	= '<div>'.$user->first_name.' '.$user->last_name.' has changed their membership level from <b>'.$old_member.' </b> to <b>'.$new_member.'</b></div>';
			$headers 	= ['Content-Type: text/html; charset=UTF-8', 'From: Club Connection <'. $adminEmail .'>']; 

			$mail = wp_mail( $to, $subject, $messeage, $headers);

			if($mail) { echo "<div class='notice notice-success'> Successfully Updated from <b> $old_member </b> to <b> $new_member </b> </div>";
			} else { 
				echo "<div class='notice notice-error'> Email Sent error </div>";
			}
    }

	$club_id = get_the_author_meta( 'wineClubMembershipLevel', $user->ID );
	// foreach ($club_ids as $club_id) {
	// 	$membershipLevel = $wpdb->get_row($wpdb->prepare("SELECT * FROM ". $wpdb->prefix."wineClubMembershipLevels WHERE id=%d", $club_id));
	// }
	if(!empty($club_id)){

	$sql = "SELECT name, description FROM ".$wpdb->prefix."wineClubMembershipLevels where id = '$club_id'";
	$membershipLevelsName = $wpdb->get_results($wpdb->prepare($sql));
	$membershipLevel =  $membershipLevelsName[0]->name;
	$membershipLevelDescription = $membershipLevelsName[0]->description;

	}

	if(isset($membershipLevel)) :?>
	<h2><?php _e('Your membership level is:'); ?> <?php echo esc_attr($membershipLevel) ?></h2>
	<p><?php echo esc_textarea($membershipLevelDescription) ?></p>

	<script>
		//  jQuery(document).ready(function(){
		//  	jQuery('#membershipLevelCancelForm').submit(function(event){
		// 	     if(!confirm("Are you sure that you want to cancel your membership?")){
		// 	        event.preventDefault();
		// 	      }
		// 	    });
		//    });
	</script>
<?php else: ?>
	<h2><?php _e('Club Connection Membership'); ?></h2>
	<p><?php _e('You can join our club connection or change your membership level below.'); ?></p>
<?php endif; ?>

<?php if(isset($membershipLevel)) :?>
	<h3 style="margin-top:30px"><?php _e('Switch membership level'); ?></h3>
<?php else: ?>
	<h3 style="margin-top:30px"><?php _e('Join Club Connection!'); ?></h3>
<?php endif; ?>

<?php 
	$membershipLevels = $wpdb->get_results( "SELECT * FROM ".$wpdb->prefix."wineClubMembershipLevels");
	User::checkIfUserProcessManullyDateExpired($user->ID);
	?>
	<form method="post">
		<?php wp_nonce_field( 'changeMembership_nonce') ?>
		<table class="form-table wineClubTable">
			<tr>
				<th><label for="wineClubMembershipLevel"><?php _e("Club connection membership level:"); ?></label></th>
				<td>
					<select name="wineClubMembershipLevel" id="wineClubMembershipLevel">
						<?php foreach($membershipLevels as $Level): ?>
							<option
							value="<?php echo $Level->id; ?>"
							<?php if(get_the_author_meta( 'wineClubMembershipLevel', $user->ID ) == $Level->id) {
								echo 'selected';
							}
							?>
							>
							<?php echo $Level->name; ?>
						</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<td></td>
				<td>
					<input type="submit" name="change_membership" value="Update Membership" class="button button-primary">
				</td>
			</tr>
		</table>
	</form>
	<br><br><br><hr>
	
	<?php // if(isset($membershipLevel)) :
		//  <form  method="POST" id="membershipLevelCancelForm">
		// 	<input type="hidden" value="cancelMembership">
		// 	<?php wp_nonce_field( 'cancelMembership_nonce') 
		// 	<input type="submit" class="woocommerce-Button button" value="Cancel membership" name="cancel_membership">
		// </form>
		// endif; ?>


<?php
$member_id = get_current_user_id();
if(isset($_POST['wineClubProcesManully']))
{
	if($_POST['wineClubProcesManully']) {
    	update_user_meta($member_id, 'wineClubProcesManully', $_POST['wineClubProcesManully']);

    if(isset($_POST['wineClubProcesManullyTillDate'])) {
    	if(DateTime::createFromFormat('Y-m-d', $_POST['wineClubProcesManullyTillDate']) !== FALSE || $_POST['wineClubProcesManullyTillDate'] == '') {
    		update_user_meta($member_id, 'wineClubProcesManullyTillDate', $_POST['wineClubProcesManullyTillDate']);
    	}
    	$adminEmail = get_option('admin_email');
			$to 		= $adminEmail; 
			$subject 	= 'User Pause Membership';
			$messeage 	= '<div>This is to inform you that '.$user->first_name.' '.$user->last_name.' has paused their membership until '.$_POST['wineClubProcesManullyTillDate'].'. If this is not correct, please login to the account to change.</div>';
			$headers 	= ['Content-Type: text/html; charset=UTF-8', 'From: Club Connection <'. $adminEmail .'>']; 

			$mail = wp_mail( $to, $subject, $messeage, $headers);

			if($mail) {  echo "<div class='notice notice-success'> Membership Status Updated Successfully </div>";
			} else { 
				echo "<div class='notice notice-error'> Email Sent error </div>";
			}
    }
	}else{
		    update_user_meta($member_id, 'wineClubProcesManully', $_POST['wineClubProcesManully']);
	}
    //echo "<div class='notice notice-success'> Membership Status Updated Successfully </div>";
}

?>	
	<h2> Pause Membership</h2>
<div>
	<form method="post" action="">
	<table>
		<tr>
			<th><label for="wineClubProcesManully"><?php _e("Pause Membership"); ?></label></th>
			<td>
				<fieldset>
					<label for="wineClubProcesManully">
						<input name="wineClubProcesManully" type="hidden" id="wineClubProcesManully" value="0">
						<input name="wineClubProcesManully" type="checkbox" id="wineClubProcesManully" value="1" <?php 	
						 if(get_the_author_meta( 'wineClubProcesManully', $member_id )): ?> checked="checked" <?php endif; ?>>
						<?php _e("Pause Membership"); ?>
					</label>
				</fieldset>
			</td>
		</tr>
		<tr>
			<th><label for="wineClubProcesManullyTillDate"><?php _e("Resume Membership On: "); ?> </label><small><?php // _e("( Leave blank for process manually forever )"); ?></small></th>
				<td>
					<fieldset>
						<input min="<?php echo date('Y-m-d') ?>" name="wineClubProcesManullyTillDate" type="date" id="wineClubProcesManullyTillDate"  value="<?php if(isset($_POST['wineClubProcesManullyTillDate'])){ echo $_POST['wineClubProcesManullyTillDate']; }else{ echo get_the_author_meta( 'wineClubProcesManullyTillDate', $member_id ); } ?>">
						<br>
					</fieldset>
				</td>
			</tr>
			<tr>
				<td></td>
				<td>
					<input type="submit" name="" value="Update" class="button button-primary">
				</td>

			</tr>
	</table>
	</form>
</div>
