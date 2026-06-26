<?php
global $wpdb;
$prefix = $wpdb->prefix;

$total = (int) $wpdb->get_var(
    "SELECT COUNT(*) FROM {$prefix}pmpro_memberships_users WHERE status='active'"
);
$joined_30 = (int) $wpdb->get_var(
    "SELECT COUNT(*) FROM {$prefix}pmpro_memberships_users WHERE status='active' AND startdate >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
);

$levels = $wpdb->get_results(
    "SELECT id, name FROM {$prefix}pmpro_membership_levels ORDER BY id"
);

$by_level = [];
foreach ($levels as $l) {
    $cnt = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$prefix}pmpro_memberships_users WHERE status='active' AND membership_id=%d",
        $l->id
    ));
    $by_level[] = "id={$l->id} name={$l->name} count={$cnt}";
}

echo "total={$total}\n";
echo "joined_30={$joined_30}\n";
foreach ($by_level as $row) echo $row . "\n";
