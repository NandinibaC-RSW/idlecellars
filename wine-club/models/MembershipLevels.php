<?php
class MembershipLevels {

	protected $table = 'wineClubMembershipLevels';

	public static function getAll() {
		global $wpdb;
		$self = new static;
		return $wpdb->get_results('SELECT * FROM '. $wpdb->prefix.''.$self->table);
	}

	public static function find($id) {
		global $wpdb;
		return $wpdb->get_row($wpdb->prepare("SELECT * FROM ". $wpdb->prefix."wineClubMembershipLevels WHERE id=%d", $id));
	}

	public static function ifIsNotWineClubMember() {
        $userId = get_current_user_id();

        if($userId == 0) {
            return true;
        }

        $membershipLevelId = get_the_author_meta( 'wineClubMembershipLevel', $userId );
        if(!$membershipLevelId) {
            return true;
        }

        $membershipLevel = static::find($membershipLevelId);
        if($membershipLevel) {
            return false;
        }

        return true;
    }

}

