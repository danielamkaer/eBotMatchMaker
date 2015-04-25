<?php

/*

ebotcontrol.class.php

Contains functions for eBot to Challonge and Vice Versa

*/


class eBotController {
	private $eBotMySQL = null;
	private $challongeInfo = null;
	private $eBotTeamSettings = array("teamflag"=>"AU", "seasonid"=>"1");
	private $eBotMatchSettings = null;

	public $constructed = false;
	private $MySQLcon = null;

	public function __construct($emsql, $chinf, $ebteam, $ebmatch) {
		$this->eBotMySQL = $emsql;
		$this->challongeInfo = $chinf;
		$this->eBotTeamSettings = $ebteam;
		$this->eBotMatchSettings = $ebmatch;
		$constructed = true;
	}

	public function connectMySQL() {
		if(!$this->constructed) {return false;}	
		$con = mysqli_connect($eBotMySQL['hostname'], $eBotMySQL['username'], $eBotMySQL['password'], $eBotMySQL['database']) or die(mysqli_error());
		if(!$con) {return $con;}
		$this->MySQLcon = $con;
		return $con;
	}

	public function query($query) {
		if(!$this->constructed) {return false;}
		if(!$this->MySQLcon) {return false;}
		$query = mysqli_query($MySQLcon, $query);
		if(!$query) {return false;}
		return $query;
	}

	public function getAssoc($query) {
		if(!$this->constructed) {return false;}
		if(!$this->MySQLcon) {return false;}
		$assoc = mysqli_fetch_assoc($query);
		if(!$assoc) {return false;}
		return $assoc;
	}

	public function getNumRows($query) {
		if(!$this->constructed) {return false;}
		if(!$this->MySQLcon) {return false;}
		$numrow = mysqli_num_rows($query);
		return $numrow;
	}

	public function createTeams($teamjson) {
		foreach($teamjson->tournament->participants as &$value) {   
			$teamname = $value->participant->name;
			$query_team = $this->query("INSERT INTO `teams` (`id`, `name`, `shorthandle`, `flag`, `link`, `created_at`, `updated_at`) VALUES (NULL, '" . $teamname . "', '', '" . $this->eBotTeamSettings['teamflag'] . "', NULL, CURRENT_DATE(), CURRENT_DATE())");
			echo "Team Name: " . $teamname . "; Team Flag: " . $this->eBotTeamSettings['teamflag'] . "\r\n";
			$query_getteam = $this->query("SELECT * FROM `teams` WHERE `name`='$teamname'");
			$assoc_getteam = $this->getAssoc($query_getteam);
			$query_seasonteam = $this->query("INSERT INTO `teams_in_seasons` (`id`, `season_id`, `team_id`, `created_at`, `updated_at`) VALUES (NULL, '" . $this->eBotTeamSettings['seasonid'] . "', '" . $assoc_getteam['id'] . "', CURRENT_DATE(), CURRENT_DATE())");
		}
	}

	public function createMatches($json) {
		$team1_name;
		$team2_name;

		foreach($json->tournament->matches as &$value) {
			echo "Round " . $value->match->round . ", Match Identifier " . $value->match->identifier . ": ";

			if($value->match->state == "pending") {
				echo "Matches before have not finished. Skipping...\r\n";
			}else{
				$team1_id = $value->match->player1_id;
				$team2_id = $value->match->player2_id;
				$matchid = $value->match->id;

				$query_checkmatch = $this->query("SELECT * FROM `matchmaker` WHERE `matchid`='$matchid'");
				$numrow_checkmatch = $this->getNumRows($query_checkmatch);

				if($numrow_checkmatch == 0) {
					foreach($json->tournament->participants as &$team) {
						if($team->participant->id == $team1_id) {
							$team1_name = $team->participant->name;
						}

						if($team->participant->id == $team2_id) {
							$team2_name = $team->participant->name;
						}
					}
					
					echo $team1_name . " (" . $team1_id . ") vs. " . $team2_name . " (" . $team2_id . ")\r\n";
					$query_mminsert = $this->query("INSERT INTO `matchmaker`(`id`, `matchid`) VALUES (NULL, $matchid)");

					$query_team1 = $this->query("SELECT * FROM `teams` WHERE `name`='$team1_name'");
					$query_team2 = $this->query("SELECT * FROM `teams` WHERE `name`='$team2_name'");
					$assoc_team1 = $this->getAssoc($query_team1);
					$assoc_team2 = $this->getAssoc($query_team2);

					$randomString = $this->randomString();
					$creatematch = "INSERT INTO `matchs` (`id`, `ip`, `server_id`, `season_id`, `team_a`, `team_a_flag`, `team_a_name`, `team_b`, `team_b_flag`, `team_b_name`, `status`, `is_paused`, `score_a`, `score_b`, `max_round`, `rules`, `overtime_startmoney`, `overtime_max_round`, `config_full_score`, `config_ot`, `config_streamer`, `config_knife_round`, `config_switch_auto`, `config_auto_change_password`, `config_password`, `config_heatmap`, `config_authkey`, `enable`, `map_selection_mode`, `ingame_enable`, `current_map`, `force_zoom_match`, `identifier_id`, `startdate`, `auto_start`, `auto_start_time`, `created_at`, `updated_at`) VALUES (NULL, NULL, NULL, " . $this->eBotTeamSettings['seasonid'] . ", NULL, '" . $this->eBotTeamSettings['teamflag'] . "', '$team1_name', NULL, '" . $this->eBotTeamSettings['teamflag'] . "', '$team2_name', '0', NULL, '0', '0', '" . $this->eBotMatchSettings['maxround'] . "', '" . $this->eBotMatchSettings['rules'] . "', '" . $this->eBotMatchSettings['overtime_startmoney'] . "', '" . $this->eBotMatchSettings['overtime_mr'] . "', '0', '" . $this->eBotMatchSettings['overtime'] . "', '" . $this->eBotMatchSettings['streamer'] . "', '" . $this->eBotMatchSettings['knife'] . "', NULL, NULL, '" . $randomString . "', NULL, NULL, NULL, 'normal', NULL, NULL, NULL, NULL, CURRENT_DATE(), '0', '5', CURRENT_DATE(), CURRENT_DATE())";
					$query_creatematch = $this->query($creatematch);
					$query_matchid = $this->query("SELECT * FROM `matchs` WHERE `team_a_name`='$team1_name' AND `team_b_name`='$team2_name'");
					$assoc_matchid = $this->getAssoc($query_matchid);
					$matchid2 = $assoc_matchid['id'];
					$query_maps = $this->query("INSERT INTO `maps` (`id`, `match_id`, `map_name`, `score_1`, `score_2`, `current_side`, `status`, `maps_for`, `nb_ot`, `identifier_id`, `tv_record_file`, `created_at`, `updated_at`) VALUES ('" . $matchid2 . "', '" . $matchid2 . "', 'tba', '0', '0', 'ct', '0', 'default', '0', NULL, NULL, CURRENT_DATE(), CURRENT_DATE())");
					$query_updatemap = $this->query("UPDATE `matchs` SET `current_map`='$matchid2' WHERE `id`='$matchid2'");
				}else{
					echo "Already inserted match. Going onto next match...\r\n";
				}	
			}
		}
	}

	public function updateJSON() {
		$tournament = $this->curl_get_contents("http://api.challonge.com/v1/tournaments/" . $this->challongeInfo['tournamentid'] . ".json?api_key=" . $this->challongeInfo['apikey'] . "&include_participants=1&include_matches=1");
		return json_decode($tournament);
	}

	public function randomString($length = 10) {
		$characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$charactersLength = strlen($characters);
		$randomString = '';
		for ($i = 0; $i < $length; $i++) {
			$randomString .= $characters[rand(0, $charactersLength - 1)];
		}
		return $randomString;
	}
	
	function curl_get_contents($url) {
		$curl = curl_init($url);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
		$data = curl_exec($curl);
		curl_close($curl);
		return $data;
	}
}

?>