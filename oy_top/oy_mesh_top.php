<?php
if (php_sapi_name()!=="cli") exit;

$oy_punish_permit = array(
    "OY_PUNISH_PUSH_INVALID",
    "OY_PUNISH_DEPOSIT_INVALID",
    "OY_PUNISH_FULFILL_INVALID",
    "OY_PUNISH_BLACKLIST_RETURN",
    "OY_PUNISH_LATENCY_DECLINE",
    "OY_PUNISH_RECOMMEND_SELF",
    "OY_PUNISH_CONNECT_FAIL",
    "OY_PUNISH_BLACKLIST_RETURN",
    "OY_PUNISH_FALSE_AFFIRM",
    "OY_PUNISH_REJECT_RETURN",
    "OY_PUNISH_LATENCY_DECLINE",
    "OY_PUNISH_DATA_INCOHERENT",
    "OY_PUNISH_LATENCY_NONE",
    "OY_PUNISH_SIGN_NONE",
    "OY_PUNISH_SIGN_INVALID",
    "OY_PUNISH_SIGN_FAIL",
    "OY_PUNISH_LATENCY_BREACH",
    "OY_PUNISH_LATENCY_WEAK",
    "OY_PUNISH_LATENCY_INVALID",
    "OY_PUNISH_PASSPORT_MISMATCH",
    "OY_PUNISH_PASSPORT_ALREADY",
    "OY_PUNISH_LOGIC_BREACH",
    "OY_PUNISH_LATENCY_LAG",
    "OY_PUNISH_WARM_LAG",
    "OY_PUNISH_DATA_BREACH",
    "OY_PUNISH_DATA_INVALID",
    "OY_PUNISH_MESH_FLOW");

function oy_flow_format($oy_bytes, $oy_precision = 2) {
    $oy_base = log($oy_bytes, 1000);
    $oy_suffixes = array('', 'kbps', 'mbps', 'gbps', 'tbps');

    return round(pow(1000, $oy_base - floor($oy_base)), $oy_precision)." ".$oy_suffixes[intval(floor($oy_base))];
}

if ($fh = opendir("/dev/shm/oy_peers")) {
    while (($oy_file = readdir($fh))!==false) {
        if (preg_match('/\.peer$/i', $oy_file)&&(time()-filemtime("/dev/shm/oy_peers/".$oy_file))>=20) unlink("/dev/shm/oy_peers/".$oy_file);
    }
}
closedir($fh);

//[0] is nodes, [1] is peer relationships [2] is stats
$oy_mesh_top = [[], [], [["oy_stat_avg_latency"=>[], "oy_stat_avg_peership"=>[], "oy_stat_mesh_size"=>0, "oy_stat_mesh_flow"=>0], []]];
$oy_mesh_keep = [];
$oy_mesh_file_array = glob("/dev/shm/oy_peers/*.peer");
if (count($oy_mesh_file_array)<=2) {
    $fh = fopen("/var/www/html/oy_mesh_top.json", "w");
    fwrite($fh, json_encode($oy_mesh_top));
    fclose($fh);
    exit;
}
foreach ($oy_mesh_file_array as $oy_mesh_file_unique) {
    $oy_mesh_data = json_decode(file_get_contents($oy_mesh_file_unique), true);
    var_dump($oy_mesh_data);
    $oy_mesh_data[0] = sha1($oy_mesh_data[0]);
    $oy_mesh_top[0][] = $oy_mesh_data[0];
    $oy_mesh_keep[] = $oy_mesh_data;
}
//TODO add type safety since JSON comes from untrusted sources
$oy_time_local = time();
$oy_punish_track = [];
foreach ($oy_mesh_keep as $oy_mesh_data) {
    foreach ($oy_mesh_data[1] as $oy_mesh_peer => $oy_peer_data) {
        $oy_mesh_peer = sha1($oy_mesh_peer);
        if (in_array($oy_mesh_peer, $oy_mesh_top[0])) {
            $oy_peer_add = true;
            foreach ($oy_mesh_top[1] as $oy_mesh_unique) {
                if ($oy_mesh_unique[0]===$oy_mesh_peer&&$oy_mesh_unique[1]===$oy_mesh_data[0]) {
                    $oy_peer_add = false;
                    break;
                }
            }
            if ($oy_peer_add===true) $oy_mesh_top[1][] = [$oy_mesh_data[0], $oy_mesh_peer];
            $oy_mesh_top[2][0]["oy_stat_avg_latency"][] = ($oy_peer_data[3]>0)?$oy_peer_data[3]:0;
            $oy_mesh_top[2][0]["oy_stat_avg_peership"][] = $oy_time_local-$oy_peer_data[0];
            $oy_mesh_top[2][0]["oy_stat_mesh_flow"] += ($oy_peer_data[5]+$oy_peer_data[7])/2;
        }
    }
    foreach ($oy_mesh_data[2] as $oy_mesh_blacklist) {
        foreach ($oy_mesh_blacklist[3] as $oy_punish_unique) {
            if (!in_array($oy_punish_unique, $oy_punish_permit)) continue;
            if (!isset($oy_punish_track[$oy_punish_unique])) $oy_punish_track[$oy_punish_unique] = 1;
            else $oy_punish_track[$oy_punish_unique]++;
        }
    }
}
if (count($oy_mesh_top[2][0]["oy_stat_avg_latency"])===0) $oy_mesh_top[2][0]["oy_stat_avg_latency"] = 0;
else $oy_mesh_top[2][0]["oy_stat_avg_latency"] = round(array_sum($oy_mesh_top[2][0]["oy_stat_avg_latency"])/count($oy_mesh_top[2][0]["oy_stat_avg_latency"]), 4);
if (count($oy_mesh_top[2][0]["oy_stat_avg_peership"])===0) $oy_mesh_top[2][0]["oy_stat_avg_peership"] = 0;
else $oy_mesh_top[2][0]["oy_stat_avg_peership"] = round(((array_sum($oy_mesh_top[2][0]["oy_stat_avg_peership"])/count($oy_mesh_top[2][0]["oy_stat_avg_peership"])/60)), 2);
$oy_mesh_top[2][0]["oy_stat_mesh_size"] = count($oy_mesh_top[0]);
$oy_mesh_top[2][0]["oy_stat_mesh_flow"] = oy_flow_format($oy_mesh_top[2][0]["oy_stat_mesh_flow"]);
arsort($oy_punish_track);
$oy_punish_total = array_sum($oy_punish_track);
foreach ($oy_punish_track as $oy_punish_type => $oy_punish_count) {
    $oy_mesh_top[2][1][] = [str_replace("OY_PUNISH_", "", $oy_punish_type), $oy_punish_count, round(($oy_punish_count/$oy_punish_total)*100, 1)];
}

$fh = fopen("/var/www/html/oy_mesh_top.json", "w");
fwrite($fh, json_encode($oy_mesh_top));
fclose($fh);