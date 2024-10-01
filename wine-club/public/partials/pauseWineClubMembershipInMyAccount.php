
<link rel="stylesheet" type="text/css" href="<?php echo plugins_url().'/wine-club/public/wine-club-public.css'; ?>">
<?php
$member_id = get_current_user_id();
if(isset($_POST['wineClubProcesManully']))
{
	if(isset($_POST['wineClubProcesManully'])) {
    	update_user_meta($member_id, 'wineClubProcesManully', $_POST['wineClubProcesManully']);
    }

    if(isset($_POST['wineClubProcesManullyTillDate'])) {
    	if(DateTime::createFromFormat('Y-m-d', $_POST['wineClubProcesManullyTillDate']) !== FALSE || $_POST['wineClubProcesManullyTillDate'] == '') {
    		update_user_meta($member_id, 'wineClubProcesManullyTillDate', $_POST['wineClubProcesManullyTillDate']);
    	}
    }
    echo "<div class='notice notice-success'> Membership Status Updated Successfully </div>";
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
			<th><label for="wineClubProcesManullyTillDate"><?php _e("Resume Membership On: "); ?> </label><small><?php _e("( Leave blank for process manually forever )"); ?></small></th>
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