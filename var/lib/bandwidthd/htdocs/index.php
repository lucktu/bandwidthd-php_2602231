<?php
error_reporting(0); #Set the error level to 0, which means no error messages are printed
include("include.php");
$subtitle = "Sensors";
include("header.php");

// Get variables from url

if (isset($_GET['sensor_id']) && is_numeric($_GET['sensor_id']))
    $sensor_id = $_GET['sensor_id'];
else
    $sensor_id = 'abc';

// Handle time selection mode
$time_mode = isset($_GET['time_mode']) ? $_GET['time_mode'] : 'interval';

// Initialize date variables to prevent undefined variable errors
$start_date = date('Y-m-d');
$end_date = date('Y-m-d');

if ($time_mode == 'interval') {
    if (isset($_GET['interval']) && is_numeric($_GET['interval']))
        $interval = $_GET['interval'];
    else
        $interval = 24*60*60;

    if (!isset($timestamp))
        $timestamp = time() - $interval;
} else {
    // Date range logic
    if (isset($_GET['start_date']) && !empty($_GET['start_date'])) {
        $start_timestamp = strtotime($_GET['start_date'] . ' 00:00:00');
        $start_date = $_GET['start_date'];
    } else {
        // Fixed: Default to today 00:00:00 for accurate 24-hour display
        $start_timestamp = strtotime(date('Y-m-d') . ' 00:00:00');
        $start_date = date('Y-m-d', $start_timestamp);
    }

    if (isset($_GET['end_date']) && !empty($_GET['end_date'])) {
        $end_timestamp = strtotime($_GET['end_date'] . ' 23:59:59');
        $end_date = $_GET['end_date'];
    } else {
        // Fixed: Default to today 23:59:59 for accurate 24-hour display
        $end_timestamp = $start_timestamp + 24*60*60 - 1;
        $end_date = date('Y-m-d', $end_timestamp);
    }

    // Use absolute timestamps, not sliding window
    $timestamp = $start_timestamp;
    $interval = $end_timestamp - $start_timestamp;
}

if (isset($_GET['subnet']) && $_GET['subnet'] != "none" && $_GET['subnet'] != "" )
    $subnet = pg_escape_string($_GET['subnet']);

if (isset($_GET['limit']) && ($_GET['limit'] == "all" || is_numeric($_GET['limit'])))
	$limit = $_GET['limit'];

$graphs = false;
if (isset($_GET['graphs']))
	$graphs = true;

$db = ConnectDb();
?>

<FORM name="navigation" method=get action="<?=$_SERVER['PHP_SELF']?>">

<table width="100%" cellspacing=0 cellpadding=5 border=1>
<tr>
<td>
<?php
$sql = "SELECT sensor_name, interface, sensor_id, last_connection from sensors order by sensor_name, interface;";
$result = @pg_query($sql);
if (!$result)
	{
	echo "<center>Collecting data...</center>";
	exit();
	}
?>

<SELECT name="sensor_id">
<?php
while ($r = pg_fetch_array($result)) {
	if($sensor_name == $r['sensor_name'])
      $last = $r['last_connection'];
    echo '<OPTION value="' . $r['sensor_id'] .'" '
        . ($sensor_id==$r['sensor_id']?"SELECTED":"") . '>'
        . $r['sensor_name'] . ' - ' . $r['interface'] . "</OPTION>\n";
}

if (!isset($limit))
  $limit = 10;
?>
<OPTION value="none">--Select A Sensor--</OPTION>
</SELECT>

</td>
<td>

<!-- Time Selection Mode -->
<div class="time-selection-mode" style="display: flex; flex-wrap: wrap; align-items: center;">
    <input type="radio" name="time_mode" value="interval" id="mode_interval" <?=$time_mode=='interval'?'checked':''?> onchange="toggleTimeMode()">
    <label for="mode_interval">Sel</label>

    <input type="radio" name="time_mode" value="daterange" id="mode_daterange" <?=$time_mode=='daterange'?'checked':''?> onchange="toggleTimeMode()">
    <label for="mode_daterange">Rng</label>

    <!-- Interval controls -->
    <select name="interval" id="interval_controls" style="display:<?=$time_mode=='interval'?'inline-block':'none'?>;">
    <OPTION value="none">--Select An Interval--</OPTION>
    <OPTION value=<?=1*24*60*60?> <?=$interval==1*24*60*60?"SELECTED":""?>>1 Day</OPTION>
    <OPTION value=<?=2*24*60*60?> <?=$interval==2*24*60*60?"SELECTED":""?>>2 Days</OPTION>
    <OPTION value=<?=4*24*60*60?> <?=$interval==4*24*60*60?"SELECTED":""?>>4 Days</OPTION>
    <OPTION value=<?=7*24*60*60?> <?=$interval==7*24*60*60?"SELECTED":""?>>7 Days</OPTION>
    <OPTION value=<?=30*24*60*60?> <?=$interval==30*24*60*60?"SELECTED":""?>>1 Month</OPTION>
    <OPTION value=<?=365*24*60*60?> <?=$interval==365*24*60*60?"SELECTED":""?>>1 Year</OPTION>
    <OPTION value=<?=365*24*60*60*2?> <?=$interval==365*24*60*60*2?"SELECTED":""?>>2 Years</OPTION>
    <OPTION value=<?=365*24*60*60*3?> <?=$interval==365*24*60*60*3?"SELECTED":""?>>3 Years</OPTION>
    <OPTION value=<?=365*24*60*60*4?> <?=$interval==365*24*60*60*4?"SELECTED":""?>>4 Years</OPTION>
    <OPTION value=<?=365*24*60*60*5?> <?=$interval==365*24*60*60*5?"SELECTED":""?>>5 Years</OPTION>
    <OPTION value=<?=365*24*60*60*10?> <?=$interval==365*24*60*60*10?"SELECTED":""?>>+ Years</OPTION>
    </select>

    <!-- Date range controls -->
    <span id="daterange_controls" style="display:<?=$time_mode=='daterange'?'inline-block':'none'?>;">
    <input type="date" name="start_date" value="<?=$start_date?>" max="<?=date('Y-m-d')?>" style="width: 97px;">
    <input type="date" name="end_date" value="<?=$end_date?>" max="<?=date('Y-m-d')?>" style="width: 97px;">
    </span>
</div>

</td>
<td>

<SELECT name="limit">
<OPTION value="none">--How Many Results--</OPTION>
<OPTION value=10 <?=$limit==10?"SELECTED":""?>>10</OPTION>
<OPTION value=20 <?=$limit==20?"SELECTED":""?>>20</OPTION>
<OPTION value=50 <?=$limit==50?"SELECTED":""?>>50</OPTION>
<OPTION value=100 <?=$limit==100?"SELECTED":""?>>100</OPTION>
<OPTION value=all <?=$limit=="all"?"SELECTED":""?>>All</OPTION>
<OPTION value=0 <?=$limit==0?"SELECTED":""?>>0</OPTION>
</select>

<td>

Subnet Filter:<input name=subnet value="<?=isset($subnet)?$subnet:"192.0.0.0/0"?>">

</td>

<td>
<label><input type="checkbox" id="toggle-receive" checked>Recv</label>
<label><input type="checkbox" id="toggle-send">Send</label>
</td>

<td>

<?php if ($graphs) $GraphsChecked = "CHECKED"; else $GraphsChecked = ""; ?>
<input type="checkbox" name="graphs" <?=$GraphsChecked?>>More
<input type=submit value="      Refresh      ">

</td>

</tr>

</table>
</FORM>

<?php

// Validation
if (!isset($sensor_id)) {
	exit();
}

$sql = "SELECT sensor_name, interface, sensor_id, last_connection FROM sensors WHERE sensor_id = '$sensor_id';";
$result = @pg_query($sql);
$r = pg_fetch_array($result);
$sensor_name = $r['sensor_name'];
$interface = $r['interface'];
$last = $r['last_connection'];

// Print Title

if (isset($limit))
	echo "<div>Top $limit - $sensor_name - $interface <a style='float:right'>Last Connection: " . substr($last,0,19) . "</a></div>";
else
	echo "<div>All Records - $sensor_name - $interface <a style='float:right'>Last Connection: " . substr($last,0,19) . "</a></div>";

// Sqlize the incomming variables
if (isset($subnet))
	$sql_subnet = "and ip <<= '$subnet'";

// Sql Statement
$sql = "select tx.ip, rx.scale as rxscale, tx.scale as txscale, tx.total+rx.total as total, tx.total as sent,
rx.total as received, tx.tcp+rx.tcp as tcp, tx.udp+rx.udp as udp,
tx.icmp+rx.icmp as icmp, tx.http+rx.http as http,
tx.mail+rx.mail as mail,
tx.p2p+rx.p2p as p2p, tx.ftp+rx.ftp as ftp
from

(SELECT ip, max(total/sample_duration)*8 as scale, sum(total) as total, sum(tcp) as tcp, sum(udp) as udp, sum(icmp) as icmp,
sum(http) as http, sum(mail) as mail, sum(p2p) as p2p, sum(ftp) as ftp
from sensors, bd_tx_log
where sensors.sensor_id = '$sensor_id'
and sensors.sensor_id = bd_tx_log.sensor_id
$sql_subnet
and timestamp > $timestamp::abstime and timestamp < ".($timestamp+$interval)."::abstime
group by ip) as tx,

(SELECT ip, max(total/sample_duration)*8 as scale, sum(total) as total, sum(tcp) as tcp, sum(udp) as udp, sum(icmp) as icmp,
sum(http) as http, sum(mail) as mail, sum(p2p) as p2p, sum(ftp) as ftp
from sensors, bd_rx_log
where sensors.sensor_id = '$sensor_id'
and sensors.sensor_id = bd_rx_log.sensor_id
$sql_subnet
and timestamp > $timestamp::abstime and timestamp < ".($timestamp+$interval)."::abstime
group by ip) as rx

where tx.ip = rx.ip
order by total desc;";

pg_query("SET sort_mem TO 30000;");

pg_send_query($db, $sql);

$result = pg_get_result($db);

pg_query("set sort_mem to default;");

if ($limit == "all")
	$limit = pg_num_rows($result);

?>

<table width="100%" border=1 cellspacing=0>
<tr>
<th>Ip</th><th>Name</th>
<th>Total</th><th>Sent</th><th>Received</th>
<th>tcp</th><th>udp</th><th>icmp</th>
<th>http</th><th>mail</th><th>p2p</th><th>ftp</th>
<th>Select</th>
</tr>

<?php
if (!isset($subnet)) // Set this now for total graphs
	$subnet = "0.0.0.0/0";

// Output Total Line
if (!$graphs)
    $url = "<a href=\"#\" onclick=\"window.open('details.php?sensor_id=$sensor_id&amp;ip=$subnet','_blank', 'scrollbars=yes,width=930,height=768,resizable=yes,left=20,top=20')\">";
else
    $url = '<a href="#Total">';

// The first row is counted as the total row; add a dark background every 3 rows
$rowClass = '';  // No dark background added to the total row

echo "<TR$rowClass>";
echo "<TD>".$url."Total</a></TD><TD>$subnet</TD>";
$totals = array_fill_keys(["total","sent","received","tcp","udp","icmp","http","mail","p2p","ftp"], 0);
for($Counter=0; $Counter < pg_num_rows($result); $Counter++)
{
    $r = pg_fetch_array($result, $Counter);
    foreach ($totals as $key => &$val) {
        $val += $r[$key];
    }
}
foreach (["total","sent","received","tcp","udp","icmp","http","mail","p2p","ftp"] as $key) {
    echo fmtb($totals[$key]);
}
echo "<TD><input type='checkbox' class='ip-checkbox' data-ip='Total' checked> 0</TD>";
echo "</TR>\n";

// Output Other Lines
for($Counter=0; $Counter < pg_num_rows($result) && $Counter < $limit; $Counter++)
{
    $r = pg_fetch_array($result, $Counter);

    // Extract the last digit of the IP address
    $ipParts = explode('.', $r['ip']);
    $lastDigit = end($ipParts);

    $rowClass = (($Counter + 2) % 4 == 0) ? ' class="dark-row"' : '';

    if (!$graphs)
        $url = "<a href=\"#\" onclick=\"window.open('details.php?sensor_id=$sensor_id&amp;ip=".$r['ip']."','_blank', 'scrollbars=yes,width=930,height=768,resizable=yes,left=20,top=20')\">";
    else
        $url = '<a href="#' . $r['ip'] . '">';

    echo "<tr$rowClass>";
    echo "<td>" . $url . $r['ip'] . "</a></td><td>" . gethostbyaddr($r['ip']) . "</td>";
    echo fmtb($r['total']).fmtb($r['sent']).fmtb($r['received']).
        fmtb($r['tcp']).fmtb($r['udp']).fmtb($r['icmp']).fmtb($r['http']).fmtb($r['mail']).
        fmtb($r['p2p']).fmtb($r['ftp']);

    $checked = $graphs ? 'checked' : '';
    // Display the last digit of the IP address after the checkbox
    echo "<td><input type='checkbox' class='ip-checkbox' data-ip='".$r['ip']."' $checked> " . $lastDigit . "</td>";
    echo "</tr>\n";
}
echo "</table>";

// Output Total Graph
for($Counter=0, $Total = 0; $Counter < pg_num_rows($result); $Counter++)
	{
	$r = pg_fetch_array($result, $Counter);
	$scale = max($r['txscale'], $scale);
	$scale = max($r['rxscale'], $scale);
	}

if ($subnet == "0.0.0.0/0")
	$total_table = "bd_tx_total_log";
else
	$total_table = "bd_tx_log";

$sn = str_replace("/", "_", $subnet);

echo "<div id='graph-Total'>";
echo "<h3><a name=Total href=details.php?sensor_id=$sensor_id&ip=$subnet> Total - Total of $subnet</a>";
echo "<a style='float:right' href=details.php?sensor_id=$sensor_id&ip=$subnet> Total - Total of $subnet</h3></a>";

echo "<div class='send-graph'>";
// Generate graph URLs based on time mode
if ($time_mode == 'daterange') {
    echo "Send:<br><img src=\"graph.php?ip=$sn&amp;start_date=$start_date&amp;end_date=$end_date&amp;sensor_id=".$sensor_id."&amp;table=$total_table\"><br>";
} else {
    echo "Send:<br><img src=\"graph.php?ip=$sn&amp;interval=$interval&amp;sensor_id=".$sensor_id."&amp;table=$total_table\"><br>";
}
echo '<img src="legend.gif"><br>' . "\n";
echo "</div>";

if ($subnet == "0.0.0.0/0")
    $total_table = "bd_rx_total_log";
else
    $total_table = "bd_rx_log";

echo "<div class='receive-graph'>";
// Generate graph URLs based on time mode
if ($time_mode == 'daterange') {
    echo "Receive:<br><img src=\"graph.php?ip=$sn&amp;start_date=$start_date&amp;end_date=$end_date&amp;sensor_id=".$sensor_id."&amp;table=$total_table\"><br>";
} else {
    echo "Receive:<br><img src=\"graph.php?ip=$sn&amp;interval=$interval&amp;sensor_id=".$sensor_id."&amp;table=$total_table\"><br>";
}
    echo '<img src="legend.gif"><br>' . "\n";
    echo "</div>";

echo "</div>";

//if ($graphs) {
    echo "<!-- DEBUG: Entering graphs loop -->";
    for($Counter=0; $Counter < pg_num_rows($result) && $Counter < $limit; $Counter++)
    {
        $r = pg_fetch_array($result, $Counter);
        $graphId = 'graph-' . str_replace('.', '-', $r['ip']);

        echo "<!-- DEBUG: Processing IP: ".$r['ip']." -->";
        echo "<div id='$graphId'>";
        echo "<h3><a name=".$r['ip']." href=details.php?sensor_name=$sensor_name&ip=".$r['ip'].">";
        if ($r['ip'] == "0.0.0.0")
            echo "Total - Total of all subnets";
        else
            echo $r['ip']." - ".gethostbyaddr($r['ip']);
        echo "</a>";
        echo "<a style='float:right' href=details.php?sensor_name=$sensor_name&ip=".$r['ip'].">";
        if ($r['ip'] == "0.0.0.0")
            echo "Total - Total of all subnets";
        else
            echo $r['ip']." - ".gethostbyaddr($r['ip']);
        echo "</a></h3>";

        echo "<div class='send-graph'>";
        // Generate graph URLs based on time mode
        if ($time_mode == 'daterange') {
            echo "Send:<a style='float:right' href='#top'>[Return to Top]</a><br><img src=\"graph.php?ip=".$r['ip']."&amp;start_date=$start_date&amp;end_date=$end_date&amp;sensor_id=".$sensor_id."&amp;table=bd_tx_log&amp;yscale=".(max($r['txscale'], $r['rxscale']))."\"><br>";
        } else {
            echo "Send:<a style='float:right' href='#top'>[Return to Top]</a><br><img src=\"graph.php?ip=".$r['ip']."&amp;interval=$interval&amp;sensor_id=".$sensor_id."&amp;table=bd_tx_log&amp;yscale=".(max($r['txscale'], $r['rxscale']))."\"><br>";
        }
        echo '<img src="legend.gif"><br>' . "\n";
        echo "</div>";

        echo "<div class='receive-graph'>";
        // Generate graph URLs based on time mode
        if ($time_mode == 'daterange') {
            echo "Receive:<a style='float:right' href='#top'>[Return to Top]</a><br><img src=\"graph.php?ip=".$r['ip']."&amp;start_date=$start_date&amp;end_date=$end_date&amp;sensor_id=".$sensor_id."&amp;table=bd_rx_log&amp;yscale=".(max($r['txscale'], $r['rxscale']))."\"><br>";
        } else {
            echo "Receive:<a style='float:right' href='#top'>[Return to Top]</a><br><img src=\"graph.php?ip=".$r['ip']."&amp;interval=$interval&amp;sensor_id=".$sensor_id."&amp;table=bd_rx_log&amp;yscale=".(max($r['txscale'], $r['rxscale']))."\"><br>";
        }
        echo '<img src="legend.gif"><br>' . "\n";
        echo "</div>";
        echo "</div>";
    }
?>

<!--  Flow graph fills the width -->
<style>
img[src*="graph.php"] {
    width: 100%;
/*  height: auto; # Choose one of the two lines below */
    height: 300px;
    object-fit: fill;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize display based on current time mode
    var timeMode = '<?php echo $time_mode; ?>';

    if (timeMode == 'daterange') {
        document.getElementById('interval_controls').style.display = 'none';
        document.getElementById('daterange_controls').style.display = 'block';
    } else {
        document.getElementById('interval_controls').style.display = 'block';
        document.getElementById('daterange_controls').style.display = 'none';
    }

    // Handle time mode radio button changes
    document.querySelectorAll('input[name="time_mode"]').forEach(function(radio) {
        radio.addEventListener('change', function() {
            var intervalControls = document.getElementById('interval_controls');
            var daterangeControls = document.getElementById('daterange_controls');

            if (this.value == 'daterange') {
                intervalControls.style.display = 'none';
                daterangeControls.style.display = 'block';
            } else {
                intervalControls.style.display = 'block';
                daterangeControls.style.display = 'none';
            }
        });
    });

    // Initialize send graphs as hidden
    document.querySelectorAll('.send-graph').forEach(function(graph) {
        graph.style.display = 'none';
    });

    // Listen for changes in the More checkbox
    var moreCheckbox = document.querySelector('input[name="graphs"]');
    if (moreCheckbox) {
        moreCheckbox.addEventListener('change', function() {
            var isChecked = this.checked;

            // Update the status of all IP checkboxes
            document.querySelectorAll('.ip-checkbox').forEach(function(checkbox) {
                checkbox.checked = isChecked;

                // Trigger the change event for each checkbox to update the chart display
                var ip = checkbox.getAttribute('data-ip');
                var graphContainer = document.getElementById('graph-' + ip.replace(/\./g, '-'));
                if (graphContainer) {
                    graphContainer.style.display = isChecked ? 'block' : 'none';
                }
            });
        });
    }

    // Send master switch
    document.getElementById('toggle-send').addEventListener('change', function() {
        var sendGraphs = document.querySelectorAll('.send-graph');
        var isChecked = this.checked;

        sendGraphs.forEach(function(graph) {
            graph.style.display = isChecked ? 'block' : 'none';
        });
    });

    // Receive master switch
    document.getElementById('toggle-receive').addEventListener('change', function() {
        var receiveGraphs = document.querySelectorAll('.receive-graph');
        var isChecked = this.checked;

        receiveGraphs.forEach(function(graph) {
            graph.style.display = isChecked ? 'block' : 'none';
        });
    });

    // Modified IP checkbox event handler
    document.querySelectorAll('.ip-checkbox').forEach(function(checkbox) {
        checkbox.addEventListener('change', function() {
            var ip = this.getAttribute('data-ip');
            var graphContainer = document.getElementById('graph-' + ip.replace(/\./g, '-'));

            if (graphContainer) {
                if (this.checked) {
                    // Check send and receive states
                    var sendChecked = document.getElementById('toggle-send').checked;
                    var recvChecked = document.getElementById('toggle-receive').checked;

                    // Show only the appropriate graphs
                    var sendGraph = graphContainer.querySelector('.send-graph');
                    var recvGraph = graphContainer.querySelector('.receive-graph');

                    if (sendGraph) {
                        sendGraph.style.display = sendChecked ? 'block' : 'none';
                    }
                    if (recvGraph) {
                        recvGraph.style.display = recvChecked ? 'block' : 'none';
                    }

                    // Only show container if at least one graph should be visible
                    graphContainer.style.display = (sendChecked || recvChecked) ? 'block' : 'none';
                } else {
                    graphContainer.style.display = 'none';
                }
            }
        });
    });

    // Initialize: Hide all unselected charts and apply send/recv states
    document.querySelectorAll('.ip-checkbox').forEach(function(checkbox) {
        if (!checkbox.checked) {
            var ip = checkbox.getAttribute('data-ip');
            var graphContainer = document.getElementById('graph-' + ip.replace(/\./g, '-'));
            if (graphContainer) {
                graphContainer.style.display = 'none';
            }
        } else {
            // Apply current send/recv states to checked boxes
            var event = new Event('change');
            checkbox.dispatchEvent(event);
        }
    });
});
</script>
<?php
include('footer.php');
?>
