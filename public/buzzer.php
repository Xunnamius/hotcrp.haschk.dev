<?php
// buzzer.php -- HotCRP buzzer page
// Copyright (c) 2006-2018 Eddie Kohler; see LICENSE.
// First buzzer version by Nickolai B. Zeldovich

require_once("src/initweb.php");
$show_papers = true;

// kiosk mode
if ($Me->privChair) {
    $kiosks = (array) ($Conf->setting_json("__tracker_kiosk") ? : array());
    uasort($kiosks, function ($a, $b) {
        return $a->update_at - $b->update_at;
    });
    $kchange = false;
    // delete old kiosks
    while (count($kiosks)
           && (count($kiosks) > 12 || current($kiosks)->update_at <= $Now - 172800)) {
        array_shift($kiosks);
        $kchange = true;
        reset($kiosks);
    }
    // look for new kiosks
    $kiosk_keys = array(null, null);
    foreach ($kiosks as $k => $kj)
        if ($kj->update_at >= $Now - 7200)
            $kiosk_keys[$kj->show_papers ? 1 : 0] = $k;
    for ($i = 0; $i <= 1; ++$i)
        if (!$kiosk_keys[$i]) {
            $key = hotcrp_random_password();
            $kiosks[$key] = (object) array("update_at" => $Now, "show_papers" => !!$i);
            $kiosk_keys[$i] = $kchange = $key;
        }
    // save kiosks
    if ($kchange)
        $Conf->save_setting("__tracker_kiosk", 1, $kiosks);
}

if ($Me->privChair && $Qreq->signout_to_kiosk && $Qreq->post_ok()) {
    LoginHelper::logout(false);
    $Me->set_capability("tracker_kiosk", $kiosk_keys[$Qreq->buzzer_showpapers ? 1 : 0]);
    SelfHref::redirect($Qreq);
}

function kiosk_lookup($key) {
    global $Conf, $Now;
    $kiosks = (array) ($Conf->setting_json("__tracker_kiosk") ? : array());
    if (isset($kiosks[$key]) && $kiosks[$key]->update_at >= $Now - 604800)
        return $kiosks[$key];
    return null;
}

$kiosk = null;
if (!$Me->has_email()
    && !$Me->capability("tracker_kiosk")
    && ($key = Navigation::path_component(0))
    && ($kiosk = kiosk_lookup($key)))
    $Me->set_capability("tracker_kiosk", $key);
else if (($key = $Me->capability("tracker_kiosk")))
    $kiosk = kiosk_lookup($key);

if ($kiosk) {
    $Me->tracker_kiosk_state = $kiosk->show_papers ? 2 : 1;
    $show_papers = $kiosk->show_papers;
}

// user
if (!$Me->isPC && !$Me->tracker_kiosk_state)
    $Me->escape();


$Conf->header("Discussion status", "buzzer", ["action_bar" => false, "class" => "hide-tracker"]);

echo '<div id="trackertable" class="demargin" style="margin-top:1em"></div>';
echo "<audio id=\"buzzersound\"><source src=\"", Ht::$img_base, "buzzer.mp3\"></audio>";

echo Ht::form(hoturl_post("buzzer"));
echo '<table style="margin-top:3em"><tr>';

// mute button
echo '<td><button id="trackertable_mute" type="button" class="btn foldc" style="padding-bottom:5px">
<svg id="soundicon" class="fn" width="1.5em" height="1.5em" viewBox="0 0 75 75" style="position:relative;bottom:-3px">
 <polygon points="39.389,13.769 22.235,28.606 6,28.606 6,47.699 21.989,47.699 39.389,62.75 39.389,13.769" style="stroke:#111111;stroke-width:5;stroke-linejoin:round;fill:#111111;" />
 <path d="M 48.128,49.03 C 50.057,45.934 51.19,42.291 51.19,38.377 C 51.19,34.399 50.026,30.703 48.043,27.577" style="fill:none;stroke:#111111;stroke-width:5;stroke-linecap:round"/>
 <path d="M 55.082,20.537 C 58.777,25.523 60.966,31.694 60.966,38.377 C 60.966,44.998 58.815,51.115 55.178,56.076" style="fill:none;stroke:#111111;stroke-width:5;stroke-linecap:round"/>
 <path d="M 61.71,62.611 C 66.977,55.945 70.128,47.531 70.128,38.378 C 70.128,29.161 66.936,20.696 61.609,14.01" style="fill:none;stroke:#111111;stroke-width:5;stroke-linecap:round"/>
</svg><svg id="muteicon" class="fx" width="1.5em" height="1.5em" viewBox="0 0 75 75" style="position:relative;bottom:-3px">
 <polygon points="39.389,13.769 22.235,28.606 6,28.606 6,47.699 21.989,47.699 39.389,62.75 39.389,13.769" style="stroke:#111111;stroke-width:5;stroke-linejoin:round;fill:#111111;" />
 <path d="M 48.651772,50.269646 69.395223,25.971024" style="fill:none;stroke:#111111;stroke-width:5;stroke-linecap:round"/>
 <path d="M 69.395223,50.269646 48.651772,25.971024" style="fill:none;stroke:#111111;stroke-width:5;stroke-linecap:round" />
</svg></button></td>';
//echo '<span class="hidden fhn_ib">&nbsp;Mute</span>';
//echo '<span class="hidden fhx_ib">&nbsp;Unmute</span></button></td>';

// show-papers
if ($Me->has_database_account()) {
    echo '<td style="padding-left:2em">',
        Ht::checkbox("buzzer_showpapers", 1, $show_papers, ["id" => "trackertable_showpapers"]),
        "&nbsp;", Ht::label("Show papers"), '</td>';
}

// kiosk mode
if ($Me->privChair) {
    echo '<td style="padding-left:2em">',
        Ht::button("Kiosk mode", ["id" => "trackertable_kioskmode"]),
        '</td>';
}

// header and script
$buzzer_status = ["status" => "open", "muted" => false,
                  "show_papers" => $show_papers];
$no_discussion = '<div class="remargin-left remargin-right"><h2>No discussion</h2>';
if ($Me->privChair) {
    $no_discussion .= '<p>To start a discussion, <a href="' . hoturl("search") . '">search</a> for a list, go to a paper in that list, and use the “&#9759;” button.</p>';
    $buzzer_status["kiosk_urls"] = [hoturl_absolute("buzzer", ["__PATH__" => $kiosk_keys[0]]),
        hoturl_absolute("buzzer", ["__PATH__" => $kiosk_keys[1]])];
} else if ($kiosk)
    $buzzer_status["is_kiosk"] = true;
$buzzer_status["no_discussion"] = $no_discussion . '</div>';
echo Ht::unstash();
echo $Conf->make_script_file("scripts/buzzer.js");
echo Ht::unstash_script('start_buzzer_page(' . json_encode_browser($buzzer_status) . ')');

echo "</tr></table></form>\n";
$Conf->footer();
