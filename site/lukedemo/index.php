<?php
// Public LUKE demo: the real LUKE code (synced into ./luke/) with demo mode forced on.
// Each visitor gets a private, expiring sandbox. No real board data exists here.
const PROJECT_BOARD_DEMO_FORCED = true;
const PROJECT_BOARD_ASSET_BASE = 'luke/assets/';
require __DIR__ . '/luke/index.php';
