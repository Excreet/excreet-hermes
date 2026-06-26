<?php
$raw = get_post_meta(6, '_forminator_form_meta', true);
echo 'type=' . gettype($raw) . ' len=' . strlen((string)$raw) . "\n";
echo 'first60=' . substr((string)$raw, 0, 60) . "\n";

// Try JSON first
$m = json_decode($raw, true);
if (!is_array($m)) {
    $m = maybe_unserialize($raw);
}
if (!is_array($m)) {
    echo "Could not decode meta.\n";
    return;
}

$fields = $m['fields'] ?? [];
echo count($fields) . " fields found\n";
$changed = false;

foreach ($fields as &$f) {
    $type  = $f['type']        ?? '';
    $label = $f['field_label'] ?? ($f['element_id'] ?? '?');
    $def   = $f['default_value'] ?? '';
    $ph    = $f['placeholder']   ?? '';
    echo $type . ' | ' . $label . ' | def=' . $def . ' | ph=' . $ph . "\n";

    if ($type === 'number') {
        $f['default_value'] = '';
        $f['placeholder']   = 'e.g. 34';
        $f['min']           = '1';
        $f['max']           = '120';
        $changed = true;
        echo "  -> PATCHED (age)\n";
    }
    if ($type === 'email' && empty($f['placeholder'])) {
        $f['placeholder'] = 'your@email.com';
        $changed = true;
        echo "  -> PATCHED (email placeholder)\n";
    }
    if (($type === 'phone' || $type === 'tel') && empty($f['placeholder'])) {
        $f['placeholder'] = '+1 (555) 000-0000';
        $changed = true;
        echo "  -> PATCHED (phone placeholder)\n";
    }
    if ($type === 'text' && empty($f['placeholder'])) {
        $lc = strtolower($label);
        if (strpos($lc, 'alias') !== false) {
            $f['placeholder'] = 'Choose a private alias';
            $changed = true;
            echo "  -> PATCHED (alias placeholder)\n";
        }
    }
}
unset($f);

if ($changed) {
    $encoded = json_encode($m);
    if ($encoded === false) {
        $result = update_post_meta(6, '_forminator_form_meta', $m);
    } else {
        $result = update_post_meta(6, '_forminator_form_meta', wp_slash($encoded));
    }
    echo $result ? "Saved OK.\n" : "Save returned false (may be unchanged).\n";
} else {
    echo "No changes needed.\n";
}
