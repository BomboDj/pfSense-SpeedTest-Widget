<?php
/*
 * *** pfSense SpeedTest Widget ***
 *
 * Based on:
 *   “speedtest-go” utility by showwin (https://github.com/showwin/speedtest-go)
 *   “pfsense-speedtest-widget” widget by LeonStraathof (https://github.com/LeonStraathof/pfsense-speedtest-widget)
 *
 *
 * INSTALL
 *
 *     Diagnotics -> Command Prompt -> Execute Shell Command:
 *         pkg update
 *     Diagnotics -> Command Prompt -> Execute Shell Command:
 *         pkg install -y speedtest-go
 *     Diagnotics -> Command Prompt -> Upload File:
 *         speedtest.widget.php
 *     Diagnotics -> Command Prompt -> Execute Shell Command:
 *         mv -f /tmp/speedtest.widget.php /usr/local/www/widgets/widgets/
 *     Status -> Dashboard:
 *         Add the speedtest widget
 *
 *
 * UNINSTALL
 *
 *     Status -> Dashboard:
 *         Remove the speedtest widget
 *     Diagnotics -> Command Prompt -> Execute Shell Command:
 *         rm -f /usr/local/www/widgets/widgets/speedtest.widget.php
 *     Diagnotics -> Command Prompt -> Execute Shell Command:
 *         pkg remove -y speedtest-go
 */

require_once("guiconfig.inc");

const SPEEDTEST_GO = "/usr/local/bin/speedtest-go";

/**
 * Run speedtest-go and return its JSON response as an array.
 */
function speedtest_go_json($serverid = 0) {
	$command = escapeshellarg(SPEEDTEST_GO) . " --json";
	if ($serverid > 0) {
		$command .= " --server " . (int)$serverid;
	}
	$command .= " 2>/dev/null";

	$output = shell_exec($command);
	if (!is_string($output) || trim($output) === "") {
		return null;
	}

	$data = json_decode($output, true);
	return is_array($data) ? $data : null;
}

/**
 * Parse the human-readable output of speedtest-go --list.
 * Example:
 * [26706] 0.91km 5ms Milan (Italy) by Navigabene
 */
function speedtest_go_servers() {
	$output = shell_exec(escapeshellarg(SPEEDTEST_GO) . " --list 2>/dev/null");
	$servers = array();

	if (!is_string($output)) {
		return $servers;
	}

	foreach (preg_split("/\r?\n/", $output) as $line) {
		$line = trim($line);
		if ($line === "") {
			continue;
		}

		if (!preg_match(
			'/^\[\s*(\d+)\]\s+([0-9.]+)km\s+([0-9.]+)ms\s+(.+?)\s+\(([^)]*)\)\s+by\s+(.+)$/',
			$line,
			$matches
		)) {
			continue;
		}

		$servers[] = array(
			'latency' => (float)$matches[3],
			'server' => array(
				'id' => (int)$matches[1],
				'name' => trim($matches[4]),
				'location' => trim($matches[4]),
				'country' => trim($matches[5]),
				'sponsor' => trim($matches[6]),
				'distance' => (float)$matches[2]
			)
		);
	}

	return array_slice($servers, 0, 10);
}

/**
 * Convert a speedtest-go response to the JSON shape used by the widget UI.
 */
function speedtest_go_result($data, $serverlist = array()) {
	if (!is_array($data) || !isset($data['servers']) || !is_array($data['servers']) ||
		count($data['servers']) === 0 || !is_array($data['servers'][0])) {
		return null;
	}

	$source = $data['servers'][0];
	$id = isset($source['id']) ? (int)$source['id'] : 0;
	$selected = null;

	foreach ($serverlist as $listed) {
		if (isset($listed['server']['id']) && (int)$listed['server']['id'] === $id) {
			$selected = $listed;
			break;
		}
	}

	$server = array(
		'id' => $id,
		'name' => isset($source['name']) ? $source['name'] : '',
		'location' => isset($source['name']) ? $source['name'] : '',
		'country' => isset($source['country']) ? $source['country'] : '',
		'host' => isset($source['host']) ? $source['host'] : '',
		'sponsor' => isset($source['sponsor']) ? $source['sponsor'] : ''
	);

	if ($selected !== null) {
		$server = array_merge($server, $selected['server']);
	}

	$user = isset($data['user_info']) && is_array($data['user_info']) ? $data['user_info'] : array();
	$packetLoss = -1;
	if (isset($source['packet_loss']) && is_array($source['packet_loss']) &&
		isset($source['packet_loss']['sent']) && (int)$source['packet_loss']['sent'] > 0) {
		$sent = (int)$source['packet_loss']['sent'];
		$dup = isset($source['packet_loss']['dup']) ? (int)$source['packet_loss']['dup'] : 0;
		$max = isset($source['packet_loss']['max']) ? (int)$source['packet_loss']['max'] : 0;
		if ($max >= 0) {
			$packetLoss = round((1 - (($sent - $dup) / ($max + 1))) * 100, 2);
		}
	}

	return array(
		'timestamp' => isset($data['timestamp']) ? $data['timestamp'] : '',
		'ping' => array(
			'latency' => isset($source['latency']) ? ((float)$source['latency'] / 1000000) : 0
		),
		'download' => array(
			'bandwidth' => isset($source['dl_speed']) ? (float)$source['dl_speed'] : 0
		),
		'upload' => array(
			'bandwidth' => isset($source['ul_speed']) ? (float)$source['ul_speed'] : 0
		),
		'packetLoss' => $packetLoss,
		'isp' => isset($user['Isp']) ? $user['Isp'] : '',
		'interface' => array(
			'externalIp' => isset($user['IP']) ? $user['IP'] : ''
		),
		'server' => $server,
		'serverSelection' => array(
			'servers' => array_values($serverlist)
		)
	);
}

if (isset($_REQUEST['serverid']) && filter_var($_REQUEST['serverid'], FILTER_VALIDATE_INT) !== false) {
	$serverid = (int)$_REQUEST['serverid'];
	if ($serverid < 0) {
		$serverid = 0;
	}

	$serverlist = speedtest_go_servers();
	$data = speedtest_go_json($serverid);
	$results = speedtest_go_result($data, $serverlist);

	if ($results !== null) {
		$config['widgets']['speedtest_result'] = json_encode($results);
		write_config("Save speedtest-go results");
	}

	header("Content-Type: application/json");
	echo json_encode($results);
	exit;
}

$results = null;
if (isset($config['widgets']['speedtest_result'])) {
	$stored = json_decode($config['widgets']['speedtest_result'], true);
	if (is_array($stored)) {
		$results = $stored;
	}
}

$initialResults = json_encode($results);
?>
<table class="table">
	<tr>
		<td style="width:25%"><font color="#40ab56"><i class="fas fa-ellipsis-h"></i></font> <b id="speedtest-ping">N/A</b></td>
		<td style="width:25%"><font color="#00a8f3"><i class="fas fa-arrow-down"></i></font> <b id="speedtest-download">N/A</b></td>
		<td style="width:50%"><font color="#ff7f0e"><i class="fas fa-arrow-up"></i></font> <b id="speedtest-upload">N/A</b></td>
	</tr>
	<tr>
		<td>Packetloss</td>
		<td colspan="2" id="speedtest-packetloss">N/A</td>
	</tr>
	<tr>
		<td>ISP</td>
		<td colspan="2" id="speedtest-isp">N/A</td>
	</tr>
	<tr>
		<td>Host</td>
		<td colspan="2">
			<select name="speedtest-host" id="speedtest-host" style="width: 100%">
				<option value="0">= AUTOSELECT AND REFRESH SERVER LIST =</option>
			</select>
		</td>
		<td style="width:1%"</td>
	</tr>
	<tr>
		<td colspan="3" id="speedtest-ts" style="font-size: 0.8em;">&nbsp;</td>
	</tr>
</table>
<a id="updspeed" href="#" class="fa fa-refresh" style="display: none;"></a>
<script type="text/javascript">
function reset_result(message) {
	$("#speedtest-ts").text(message || "Speedtest failed");
	$("#speedtest-ping, #speedtest-download, #speedtest-upload, #speedtest-packetloss, #speedtest-isp").text("N/A");
	$("#speedtest-host").empty().append($('<option>', {
		value: 0,
		text: '= AUTOSELECT AND REFRESH SERVER LIST ='
	})).val(0);
}

function update_result(results) {
	if (!results || !results.server) {
		reset_result();
		return;
	}

	var date = new Date(results.timestamp.replace(" ", "T") + "Z");
	$("#speedtest-ts").text(date);
	$("#speedtest-ping").html(Number(results.ping.latency).toFixed(2) + "<small> ms</small>");
	$("#speedtest-download").html((Number(results.download.bandwidth) / 1000000 * 8).toFixed(2) + "<small> Mbps</small>");
	$("#speedtest-upload").html((Number(results.upload.bandwidth) / 1000000 * 8).toFixed(2) + "<small> Mbps</small>");
	$("#speedtest-packetloss").text(Number(results.packetLoss) < 0 ? "N/A" : Number(results.packetLoss).toFixed(2) + "%");
	$("#speedtest-isp").text((results.isp || "N/A") + (results.interface && results.interface.externalIp ? " (" + results.interface.externalIp + ")" : ""));

	var select = $("#speedtest-host").empty();
	var current = results.server;
	select.append($('<option>', {
		value: current.id,
		text: (current.sponsor || "Server") + " [" + current.id + "] " +
			(current.location || current.name || "") + " " + (current.country || "") +
			" (" + Number(results.ping.latency).toFixed(2) + " ms)"
	})).val(current.id);
	select.append($('<option>', {
		value: 0,
		text: '= AUTOSELECT AND REFRESH SERVER LIST ='
	}));

	var servers = results.serverSelection && results.serverSelection.servers ? results.serverSelection.servers.slice() : [];
	servers.sort(function(a, b) { return Number(a.latency) - Number(b.latency); });
	$.each(servers, function(i, entry) {
		if (!entry.server || Number(entry.server.id) === Number(current.id)) return;
		var latency = Number(entry.latency);
		select.append($('<option>', {
			value: entry.server.id,
			text: (entry.server.sponsor || "Server") + " [" + entry.server.id + "] " +
				(entry.server.location || entry.server.name || "") + " " + (entry.server.country || "") +
				" (" + (isFinite(latency) ? latency.toFixed(2) + " ms" : "N/A") + ")"
		}));
	});
}

function update_speedtest() {
	$('#updspeed').off("click").blur().addClass("fa-spin").click(function() {
		return false;
	});
	$.ajax({
		type: 'POST',
		url: "/widgets/widgets/speedtest.widget.php",
		dataType: 'json',
		data: { serverid: $("#speedtest-host option:selected").val() },
		success: function(data) { update_result(data); },
		error: function() { reset_result(); },
		complete: function() {
			$('#updspeed').off("click").removeClass("fa-spin").click(function() {
				update_speedtest();
				return false;
			});
		}
	});
}

events.push(function() {
	var target = $("#updspeed").closest(".panel").find(".widget-heading-icon");
	$("#updspeed").prependTo(target).show();
	$("#updspeed").click(function() {
		update_speedtest();
		return false;
	});
	update_result(<?php echo $initialResults; ?>);
});
</script>
