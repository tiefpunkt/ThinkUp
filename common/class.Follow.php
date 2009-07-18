<?php

class Follow {
	
//TODO set up this object and use it instead of associative arrays!
	
}


class FollowDAO {	
 
	function followExists($user_id, $follower_id) {
		$q = "
			SELECT 
				user_id, follower_id
			FROM 
				follows
			WHERE 
				user_id = ".$user_id." AND follower_id=".$follower_id.";";
		$sql_result = mysql_query($q) or die('Error, selection query failed:' .$q );
		if ( mysql_num_rows($sql_result) > 0 )
			return true;
		else
			return false;		
	}


	function update($user_id, $follower_id) {
		$q = "
			UPDATE 
			 	follows
			SET
				last_seen=NOW()
			WHERE
				user_id = ".$user_id." AND follower_id=".$follower_id.";";
		$sql_result = mysql_query($q) or die('Error, update failed:' .$q );
		if (mysql_affected_rows() > 0)
			return true;
		else
			return false;
	}
	
	function insert($user_id, $follower_id) {
		$q = "
			INSERT INTO
				follows (user_id,follower_id,last_seen)
				VALUES (
					".$user_id.",".$follower_id.",NOW()
				);";
		$foo = mysql_query($q) or die('Error, insert query failed: '. $q );
		if (mysql_affected_rows() > 0)
			return true;
		else
			return false;
	}
	
	function getUnloadedFollowerDetails($user_id) {
		$q = "
			SELECT
				follower_id
			FROM 
				follows f 
			WHERE 
				f.user_id=".$user_id."
				AND f.follower_id NOT IN (SELECT user_id FROM users) 
				AND f.follower_id NOT IN (SELECT user_id FROM user_errors)
			LIMIT 100;";
		$sql_result = mysql_query($q)  or die("Error, selection query failed: $sql_query");
		$strays = array();
		while ($row = mysql_fetch_assoc($sql_result)) { $strays[] = $row; }
		mysql_free_result($sql_result);	
		return $strays;
		
	}
	
	function getTotalFollowsWithErrors($user_id) {
		$q = "
			SELECT
				count(follower_id) as follows_with_errors
			FROM 
				follows f 
			WHERE 
				f.user_id=".$user_id."
				AND f.follower_id IN (SELECT user_id FROM user_errors WHERE error_issued_to_user_id=".$user_id.");";
		$sql_result = mysql_query($q)  or die("Error, selection query failed: $sql_query");
		$ferrors = array();
		while ($row = mysql_fetch_assoc($sql_result)) { $ferrors[] = $row; }
		mysql_free_result($sql_result);	
		return $ferrors[0]['follows_with_errors'];		
		
	}

	function getTotalFriendsWithErrors($user_id) {
		$q = "
			SELECT
				count(follower_id) as friends_with_errors
			FROM 
				follows f 
			WHERE 
				f.follower_id=".$user_id."
				AND f.user_id IN (SELECT user_id FROM user_errors WHERE error_issued_to_user_id=".$user_id.");";
		$sql_result = mysql_query($q)  or die("Error, selection query failed: $sql_query");
		$ferrors = array();
		while ($row = mysql_fetch_assoc($sql_result)) { $ferrors[] = $row; }
		mysql_free_result($sql_result);	
		return $ferrors[0]['friends_with_errors'];		
		
	}

	
	function getTotalFollowsWithFullDetails($user_id) {
		$q = "
			 SELECT count( * ) as follows_with_details
			FROM `follows` f
			INNER JOIN users u ON u.user_id = f.follower_id
			WHERE f.user_id = ".$user_id;
		$sql_result = mysql_query($q)  or die("Error, selection query failed: $sql_query");
		$details = array();
		while ($row = mysql_fetch_assoc($sql_result)) { $details[] = $row; }
		mysql_free_result($sql_result);	
		return $details[0]['follows_with_details'];		
	}

	function getTotalFollowsProtected($user_id) {
		$q = "
			 SELECT count( * ) as follows_protected
			FROM `follows` f
			INNER JOIN users u ON u.user_id = f.follower_id
			WHERE f.user_id = ".$user_id." AND u.is_protected=1";
		$sql_result = mysql_query($q)  or die("Error, selection query failed: $sql_query");
		$details = array();
		while ($row = mysql_fetch_assoc($sql_result)) { $details[] = $row; }
		mysql_free_result($sql_result);	
		return $details[0]['follows_protected'];		
	}

	function getTotalFriends($user_id) {
		$q = "
			 SELECT count( * ) as total_friends
			FROM `follows` f
			INNER JOIN users u ON u.user_id = f.user_id
			WHERE f.follower_id = ".$user_id."";
		$sql_result = mysql_query($q)  or die("Error, selection query failed: $sql_query");
		$details = array();
		while ($row = mysql_fetch_assoc($sql_result)) { $details[] = $row; }
		mysql_free_result($sql_result);	
		return $details[0]['total_friends'];		
	}

	function getTotalFriendsProtected($user_id) {
		$q = "
			 SELECT count( * ) as friends_protected
			FROM `follows` f
			INNER JOIN users u ON u.user_id = f.user_id
			WHERE f.follower_id = ".$user_id." AND u.is_protected=1";
		$sql_result = mysql_query($q)  or die("Error, selection query failed: $sql_query");
		$details = array();
		while ($row = mysql_fetch_assoc($sql_result)) { $details[] = $row; }
		mysql_free_result($sql_result);	
		return $details[0]['friends_protected'];		
	}
	
	function getStalestFriend($user_id) {
		$q = "
			SELECT
				u.*
			FROM 
				users u
			INNER JOIN
				follows f
			ON
			 	f.user_id = u.user_id
			WHERE 
				f.follower_id=".$user_id." 
				AND u.user_id NOT IN (SELECT user_id FROM user_errors) 
				AND u.last_updated < DATE_SUB(NOW(), INTERVAL 1 DAY)
			ORDER BY
				u.last_updated ASC
			LIMIT 1;";
		$sql_result = mysql_query($q)  or die("Error, selection query failed: $sql_query");
		$oldfriend = array();
		if ( mysql_num_rows($sql_result) > 0 ) {
			while ($row = mysql_fetch_assoc($sql_result)) { $oldfriend[] = $row; }
			mysql_free_result($sql_result);
			$friend_object = new User($oldfriend[0], "Friends");
		} else {
			$friend_object = null;
		}
		return $friend_object;
	}
	


}

?>
