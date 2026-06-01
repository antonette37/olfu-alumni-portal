<?php
/**
 * Kept for deploy paths that load this file first (see db_config.php).
 * Single source of truth: includes/alumni_id_cards_embed.php
 */
$_ol_emb = __DIR__ . '/includes/alumni_id_cards_embed.php';
if (!is_file($_ol_emb)) {
    $_ol_emb = __DIR__ . '/Includes/alumni_id_cards_embed.php';
}
if (is_file($_ol_emb)) {
    require_once $_ol_emb;
}
unset($_ol_emb);
