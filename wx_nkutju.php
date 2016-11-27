<?php
require_once ('include/bittorrent.php');

dbconn ();

function err($msg)
{
	echo $msg;
}
$Cache->delete('allowed_client_list');

function check_client($peer_id, $agent, $agent_familyid)
{
/*	$clients = array(array(
		'id' => 30,
		'family' => 'ACEStream 2.0',
		'peer_id_pattern' => '',
		'peer_id_match_num' => 0,
		'peer_id_matchtype' => 'dec',
		'peer_id_start' => 'R20',
		'agent_pattern' => '/^ACEStream(.*)/',
		'agent_match_num' => 0,
		'agent_matchtype' => 'dec',
		'agent_start' => 'ACEStream',
		'exception' => 'no',
		'allowhttps' => 'yes',
		'hits' => '0'
	));*/
	
	global $BASEURL, $Cache;

	if (!$clients = $Cache->get_value('allowed_client_list')){
		$clients = array();
		$res = mysql_query("SELECT * FROM agent_allowed_family ORDER BY hits DESC") or err("check err");
		while ($row = mysql_fetch_array($res))
			$clients[] = $row;
		$Cache->cache_value('allowed_client_list', $clients, 86400);
	}
	foreach ($clients as $row_allowed_ua)
	{
		$allowed_flag_peer_id = false;
		$allowed_flag_agent = false;
		$version_low_peer_id = false;
		$version_low_agent = false;

		if($row_allowed_ua['peer_id_pattern'] != '')
		{
			if(!preg_match($row_allowed_ua['peer_id_pattern'], $row_allowed_ua['peer_id_start'], $match_bench))
			err("�ͻ���(peerid): " . $row_allowed_ua['peer_id_start'] . " ����ʼ�汾��ƥ�䣬����ϵ��վ����Ա�޸���");

			if(preg_match($row_allowed_ua['peer_id_pattern'], $peer_id, $match_target))
			{
				if($row_allowed_ua['peer_id_match_num'] != 0)
				{
					for($i = 0 ; $i < $row_allowed_ua['peer_id_match_num']; $i++)
					{
						if($row_allowed_ua['peer_id_matchtype'] == 'dec')
						{
							$match_target[$i+1] = 0 + $match_target[$i+1];
							$match_bench[$i+1] = 0 + $match_bench[$i+1];
						}
						else if($row_allowed_ua['peer_id_matchtype'] == 'hex')
						{
							$match_target[$i+1] = hexdec($match_target[$i+1]);
							$match_bench[$i+1] = hexdec($match_bench[$i+1]);
						}

						if ($match_target[$i+1] > $match_bench[$i+1])
						{
							$allowed_flag_peer_id = true;
							break;
						}
						else if($match_target[$i+1] < $match_bench[$i+1])
						{
							$allowed_flag_peer_id = false;
							$version_low_peer_id = true;
							$low_version = "��ʹ�õĿͻ��� " . $row_allowed_ua['family'] . " �汾���ͣ��������� " . $row_allowed_ua['start_name']. " ֮��汾��";
							break;
						}
						else if($match_target[$i+1] == $match_bench[$i+1])//equal
						{
							if($i+1 == $row_allowed_ua['peer_id_match_num'])		//last
							{
								$allowed_flag_peer_id = true;
							}
						}
					}
				}
				else // no need to compare version
				$allowed_flag_peer_id = true;
			}
		}
		else	// not need to match pattern
		$allowed_flag_peer_id = true;

		if($row_allowed_ua['agent_pattern'] != '')
		{
			if(!preg_match($row_allowed_ua['agent_pattern'], $row_allowed_ua['agent_start'], $match_bench))
			err("�ͻ���(agent): " . $row_allowed_ua['agent_start'] . " ����ʼ�汾��ƥ�䣬����ϵ��վ����Ա�޸���");

			if(preg_match($row_allowed_ua['agent_pattern'], $agent, $match_target))
			{
				if( $row_allowed_ua['agent_match_num'] != 0)
				{
					for($i = 0 ; $i < $row_allowed_ua['agent_match_num']; $i++)
					{
						if($row_allowed_ua['agent_matchtype'] == 'dec')
						{
							$match_target[$i+1] = 0 + $match_target[$i+1];
							$match_bench[$i+1] = 0 + $match_bench[$i+1];
						}
						else if($row_allowed_ua['agent_matchtype'] == 'hex')
						{
							$match_target[$i+1] = hexdec($match_target[$i+1]);
							$match_bench[$i+1] = hexdec($match_bench[$i+1]);
						}

						if ($match_target[$i+1] > $match_bench[$i+1])
						{
							$allowed_flag_agent = true;
							break;
						}
						else if($match_target[$i+1] < $match_bench[$i+1])
						{
							$allowed_flag_agent = false;
							$version_low_agent = true;
							$low_version = "��ʹ�õĿͻ��� " . $row_allowed_ua['family'] . " �汾���ͣ��������� " . $row_allowed_ua['start_name']. " ֮��汾��";
							break;
						}
						else //equal
						{
							if($i+1 == $row_allowed_ua['agent_match_num'])		//last
							$allowed_flag_agent = true;
						}
					}
				}
				else // no need to compare version
				$allowed_flag_agent = true;
			}
		}
		else
		$allowed_flag_agent = true;

		if($allowed_flag_peer_id && $allowed_flag_agent)
		{
			$exception = $row_allowed_ua['exception'];
			$family_id = $row_allowed_ua['id'];
			$allow_https = $row_allowed_ua['allowhttps'];
			break;
		}
		elseif(($allowed_flag_peer_id || $allowed_flag_agent) || ($version_low_peer_id || $version_low_agent))	//client spoofing possible
		;//add anti-cheat code here
	}

	if($allowed_flag_peer_id && $allowed_flag_agent)
	{
		if($exception = 'yes')
		{
			if($clients_exp)
			{
				foreach ($clients_exp as $row_allowed_ua_exp)
				{
					if($row_allowed_ua_exp['agent'] == $agent && preg_match("/^" . $row_allowed_ua_exp['peer_id'] . "/", $peer_id))
					return "�ͻ��� " . $row_allowed_ua_exp['name'] . " ��Ϊ " . $row_allowed_ua_exp['comment'] . " ����ֹ�ڱ�վʹ�ã�";
				}
			}
			$agent_familyid = $row_allowed_ua['id'];
		}
		else
		{
			$agent_familyid = $row_allowed_ua['id'];
		}

		if($_SERVER["HTTPS"] == "on")
		{
			if($allow_https == 'yes')
			return 0;
			else
			return "��ǰ�ͻ��˲��ܺܺõ�֧��https���뵽 $BASEURL/faq.php#id29 �鿴�Ƽ��ͻ��ˣ�";
		}
		else
		return 0;	// no exception found, so allowed or just allowed
	}
	else
	{
		if($version_low_peer_id && $version_low_agent)
		return $low_version;
		else
		return "�Ƿ��ͻ��ˣ��뵽 $BASEURL/faq.php#id29 �鿴����ͻ����б�";
	}
}

var_dump(check_client ( 'R20------iZuNXWGkACK', 'ACEStream/ACEStream-2.0', $client_familyid ));
var_Dump($client_familyid);
?>