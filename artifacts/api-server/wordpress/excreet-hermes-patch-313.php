<?php
/**
 * Plugin Name: Excreet Patch 313 — Final Completions
 * Description: Affiliate upgrade, dashboard digest, ministry session history.
 *
 *   A — Affiliate Area visual upgrade (page 491)
 *   B — Member Dashboard weekly digest (page 772)
 *   C — Ministry session history panel (page 231)
 *
 * Version: 3.1.3c
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ─────────────────────────────────────────────────────────────────────────── */
/* A — AFFILIATE AREA VISUAL UPGRADE  (page 491)                              */
/* ─────────────────────────────────────────────────────────────────────────── */

add_action( 'wp_head',   'excreet_313_affiliate_styles', 99 );
add_action( 'wp_footer', 'excreet_313_affiliate_js',     20 );

function excreet_313_affiliate_styles(): void {
    if ( ! is_page( 491 ) ) {
        return;
    }
    echo '<style id="ex313-aff-css">
/* ── Hero header ── */
.ex313-hero {
    background: linear-gradient(135deg, #1a0535 0%, #3D1060 50%, #1a0535 100%);
    border: 1px solid rgba(201,168,76,0.3);
    border-radius: 16px;
    padding: 2rem 2rem 1.5rem;
    margin-bottom: 1.5rem;
    position: relative;
    overflow: hidden;
}
.ex313-hero::before {
    content: "";
    position: absolute;
    top: -40px; right: -40px;
    width: 160px; height: 160px;
    background: radial-gradient(circle, rgba(201,168,76,0.15) 0%, transparent 70%);
    border-radius: 50%;
    pointer-events: none;
}
.ex313-hero-label {
    font-size: .7rem; letter-spacing: .15em; text-transform: uppercase;
    color: rgba(201,168,76,0.7); font-family: Georgia, serif; margin-bottom: .3rem;
}
.ex313-hero-code {
    font-size: 2.6rem; font-weight: 800; color: #C9A84C;
    letter-spacing: .06em; line-height: 1.1; margin: .2rem 0;
}
.ex313-hero-sub {
    font-size: .92rem; color: rgba(255,255,255,0.85); margin-bottom: .8rem;
}
.ex313-copy-btn {
    display: inline-flex; align-items: center; gap: 6px;
    background: rgba(201,168,76,0.12); border: 1px solid rgba(201,168,76,0.35);
    color: #C9A84C; border-radius: 8px; padding: .4rem .95rem;
    font-size: .78rem; font-weight: 600; letter-spacing: .04em;
    cursor: pointer; transition: background .2s; font-family: inherit;
}
.ex313-copy-btn:hover { background: rgba(201,168,76,0.22); }
.ex313-copy-btn.ex313-copied { color: #4caf50; border-color: rgba(76,175,80,0.4); }

/* ── Share link block ── */
.ex313-share {
    background: rgba(201,168,76,0.05); border: 1px solid rgba(201,168,76,0.18);
    border-radius: 10px; padding: 1rem 1.2rem; margin-bottom: 1.5rem;
}
.ex313-share-label {
    font-size: .7rem; letter-spacing: .12em; text-transform: uppercase;
    color: rgba(201,168,76,0.55); margin-bottom: .5rem;
}
.ex313-share-url {
    font-size: .8rem; color: #C9A84C; word-break: break-all;
    font-family: monospace; background: rgba(0,0,0,0.3);
    border-radius: 6px; padding: .4rem .7rem; display: block; margin-bottom: .6rem;
}
.ex313-share-btn {
    background: none; border: 1px solid rgba(201,168,76,0.35);
    color: #C9A84C; border-radius: 6px; padding: .35rem .9rem;
    font-size: .75rem; cursor: pointer; transition: background .2s; font-family: inherit;
}
.ex313-share-btn:hover { background: rgba(201,168,76,0.1); }

/* ── Progress bar ── */
.ex313-prog-wrap {
    background: #141414; border: 1px solid #222;
    border-radius: 10px; padding: 1rem 1.2rem; margin-bottom: 1.5rem;
}
.ex313-prog-header {
    display: flex; justify-content: space-between; align-items: center;
    font-size: .9rem; color: #bbb; margin-bottom: .6rem;
}
.ex313-prog-header strong { color: #e8e0d5; }
.ex313-prog-track {
    background: #222; border-radius: 99px; height: 8px; overflow: hidden;
}
.ex313-prog-fill {
    height: 100%; border-radius: 99px;
    background: linear-gradient(90deg, #C9A84C, #f0c040);
    transition: width .8s cubic-bezier(.4,0,.2,1);
    width: 0%;
}
.ex313-prog-note { font-size: .85rem; color: #aaa; margin-top: .5rem; }

/* ── Summary card upgrades ── */
.ex299-card {
    background: #141414 !important; border: 1px solid #222 !important;
    border-radius: 12px !important; padding: 1.2rem !important;
    transition: border-color .2s !important;
}
.ex299-card:hover { border-color: rgba(201,168,76,0.3) !important; }

/* ── Table upgrades ── */
.ex299-dashboard table {
    background: #0e0e0e; border: 1px solid #1a1a1a; border-radius: 10px; overflow: hidden;
}
.ex299-dashboard table thead tr { background: #141414; }
.ex299-dashboard table thead th {
    font-size: .78rem !important; letter-spacing: .1em !important;
    text-transform: uppercase !important; color: #999 !important;
    padding: .75rem 1rem !important; font-weight: 600 !important;
}
.ex299-dashboard table tbody tr { transition: background .15s; }
.ex299-dashboard table tbody tr:hover { background: rgba(201,168,76,0.03); }
.ex299-dashboard h3 { font-family: Georgia, serif !important; font-size: .82rem !important; letter-spacing: .1em !important; }

/* ── W-9 alert upgrade ── */
.ex299-w9-alert {
    background: linear-gradient(135deg, #1a1200, #3a2a00) !important;
    border: 1px solid rgba(201,168,76,0.4) !important;
    border-radius: 12px !important; padding: 1.2rem 1.5rem !important;
}
</style>' . "\n";
}

function excreet_313_affiliate_js(): void {
    if ( ! is_page( 491 ) ) {
        return;
    }

    $user_id       = get_current_user_id();
    $referral_code = (string) $user_id;
    $share_url     = home_url( '/membership-checkout/?level=1&ref=' . $user_id );

    $code_json  = wp_json_encode( $referral_code );
    $share_json = wp_json_encode( $share_url );

    echo '<script id="ex313-aff-js">
(function(){
"use strict";
var CODE=' . $code_json . ';
var SHARE=' . $share_json . ';

function doCopy(text,btn,orig){
    if(navigator.clipboard&&navigator.clipboard.writeText){
        navigator.clipboard.writeText(text).then(function(){
            btn.textContent="Copied!";btn.classList.add("ex313-copied");
            setTimeout(function(){btn.textContent=orig;btn.classList.remove("ex313-copied");},2200);
        });
    }
}

document.addEventListener("DOMContentLoaded",function(){
    var dashboard=document.querySelector(".ex299-dashboard");
    if(!dashboard)return;

    // Hero
    var hero=document.createElement("div");
    hero.className="ex313-hero";
    hero.innerHTML="<div class=\"ex313-hero-label\">Your Referral Code</div>"+
        "<div class=\"ex313-hero-code\">"+CODE+"</div>"+
        "<div class=\"ex313-hero-sub\">Earn $5\/mo per Starter referral &bull; $10\/mo per Premium referral</div>"+
        "<button class=\"ex313-copy-btn\" id=\"ex313CopyCode\">&#128203; Copy Code<\/button>";
    dashboard.insertBefore(hero,dashboard.firstChild);
    document.getElementById("ex313CopyCode").addEventListener("click",function(){doCopy(CODE,this,"Copy Code");});

    // Share link
    var share=document.createElement("div");
    share.className="ex313-share";
    share.innerHTML="<div class=\"ex313-share-label\">Your Personal Invite Link<\/div>"+
        "<span class=\"ex313-share-url\">"+SHARE+"<\/span>"+
        "<button class=\"ex313-share-btn\" id=\"ex313CopyUrl\">Copy Link<\/button>";
    hero.after(share);
    document.getElementById("ex313CopyUrl").addEventListener("click",function(){doCopy(SHARE,this,"Copy Link");});

    // Progress bar
    var balanceDollars=0;
    document.querySelectorAll(".ex299-card").forEach(function(c){
        if(c.textContent&&c.textContent.toLowerCase().indexOf("pending balance")!==-1){
            var el=c.querySelector("div[style*=\"1.8\"]");
            if(el)balanceDollars=parseFloat(el.textContent.replace(/[^0-9.]/g,""))||0;
        }
    });
    var pct=Math.min(100,Math.round((balanceDollars/50)*100));
    var prog=document.createElement("div");
    prog.className="ex313-prog-wrap";
    prog.innerHTML="<div class=\"ex313-prog-header\"><span>Progress to Payout<\/span>"+
        "<strong>$"+balanceDollars.toFixed(2)+" \/ $50.00<\/strong><\/div>"+
        "<div class=\"ex313-prog-track\"><div class=\"ex313-prog-fill\" id=\"ex313Fill\"><\/div><\/div>"+
        "<div class=\"ex313-prog-note\">Payouts release bi-weekly when balance reaches $50 and W-9 is on file.<\/div>";
    share.after(prog);
    setTimeout(function(){
        var fill=document.getElementById("ex313Fill");
        if(fill)fill.style.width=pct+"%";
    },120);

    // Hide the referral-code summary card (now shown in hero)
    document.querySelectorAll(".ex299-card").forEach(function(c){
        if(c.textContent&&c.textContent.toLowerCase().indexOf("referral code")!==-1){
            c.style.display="none";
        }
    });
});
})();
</script>' . "\n";
}

/* ─────────────────────────────────────────────────────────────────────────── */
/* B — DASHBOARD WEEKLY DIGEST  (page 772)                                    */
/* ─────────────────────────────────────────────────────────────────────────── */

add_action( 'wp_footer', 'excreet_313_digest_panel', 20 );

function excreet_313_digest_panel(): void {
    if ( ! is_page( 772 ) ) {
        return;
    }
    if ( ! is_user_logged_in() ) {
        return;
    }

    $user_id    = get_current_user_id();
    $member_id  = (string) $user_id;
    $hermes_key = defined( 'EXCREET_HERMES_API_KEY' ) ? (string) EXCREET_HERMES_API_KEY : '';

    $base = defined( 'EXCREET_HERMES_URL' )
        ? (string) EXCREET_HERMES_URL
        : 'https://core-status-check.replit.app/api/hermes';
    $base = rtrim( preg_replace( '#/intake$#', '', rtrim( $base, '/' ) ), '/' );

    $snapshot_url = $base . '/api/hermes/body-snapshot/' . rawurlencode( $member_id );

    $score      = null;
    $snap_date  = null;
    $ring_color = '#888';
    $ring_label = 'No data';

    $resp = wp_remote_get( $snapshot_url, [
        'headers' => [ 'Authorization' => 'Bearer ' . $hermes_key ],
        'timeout' => 5,
    ] );

    if ( ! is_wp_error( $resp ) ) {
        $snap = json_decode( wp_remote_retrieve_body( $resp ), true );
        if ( is_array( $snap ) && isset( $snap['bodyScore'] ) ) {
            $score     = (int) $snap['bodyScore'];
            $snap_date = isset( $snap['snapshotDate'] ) ? (string) $snap['snapshotDate'] : null;
        }
    }

    if ( $score !== null ) {
        if ( $score >= 70 ) {
            $ring_color = '#4caf50';
            $ring_label = 'Strong';
        } elseif ( $score >= 45 ) {
            $ring_color = '#f0c040';
            $ring_label = 'Fair';
        } else {
            $ring_color = '#e57373';
            $ring_label = 'Needs Attention';
        }
    }

    $snap_label  = '';
    $days_since  = null;
    if ( $snap_date ) {
        $ts         = (int) strtotime( $snap_date );
        $days_since = (int) floor( ( time() - $ts ) / DAY_IN_SECONDS );
        if ( $days_since === 0 )     { $snap_label = 'Today'; }
        elseif ( $days_since === 1 ) { $snap_label = 'Yesterday'; }
        elseif ( $days_since < 7 )   { $snap_label = $days_since . ' days ago'; }
        else                          { $snap_label = date( 'M j', $ts ); }
    }

    // Status message
    if ( $score === null ) {
        $status_msg   = 'No Body Snapshot on record yet. Log your first snapshot to start tracking.';
        $status_icon  = '&#128203;';
        $status_color = '#888';
    } elseif ( $days_since === 0 ) {
        $status_msg   = 'Snapshot logged today. Your data is current.';
        $status_icon  = '&#9989;';
        $status_color = '#4caf50';
    } elseif ( $days_since <= 2 ) {
        $status_msg   = 'Last snapshot ' . esc_html( $snap_label ) . '. You are keeping up well.';
        $status_icon  = '&#127807;';
        $status_color = '#f0c040';
    } elseif ( $days_since <= 7 ) {
        $status_msg   = 'It has been ' . esc_html( $snap_label ) . ' since your last snapshot. Time to log today\'s signals.';
        $status_icon  = '&#9889;';
        $status_color = '#f0c040';
    } else {
        $status_msg   = 'Your body signals have not been logged in over a week. Reconnect today.';
        $status_icon  = '&#128276;';
        $status_color = '#e57373';
    }

    // SVG ring (only built when score is available)
    $ring_svg = '';
    if ( $score !== null ) {
        $dash      = round( 2 * M_PI * 22 * $score / 100, 1 );
        $ring_svg  = '<svg width="56" height="56" viewBox="0 0 56 56" aria-hidden="true">';
        $ring_svg .= '<circle cx="28" cy="28" r="22" fill="none" stroke="#1e1e1e" stroke-width="5"/>';
        $ring_svg .= '<circle cx="28" cy="28" r="22" fill="none"';
        $ring_svg .= ' stroke="' . esc_attr( $ring_color ) . '" stroke-width="5"';
        $ring_svg .= ' stroke-dasharray="' . esc_attr( (string) $dash ) . ' 999"';
        $ring_svg .= ' stroke-linecap="round" transform="rotate(-90 28 28)"/>';
        $ring_svg .= '<text x="28" y="33" text-anchor="middle" fill="' . esc_attr( $ring_color ) . '"';
        $ring_svg .= ' font-size="13" font-weight="800" font-family="system-ui,-apple-system,sans-serif">';
        $ring_svg .= esc_html( (string) $score );
        $ring_svg .= '</text></svg>';
    }

    $score_block = '';
    if ( $score !== null ) {
        $score_block  = '<div style="display:flex;align-items:center;gap:.8rem;">';
        $score_block .= $ring_svg;
        $score_block .= '<div>';
        $score_block .= '<div style="font-size:.68rem;color:#888;text-transform:uppercase;letter-spacing:.05em;">Body Score</div>';
        $score_block .= '<div style="font-size:.88rem;font-weight:700;color:' . esc_attr( $ring_color ) . ';">' . esc_html( $ring_label ) . '</div>';
        if ( $snap_label ) {
            $score_block .= '<div style="font-size:.7rem;color:#555;">' . esc_html( $snap_label ) . '</div>';
        }
        $score_block .= '</div></div>';
    }

    echo '
<div id="ex313-digest" style="max-width:780px;margin:0 auto 2.5rem;background:#141414;border:1px solid #222;border-radius:16px;padding:1.5rem 2rem;font-family:system-ui,-apple-system,sans-serif;">

  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:1.2rem;">
    <div>
      <div style="font-size:.68rem;letter-spacing:.14em;text-transform:uppercase;color:#444;font-family:Georgia,serif;">This Week</div>
      <div style="font-size:1.1rem;font-weight:700;color:#e8e0d5;margin-top:.2rem;">Your Body at a Glance</div>
    </div>
    ' . $score_block . '
  </div>

  <div style="background:#0e0e0e;border:1px solid #1a1a1a;border-radius:10px;padding:.85rem 1.1rem;margin-bottom:1rem;">
    <span>' . $status_icon . '</span>
    <span style="font-size:.85rem;color:' . esc_attr( $status_color ) . ';margin-left:.5rem;">' . $status_msg . '</span>
  </div>

  <div style="display:flex;gap:10px;flex-wrap:wrap;">
    <a href="/healing-command-center/" style="flex:1;min-width:140px;text-align:center;text-decoration:none;background:rgba(201,168,76,0.08);border:1px solid rgba(201,168,76,0.22);border-radius:10px;padding:.75rem;color:#C9A84C;font-size:.8rem;font-weight:600;letter-spacing:.04em;display:block;">
      &#128202; Log Body Snapshot
    </a>
    <a href="/ask-the-healer/" style="flex:1;min-width:140px;text-align:center;text-decoration:none;background:rgba(107,47,160,0.14);border:1px solid rgba(107,47,160,0.3);border-radius:10px;padding:.75rem;color:#b57bee;font-size:.8rem;font-weight:600;letter-spacing:.04em;display:block;">
      &#127807; Ask the Ministry
    </a>
    <a href="/provider-report/" style="flex:1;min-width:140px;text-align:center;text-decoration:none;background:rgba(100,181,246,0.07);border:1px solid rgba(100,181,246,0.18);border-radius:10px;padding:.75rem;color:#64b5f6;font-size:.8rem;font-weight:600;letter-spacing:.04em;display:block;">
      &#128196; Provider Report
    </a>
  </div>

</div>

<script id="ex313-digest-js">
(function(){
var d=document.getElementById("ex313-digest");
if(!d)return;
var sc=document.querySelector(".excreet-member-dashboard,.ex297-dashboard,.entry-content");
if(sc&&sc.parentNode){sc.parentNode.insertBefore(d,sc.nextSibling);}
else{var m=document.getElementById("main")||document.querySelector(".site-main");if(m)m.appendChild(d);}
d.style.display="block";
})();
</script>
' . "\n";
}

/* ─────────────────────────────────────────────────────────────────────────── */
/* C — MINISTRY SESSION HISTORY PANEL  (page 231)                             */
/* ─────────────────────────────────────────────────────────────────────────── */

add_action( 'wp_footer', 'excreet_313_session_history', 20 );

function excreet_313_session_history(): void {
    if ( ! is_page( 231 ) ) {
        return;
    }
    if ( ! is_user_logged_in() ) {
        return;
    }

    $ajax_url    = admin_url( 'admin-ajax.php' );
    $reset_nonce = wp_create_nonce( 'excreet_reset_ministry' );

    echo '<style id="ex313-sess-css">
.ex313-spanel{
    position:fixed;bottom:88px;right:20px;width:270px;
    background:rgba(16,4,32,.97);border:1px solid rgba(201,168,76,.3);
    border-radius:14px;box-shadow:0 8px 40px rgba(0,0,0,.6);z-index:8999;
    backdrop-filter:blur(12px);font-family:system-ui,-apple-system,sans-serif;
    transform:translateX(310px);transition:transform .3s cubic-bezier(.4,0,.2,1);
}
.ex313-spanel.ex313-open{transform:translateX(0);}
.ex313-stoggle{
    position:fixed;bottom:88px;right:20px;
    background:rgba(16,4,32,.92);border:1px solid rgba(201,168,76,.35);
    border-radius:50px;padding:.48rem 1.05rem;color:#C9A84C;
    font-size:.75rem;font-weight:600;letter-spacing:.06em;text-transform:uppercase;
    cursor:pointer;z-index:8998;display:flex;align-items:center;gap:5px;
    box-shadow:0 4px 16px rgba(0,0,0,.5);transition:right .3s;font-family:inherit;
}
.ex313-spanel.ex313-open+.ex313-stoggle{right:300px;}
.ex313-shead{
    display:flex;align-items:center;justify-content:space-between;
    padding:.85rem 1rem .55rem;border-bottom:1px solid rgba(201,168,76,.1);
}
.ex313-stitle{font-size:.7rem;letter-spacing:.12em;text-transform:uppercase;color:rgba(201,168,76,.8);font-family:Georgia,serif;}
.ex313-sclose{background:none;border:none;color:#444;cursor:pointer;font-size:1.1rem;line-height:1;padding:0;}
.ex313-sclose:hover{color:#C9A84C;}
.ex313-sbody{padding:.6rem;max-height:280px;overflow-y:auto;}
.ex313-sitem{
    display:flex;align-items:center;gap:7px;
    padding:.45rem .55rem;border-radius:7px;
    border-bottom:1px solid rgba(255,255,255,.03);font-size:.78rem;
}
.ex313-sitem:last-child{border-bottom:none;}
.ex313-sdot{width:7px;height:7px;border-radius:50%;background:rgba(201,168,76,.4);flex-shrink:0;}
.ex313-sdate{color:#999;}
.ex313-scur .ex313-sdot{background:#C9A84C;}
.ex313-scur .ex313-sdate{color:#C9A84C;font-weight:600;}
.ex313-sfoot{padding:.55rem .75rem .85rem;border-top:1px solid rgba(255,255,255,.04);}
.ex313-snewbtn{
    display:block;width:100%;text-align:center;
    background:rgba(201,168,76,.09);border:1px solid rgba(201,168,76,.28);
    color:#C9A84C;border-radius:8px;padding:.52rem;
    font-size:.75rem;font-weight:600;letter-spacing:.05em;
    cursor:pointer;text-transform:uppercase;transition:background .2s;font-family:inherit;
}
.ex313-snewbtn:hover{background:rgba(201,168,76,.18);}
.ex313-sempty{color:#444;font-size:.8rem;text-align:center;padding:1.2rem;}
</style>' . "\n";

    echo '<div class="ex313-spanel" id="ex313Panel">
  <div class="ex313-shead">
    <span class="ex313-stitle">Session History</span>
    <button class="ex313-sclose" id="ex313Close" aria-label="Close">&#10005;</button>
  </div>
  <div class="ex313-sbody" id="ex313List"><div class="ex313-sempty">Loading&hellip;</div></div>
  <div class="ex313-sfoot"><button class="ex313-snewbtn" id="ex313NewSess">&#43; Start New Session</button></div>
</div>
<button class="ex313-stoggle" id="ex313Toggle" aria-label="Session History">&#128196; Sessions</button>
' . "\n";

    $ajax_json  = wp_json_encode( $ajax_url );
    $nonce_json = wp_json_encode( $reset_nonce );

    echo '<script id="ex313-sess-js">
(function(){
"use strict";
var AJAX=' . $ajax_json . ';
var NONCE=' . $nonce_json . ';
var panel=document.getElementById("ex313Panel");
var toggle=document.getElementById("ex313Toggle");
var list=document.getElementById("ex313List");
var loaded=false;

function open(){panel.classList.add("ex313-open");if(!loaded){loadHistory();loaded=true;}}
function close(){panel.classList.remove("ex313-open");}
toggle.addEventListener("click",function(){panel.classList.contains("ex313-open")?close():open();});
document.getElementById("ex313Close").addEventListener("click",close);

function fmtDate(str){
    var months=["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"];
    var d=new Date(str);
    if(isNaN(d.getTime()))return str;
    return months[d.getMonth()]+" "+d.getDate()+", "+d.getFullYear();
}

function render(sessions){
    if(!sessions||!sessions.length){
        list.innerHTML="<div class=\"ex313-sempty\">No previous sessions found.</div>";return;
    }
    var html="";
    var rev=sessions.slice().reverse();
    rev.forEach(function(s){
        html+="<div class=\"ex313-sitem"+(s.cur?" ex313-scur":"")+"\">";
        html+="<div class=\"ex313-sdot\"></div>";
        html+="<span class=\"ex313-sdate\">"+s.label+"</span></div>";
    });
    list.innerHTML=html;
}

function loadHistory(){
    var fd=new FormData();
    fd.append("action","excreet_moh_load_history");
    fd.append("nonce",NONCE);
    fetch(AJAX,{method:"POST",body:fd,credentials:"same-origin"})
        .then(function(r){return r.json().catch(function(){return{};});})
        .then(function(data){
            var msgs=Array.isArray(data.messages)?data.messages:[];
            var sessions=[];
            msgs.forEach(function(m){
                if(m.role==="system"&&m.content&&m.content.indexOf("New session")!==-1){
                    var ts=m.timestamp||m.created_at||null;
                    sessions.push({label:ts?fmtDate(ts):"Previous session",cur:false});
                }
            });
            if(!sessions.length&&msgs.length>1){
                var first=null;
                for(var i=0;i<msgs.length;i++){if(msgs[i].timestamp||msgs[i].created_at){first=msgs[i];break;}}
                if(first){sessions.push({label:fmtDate(first.timestamp||first.created_at),cur:false});}
            }
            sessions.push({label:"Current session",cur:true});
            render(sessions);
        })
        .catch(function(){render([{label:"Current session",cur:true}]);});
}

document.getElementById("ex313NewSess").addEventListener("click",function(){
    if(!confirm("Start a fresh Ministry session? Your previous conversations will be archived."))return;
    var fd=new FormData();
    fd.append("action","excreet_297_reset_ministry");
    fd.append("nonce",NONCE);
    fetch(AJAX,{method:"POST",body:fd,credentials:"same-origin"})
        .then(function(){window.location.reload();})
        .catch(function(){window.location.reload();});
});
})();
</script>' . "\n";
}
