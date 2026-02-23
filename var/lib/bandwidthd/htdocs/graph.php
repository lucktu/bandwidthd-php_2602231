<?php
require("include.php");
error_reporting(0); #Set the error level to 0, which means no error messages are printed

$db = ConnectDb();
$SentPeak = 0;
$TotalSent = 0;
$TotalPackets = 0;
$YMax = 0;

// Get parameters FIRST

if (isset($_GET['width']) && is_numeric($_GET['width']))
    $width = $_GET['width'];
else
    $width = DFLT_WIDTH;

if (isset($_GET['height']) && is_numeric($_GET['height']))
    $height = $_GET['height'];
else
    $height = DFLT_HEIGHT;

if (isset($_GET['interval']) && is_numeric($_GET['interval']))
    $interval = $_GET['interval'];
else
    $interval = DFLT_INTERVAL;

if (isset($_GET['ip']))
    {
    $ip = $_GET['ip'];
    #optionall call using underscore instead of slash to seperate subnet from bits
    $ip = str_replace("_", "/", $ip);
    $ip = pg_escape_string($ip);
    }
else
    exit(1);

if (isset($_GET['sensor_id']) && is_numeric($_GET['sensor_id']))
    $sensor_id = $_GET['sensor_id'];
else
    exit(1);

// NOW handle date range parameters after interval is set
if (isset($_GET['start_date']) && !empty($_GET['start_date'])) {
    $timestamp = strtotime($_GET['start_date'] . ' 00:00:00');
} else {
    $timestamp = time() - $interval;
}

if (isset($_GET['end_date']) && !empty($_GET['end_date'])) {
    $interval = strtotime($_GET['end_date'] . ' 23:59:59') - $timestamp;
}

switch ($_GET['table']) {
    case 'bd_rx_total_log':
    case 'bd_tx_total_log':
    case 'bd_rx_log':
    case 'bd_tx_log':
        $table = $_GET['table'];
    break;
    default: /* invalid value in table argument! */
        exit(1);
    break;
}

if (isset($_GET['yscale']))
    $yscale = $_GET['yscale'];

// Returns x location of any given timestamp
function ts2x($ts)
    {
    global $timestamp, $width, $interval;
    return(($ts-$timestamp)*(($width-XOFFSET)*0.95 / $interval) + XOFFSET);
    }

// If we have multiple IP's in a result set we need to total the average of each IP's samples
function AverageAndAccumulate()
    {
    global $Count, $total, $icmp, $udp, $tcp, $ftp, $http, $mail, $p2p, $YMax;
    global $a_total, $a_icmp, $a_udp, $a_tcp, $a_ftp, $a_http, $a_mail, $a_p2p;

    foreach ($Count as $key => $number)
        {
        $total[$key] /= $number;
        $icmp[$key] /= $number;
        $udp[$key] /= $number;
        $tcp[$key] /= $number;
        $ftp[$key] /= $number;
        $http[$key] /= $number;
        $mail[$key] /= $number;
        $p2p[$key] /= $number;
        }

    foreach ($Count as $key => $number)
        {
        $a_total[$key] += $total[$key];
        $a_icmp[$key] += $icmp[$key];
        $a_udp[$key] += $udp[$key];
        $a_tcp[$key] += $tcp[$key];
        $a_ftp[$key] += $ftp[$key];
        $a_http[$key] += $http[$key];
        $a_mail[$key] += $mail[$key];
        $a_p2p[$key] += $p2p[$key];

        if ($a_total[$key] > $YMax)
            $YMax = $a_total[$key];
        }

    unset($GLOBALS['total'], $GLOBALS['icmp'], $GLOBALS['udp'], $GLOBALS['tcp'], $GLOBALS['ftp'], $GLOBALS['http'], $GLOBALS['mail'], $GLOBALS['p2p'], $GLOBALS['Count']);

    $total = array();
    $icmp = array();
    $udp = array();
    $tcp = array();
    $ftp = array();
    $http = array();
    $mail = array();
    $p2p = array();
    $Count = array();
    }

$total = array();
$icmp = array();
$udp = array();
$tcp = array();
$ftp = array();
$http = array();
$mail = array();
$p2p = array();
$Count = array();

// Accumulator
$a_total = array();
$a_icmp = array();
$a_udp = array();
$a_tcp = array();
$a_ftp = array();
$a_http = array();
$a_mail = array();
$a_p2p = array();

$sql = "select *, extract(epoch from timestamp) as ts from $table where ip <<= '$ip' and sensor_id = '$sensor_id' and timestamp > $timestamp::abstime and timestamp < ".($timestamp+$interval)."::abstime order by ip;";
//echo $sql."<br>"; exit(1);
$result = pg_query($sql);

// The SQL statement pulls the data out of the database ordered by IP address, that way we can average each
// datapoint for each IP address to provide smoothing and then toss the smoothed value into the accumulator
// to provide accurate total traffic rate.

while ($row = pg_fetch_array($result))
    {
    if ($row['ip'] != $last_ip)
        {
        AverageAndAccumulate();
        $last_ip = $row['ip'];
        }

    $x = ($row['ts']-$timestamp)*(($width-XOFFSET)*0.95/$interval)+XOFFSET;
    $xint = (int) $x;

    //echo "xint: ".$xint."<br>";
    $Count[$xint]++;

    if ($row['total']/$row['sample_duration'] > $SentPeak)
        $SentPeak = $row['total']/$row['sample_duration'];
    $TotalSent += $row['total'];
    $TotalPackets += $row['packet_count'];
    $total[$xint] += $row['total']/$row['sample_duration'];
    $icmp[$xint] += $row['icmp']/$row['sample_duration'];
    $udp[$xint] += $row['udp']/$row['sample_duration'];
    $tcp[$xint] += $row['tcp']/$row['sample_duration'];
    $ftp[$xint] += $row['ftp']/$row['sample_duration'];
    $http[$xint] += $row['http']/$row['sample_duration'];
    $mail[$xint] += $row['mail']/$row['sample_duration'];
    $p2p[$xint] += $row['p2p']/$row['sample_duration'];
    }

// One more time for the last IP
AverageAndAccumulate();

// Pull the data out of Accumulator
$total = $a_total;
$icmp = $a_icmp;
$udp = $a_udp;
$tcp = $a_tcp;
$ftp = $a_ftp;
$http = $a_http;
$mail = $a_mail;
$p2p = $a_p2p;

$YMax += $YMax*0.05;    // Add an extra 5%

// if a y scale was specified override YMax
if (isset($yscale))
    $YMax = $yscale/8;

// Calculate month label reduction factor
$total_months = ceil($interval / (24*60*60*30)); // Approximate months in interval
$month_label_factor = 1; // Show all labels by default
if ($total_months > 36) {
    // Calculate reduction factor to show max 36 labels
    $month_label_factor = ceil($total_months / 36);
}

// Plot the data
header("Content-type: image/png");

// Not enough data to graph
if ($YMax <= 1.1)
    {
    $im = imagecreate($width, 20);
    $white = imagecolorallocate($im, 255, 255, 255);
    $black  = ImageColorAllocate($im, 0, 0, 0);
    ImageString($im, 2, $width/2,  0, "No Data", $black);
    imagepng($im);
    imagedestroy($im);
    exit(0);
    }

$im = imagecreate($width, $height);
$white = imagecolorallocate($im, 255, 255, 255);
$yellow = ImageColorAllocate($im, 255, 215, 0);
$purple = ImageColorAllocate($im, 255, 0, 255);
$green  = ImageColorAllocate($im, 0, 255, 0);
$blue   = ImageColorAllocate($im, 0, 0, 255);
$orange = ImageColorAllocate($im, 255, 128, 0);
$darkgreen  = ImageColorAllocate($im, 0, 100, 0);
$brown  = ImageColorAllocate($im, 128, 0, 0);
$red    = ImageColorAllocate($im, 255, 0, 0);
$black  = ImageColorAllocate($im, 0, 0, 0);

for($Counter=XOFFSET+1; $Counter < $width; $Counter++)
    {
    if (isset($total[$Counter]))
        {
        // Convert the bytes/sec to y coords
        $total[$Counter] = ($total[$Counter]*($height-YOFFSET))/$YMax;
        $tcp[$Counter] = ($tcp[$Counter]*($height-YOFFSET))/$YMax;
        $ftp[$Counter] = ($ftp[$Counter]*($height-YOFFSET))/$YMax;
        $http[$Counter] = ($http[$Counter]*($height-YOFFSET))/$YMax;
        $mail[$Counter] = ($mail[$Counter]*($height-YOFFSET))/$YMax;
        $p2p[$Counter] = ($p2p[$Counter]*($height-YOFFSET))/$YMax;
        $udp[$Counter] = ($udp[$Counter]*($height-YOFFSET))/$YMax;
        $icmp[$Counter] = ($icmp[$Counter]*($height-YOFFSET))/$YMax;

        // Stack 'em up!
        // Total is stacked from the bottom
        // Icmp is on the bottom too
        // Udp is stacked on top of icmp
        $udp[$Counter] += $icmp[$Counter];
        // TCP and p2p are stacked on top of Udp
        $tcp[$Counter] += $udp[$Counter];
        $p2p[$Counter] += $udp[$Counter];
         // Http is stacked on top of p2p
        $http[$Counter] += $p2p[$Counter];
        // Mail is stacked on top of http
        $mail[$Counter] += $http[$Counter];
        // Ftp is stacked on top of mail
        $ftp[$Counter] += $mail[$Counter];

        // Plot them!
        //echo "$Counter:".$Counter." (h-y)-t:".($height-YOFFSET) - $total[$Counter]." h-YO-1:".$height-YOFFSET-1;
        ImageLine($im, $Counter, ($height-YOFFSET) - $total[$Counter], $Counter, $height-YOFFSET-1, $yellow);
        ImageLine($im, $Counter, ($height-YOFFSET) - $icmp[$Counter], $Counter, $height-YOFFSET-1, $red);
        ImageLine($im, $Counter, ($height-YOFFSET) - $udp[$Counter], $Counter, ($height-YOFFSET) - $icmp[$Counter] - 1, $brown);
        ImageLine($im, $Counter, ($height-YOFFSET) - $tcp[$Counter], $Counter, ($height-YOFFSET) - $udp[$Counter] - 1, $green);
        ImageLine($im, $Counter, ($height-YOFFSET) - $p2p[$Counter], $Counter, ($height-YOFFSET) - $udp[$Counter] - 1, $purple);
        ImageLine($im, $Counter, ($height-YOFFSET) - $http[$Counter], $Counter, ($height-YOFFSET) - $p2p[$Counter] - 1, $blue);
        ImageLine($im, $Counter, ($height-YOFFSET) - $mail[$Counter], $Counter, ($height-YOFFSET) - $http[$Counter] - 1, $darkgreen);
        ImageLine($im, $Counter, ($height-YOFFSET) - $ftp[$Counter], $Counter, ($height-YOFFSET) - $mail[$Counter] - 1, $orange);
        }
//    else
//        echo $Counter." not set<br>";
    }

// Margin Text
if ($SentPeak < 1024/8)
    $txtPeakSendRate = sprintf("Peak Rate: %.1f KB/s", $SentPeak*8);
else if ($SentPeak < (1024*1024)/8)
    $txtPeakSendRate = sprintf("Peak Rate: %.1f MB/s", ($SentPeak*8)/1024.0);
else
    $txtPeakSendRate = sprintf("Peak Rate: %.1f GB/s", ($SentPeak*8)/(1024.0*1024.0));

if ($TotalSent < 1024)
    $txtTotalSent = sprintf("Total %.1f KB", $TotalSent);
else if ($TotalSent < 1024*1024)
    $txtTotalSent = sprintf("Total %.1f MB", $TotalSent/1024.0);
else
    $txtTotalSent = sprintf("Total %.1f GB", $TotalSent/(1024.0*1024.0));

$avgRate = ($TotalSent*977.0)/$interval; // bytes per second
if ($avgRate < 1024)
    $txtAvgRate = sprintf("Avg Rate: %.1f B/s", $avgRate);
else if ($avgRate < 1024*1024)
    $txtAvgRate = sprintf("Avg Rate: %.1f KB/s", $avgRate/1024.0);
else
    $txtAvgRate = sprintf("Avg Rate: %.1f MB/s", $avgRate/(1024.0*1024.0));

ImageString($im, 2, XOFFSET+5,  $height-20, $txtTotalSent, $black);
ImageString($im, 2, ($width-XOFFSET)/3+XOFFSET+50,  $height-20, $txtAvgRate, $black);
ImageString($im, 2, 2*(($width-XOFFSET)/3)+XOFFSET+100,  $height-20, $txtPeakSendRate, $black);

// Draw X Axis

ImageLine($im, 0, $height-YOFFSET, $width, $height-YOFFSET, $black);

// Day/Month Seperator bars

if ((24*60*60*($width-XOFFSET))/$interval > ($width-XOFFSET)/10)
    {
    $ts = getdate($timestamp);
    $MarkTime = mktime(0, 0, 0, $ts['mon'], $ts['mday'], $ts['year']);

    $x = ts2x($MarkTime);
    while ($x < XOFFSET)
        {
        $MarkTime += (24*60*60);
        $x = ts2x($MarkTime);
        }

    while ($x < ($width-10))
        {
        // Day Lines
        ImageLine($im, $x, 0, $x, $height-YOFFSET, $black);
        ImageLine($im, $x+1, 0, $x+1, $height-YOFFSET, $black);

        $txtDate = strftime("%a, %b %d", $MarkTime);
        ImageString($im, 2, $x-30,  $height-YOFFSET+10, $txtDate, $black);

        // Calculate Next x
        $MarkTime += (24*60*60);
        $x = ts2x($MarkTime);
        }
    }
else if ((24*60*60*30*($width-XOFFSET))/$interval > ($width-XOFFSET)/10)
    {
    // Monthly Bars
    $ts = getdate($timestamp);
    $month = $ts['mon'];
    $MarkTime = mktime(0, 0, 0, $month, 1, $ts['year']);
    $month_counter = 0;

    $x = ts2x($MarkTime);
    while ($x < XOFFSET)
        {
        $month++;
        $MarkTime = mktime(0, 0, 0, $month, 1, $ts['year']);
        $x = ts2x($MarkTime);
        }

    while ($x < ($width-10))
        {
        // Day Lines - Always draw vertical lines
        ImageLine($im, $x, 0, $x, $height-YOFFSET, $black);
        ImageLine($im, $x+1, 0, $x+1, $height-YOFFSET, $black);

        // Apply month label reduction - only show every nth month label
        if ($month_counter % $month_label_factor == 0) {
            $txtDate = strftime("%b, %Y", $MarkTime);
            ImageString($im, 2, $x-25,  $height-YOFFSET+10, $txtDate, $black);
        }

        // Calculate Next x
        $month++;
        $MarkTime = mktime(0, 0, 0, $month, 1, $ts['year']);
        $x = ts2x($MarkTime);
        $month_counter ++;
        }
    }
else
    {
    // Year Bars
    $ts = getdate($timestamp);
    $year = $ts['year'];
    $MarkTime = mktime(0, 0, 0, 1, 1, $year);

    $x = ts2x($MarkTime);
    while ($x < XOFFSET)
        {
        $year++;
        $MarkTime = mktime(0, 0, 0, 1, 1, $year);
        $x = ts2x($MarkTime);
        }

    while ($x < ($width-10))
        {
        // Year Lines - Use thick vertical lines like months
        ImageLine($im, $x, 0, $x, $height-YOFFSET, $black);
        ImageLine($im, $x+1, 0, $x+1, $height-YOFFSET, $black);

        // Add tick marks above year labels (like months)
        ImageLine($im, $x, $height-YOFFSET-0, $x, $height-YOFFSET+10, $black);

        // Display year text rotated 90 degrees counterclockwise (reads from bottom to top)
        $txtDate = strftime("%Y", $MarkTime);
        $year_chars = str_split($txtDate);
        $char_height = 10; // Spacing between characters
        $start_y = 8; // Start position from top

        // Display characters in normal order but positioned to read from bottom to top
        for ($i = 0; $i < count($year_chars); $i++) {
            // Position so first character is at bottom, last at top
            $y_pos = $start_y + ($i * $char_height);
            ImageString($im, 2, $x+5, $y_pos, $year_chars[$i], $black);
        }

        // Calculate Next x
        $year++;
        $MarkTime = mktime(0, 0, 0, 1, 1, $year);
        $x = ts2x($MarkTime);
        }
    }

// Draw Major Tick Marks
if ((6*60*60*($width-XOFFSET))/$interval > 10) // pixels per 6 hours is more than 2
    $MarkTimeStep = 6*60*60; // Major ticks are 6 hours
else if ((24*60*60*($width-XOFFSET))/$interval > 10)
    $MarkTimeStep = 24*60*60; // Major ticks are 24 hours;
else if ((24*60*60*30*($width-XOFFSET))/$interval > 10)
    {
    // Major tick marks are months
    $MarkTimeStep = 0; // Skip the standard way of drawing major tick marks below

    $ts = getdate($timestamp);
    $month = $ts['mon'];
    $MarkTime = mktime(0, 0, 0, $month, 1, $ts['year']);
    $month_counter = 0;

    $x = ts2x($MarkTime);
    while ($x < XOFFSET)
        {
        $month++;
        $MarkTime = mktime(0, 0, 0, $month, 1, $ts['year']);
        $x = ts2x($MarkTime);
        }

    while ($x < ($width-10))
        {
        // Day Lines
        $date = getdate($MarkTime);
        ImageLine($im, $x, $height-YOFFSET-0, $x, $height-YOFFSET+10, $black);

        // Apply month label reduction for major tick marks
        if ($month_counter % $month_label_factor == 0) {
            $txtDate = strftime("%b", $MarkTime);
            ImageString($im, 2, $x-5,  $height-YOFFSET+10, $txtDate, $black);
        }

        // Calculate Next x
        $month++;
        $MarkTime = mktime(0, 0, 0, $month, 1, $ts['year']);
        $x = ts2x($MarkTime);
        $month_counter++;
        }
    }
else
    $MarkTimeStep = 0; // Skip Major Tick Marks

if ($MarkTimeStep)
    {
    $ts = getdate($timestamp);
    $MarkTime = mktime(0, 0, 0, $ts['mon'], $ts['mday'], $ts['year']);
    $x = ts2x($MarkTime);

    while ($x < ($width-10))
        {
        if ($x > XOFFSET)
            {
            ImageLine($im, $x, $height-YOFFSET-0, $x, $height-YOFFSET+10, $black);
            }
        $MarkTime += $MarkTimeStep;
        $x = ts2x($MarkTime);
        }
    }

// Draw Minor Tick marks
if ((60*60*($width-XOFFSET))/$interval > 4) // pixels per hour is more than 2
    $MarkTimeStep = 60*60;  // Minor ticks are 1 hour
else if ((6*60*60*($width-XOFFSET))/$interval > 4)
    $MarkTimeStep = 6*60*60; // Minor ticks are 6 hours
else if ((24*60*60*($width-XOFFSET))/$interval > 4)
    $MarkTimeStep = 24*60*60;
else
    $MarkTimeStep = 0; // Skip minor tick marks

if ($MarkTimeStep)
    {
    $ts = getdate($timestamp);
    $MarkTime = mktime(0, 0, 0, $ts['mon'], $ts['mday'], $ts['year']);
    $x = ts2x($MarkTime);

    while ($x < ($width-10))
        {
        if ($x > XOFFSET)
            {
            ImageLine($im, $x, $height-YOFFSET, $x, $height-YOFFSET+5, $black);
            }
        $MarkTime += $MarkTimeStep;
        $x = ts2x($MarkTime);
        }
    }

// Draw Y Axis
ImageLine($im, XOFFSET, 0, XOFFSET, $height, $black);

$YLegend = 'K';
$Divisor = 1;
if ($YMax*8 > 1024*2)
    {
    $Divisor = 1024;    // Display in m
    $YLegend = 'M';
    }

if ($YMax*8 > 1024*1024*2)
    {
    $Divisor = 1024*1024; // Display in g
    $YLegend = 'G';
    }

if ($YMax*8 > 1024*1024*1024*2)
    {
    $Divisor = 1024*1024*1024; // Display in t
    $YLegend = 'T';
    }

if ($height/10 > 15)
    $YMarks = 10;
else
    $YMarks = 5;

$YStep = $YMax/$YMarks;
if ($YStep < 1)
    $YStep=1;
$YTic=$YStep;

while ($YTic <= ($YMax - $YMax/$YMarks))
    {
    $y = ($height-YOFFSET)-(($YTic*($height-YOFFSET))/$YMax);
    ImageLine($im, XOFFSET, $y, $width, $y, $black);
    $txtYLegend = sprintf("%4.1f %sB/s", (1.0*$YTic)/$Divisor, $YLegend);
    ImageString($im, 2, 3, $y-7, $txtYLegend, $black);
    $YTic += $YStep;
    }

imagepng($im);
imagedestroy($im);

?>
